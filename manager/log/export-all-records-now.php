<?php
use REDCap;

namespace ExternalModules;
require_once __DIR__ . '/../../classes/ExternalModules.php';

$enabledModules = ExternalModules::getEnabledModules();
if (isset($_GET['prefix']) && isset($enabledModules[$_GET['prefix'])) {
	$module = ExternalModules::getModuleInstance($_GET['prefix']);
} else {
	throw new \Exception($_GET['prefix']." ".ExternalModules::tt("em_log_1"));
}

$recordIdFieldName = $module->getRecordIdField();
$records = json_decode(REDCap::getData($module->getProjectId(), 'json', null, $recordIdFieldName), true);

foreach($records as $record){
	$recordId = $record[$recordIdFieldName];
	$module->queueForUpdate($recordId);
}

require_once __DIR__ . '/export-now.php';
