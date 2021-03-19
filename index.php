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
require '../bootstrap.php';
require('FlywireInvoiceAPI.inc.php');
require('AlmaFlywireBridge.inc.php');

// Establish cross-origin policy
establish_crossorigin_headers('GET, OPTIONS');

$almaFees = null;
$flywireInvoice = null;
$error = null;
$uid = null;
// Process request
$response = array(500 => 'Unexpected error');
$token = $_GET['jwt'];
if (!$token) {
	$response = array(401 => 'No authentication token');
} else {
	try {
		$primo = new Scriptotek\PrimoSearch\Primo([
			'apiKey' => ALMA_API_KEY,
			'region' => ALMA_REGION,
			'vid'    => '',
			'scope'  => 'default_scope',
		]);
		
		$pubKey = $primo->getPublicKey();
		$jwt = Jose\Easy\Load::jws($token)->exp()->key($pubKey)->run();
		$uid = $jwt->claims->get('userName');
	} catch (Exception $e) {
		$response = array(500 => $e->getCode.' '.$e->getMessage());
		error_log(get_class($e).': ('.$e->getCode().') '.$e->getMessage());
	}
}
if ($uid) {
	$alma = new Scriptotek\Alma\Client(ALMA_API_KEY, ALMA_REGION);
	$flywireInvoiceAPI = new FlywireInvoiceAPI(FLYWIRE_TEST, FLYWIRE_CLIENT_ID, FLYWIRE_CLIENT_SECRET, FLYWIRE_COMPANY_REF, FLYWIRE_COMPANY_ID);
	try {
		$flywirePatron = null;
		$almaUser = $alma->users->get($uid);
		$almaFees = $almaUser->fees->get();
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
		$response = array(500 => $e->getCode.' '.$e->getMessage());
		$error = 'Please try again later';
	}
}
header('Content-type: application/json');
if ($error) {
	print json_encode(array('status' => 'error', 'message' => $error));
} else if (!$almaFees) {
	print json_encode(array('status' => 'noop', 'message' => 'There are no outstanding changes.'));
} else {
	print json_encode(array('status' => 'success', 'message' => ''));
}
