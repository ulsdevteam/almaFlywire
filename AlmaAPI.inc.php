<?php
/**
 * @file AlmaAPI.inc.php
 *
 * Copyright 2020 University of Pittsburgh
 * Distributed under GNU GPL v2 or later.
 *
 * @brief Interface against Alma API endpoint
 *
**/

require_once 'vendor/tcdent/php-restclient/restclient.php';
require_once 'RestApiException.inc.php';

class AlmaAPI {
	/**
	 * @var $_baseUrl base URL for this API
	 **/
	protected $_baseUrl = '';

	/**
	 * @var $_endpoint base endpoint for this API
	 **/
	protected $_endpoint = '';

	/**
	 * @var $_key endpoint for this API
	 **/
	protected $_apikey = '';

	/**
	 * @var $_client REST client
	 **/
	protected $_client = null;


	/**
	 * Constructor
	 * @param $baseUrl string the base url for the Alma instance, e.g. "https://api-na.hosted.exlibrisgroup.com"
	 * @param $apiKey string the API Key for your Alma instance, e.g. "AW0xHK7IYPHN5Z9C29729Nc4z4ccczrMEEoW"
	 **/
	public function __construct($baseUrl, $apiKey) {
		$this->_baseUrl = $baseUrl;
		$this->_apikey = $apiKey;
		$this->_client = $this->getClient();
	}

	/**
	 * Create a REST client
	 * @return void
	 **/
	public function getClient() {
		return new RestClient([
			'base_url' => $this->_baseUrl,
			'headers' => [
				'Authorization' => 'apikey '.$this->_apikey,
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
			],
		]);
	}

	/**
	 * @param $uri string The URL to fetch
	 * @param $expecting int The expected HTTP code
	 * @return array The associative array of the response
	 * @throws Exception
	 **/
	protected function _getExpecting($uri, $expecting) {
		$response = $this->_client->get($uri);
		if ($response->info->http_code == $expecting) {
			$data = json_decode($response->response, true);
			if ($data) {
				return $data;
			} else {
				throw new RestApiException($response);
			}
		} else {
			throw new RestApiException($response);
		}
	}

	public function getPublicKey() {
		$response = $this->_client->get('/primo/v1/instPublicKey');
		if ($response->info->http_code == 200) {
			return $reponse->response;
		}
		throw new RestApiException($response);
	}
}
