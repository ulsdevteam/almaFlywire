<?php
/**
 * @file index.php
 *
 * Copyright 2020 University of Pittsburgh
 * Distributed under GNU GPL v2 or later.
 *
 * @brief Respond to an incoming HTTP request by querying outstanding fines/fees in Alma and posting them to Flywire.
 *
**/
require('AlmaUserAPI.inc.php');
require('FlywireInvoiceAPI.inc.php');
require('settings.php'); // See settings.template.php

// Establish cross-origin policy
if (isset($_SERVER['HTTP_ORIGIN'])) {
	$origin_hostname = preg_replace('|^https?://|', '', $_SERVER['HTTP_ORIGIN']);
	if ($origin_hostname !== ALLOWED_AJAX_ORIGIN) {
		$error = 'Cross origin request disallowed';
	} else {
		header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
		header('Access-Control-Allow-Credentials: true');
		header('Access-Control-Allow-Methods: GET, OPTIONS');
		header('Access-Control-Allow-Headers; Origin');
		header('P3P: CP="CAO PSA OUR"');
		if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			exit;
		}
	}
}

$patronId = $_SERVER{SAML_USER_VARIABLE};
$almaFees = null;
$flywireInvoice = null;
$error = null;
if ($patronId) {
	$almaUserAPI = new AlmaUserAPI(ALMA_URL, ALMA_API_KEY);
	$flywireInvoiceAPI = new FlywireInvoiceAPI(FLYWIRE_TEST, FLYWIRE_CLIENT_ID, FLYWIRE_CLIENT_SECRET, FLYWIRE_COMPANY_REF, FLYWIRE_COMPANY_ID);
	try {
		$flywirePatron = null;
		$almaUser = $almaUserAPI->getUserByExternalId($patronId);
		$almaFees = $almaUserAPI->getFees($almaUser['primary_id']);
		if ($almaFees) {
			$flywirePatron = $flywireInvoiceAPI->getContactByAccountNumber(FLYWIRE_ACCOUNTNUMBER_PREFIX.$patronId);
			if (!$flywirePatron) {
				$contact = constructContact($almaUser);
				$flywirePatron = $flywireInvoiceAPI->createContact($contact);
			}
			$flywireInvoice = constructInvoice($flywirePatron, $almaFees);
			$flywireInvoiceAPI->deleteInvoices($flywirePatron);
			$flywireInvoiceAPI->postInvoice($flywireInvoice);
		}
	} catch (Exception $ex) {
		error_log($ex);
		$error = 'Please try again later';
	}
} else {
	error_log('Enviroment variable '.SAML_USER_VARIABLE.' was empty.');
	$error = 'Authentication problem.';
}
header('Content-type: application/json');
if ($error) {
	print json_encode(array('status' => 'error', 'message' => $error));
} else if (!$almaFees) {
	print json_encode(array('status' => 'noop', 'message' => 'There are no outstanding changes.'));
} else {
	print json_encode(array('status' => 'success', 'message' => ''));
}

/**
 * Create a Flywire Invoice from a Flywire Contact and Alma Fees
 * @param $contact array The Flywire Contact as an associative array
 * @param $finefee array The Alma Fines/Fees as an associative array
 * @return array The Flywire Invoice as an associative array
 * @throws Exception
 **/
function constructInvoice($contact, $finefee) {
	if (!$contact['companyId']) {
		throw new Exception('Invalid Flywire Contact');
	}
	if (!count($finefee['fee'])) {
		throw new Exception('Invalid Alma Fees');
	}
	$lineItems = array();
	foreach ($finefee['fee'] as $fee) {
		$lineItems[] = array(
			'name' => $fee['type']['desc'].': '.$fee['title'],
			'description' => 'Item '.$fee['barcode']['value'].' from '.$fee['owner']['desc'],
			'unitPrice' => $fee['balance'],
			'quantity' => 1,
		);
	}
	$flywireInvoice = array(
		'currency' => 'USD',
		'date' => date('Y-m-d\TH:i:s.000\Z'),
		'dueDate' => date('Y-m-d\TH:i:s.000\Z'),
		'paymentMethod' => 'PAY_NOW',
		'details' => array(
			'services' => $lineItems,
			'notes' => '',
			'paymentConfiguration' => array(
				'payNow' => array(
					array(
						'companyCode' => FLYWIRE_COMPANY_PAYCONFIG,
					),
				),
			),
		),
		'receiverId' => $contact['companyId'],
		'issuerId' => FLYWIRE_COMPANY_ID,
		'status' => 'DUE',
	);
	return $flywireInvoice;
}

/**
 * Create a Flywire Contact from an Alma User
 * @param $user array The Alma User as an associative array
 * @return array The Flywire Contact as an associative array
 * @throws Exception
 **/
function constructContact($user) {
	if (!$user['primary_id']) {
		throw new Exception('Invalid Alma User');
	}
	$preferredEmail = '';
	foreach ($user['contact_info']['email'] as $email) {
		if (!$preferredEmail || $email['preferred']) {
			$preferredEmail = $email['email_address'];
		}
	}
	if (!$preferredEmail) {
		throw new Exception('No email for Alma User');
	}
	$preferredPhone = '';
	foreach ($user['contact_info']['phone'] as $phone) {
		if (!$preferredPhone || $phone['preferred']) {
			$preferredPhone = $phone['email_number'];
		}
	}
	$preferredAddress = array();
	foreach ($user['contact_info']['address'] as $address) {
		if (!count($preferredAddress) || $address['preferred']) {
			$preferredAddress = array(
				'country' => $address['country']['value'] ? $address['country']['value'] : 'US',
				'street1' => $address['line1'],
				'street2' => $address['line2'].($address['line3'] ? ', '.$address['line3'] : '').($address['line4'] ? ', '.$address['line4'] : '').($address['line5'] ? ', '.$address['line5'] : ''),
				'city' => $address['city'],
				'state' => $address['state_province'],
				'postalCode' => $address['postal_code'],
			);
		}
	}
	$flywireUser = array(
		'firstName' => $user['first_name'],
		'lastName' => $user['last_name'],
		'email' => $preferredEmail,
		'phone' => $preferredPhone,
		'address' => array(
			'country' => $preferredAddress['country'],
			'street1' => $preferredAddress['street1'],
			'street2' => $preferredAddress['street2'],
			'city' => $preferredAddress['city'],
			'state' => $preferredAddress['state'],
			'postalCode' => $preferredAddress['postalCode'],
		),
		'accountNumber' => FLYWIRE_ACCOUNTNUMBER_PREFIX.$user['primary_id'],
		'tags' => array(
			array(
				'name' => 'CUSTOMER',
				'date' => date('Y-m-d\TH:i:s.000\Z'),
			),
		),
	);
	return $flywireUser;
}
