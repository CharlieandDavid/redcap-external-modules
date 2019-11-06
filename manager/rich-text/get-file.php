<?php
namespace ExternalModules;
define('NOAUTH', true);
set_include_path('.' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../classes/ExternalModules.php';


$filename = $_GET['file'];
$prefix = $_GET['prefix'];
$pid = @$_GET['pid'];

$parts = explode('.', $filename);
$edocId = $parts[0];
$extension = $parts[1];

$ensureEDocIsRichText = function() use ($prefix, $pid, $edocId){
	$files = ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST);
	foreach($files as $file){
		if($file['edocId'] == $edocId){
			return;
		}
	}

	// Only allow rich text edocs to be accessed publicly.
	//= EDoc {0} was not found on project {1}!
	throw new \Exception(ExternalModules::tt("em_errors_79", $edocId, $pid)); 
};

$tempDirPath = sys_get_temp_dir() . '/external-module-rich-text-file-cache/';
$tempPath = $tempDirPath . $filename;

if(!file_exists($tempPath)){
	$ensureEDocIsRichText();

	@mkdir($tempDirPath, 0777);
	rename(\Files::copyEdocToTemp($edocId), $tempPath);
}

$fp = fopen($tempPath, 'rb');
$mimeType = \Files::get_mime_types()[$extension];

header("Content-Type: $mimeType");
header("Content-Length: " . filesize($tempPath));
header('Pragma: public');
header('Cache-Control: max-age=86400');
header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));

fpassthru($fp);
