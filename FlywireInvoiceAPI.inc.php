<?php
/**
 * @file FlywireInvoiceAPI.inc.php
 *
 * Copyright 2020 University of Pittsburgh
 * Distributed under GNU GPL v2 or later.
 *
 * @brief Interface against FlywireInvoice API endpoint
 *
**/

require_once('vendor/tcdent/php-restclient/restclient.php');

class FlywireInvoiceAPI {
	/**
	 * @var $_baseUrl string base URL for this API
	 **/
	protected $_baseUrl = '';

	/**
	 * @var $_authUrl string base URL for this API
	 **/
	protected $_authUrl = '';

	/**
	 * @var $_clientId string Client ID for authentication
	 **/
	protected $_clientId = '';

	/**
	 * @var $_clientSecret string Client Secret for authentication
	 **/
	protected $_clientSecret = '';

	/**
	 * @var $_companyRef string Company Reference 
	 **/
	protected $_companyRef = '';

	/**
	 * @var $_companyId string Company Id
	 **/
	protected $_companyId = '';

	/**
	 * @var $_client RestClient REST client
	 **/
	protected $_client = null;

	/**
	 * @var $_authToken string Authentication token
	 **/
	protected $_authToken = null;

	/**
	 * @var $_authTokenExpires int timestamp of token expiration
	 **/
	protected $_authTokenExpires = null;

	/**
	 * Constructor
	 * @param $test boolean Whether to use test API endpoints
	 * @param $clientId string The client id for the Flywire instance, e.g. "e9cbfabd-50a2-4528-aa21-278d1159d244"
	 * @param $clientSecret string The client secret for the Flywire instance, e.g. "g5Ur5u1z728XoUKsgs9NvEHW5x63JY82Ic9Ft4lI7e7SXjJ0I7C8087j0L39Dm818zqWE3K7a21h39qjqFMV5qHBlzUIfH1409z8X9m7rg4NX925M8109K5422QRC"
         * @param $companyRef string e.g. "1N03K8"
         * @param $companyId int e.g. 239818
	 **/
	public function __construct($test, $clientId, $clientSecret, $companyRef, $companyId) {
		$this->_authUrl = $test ? 'https://api.demo.flywire.com' : 'https://api.flywire.com';
		$this->_baseUrl = $test ? 'https://app.flywire.lol/rest' : 'https://app.flywire.com/rest';
		$this->_clientId = $clientId;
		$this->_clientSecret = $clientSecret;
		$this->_companyRef = $companyRef;
		$this->_companyId = $companyId;
		$this->_authToken = $this->getAuthToken();
		$this->_client = $this->getClient();
	}

	/**
	 * Create a REST client
	 * @return void
	 **/
	public function getClient() {
		return $this->_client = new RestClient([
			'base_url' => $this->_baseUrl.'/company/'.urlencode($this->_companyRef),
			'headers' => [
				'Authorization' => 'Bearer '.$this->getAuthToken(),
			],
		]);
	}


	/**
	 * Get an authentication token
	 * @return string token
	 **/
	public function getAuthToken() {
		if ($this->_authToken && time() < $this->_authTokenExpires) {
			return $this->_authToken;
		}
		$client = new RestClient([
			'base_url' => $this->_authUrl,
		]);
		$auth = array(
			'grant_type' => 'client_credentials',
			'client_id' => $this->_clientId,
			'client_secret' => $this->_clientSecret,
		);
		$response = $client->post('/oauth/token', json_encode($auth));
		if ($response->info->http_code == 200 || $response->info->http_code == 201) {
			$data = json_decode($response->response);
		} else {
			throw new Exception('Failed request for Flywire authentication');
		}
		if ($data['access_token']) {
			$this->_authToken = $data['access_token'];
			$this->_authTokenExpires = time() + $data['expires_in'];
		} else {
			throw new Exception('Bad response from Flywire authentication');
		}
		return $this->_authToken;
	}

	/**
	 * Get a Flywire contact for an account number
	 * @param $accountNumber string The external account number of the Flywire Contact
	 * @return array|boolean The associative array of contact, or false if none found
	 * @throws Exception
	 **/
	public function getContactByAccountNumber($accountNumber) {
		$response = $this->_client->get('/contact?s=accountNumber=='.urlencode($accountNumber));
		if ($response->info->http_code == 200) {
			$data = json_decode($response->response);
			if ($data) {
				return $data;
			} else {
				throw new Exception('Failed to decode Contact');
			}
		} else if ($response->info->http_code == 404) {
			return false;
		} else {
			throw new Exception('Unxepected response from Flywire Contact search');
		}
	}

	/**
	 * Get unpaid Flywire invoices for a contact
	 * @param $contact array The associative array of the contact
	 * @param $countOnly boolean Optional flag to indicate that only the count of invoices should be returned
	 * @return array Associative array of the invoices or the count of invoices
	 * @throws Exception
	 **/
	public function getUnpaidInvoices($contact, $countOnly = false) {
		$response = $this->_client->get('/invoice'.($countOnly ? '/counts' : '').'?s=status=DUE;receiverId='.urlencode($contact['companyId']));
		if ($response->info->http_code == 200) {
			$data = json_decode($response->response);
			if ($data) {
				return $data;
			} else {
				throw new Exception('Failed to decode Invoices');
			}
		} else {
			throw new Exception('Unxepected response from Flywire Invoice search');
		}
	}

	/**
	 * Delete Flywire invoices for a contact
	 * @param $contact array The assciative array of the contact
	 * @return void
	 * @throws Exception
	 **/
	public function deleteInvoices($contact) {
		$response = $this->getUnpaidInvoices($contact, true);
		if ($response['count'] > 0) {
			$response = $this->getUnpaidInvoices($contact);
			$invoiceIds = '';
			foreach ($response as $invoice) {
				$invoiceIds .= $invoiceIds ? ',' : '';
				$invoiceIds .= $invoice['id'];
			}
			$response = $this->_client->delete('/invoice/'.urlencode($invoiceIds));
			if ($response->info->http_code != 204) {
				throw new Exception('Unxepected response from Flywire Invoice delete');
			}
		}
	}

	/**
	 * Post a new Flywire invoice for a contact
	 * @param $invoice array The associative array of the invoice
	 * @return int The invoice id
	 * @throws Exception
	 **/
	public function postInvoice($invoice) {
		$response = $this->_client->post('/invoice', $invoice);
		if ($response->info->http_code == 201) {
			return array_pop(explode('/', $response->info->redirect_url));
		} else {
			throw new Exception('Unxepected response from Flywire Invoice creation');
		}
	}

	/**
	 * Create a new Flywire contact
	 * @param $contact array The associative array of the contact to be sent to the server
	 * @return array The associative array of the contact, as returned by the server
	 * @throws Exception
	 **/
	public function createContact($contact) {
		$response = $this->_client->post('/contact', $contact);
		if ($response->info->http_code == 200) {
			$data = json_decode($response->response);
			if ($data['companyId']) {
				return $data;
			} else {
				throw new Exception('Failed to decode Contact');
			}
		} else {
			throw new Exception('Unxepected response from Flywire Contact creation');
		}
	}


}
