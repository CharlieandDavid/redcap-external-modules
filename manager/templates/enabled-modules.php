<?php
namespace ExternalModules;
set_include_path('.' . PATH_SEPARATOR . get_include_path());
require_once dirname(__FILE__) . '/../../classes/ExternalModules.php';

use Exception;

ExternalModules::addResource('css/style.css');

$pid = $_GET['pid'];
$disableModuleConfirmProject = (isset($_GET['pid']) & !empty($_GET['pid'])) ? " for the current project" : "";
?>

<div id="external-modules-download" class="simpleDialog" role="dialog">
	Do you wish to download the External Module named 
	"<b><?php print \RCView::escape(rawurldecode(urldecode($_GET['download_module_title']))) ?></b>"?
	This will create a new directory folder for the module on the REDCap web server.
</div>

<div id="external-modules-disable-confirm-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Disable module? <span class="module-name"></span></h4>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				Are you sure you wish to disable this module 
				(<b><span id="external-modules-disable-confirm-module-name"></span>_<span id="external-modules-disable-confirm-module-version"></span></b>)<?=$disableModuleConfirmProject?>?
			</div>
			<div class="modal-footer">
				<button data-dismiss="modal">Cancel</button>
				<button id="external-modules-disable-button-confirmed" class="save">Disable module</button>
			</div>
		</div>
	</div>
</div>

<div id="external-modules-disabled-modal" class="modal fade" role="dialog">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title clearfix">
					<div class="float-left">Available Modules</div>
					<div class="float-right" style="margin-left:50px;"><input type="text" id="disabled-modules-search" class="quicksearchsm" placeholder="Search available modules" autofocus></div>
				</h4>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<form>
				</form>
			</div>
		</div>
	</div>
</div>

<div id="external-modules-usage-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"></h4>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
			</div>
		</div>
	</div>
</div>

<p>External Modules are individual packages of software that can be downloaded and installed by a REDCap administrator.
Modules can extend REDCap's current functionality, and can also provide customizations and enhancements for REDCap's
existing behavior and appearance at the system level or project level.</p>

<?php if (isset($_GET['pid']) && SUPER_USER) { ?>

<p>As a REDCap administrator, you may enable any module that has been installed in REDCap for this project.
Some configuration settings might be required to be set, in which administrators or
users in this project with Project Setup/Design privileges can modify the configuration of any module at any time after the module
has first been enabled by an administrator. Note: Normal project users will not be able to enable or disable modules.</p>

<?php } elseif (isset($_GET['pid']) && !SUPER_USER) { ?>

<p>As a user with Project Setup/Design privileges in this project, you can modify the configuration (if applicable)
of any enabled module. Note: Only REDCap administrators are able to enable or disable modules.</p>

<?php } else { ?>

<p>You may click the "View modules" button below to navigate to the REDCap Repo (Repository of External Modules), which is a centralized catalog 
of curated modules that have been submitted by various REDCap partner institutions. If you find a module in the repository that you wish
to download, you will be able to install it, enable it, and then set any configuration settings (if applicable).
If you choose not to enable the module in all REDCap projects by default, then you will need to navigate to the External Modules page
on the left-hand menu of a given project to enable it there for that project. Some project-level configuration settings, depending on the module,
may also need to set on the project page.</p>

<?php 
// Display alert message in Control Center if any modules have updates in the REDCap Repo
ExternalModules::renderREDCapRepoUpdatesAlert();
?>

<?php } ?>

