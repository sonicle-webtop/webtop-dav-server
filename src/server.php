<?php

require_once 'version.php';
require_once 'vendor/autoload.php';

// no php execution timeout for webdav
if (strpos(@ini_get('disable_functions'), 'set_time_limit') === false) {
	@set_time_limit(0);
}
ignore_user_abort(true);

// Turn off output buffering to prevent memory problems
\WT\Util::obEnd();

$logFile = \WT\Util::getConfigValue('log.file');
if (isset($logFile)) {
	\WT\Log::setFileHandler($logFile);
}

$server = new \WT\DAV\Server();
$server->exec();
