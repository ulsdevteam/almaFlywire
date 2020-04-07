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

$patronId = $ENV{SAML_USER_VARIABLE};
$almaInvoice = null;
$flywireInvoice = null;
$error = null;
if ($patronId) {
	$almaInvoiceAPI = new AlmaInvoiceAPI(ALMA_URL, ALMA_API_KEY);
	$flywireInvoiceAPI = new FlywireInvoiceAPI(FLYWIRE_TESTPROD, FLYWIRE_CLIENT_ID, FLYWIRE_CLIENT_SECRET, FLYWIRE_COMPANY_REF, FLYWIRE_COMPANY_ID);
	try {
		$flywirePatron = null;
		$almaUser = $almaUserAPI->getUserByExternalId($patronId);
		$almaFees = $almaUserAPI->getFees($almaUser['primary_id']);
		if ($almaFees) {
			$flywirePatron = $flywireInvoiceAPI->getContactByAccountNumber($patronId);
			if (!$flywirePatron) {
				$contact = constructContact($almaUser);
				$flywirePatron = $flywireInvoiceAPI->getCreateContact($contact);
			}
			$flywireInvoice = constructInvoice($flywirePatron, $almaFees);
			$flywireInvoiceAPI->deleteInvoices($flywirePatron);
			$flywireInvoiceAPI->postInvoice($flywireInvoice);
		}
	} catch (Exception $ex) {
		error_log($ex);
		$error = 'Please try again later';
	}
}
if ($error) {
	print json_encode(array('status' => 'error', 'message' => $error));
} else if (!$almaInvoice) {
	print json_encode(array('status' => 'noop', 'message' => 'There are no outstanding changes.'));
} else {
	print json_encode(array('status' => 'success', 'message' => ''));
}

/**
 * Create a Flywire Invoice from a Flywire Contact and Alma Fees
 * @param $contact array The Flywire Contact as an associative array
 * @param $finefee array The Alma Fines/Fees as an associative array
 * @return array The Flywire Invoice as an associative array
 **/
function constructInvoice($contact, $finefee) {
	if (!$contact['companyId']) {
		throw new Exception('Invalid Flywire Contact');
	}
	if (!count($finefee['fees']['fee'])) {
		throw new Exception('Invalid Alma Fees');
	}
	$lineItems = array();
	foreach ($finefee['fees'] as $fee) {
		$lineItems[] = array(
			'name' => $fee['type'].': '.$fee['title'],
			'description' => 'Item '.$fee['barcode'].' from '.$fee['owner'],
			'unitPrice' => $fee['balance'],
			'quantity' => 1,
		);
	}
	$flywireInvoice = array(
		'currency' => 'USD',
		'date' => date('%Y-%m-%dT%H:%M:%S%z'),
		'dueDate' => date('%Y-%m-%dT%H:%M:%S%z'),
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
				'country' => $address['country']['value'],
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
		'acountNumber' => 'alma'.$user['primary_id'],
		'tags' => array(
			array(
				'name' => 'CUSTOMER',
				'date' => date('%Y-%m-%dT%H:%M:%S%z'),
			),
		),
	);
	return $flywireUser;
}
