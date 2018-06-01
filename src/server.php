<?php

require_once 'version.php';
require_once 'vendor/autoload.php';

// No php execution timeout for webdav
if (strpos(@ini_get('disable_functions'), 'set_time_limit') === false) {
	@set_time_limit(0);
}
ignore_user_abort(true);

// Turn off output buffering to prevent memory problems
\WT\Util::obEnd();

$timezone = \WT\Util::getConfigValue('timezone', true);
if (!date_default_timezone_set($timezone)) {
	throw new Exception(sprintf("The configured timezone '%s' is not valid. Please check supported timezones at http://www.php.net/manual/en/timezones.php", $timezone));
}

$logFile = \WT\Util::getConfigValue('log.file');
if (isset($logFile)) {
	\WT\Log::setFileHandler($logFile);
}

$server = new \WT\DAV\Server();
$server->exec();
