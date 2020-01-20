<?php

$enabledModules = ExternalModules::getEnabledModules();
if (isset($_GET['prefix']) && isset($enabledModules[$_GET['prefix'])) {
	$module = ExternalModules::getModuleInstance($_GET['prefix']);
} else {
	throw new \Exception($_GET['prefix']." ".ExternalModules::tt("em_log_1"));
}

$offset = \db_real_escape_string($_GET['start']);
$limit = \db_real_escape_string($_GET['length']);
$limitClause = "limit $limit offset $offset";

$columnName = 'count(1)';
$result = $module->queryLogs("select $columnName");
$row = db_fetch_assoc($result);
$totalRowCount = $row[$columnName];

$results = $module->queryLogs("
	select project_id, log_id, timestamp, message, details
	order by log_id desc
	$limitClause
");

$rows = [];
while($row = $results->fetch_assoc()){
	$rows[] = $row;
}

?>

{
	"draw": <?=$_GET['draw']?>,
	"recordsTotal": <?=$totalRowCount?>,
	"recordsFiltered": <?=$totalRowCount?>,
	"data": <?=json_encode($rows)?>
}
