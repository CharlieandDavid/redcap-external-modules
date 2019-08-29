<?php
namespace ExternalModules;
require_once __DIR__ . '/../../classes/ExternalModules.php';

$pid = $_POST['pid'];

ExternalModules::recreateAllEDocs($pid);

echo 'success';