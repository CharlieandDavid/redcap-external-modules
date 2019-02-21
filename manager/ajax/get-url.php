<?php
namespace ExternalModules;
require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

$prefix = $_POST['moduleDirectoryPrefix'];
$page = $_POST['page'];
$pid = $_POST['pid'];

$url = ExternalModules::getUrl($prefix, $page)."&pid=".$pid;

echo json_encode(array(
    'status' => 'success',
    'prefix' => $prefix,
    'url' => $url
));