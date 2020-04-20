<?php
/**
 * @file RestApiException.inc.php
 *
 * Copyright 2020 University of Pittsburgh
 * Distributed under GNU GPL v2 or later.
 *
 * @brief Exception which encapsulates a RESTClient response
 *
**/

require_once 'vendor/tcdent/php-restclient/restclient.php';

class RestApiException extends Exception {
	/**
	 * @var $_client RestClient Executed REST client with response
	 **/
	protected $_client = null;

	/**
	 * Constructor
	 * @param $message RestClient The REST Client with failed response
	 * @param $code int Optional code
	 * @param $previous Exception Optional prior exception
	 **/
	public function __construct(RestClient $message, $code = 0, Exception $previous = null) {
		$this->_client = $message;
		parent::__construct($this->_client->error ? $this->_client->error : 'HTTP '.$this->_client->info->http_code, $code, $previous);
	}

	/**
	 * Expose the REST client
	 * @return string A string representation of the failure
	 **/
	public function __toString() {
		return parent::__toString() . "\n" .
			'Header: '.var_export($this->_client->response_status_lines, true) . "\n" .
			'Info: '.var_export($this->_client->info, true) . "\n" .
			'Body: '.$this->_client->response;
	}

	/**
	 * Expose the REST client
	 * @return RestClient
	 **/
	public function getClient() {
		return $this->_client;
	}

}
