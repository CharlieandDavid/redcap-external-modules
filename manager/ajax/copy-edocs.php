<?php
namespace ExternalModules;
require_once __DIR__ . '/../../classes/ExternalModules.php';

$pid = $_POST['pid'];

// The following method checks for design rights before making any changes.
ExternalModules::recreateAllEDocs($pid);

echo 'success';