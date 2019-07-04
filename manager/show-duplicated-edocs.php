<?php
namespace ExternalModules;

?>
<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
<?

require_once dirname(__FILE__) . '/../classes/ExternalModules.php';

$keysByPrefix = [];
$handleSetting = function($prefix, $setting) use (&$handleSetting, &$keysByPrefix){
	$type = $setting['type'];
	if($type === 'file'){
		$keysByPrefix[$prefix][] = $setting['key'];
	}
	else if ($type === 'sub_settings'){
		foreach($setting['sub_settings'] as $subSetting){
			$handleSetting($prefix, $subSetting);
		}
	}
};

foreach(ExternalModules::getSystemwideEnabledVersions() as $prefix=>$version){
	$config = ExternalModules::getConfig($prefix, $version);
	foreach(['system-settings', 'project-settings', 'email-dashboard-settings'] as $settingType){
		$settings = @$config[$settingType];
		if(!$settings){
			continue;
		}

		foreach($settings as $setting){
			$handleSetting($prefix, $setting);
		}
	}
}

$edocs = [];
$addEdoc = function($prefix, $pid, $key, $edocId) use (&$edocs){
	if($edocId === ''){
		return;
	}

	$edocs[$edocId][] = [
		'prefix' => $prefix,
		'pid' => $pid,
		'key' => $key
	];
};

$parseRichTextValue = function($prefix, $pid, $key, $files) use ($addEdoc){
	foreach($files as $file){
		$addEdoc($prefix, $pid, $key, $file['edocId']);
	}
};

$parseFileSettingValue = function($prefix, $pid, $key, $value) use (&$parseFileSettingValue, &$addEdoc){
	if(is_array($value)){
		foreach($value as $subValue){
			$parseFileSettingValue($prefix, $pid, $key, $subValue);
		}
	}
	else{
		$addEdoc($prefix, $pid, $key, $value);
	}
};

$clauses = [
	"`key` = '" . ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST . "'"
];

foreach($keysByPrefix as $prefix=>$keys){
	$moduleId = ExternalModules::getIdForPrefix($prefix);
	$clauses[] = "(external_module_id = $moduleId and " . ExternalModules::getSQLInClause('`key`', $keys) .  ")";
}

$sql = "
	select * from redcap_external_module_settings
	where
	" . implode("\n\t or ", $clauses) . "
";

$result = ExternalModules::query($sql);
while($row = db_fetch_assoc($result)){
	$prefix = ExternalModules::getPrefixForID($row['external_module_id']);
	$pid = $row['project_id'];
	$key = $row['key'];
	$value = json_decode($row['value'], true);

	if($key === ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST){
		$parseRichTextValue($prefix, $pid, $key, $value);
	}
	else{
		$parseFileSettingValue($prefix, $pid, $key, $value);
	}
}

echo "<h5 style='margin:10px'>EDocs Unsafely Referenced Outside Their Source Project</h5>";
echo "<table class='table' style='width: auto'>";

foreach(['EDoc ID', 'Source Project', 'Referencing Project', 'Module', 'Setting Key'] as $value){
	echo "<th>$value</th>";
}

$result = ExternalModules::query("select * from redcap_edocs_metadata where " . ExternalModules::getSQLInClause('doc_id', array_keys($edocs)));
$sourceProjectsByEdocId = [];
while($row = db_fetch_assoc($result)){
	$sourceProjectsByEdocId[$row['doc_id']] = $row['project_id'];
}

ksort($edocs);
foreach($edocs as $edocId=>$references){
	foreach($references as $reference){
		$sourcePid = $sourceProjectsByEdocId[$edocId];
		$referencePid = $reference['pid'];
		if($referencePid === $sourcePid){
			continue;
		}

		echo "<tr>";
		foreach([$edocId, $sourcePid, $referencePid, $reference['prefix'], $reference['key']] as $value){
			echo "<td>$value</td>";
		}
		echo "</tr>";
	}
}

echo "</table>";