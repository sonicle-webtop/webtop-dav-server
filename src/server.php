<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_USER_NOTICE);

require_once 'version.php';
require_once 'vendor/autoload.php';

// No php execution timeout for webdav
if (strpos(@ini_get('disable_functions'), 'set_time_limit') === false) {
	@set_time_limit(0);
}
ignore_user_abort(true);

// Turn off output buffering to prevent memory problems
\WT\Util::obEnd();

\WT\DAV\Config::load('config.json');
$config = \WT\DAV\Config::get();
\WT\Log::init('dav-server', $config->getLogLevel(), $config->getLogFile());

$server = new \WT\DAV\Server();
$server->exec();
