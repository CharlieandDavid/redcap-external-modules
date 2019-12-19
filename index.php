<?php
namespace ExternalModules;

$page = rawurldecode(urldecode($_GET['page']));
$isGetFilePage = $page === '/manager/rich-text/get-file.php';
$noAuth = isset($_GET['NOAUTH']) || $isGetFilePage;
if($noAuth){
	// This must be defined at the top before redcap_connect.php is required.
	define('NOAUTH', true);
}

// We call redcap_connect.php before loading any classes to make sure redirections from previous REDCap
// version URLs happen first.  We don't want to try to load old and new versions of the same class.
require_once __DIR__ . '/redcap_connect.php';
require_once __DIR__ . '/classes/ExternalModules.php';

if($isGetFilePage){
	require_once __DIR__ . $page;
	return;
}

use Exception;

$pid = @$_GET['pid'];

$prefix = $_GET['prefix'];
if(empty($prefix)){
	$prefix = ExternalModules::getPrefixForID($_GET['id']);
	if(empty($prefix)){
		throw new Exception("A module prefix must be specified as a query parameter!");
	}
}

$version = ExternalModules::getSystemSetting($prefix, ExternalModules::KEY_VERSION);
if(empty($version)){
	throw new Exception("The module with prefix '$prefix' is currently disabled systemwide.");
}

$config = ExternalModules::getConfig($prefix, $version);
if($noAuth && !in_array($page, $config['no-auth-pages'])){
	throw new Exception("The NOAUTH parameter is not allowed on this page.");
}

$getLink = function () use ($prefix, $version, $page) {
	$links = ExternalModules::getLinks($prefix, $version);
	foreach ($links as $link) {
		if ($link['url'] == ExternalModules::getUrl($prefix, $page)) {
			return $link;
		}
	}

	return null;
};

$link = $getLink();
$showHeaderAndFooter = false;
if($pid != null){
	$enabledGlobal = ExternalModules::getSystemSetting($prefix,ExternalModules::KEY_ENABLED);
	$enabled = ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::KEY_ENABLED);
	if(!$enabled && !$enabledGlobal){
		throw new Exception("The '$prefix' module is not currently enabled on project $pid.");
	}

	$showHeaderAndFooter = @$link['show-header-and-footer'] === true;
}

$pageExtension = strtolower(pathinfo($page, PATHINFO_EXTENSION));
$pagePath = ExternalModules::getModuleDirectoryPath($prefix, $version) . "/$page" . ($pageExtension == '' ? ".php" : "");
if(!file_exists($pagePath)){
	throw new Exception("The specified page does not exist for this module. $pagePath");
}

$checkLinkPermission = function ($module) use ($link) {
	if (!$link) {
		// This url is not defined in config.json.  Allow it to work for backward compatibility.
		return true;
	}

	$link = $module->redcap_module_link_check_display($_GET['pid'], $link);
	if (!$link) {
		die("You do not have permission to access this page.");
	}
};

switch ($pageExtension) {
    case "php":
    case "":
        // PHP content
		$module = ExternalModules::getModuleInstance($prefix, $version);

        // Leave setting permissions up to module authors.
		// The redcap_module_link_check_display() hook already limits access to design rights users by default.
		// No additional security should be required.
		$module->disableUserBasedSettingPermissions();

		$checkLinkPermission($module);

		if($showHeaderAndFooter){
			require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
		}

        require_once $pagePath;

		if($showHeaderAndFooter){
			require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		}

        break;
    case "md":
        // Markdown Syntax
        $Parsedown = new \Parsedown();
        $html = $Parsedown->text(file_get_contents($pagePath));

        $search = '<img src="';
        $replace = $search . ExternalModules::getModuleDirectoryUrl($prefix, $version);
        $html = str_replace($search, $replace, $html);

		ExternalModules::addResource('css/markdown.css');
        echo $html;
        break;
    default:
        // OTHER content (css/js/etc...):
        $contentType = ExternalModules::getContentType($pageExtension);
        if($contentType){
            // In most cases index.php is not used to access non-php files (and a content type is not needed).
            // However, Andy Martin has a use case where users are behind Shibboleth and it makes sense to serve all
            // files through index.php.  This content type was added specifically for that case.
            $mime_type = $contentType;
        } else {
            // Make a best guess
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $pagePath);
        }

        // send the headers
        // header("Content-Disposition: attachment; filename=$public_name;");
        header("Content-Type: $mime_type");
        header('Content-Length: ' . filesize($pagePath));

        // stream the file
        $fp = fopen($pagePath, 'rb');
        fpassthru($fp);
        exit();
}
