<?php

namespace ExternalModules;

require_once(dirname(__FILE__)."/../classes/ExternalModules.php");

if (ExternalModules::isSuperUser()) {
	$moduleDirectoryPrefix = @$_GET['prefix'];
	$result = ExternalModules::resetCron($moduleDirectoryPrefix);
	if ($result) {
		echo ExternalModules::tt("em_manage_92");
	}
} else {
	throw new \Exception(self::tt("em_errors_120"));
}