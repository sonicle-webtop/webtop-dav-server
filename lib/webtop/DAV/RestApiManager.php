<?php

namespace WT\DAV;

use WT\Log;

class RestApiManager {
	
	private $userAgent;
	private $authUsername;
	private $authPassword;
	private $authPrincipalInfo;
	
	public function __construct() {
		$this->userAgent = constant('WEBTOP-DAV-SERVER_NAME') . '/' . constant('WEBTOP-DAV-SERVER_VERSION') . '/php';
	}
	
	public function buildDAVApiHost() {
		$config = \WT\Util::getConfig();
		return $this->buildHost($config->get('api.baseUrl'), $config->get('api.dav.baseUrl'), $config->get('api.dav.url'));
	}
	
	public function buildCalDAVApiHost() {
		$config = \WT\Util::getConfig();
		return $this->buildHost($config->get('api.baseUrl'), $config->get('api.caldav.baseUrl'), $config->get('api.caldav.url'));
	}
	
	public function buildCardDAVApiHost() {
		$config = \WT\Util::getConfig();
		return $this->buildHost($config->get('api.baseUrl'), $config->get('api.carddav.baseUrl'), $config->get('api.carddav.url'));
	}
	
	public function setAuthenticatedPrincipal($username, $password, \WT\Client\DAV\Model\PrincipalInfo $principalInfo) {
		$this->authUsername = $username;
		$this->authPassword = $password;
		$this->authPrincipalInfo = $principalInfo;
	}
	
	public function setAuthenticatedUser($username, $password) {
		$this->authUsername = $username;
		$this->authPassword = $password;
	}
	
	public function getUserAgent() {
		return $this->userAgent;
	}

	public function getAuthUsername() {
		return $this->authUsername;
	}
	
	public function getAuthPassword() {
		return $this->authPassword;
	}
	
	public function getAuthPrincipalInfo() {
		return $this->authPrincipalInfo;
	}
	
	public function buildHost($baseHost, $host, $url) {
		$apiUrl = [$baseHost];
		if (isset($host)) {
			$apiUrl[0] = trim($host, '/');
		}
		$apiUrl[] = trim($url, '/');
		return implode('/', $apiUrl);
	}
	
	
	
	
	
	
	
	
	
	
	public function configureApiConfig($config) {
		$config->setUserAgent($this->userAgent);
		$config->setUsername($this->authUsername);
		$config->setPassword($this->authPassword);
		//$config->setHost('http://10.0.0.22:8084/webtop/api/com.sonicle.webtop.contacts/v1');
		//$config->addDefaultHeader('Authorization', 'Basic '. base64_encode($this->authUsername.':'.$this->authPassword));
	}
}
