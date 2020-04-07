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
require_once('AlmaAPI.inc.php');

class AlmaUserAPI extends AlmaAPI {
	/**
         * @copydoc AlamAPI::__construct()
	 **/
	public function __contruct($baseUrl, $apiKey) {
		parent::constuct($baseUrl, $apiKey);
		$this->_endpoint = '/almaws/v1/users';
	}

	/**
	 * @param $externalId string The ID of the user in the source insticution (source_user_id)
	 * @return array The associative array of the user
	 * @throws Exception
	 **/
	public function getUserByExternalId($externalId) {
		$users = $this->_getExpecting($this->_endpoint.'/?source_user_id='.urlencode($externalId), 200, 'User by External Id');
		if ($users['total_record_count'] == 1) {
			return $users['user'][0];
		} else {
			throw new Exception('Found '.$users['total_record_count'].' users, expected 1.');
		}
	}

	/**
	 * @param $userId string The Primary ID of the user
	 * @return array|boolean The associative array of the user fees, or false if none
	 * @throws Exception
	 **/
	public function getFees($userId) {
		$fees = $this->_getExpecting($this->_endpoint.'/'.urlencode($userId).'/fees/', 200, 'User Fees');
		if ($fees['total_record_count'] === 0) {
			return false;
		} else {
			return $fees;
		}
	}
}