<?php if (isset($_GET['pid'])) { ?>

<p style="color:#800000;font-size:11px;line-height:13px;">
	<b>DISCLAIMER:</b> Please be aware that External Modules are not part of the REDCap software but instead are add-on packages
	that, in most cases, have been created by software developers at other REDCap institutions.
	Be aware that the entire risk as to the quality and performance of the module as it is used in your REDCap project 
	is borne by you and your local REDCap administator. 
	If you experience any issues with a module, your REDCap administrator should contact the author of that particular module.
</p>

<?php
	// Show custom external modules text (optional)
	if (isset($GLOBALS['external_modules_project_custom_text']) && trim($GLOBALS['external_modules_project_custom_text']) != "") {
		print \RCView::div(array('id'=>'external_modules_project_custom_text', 'style'=>'max-width:800px;border:1px solid #ccc;background-color:#f5f5f5;margin:15px 0;padding:8px;'), nl2br(decode_filter_tags($GLOBALS['external_modules_project_custom_text'])));
	}

}

if (!isVanderbilt() && !isset($_GET['pid']) && defined("EXTMOD_EXTERNAL_INSTALL") && EXTMOD_EXTERNAL_INSTALL) { 
	?>
	<p class="yellow" style="max-width:600px;color:#800000;font-size:11px;line-height:13px;">
		<b>NOTICE:</b> It has been detected that you have a development version of External Modules installed at
		<code><?=APP_PATH_EXTMOD?></code>. As such, please note that REDCap will use that version of External Modules rather than
		the one bundled in this REDCap version.
	</p>
	<?php 
}


// Ensure that server is running PHP 5.4.0+ since REDCap's minimum requirement is PHP 5.3.0
if (version_compare(PHP_VERSION, ExternalModules::MIN_PHP_VERSION, '<')) {
	?>
	<div class="red">
		<b>PHP <?=ExternalModules::MIN_PHP_VERSION?> or higher is required for External Modules:</b>
		Sorry, but unfortunately your REDCap web server must be running PHP <?=ExternalModules::MIN_PHP_VERSION?>
		or a later version to utilize the External Modules functionality. Your current version is PHP <?=PHP_VERSION?>.
		You should consider upgrading PHP.
	</div>
	<?php
	require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';
	exit;
}

$displayModuleDialogBtn = (SUPER_USER || ExternalModules::hasDiscoverableModules());
$moduleDialogBtnText = SUPER_USER ? "Enable a module" : "View available modules";
$moduleDialogBtnImg = SUPER_USER ? "fas fa-plus-circle" : "fas fa-info-circle";

?>
<br>
<?php if($displayModuleDialogBtn) { ?>
	<button id="external-modules-enable-modules-button" class="btn btn-success btn-sm">
		<span class="<?=$moduleDialogBtnImg?>" aria-hidden="true"></span>
		<?=$moduleDialogBtnText?>
	</button> &nbsp; 
<?php } ?>
<?php if (SUPER_USER && !isset($_GET['pid'])) { ?>
	<button id="external-modules-download-modules-button" class="btn btn-primary btn-primaryrc btn-sm">
		<span class="fas fa-download" aria-hidden="true"></span>
		View modules available in the REDCap Repo
	</button>
	<form id="download-new-mod-form" action="<?=APP_URL_EXTMOD_LIB?>login.php" method="post" enctype="multipart/form-data">
		<input type="hidden" name="user" value="<?=USERID?>">
		<input type="hidden" name="name" value="<?=htmlspecialchars($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname'], ENT_QUOTES)?>">
		<input type="hidden" name="email" value="<?=htmlspecialchars($GLOBALS['user_email'], ENT_QUOTES)?>">
		<input type="hidden" name="server" value="<?=SERVER_NAME?>">		
		<input type="hidden" name="referer" value="<?=htmlspecialchars(APP_URL_EXTMOD."manager/control_center.php", ENT_QUOTES)?>">
		<input type="hidden" name="php_version" value="<?=PHP_VERSION?>">
		<input type="hidden" name="redcap_version" value="<?=REDCAP_VERSION?>">		
		<input type="hidden" name="institution" value="<?=htmlspecialchars($GLOBALS['institution'], ENT_QUOTES)?>">
		<?php foreach (\ExternalModules\ExternalModules::getModulesInModuleDirectories() as $thisModule) { ?>
			<input type="hidden" name="downloaded_modules[]" value="<?=$thisModule?>">
		<?php } ?>
	</form>
<?php } ?>
<br>
<br>

