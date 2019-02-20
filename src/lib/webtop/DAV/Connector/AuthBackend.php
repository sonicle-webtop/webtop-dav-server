<?php

namespace WT\DAV\Connector;

use lf4php\LoggerFactory;
use WT\DAV\Bridge;

class AuthBackend extends \Sabre\DAV\Auth\Backend\AbstractBasic {
	
	protected $bridge;
	
	public function __construct(Bridge $bridge) {
		$this->bridge = $bridge;
	}
	
	protected function getLogger() {
		return LoggerFactory::getLogger(__CLASS__);
	}

	/**
	 * Validates a username and password
	 * 
	 * This method should return true or false depending on if login succeeded.
	 * 
	 * @param string $username
	 * @param string $password
	 * @return bool
	 * @throws PasswordLoginForbidden
	 */
	protected function validateUserPass($username, $password) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, ...)', [__METHOD__, $username]);
		
		$result = $this->bridge->authenticateUser($username, $password);
		if ($result) {
			$this->currentUser = $username;
		}
		return $result;
	}
}
