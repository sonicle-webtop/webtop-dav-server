<?php

namespace WT\DAV\Connector;

class Server extends \Sabre\DAV\Server {
	
	public function __construct($treeOrNode = null) {
		parent::__construct($treeOrNode);
		self::$exposeVersion = false;
		//$this->enablePropfindDepthInfinity = true;
	}
}
