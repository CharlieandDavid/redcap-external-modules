<?php
namespace ExternalModules;
use Exception;

require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

$return_data['message'] = "success";

if (!isset($_POST) || empty($_POST) || empty($_POST['prefix']) || $_POST['prefix'] == "" || empty($_POST['version']) || $_POST['version'] == "" || !is_numeric($_GET['pid'])) {
    $return_data['error_message'] .= "No module information was passed to define the module.\n";
}
else {
    $to = array();
    $from = "";
    $message = "";
    $subject = "";

    if (defined('USERID')) {
        $message .= "Module Activiation was requested by user: " . USERID . "<br>";
    }

    /*if (isVanderbilt()) {
        $from = 'datacore@vanderbilt.edu';
        $to = getDatacoreEmails([]);
    } else {*/
    global $project_contact_email;
    $from = $project_contact_email;
    $to = [$project_contact_email];
    //}
    try {
        $config = ExternalModules::getConfig($_POST['prefix'], $_POST['version']);

        $subject = "External Module Activation Requested";
        $message .= "<p style='font-weight:bold;'>Request Details:</p>";
        $message .= "Module Name: " . $config['name'] . " (".$_POST['prefix'].")<br>";
        $message .= "Project for Activation: " . $_GET['pid'] . "\n";
        //$message .= "Module Author(s): " . implode(', ', $authorEmails) . "<br>";
        if ($message != "" && !empty($to) && $from != "" && $subject != "") {
            $email = ExternalModules::sendBasicEmail($from, $to, $subject, $message);
            //$sendStatus = $email->send();
            //echo "Send status: " . $sendStatus;
            if (!$email) {
                $return_data['error_message'] .= "Mail delivery was unable to be completed.\n";
            }
        }
    } catch (Exception $e) {
        // The problem is likely due to loading the configuration.  Ignore this Exception.
        $return_data['error_message'] .= "Failure loading external module configuration: " . $e->getMessage() . "\n";
    }
}
echo json_encode($return_data);