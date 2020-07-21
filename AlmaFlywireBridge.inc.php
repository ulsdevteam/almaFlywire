<?php
/**
 * @file AlmaFlywireBridge.php
 *
 * Copyright 2020 University of Pittsburgh
 * Distributed under GNU GPL v2 or later.
 *
 * @brief Convert data from Alma to Flywire
 *
**/

class AlmaFlywireBridge {

	/**
	 * Create a Flywire Invoice from a Flywire Contact and Alma Fees
	 * @param $contact array The Flywire Contact as an associative array
	 * @param $finefee array The Alma Fines/Fees as an associative array
	 * @param $companyCode string The Flywire Company Code for the Payment Configuration
	 * @param $issuerId string The Flywire Issuer Identifier
	 * @return array The Flywire Invoice as an associative array
	 * @throws Exception
	 **/
	public static function constructInvoice($contact, $finefee, $companyCode, $issuerId) {
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
							'companyCode' => $companyCode,
						),
					),
				),
			),
			'receiverId' => $contact['companyId'],
			'issuerId' => $issuerId,
			'status' => 'DUE',
		);
		return $flywireInvoice;
	}

	/**
	 * Create a Flywire Contact from an Alma User
	 * @param $user array The Alma User as an associative array
	 * @param $accountNumberPrefix string A prefix to apply to the Flywire Account Number, which is otherwise mapped to the Alma Primary ID
	 * @return array The Flywire Contact as an associative array
	 * @throws Exception
	 **/
	public static function constructContact($user, $accountNumberPrefix = '') {
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
			'accountNumber' => $accountNumberPrefix.$user['primary_id'],
			'tags' => array(
				array(
					'name' => 'CUSTOMER',
					'date' => date('Y-m-d\TH:i:s.000\Z'),
				),
			),
		);
		return $flywireUser;
	}

}
