<?php

if(!defined('PLUGIN')){
    // Since a change to redcap_connect.php on 4/6/18, this is required to make sure REDCap is initialized for command line calls like cron jobs.
    define('PLUGIN', true);
}

$connectPath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "redcap_connect.php";
if (!file_exists($connectPath)) {
    // We must be using the "external_modules" folder to override the version of the framework bundled with REDCap.
    $connectPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "redcap_connect.php";
}

require_once $connectPath;