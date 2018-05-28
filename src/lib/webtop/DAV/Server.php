<?php

namespace WT\DAV;

use Sabre\CalDAV\CalendarRoot;
use Sabre\CalDAV\Principal\Collection;
use Sabre\CalDAV\ICSExportPlugin;
use Sabre\CardDAV\AddressBookRoot;
use Sabre\CardDAV\VCFExportPlugin;
use WT\Log;

class Server {
	private $debug;
	private $baseUri;
	private $bridge;
	private $server;
	
	public function __construct() {
		$this->debug = \WT\Util::getConfigValue('debug', true);
		$this->baseUri = \WT\Util::getConfigValue('baseUri');
		if (!isset($this->baseUri)) {
			Log::critical('Missing baseUri configuration');
			throw new Exception('Missing baseUri configuration');
		}
		$caldavEnabled = \WT\Util::getConfigValue('caldav', true);
		$carddavEnabled = \WT\Util::getConfigValue('carddav', true);
		
		$bridge = new Bridge();
		$tree = [];
		
		$authBackend = new \WT\DAV\Connector\AuthBackend($bridge);
		$userPrincipalBackend = new \WT\DAV\Connector\PrincipalBackend($bridge);
		
		$userPrincipals = new Collection($userPrincipalBackend);
		$userPrincipals->disableListing = !$this->debug;
		$tree[] = $userPrincipals;
		
		if ($caldavEnabled) {
			$calDavBackend = new \WT\DAV\CalDAV\Backend($bridge);
			$calendarRoot = new CalendarRoot($userPrincipalBackend, $calDavBackend);
			$calendarRoot->disableListing = !$this->debug;
			$tree[] = $calendarRoot;
		}
		
		if ($carddavEnabled) {
			$cardDavBackend = new \WT\DAV\CardDAV\Backend($bridge);
			$addressBookRoot = new AddressBookRoot($userPrincipalBackend, $cardDavBackend);
			$addressBookRoot->disableListing = !$this->debug;
			$tree[] = $addressBookRoot;
		}
		
		$this->server = new \WT\DAV\Connector\Server($tree);
		$this->server->addPlugin(new \WT\DAV\Connector\ExceptionLoggerPlugin(Log::getLogger()));
		
		// Set URL explicitly due to reverse-proxy situations
		$this->server->setBaseUri($this->baseUri);
		
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
		Log::debug('Server launch');
		/*
		$ok = session_start([
			'cookie_lifetime' => 60
		]);
		if (!$ok) {
			Log::warn('Unable to initialize PHP Session');
		} else {
			Log::debug('PHP Session started', ['sid' => session_id()]);
		}
		*/
		Log::debug('headers', apache_request_headers());
		Log::debug('method', ['method' => $this->server->httpRequest->getMethod()]);
		Log::debug('body', ['body' => $this->server->httpRequest->getBody()]);
		//Log::debug('getHeader', ['method' => $this->server->httpRequest->getMethod(), 'auth' => $this->server->httpRequest->getHeader('Authorization')]);
		$this->server->exec();
	}
}
