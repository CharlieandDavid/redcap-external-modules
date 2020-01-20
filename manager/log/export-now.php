<?php

$enabledModules = ExternalModules::getEnabledModules();
if (isset($_GET['prefix']) && isset($enabledModules[$_GET['prefix'])) {
	$module = ExternalModules::getModuleInstance($_GET['prefix']);
} else {
	throw new \Exception($_GET['prefix']." ".ExternalModules::tt("em_log_1"));
}

$module->setProjectSetting('export-now', true);

echo 'success';
