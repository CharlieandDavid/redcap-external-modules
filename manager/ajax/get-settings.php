<?php
namespace ExternalModules;
require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

header('Content-type: application/json');
if (isset($_POST['pid']) && $_POST['pid']) {
	echo json_encode(array(
		'status' => 'success',
		'settings' => ExternalModules::getProjectSettingsAsArray($_POST['moduleDirectoryPrefix'], @$_POST['pid'], false)
	));
} else {
	echo json_encode(array(
		'status' => 'success',
		'settings' => ExternalModules::getSystemSettingsAsArray($_POST['moduleDirectoryPrefix'])
	));
}