<h4 class="clearfix" style="max-width: 800px;">
	<div class="float-left"><b>
	<?php if (isset($_GET['pid'])) { ?>
	Currently Enabled Modules
	<?php } else { ?>
	Modules Currently Available on this System
	<?php } ?>
	</b></div>
	<div class="float-right"><input type="text" id="enabled-modules-search" class="quicksearch" placeholder="Search enabled modules"></div>
</h4>

<script type="text/javascript">
	var override = '<?=ExternalModules::OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS?>';
	var enabled = '<?=ExternalModules::KEY_ENABLED?>';
	var overrideSuffix = '<?=ExternalModules::OVERRIDE_PERMISSION_LEVEL_SUFFIX?>';
	$(function(){
		var searchField = $('input#enabled-modules-search')

		// Enable module search
		searchField.quicksearch('table#external-modules-enabled tbody tr', {
			selector: 'td:eq(0)'
		});

		// The focus() method is used here because the 'autofocus' attribute cannot be used since it interferes with the 'autofocus' attribute on the search in the disabled modules modal.
		searchField[0].focus()
	});
</script>

<table id='external-modules-enabled' class="table">
	<?php

	$versionsByPrefix = ExternalModules::getEnabledModules($_GET['pid']);
	$prefixes = array_keys($versionsByPrefix);
	$detailsByPrefix = ExternalModules::getModuleDetailsByPrefix($prefixes);
	$supportOverrideInfoByPrefix = ExternalModules::getSupportOverrideInfo($prefixes);

	$renderSupportEndDate = function($details, $supportOverrideInfo){
		$supportEndDate = @$supportOverrideInfo['support_end_date'];
		if(!$supportEndDate){
			$supportEndDate = @$details['support_end_date'];

			if(!$supportEndDate){
				return '';
			}
		}

		$supportEndTime = strtotime($supportEndDate);
		$supportEndDateString = date('m/d/Y', $supportEndTime);

		if($supportEndTime < time()){
			// The support end date has past.
			$supportEndDateString = "<div class='not-supported'>$supportEndDateString</div>";
		}

		return $supportEndDateString;
	};

	$configsByPrefix = array();

	if (empty($versionsByPrefix)) {
		echo 'None';
	} else {
		?>
		<thead>
			<?php if(!isset($_GET['pid'])) { ?>
				<tr>
					<th></th>
					<th>Support<br>End Date<br>(if supported)</th>
					<th>Actions</th>
				</tr>
			<?php } ?>
		</thead>
		<?php
		foreach ($versionsByPrefix as $prefix => $version) {
			$config = ExternalModules::getConfig($prefix, $version, @$_GET['pid']);
			if(empty($config)){
				// This module's directory may have been removed while it was still enabled.
				continue;
			}

			$details = $detailsByPrefix[$prefix];
			$supportOverrideInfo = $supportOverrideInfoByPrefix[$prefix];

			## Add resources for custom javascript fields
			foreach(array_merge($config['project-settings'],$config['system-settings']) as $configRow) {
				if($configRow['source']) {
					$sources = explode(",",$configRow['source']);
					foreach($sources as $sourceLocation) {
						if(file_exists(ExternalModules::getModuleDirectoryPath($prefix,$version)."/".$sourceLocation)) {
							// include file from module directory
							ExternalModules::addResource(ExternalModules::getModuleDirectoryUrl($prefix,$version).$sourceLocation);
						}
						else if(file_exists(dirname(__DIR__)."/js/".$sourceLocation)) {
							// include file from external_modules directory
							ExternalModules::addResource("js/".$sourceLocation);
						}
					}
				}
			}


			$configsByPrefix[$prefix] = $config;
			$enabled = false;
			$system_enabled = ExternalModules::getSystemSetting($prefix, ExternalModules::KEY_ENABLED);
			$isDiscoverable = (ExternalModules::getSystemSetting($prefix, ExternalModules::KEY_DISCOVERABLE) == true);

			if (isset($_GET['pid'])) {
				$enabled = ExternalModules::getProjectSetting($prefix, $_GET['pid'], ExternalModules::KEY_ENABLED);
			}
			if ((isset($_GET['pid']) && $enabled) || (!isset($_GET['pid']) && isset($config['system-settings']))) {
			?>
				<tr data-module='<?= $prefix ?>' data-version='<?= $version ?>'>
					<td>
						<?php require __DIR__ . '/module-table.php'; ?>
					</td>
					<?php if(!isset($_GET['pid'])) { ?>
						<td class="support-end-date"><?=$renderSupportEndDate($details, $supportOverrideInfo)?></td>
					<?php } ?>
					<td class="external-modules-action-buttons">
						<?php
						$module_instance = ExternalModules::getModuleInstance($prefix);
						if((!empty($config['project-settings']) || (!empty($config['system-settings']) && !isset($_GET['pid'])))
						&& (!isset($_GET['pid']) || (isset($_GET['pid']) && self::hasProjectSettingSavePermission($prefix))) && $module_instance->redcap_module_configure_button_display($_GET['pid'])){
							?><button class='external-modules-configure-button'>Configure</button><?php
						}

						if(SUPER_USER) {
							?><button class='external-modules-disable-button'>Disable</button><?php
						}

						if(!isset($_GET['pid'])) {
							?><button class='external-modules-usage-button'>View Usage</button><?php
						}
						?>
					</td>
				</tr>
			<?php
			}
		}
	}

	?>
