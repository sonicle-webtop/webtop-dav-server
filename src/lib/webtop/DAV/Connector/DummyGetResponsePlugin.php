<?php

namespace WT\DAV\Connector;

class DummyGetResponsePlugin extends \Sabre\DAV\ServerPlugin {
	
	public function initialize(\Sabre\DAV\Server $server) {
		$server->on('method:GET', [$this, 'httpGet'], 200);
	}
	
	function httpGet(RequestInterface $request, ResponseInterface $response) {
		$string = 'This is the WebDAV interface. It can only be accessed by ' .
				'WebDAV clients.';
		$stream = fopen('php://memory','r+');
		fwrite($stream, $string);
		rewind($stream);
		
		$response->setStatus(200);
		$response->setBody($stream);
		
		return false;
	}
}
