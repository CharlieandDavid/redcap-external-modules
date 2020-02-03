<?php
namespace ExternalModules;
require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

$pid = @$_POST['pid'];

header('Content-type: application/json');
if (!empty($pid)) {
	ExternalModules::requireDesignRights($pid);
	
	echo json_encode(array(
		'status' => 'success',
		'settings' => ExternalModules::getProjectSettingsAsArray($_POST['moduleDirectoryPrefix'], $pid, false)
	));
} else if (ExternalModules::isSuperUser()){
	echo json_encode(array(
		'status' => 'success',
		'settings' => ExternalModules::getSystemSettingsAsArray($_POST['moduleDirectoryPrefix'])
	));
}
