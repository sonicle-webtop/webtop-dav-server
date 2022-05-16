<?php

namespace WT\DAV\Connector;

use Sabre\DAV\Exception\Conflict;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\InvalidSyncToken;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\Exception\NotAuthenticated;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\NotImplemented;
use Sabre\DAV\Exception\PreconditionFailed;
use Sabre\DAV\Exception\ServiceUnavailable;
use Sabre\DAVACL\Exception\NeedPrivileges;

class ExceptionLoggerPlugin extends \Sabre\DAV\ServerPlugin {
	protected $nonFatalExceptions = [
		NotAuthenticated::class => true,
		// If tokenauth can throw this exception (which is basically as
		// NotAuthenticated. So not fatal.
		PasswordLoginForbidden::class => true,
		// basically a NotAuthenticated
		InvalidSyncToken::class => true,
		// the sync client uses this to find out whether files exist,
		// so it is not always an error, log it as debug
		NotFound::class => true,
		// this one mostly happens when the same file is uploaded at
		// exactly the same time from two clients, only one client
		// wins, the second one gets "Precondition failed"
		PreconditionFailed::class => true,
		// forbidden can be expected when trying to upload to
		// read-only folders for example
		Forbidden::class => true,
		// Happens when an external storage or federated share is temporarily
		// not available
		StorageNotAvailableException::class => true,
		// happens if some a client uses the wrong method for a given URL
		// the error message itself is visible on the client side anyways
		NotImplemented::class => true,
		// happens when the parent directory is not present (for example when a
		// move is done to a non-existent directory)
		Conflict::class => true,
		// happens when a certain method is not allowed to be called
		// for example creating a folder that already exists
		MethodNotAllowed::class => true,
		// happens when a requested operation cannot be satisfied due to ACLs configuration
		NeedPrivileges::class => true
	];
	
	private $logger;
	
	public function __construct($logger) {
		$this->logger = $logger;
	}
	
	public function initialize(\Sabre\DAV\Server $server) {
		$server->on('exception', array($this, 'logException'), 10);
	}
	
	public function logException(\Exception $ex) {
		$exceptionClass = get_class($ex);
		if (isset($this->nonFatalExceptions[$exceptionClass]) ||
				($exceptionClass === ServiceUnavailable::class && $ex->getMessage() === 'System in maintenance mode.')) {
			$this->logger->debug($ex);
		} else {
			$this->logger->critical($ex);
		}
	}
}
