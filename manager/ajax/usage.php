<?php
namespace ExternalModules;
require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

// Only administrators can perform this action
if (!SUPER_USER) exit;

$projects = ExternalModules::getEnabledProjects($_GET['prefix']);

while($project = $projects->fetch_assoc()){
	$url = APP_PATH_WEBROOT . 'ProjectSetup/index.php?pid=' . $project['project_id'];
	?><a href="<?=$url?>" style="text-decoration: underline;"><?=\RCView::escape(strip_tags($project['name']))?></a><br><?php
}