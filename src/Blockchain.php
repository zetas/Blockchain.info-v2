<?php
namespace src\Blockchain;

/**
 * Super simple Blockchain.info V2 Receive API implementation.
 *
 * Also some usecase-specific conversion methods.
 * @package src\Blockchain
 */
class Blockchain
{

	private $_xpub = '<YOUR XPUB KEY>';
	private $_key = '<YOUR API KEY>';
    private $_currencyCode = 'USD';
    private $_userAgent = 'Zetas-BlockchainV2Api/1.0.0';

    // Do not touch.
	private $_blockchainURL = 'https://api.blockchain.info';
	private $_statsBlockchainURL = 'https://blockchain.info';
	private $_receiveEndpoint = '/v2/receive';
	private $_ratesEndpoint = '/ticker';
	private $_toBTCEndpoint = '/tobtc';

	private $_curl_handler;

	public function __construct() {
		$this->_curl_handler = curl_init();

		curl_setopt($this->_curl_handler, CURLOPT_USERAGENT, $this->_userAgent);
		curl_setopt($this->_curl_handler, CURLOPT_HEADER, false);
		curl_setopt($this->_curl_handler, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->_curl_handler, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($this->_curl_handler, CURLOPT_TIMEOUT, 60);
		curl_setopt($this->_curl_handler, CURLOPT_CAINFO, dirname(__FILE__).'/ca-bundle.crt');
	}

	public function __destruct() {
		curl_close($this->_curl_handler);
	}

	public function getAddress($params) {
		$response = $this->_call($this->_receiveEndpoint, $this->_addIdentity($params));

		if (!array_key_exists('address', $response))
			return false;

		return $response['address'];
	}

	public function toBtc($value) {
		$params = [
			'currency' => $this->_currencyCode,
			'value' => $value,
			'format' => 'json'
		];

		return $this->_call($this->_toBTCEndpoint, $params);
	}

	public function getRates() {

		$rates = $this->_call($this->_ratesEndpoint, []);

		if ($rates === false)
			return false;

		return $rates[$this->_currencyCode];
	}

	private function _call($endpoint, $params) {

		$url = $this->_buildURL($endpoint, $params);

		curl_setopt($this->_curl_handler, CURLOPT_POST, false);
		curl_setopt($this->_curl_handler, CURLOPT_URL, $url);

		$response = curl_exec($this->_curl_handler);

		if(curl_error($this->_curl_handler)) {
			$info = curl_getinfo($this->_curl_handler);
			var_dump($info);
			return false;
		}

		$json = json_decode($response,true);
		if (is_null($json)) {
			$info = curl_getinfo($this->_curl_handler);
			var_dump($info);
			return false;
		}

		return $json;
	}

	private function _addIdentity($params) {
		$params += ['key' => $this->_key, 'xpub' => $this->_xpub];

		return $params;
	}

	private function _buildURL($endpoint, $params) {

		$url = ($endpoint == $this->_ratesEndpoint || $endpoint == $this->_toBTCEndpoint) ? $this->_statsBlockchainURL : $this->_blockchainURL;

		$url = sprintf('%s%s?', $url, $endpoint);
		$url .= http_build_query($params);

		return $url;
	}
}
