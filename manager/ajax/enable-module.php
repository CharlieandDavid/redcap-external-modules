<?php
namespace ExternalModules;

use Exception;

require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

// Can the current user enable/disable modules?
$userCanEnable = (SUPER_USER || (ExternalModules::hasDesignRights() && ExternalModules::getSystemSetting($_POST['prefix'], ExternalModules::KEY_USER_ACTIVATE_PERMISSION) == true));
if (!$userCanEnable) exit;

$return_data['message'] = "success";

if (isset($_GET['pid'])) {
	ExternalModules::enableForProject($_POST['prefix'], $_POST['version'], $_GET['pid']);
	if (isset($_GET['request_id'])) {
		ExternalModules::finalizeModuleActivationRequest($_POST['prefix'], $_POST['version'], $_GET['pid'], (int)$_GET['request_id']);
	}
}
else {
    $config = ExternalModules::getConfig($_POST['prefix'], $_POST['version']);
    $return_data['error_message'] = "";
    if(empty($config['description'])){
        //= The module named '{0}' is missing a description. Fill in the config.json to ENABLE it.
        $return_data['error_message'] .= ExternalModules::tt("em_errors_82", $config['name']) . "<br/>";
    }

    if(empty($config['authors'])){
        //= The module named '{0}' is missing its authors. Fill in the config.json to ENABLE it.
        $return_data['error_message'] .= ExternalModules::tt("em_errors_83", $config['name']) . "<br/>";
    }else{
        $missingEmail = true;
        foreach ($config['authors'] as $author){
            if(!empty( $author['email'])){
                $missingEmail = false;
                break;
            }
        }

        if($missingEmail){
            //= The module named '{0}' needs at least one email inside the authors portion of the configuration. Please fill an email for at least one author in the config.json.
            $return_data['error_message'] .= ExternalModules::tt("em_errors_84", $config['name']) . "<br/>";
        }

        foreach ($config['authors'] as $author) {
            if (empty($author['institution'])) {
                //= The module named '{0}' is missing an institution for at least one of it's authors in the config.json file.
                $return_data['error_message'] .= ExternalModules::tt("em_errors_85", $config['name']) . "<br/>";
                break;
            }
        }
    }

    if(empty($return_data['error_message'])) {
		$exception = ExternalModules::enableAndCatchExceptions($_POST['prefix'], $_POST['version']);
		if($exception){
            //= Exception while enabling module: {0}
			$return_data['error_message'] = ExternalModules::tt("em_errors_86", $exception->getMessage());
			$return_data['stack_trace'] = $exception->getTraceAsString();
		}
    }
}

// Log this event
$logText = "Enable external module \"{$_POST['prefix']}_{$_POST['version']}\" for " . (!empty($_GET['pid']) ? "project" : "system");
\REDCap::logEvent($logText, "", "", null, null, $_GET['pid']);

echo json_encode($return_data);
