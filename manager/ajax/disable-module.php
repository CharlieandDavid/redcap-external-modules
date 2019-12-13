<?php
namespace ExternalModules;
require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

// Can the current user enable/disable modules?
$userCanDisable = (SUPER_USER || (ExternalModules::hasDesignRights() && ExternalModules::getSystemSetting($_POST['module'], ExternalModules::KEY_USER_ACTIVATE_PERMISSION) == true));
if (!$userCanDisable) exit;

$module = $_POST['module'];

if (empty($module)) {
	//= You must specify a module to disable
	echo ExternalModules::tt("em_errors_81"); 
	return;
}

$version = ExternalModules::getModuleVersionByPrefix($module);

if (isset($_GET["pid"])) {
	ExternalModules::setProjectSetting($module, $_GET['pid'], ExternalModules::KEY_ENABLED, false);
} else {
	ExternalModules::disable($module, false);
}

// Log this event
$logText = "Disable external module \"{$module}_{$version}\" for " . (!empty($_GET['pid']) ? "project" : "system");
\REDCap::logEvent($logText);

echo 'success';
