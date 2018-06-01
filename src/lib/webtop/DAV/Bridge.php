<?php

namespace WT\DAV;

use WT\Log;

class Bridge {
	
	protected $userAgent;
	protected $apiHostDav;
	protected $apiHostCalDav;
	protected $apiHostCardDav;
	protected $currentUser = false;
	protected $currentPassword;
	protected $currentUserInfo;
	
	public function __construct() {
		$this->userAgent = constant('WEBTOP-DAV-SERVER_NAME') . '/' . constant('WEBTOP-DAV-SERVER_VERSION') . '/php';
		$config = \WT\Util::getConfig();
		$this->apiHostDav = $this->buildHost($config->get('api.baseUrl'), $config->get('api.dav.baseUrl'), \WT\Util::getConfigValue('api.dav.url', true));
		$this->apiHostCalDav = $this->buildHost($config->get('api.baseUrl'), $config->get('api.caldav.baseUrl'), \WT\Util::getConfigValue('api.caldav.url', true));
		$this->apiHostCardDav = $this->buildHost($config->get('api.baseUrl'), $config->get('api.carddav.baseUrl'), \WT\Util::getConfigValue('api.carddav.url', true));
	}
	
	public function getUserAgent() {
		return $this->userAgent;
	}
	
	public function getApiHostDav() {
		return $this->apiHostDav;
	}
	
	public function getApiHostCalDav() {
		return $this->apiHostCalDav;
	}
	
	public function getApiHostCardDav() {
		return $this->apiHostCardDav;
	}
	
	public function getCurrentUser() {
		return $this->currentUser;
	}
	
	public function getCurrentPassword() {
		return $this->currentPassword;
	}
	
	public function getCurrentUserInfo() {
		return $this->currentUserInfo;
	}
	
	public function authenticateUser($username, $password) {
		try {
			$config = $this->getDAVApiConfig($username, $password);
			$item = $this->getPrincipalInfo($config, $username);
			
			$this->currentUser = $username;
			$this->currentPassword = $password;
			$this->currentUserInfo = $item;
			
			return true;
			
		} catch (\WT\Client\DAV\ApiException $ex) {
			Log::error($ex);
			return false;
		}
	}
	
	protected function getPrincipalInfo(\WT\Client\DAV\Configuration $config, $username) {
		$priApi = new \WT\Client\DAV\Api\DavPrincipalsApi(null, $config);
		$item = $priApi->getPrincipalInfo($username);
		if (Log::isDebugEnabled()) Log::debug('[REST] getPrincipalInfo()', ['$item' => strval($item)]);
		return $item;
	}
	
	protected function getDAVApiConfig($username, $password) {
		$config = new \WT\Client\DAV\Configuration();
		$config->setUserAgent($this->userAgent);
		$config->setUsername($username);
		$config->setPassword($password);
		$config->setHost($this->apiHostDav);
		return $config;
	}
	
	private function buildHost($baseHost, $host, $url) {
		$apiUrl = [$baseHost];
		if (isset($host)) {
			$apiUrl[0] = trim($host, '/');
		}
		$apiUrl[] = trim($url, '/');
		return implode('/', $apiUrl);
	}
}
