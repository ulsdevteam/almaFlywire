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
require('AlmaFlywireBridge.inc.php');
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
				$contact = AlmaFlywireBridge::constructContact($almaUser, FLYWIRE_ACCOUNTNUMBER_PREFIX);
				$flywirePatron = $flywireInvoiceAPI->createContact($contact);
			}
			$flywireInvoice = AlmaFlywireBridge::constructInvoice($flywirePatron, $almaFees, FLYWIRE_COMPANY_PAYCONFIG, FLYWIRE_COMPANY_ID);
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
