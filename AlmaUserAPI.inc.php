<?php
/**
 * @file AlmaUserAPI.inc.php
 *
 * Copyright 2020 University of Pittsburgh
 * Distributed under GNU GPL v2 or later.
 *
 * @brief Interface against Alma User API endpoint
 *
**/
require_once 'AlmaAPI.inc.php';

class AlmaUserAPI extends AlmaAPI {
	/**
	* @copydoc AlamAPI::__construct()
	 **/
	public function __construct($baseUrl, $apiKey) {
		parent::__construct($baseUrl, $apiKey);
		$this->_endpoint = '/almaws/v1/users';
	}

	/**
	 * @param $externalId string The ID of the user in the source insticution (source_user_id)
	 * @return array The associative array of the user
	 * @throws Exception
	 **/
	public function getUserByExternalId($externalId) {
		return $this->_getExpecting($this->_endpoint.'/'.urlencode($externalId), 200);
	}

	/**
	 * @param $userId string The Primary ID of the user
	 * @return array|boolean The associative array of the user fees, or false if none
	 * @throws Exception
	 **/
	public function getFees($userId) {
		$fees = $this->_getExpecting($this->_endpoint.'/'.urlencode($userId).'/fees/', 200);
		if ($fees['total_record_count'] === 0) {
			return false;
		} else {
			return $fees;
		}
	}
}
