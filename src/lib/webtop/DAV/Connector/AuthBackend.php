<?php

namespace WT\DAV\Connector;

use WT\Log;
use WT\DAV\Bridge;

class AuthBackend extends \Sabre\DAV\Auth\Backend\AbstractBasic {
	
	protected $bridge;
	
	public function __construct(Bridge $bridge) {
		$this->bridge = $bridge;
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
		Log::debug('validateUserPass', array('username' => $username));
		
		$result = $this->bridge->authenticateUser($username, $password);
		if ($result) {
			$this->currentUser = $username;
		}
		return $result;
	}
}
