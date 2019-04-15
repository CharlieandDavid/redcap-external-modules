<?php
namespace ExternalModules;
require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];

$rawSettings = json_decode($_POST['settings'], true);
if($rawSettings === null){
	throw new \Exception('Unable to parse module settings!');
}

$module = ExternalModules::getModuleInstance($moduleDirectoryPrefix);
$validationErrorMessage = $module->validateSettings(ExternalModules::formatRawSettings($moduleDirectoryPrefix, $pid, $rawSettings));
if(!empty($validationErrorMessage)){
	die($validationErrorMessage);
}

$saveSqlByField = ExternalModules::saveSettings($moduleDirectoryPrefix, $pid, $rawSettings);

if(!empty($saveSqlByField)){
	// At least one setting changed.  Log this event.
	$logText = "Modify configuration for external module \"{$moduleDirectoryPrefix}_{$module->VERSION}\" for " . (!empty($_GET['pid']) ? "project" : "system");
	$changeText = join(', ', array_keys($saveSqlByField));

	// We do NOT include the values of changed settings, since they could contain sensitive data that some users shouldn't see (API keys, etc.).
	$saveSql = join(";\n\n", array_values($saveSqlByField));

	\REDCap::logEvent($logText, $changeText, $saveSql);
}

ExternalModules::callHook('redcap_module_save_configuration', array($pid), $moduleDirectoryPrefix);

header('Content-type: application/json');
echo json_encode(array(
    'status' => 'success',
    'test' => 'success'
));