</table>

<?php
global $configsByPrefixJSON,$versionsByPrefixJSON;

// JSON_PARTIAL_OUTPUT_ON_ERROR was added here to fix an odd conflict between field-list and form-list types
// and some Hebrew characters on the "Israel: Healthcare Personnel (Hebrew)" project that could not be json_encoded.
// This workaround allows configs to be encoded anyway, even though the unencodable characters will be excluded
// (causing form-list and field-list to not work for any fields with unencodeable characters).
// I spent a couple of hours trying to find a solution, but was unable.  This workaround will have to do for now.
$configsByPrefixJSON = $versionsByPrefixJSON = false;
if (version_compare(PHP_VERSION, '5.5.0', '<')) {
	$configsByPrefixJSON = json_encode_rc($configsByPrefix);
}
if ($configsByPrefixJSON === false || $configsByPrefixJSON === null) {
	$configsByPrefixJSON = json_encode($configsByPrefix, JSON_PARTIAL_OUTPUT_ON_ERROR);
}
if($configsByPrefixJSON === false || $configsByPrefixJSON === null){
	echo '<script type="text/javascript">alert(' . json_encode('An error occurred while converting the configurations to JSON: ' . json_last_error_msg()) . ');</script>';
	throw new Exception('An error occurred while converting the configurations to JSON: ' . json_last_error_msg());
}

if (version_compare(PHP_VERSION, '5.5.0', '<')) {
	$versionsByPrefixJSON = json_encode_rc($versionsByPrefix);
}
if ($versionsByPrefixJSON === false || $versionsByPrefixJSON === null) {
	$versionsByPrefixJSON = json_encode($versionsByPrefix, JSON_PARTIAL_OUTPUT_ON_ERROR);
}
if ($versionsByPrefixJSON === false || $versionsByPrefixJSON === null) {
	echo '<script type="text/javascript">alert(' . json_encode('An error occurred while converting the versions to JSON: ' . json_last_error_msg()) . ');</script>';
	throw new Exception("An error occurred while converting the versions to JSON: " . json_last_error_msg());
}

require_once 'globals.php';

?>
<script type="text/javascript">
	ExternalModules.sortModuleTable($('#external-modules-enabled'))
</script>
