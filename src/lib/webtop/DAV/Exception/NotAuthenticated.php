<?php

namespace WT\DAV\Exception;

class NotAuthenticated extends \Sabre\DAV\Exception {
	
	function getHTTPCode() {
		return 401;
	}
	
	function getHTTPHeaders(\Sabre\DAV\Server $server) {
		\WT\Log::debug("getHTTPHeaders");
		return [
			'WWW-Authenticate' => 'Basic'
		];
	}
}
