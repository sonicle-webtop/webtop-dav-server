<?php

namespace WT\DAV;

use Sabre\CalDAV\CalendarRoot;
use Sabre\CalDAV\Principal\Collection;
use Sabre\CalDAV\ICSExportPlugin;
use Sabre\CardDAV\AddressBookRoot;
use Sabre\CardDAV\VCFExportPlugin;
use lf4php\LoggerFactory;
use WT\Log;
use WT\DAV\Config;

class Server {
	private $debug;
	private $bridge;
	private $server;
	
	public function __construct() {
		$this->debug = Config::get()->getBrowserEnabled();
		$caldavEnabled = Config::get()->getCalDAVEnabled();
		$carddavEnabled = Config::get()->getCardDAVEnabled();
		
		$bridge = new Bridge();
		$tree = [];
		
		$authBackend = new \WT\DAV\Connector\AuthBackend($bridge);
		$userPrincipalBackend = new \WT\DAV\Connector\PrincipalBackend($bridge);
		
		$userPrincipals = new Collection($userPrincipalBackend);
		$userPrincipals->disableListing = !$this->debug;
		$tree[] = $userPrincipals;
		
		if ($caldavEnabled) {
			$calDavBackend = new \WT\DAV\CalDAV\Backend($bridge);
			$calendarRoot = new \WT\DAV\CalDAV\CalendarRoot($userPrincipalBackend, $calDavBackend);
			$calendarRoot->disableListing = !$this->debug;
			$tree[] = $calendarRoot;
		}
		
		if ($carddavEnabled) {
			$cardDavBackend = new \WT\DAV\CardDAV\Backend($bridge);
			$addressBookRoot = new \WT\DAV\CardDAV\AddressBookRoot($userPrincipalBackend, $cardDavBackend);
			$addressBookRoot->disableListing = !$this->debug;
			$tree[] = $addressBookRoot;
		}
		
		$this->server = new \WT\DAV\Connector\Server($tree);
		$this->server->addPlugin(new \WT\DAV\Connector\ExceptionLoggerPlugin(Log::getLogger()));
		
		// Set URL explicitly due to reverse-proxy situations
		$this->server->setBaseUri(Config::get()->getBaseURLPath());
		
		$authPlugin = new \Sabre\DAV\Auth\Plugin();
		$authPlugin->addBackend($authBackend);
		$this->server->addPlugin($authPlugin);
		
		// debugging
		if ($this->debug) {
			$this->server->addPlugin(new \Sabre\DAV\Browser\Plugin());
		}
		
		$this->server->addPlugin(new \Sabre\DAV\Sync\Plugin());
		$this->server->addPlugin(new \Sabre\DAVACL\Plugin());
		
		// calendar plugins
		if ($caldavEnabled) {
			$this->server->addPlugin(new \WT\DAV\CalDAV\Plugin());
			$this->server->addPlugin(new ICSExportPlugin());
		}
		
		// addressbook plugins
		if ($carddavEnabled) {
			$this->server->addPlugin(new \WT\DAV\CardDAV\Plugin());
			$this->server->addPlugin(new VCFExportPlugin());
			//$this->server->addPlugin(new ImageExportPlugin(new PhotoCache(\OC::$server->getAppDataDir('dav-photocache'))));
		}
	}
	
	public function exec() {
		$logger = LoggerFactory::getLogger(__CLASS__);
		$logger->debug('Server launch');
		// Seems that below code causes exceptions on CentOS. I keep it commented for now!
		/*
		if ($this->debug) {
			$logger->debug($this->server->httpRequest);
		}
		*/
		$this->server->exec();
	}
}
