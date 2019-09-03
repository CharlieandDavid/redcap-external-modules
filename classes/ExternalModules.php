<?php
namespace ExternalModules;

use ExternalModules\FrameworkVersion2;

// Uncomment this line to quickly disable all External Module hooks (for troubleshooting).
//define('EXTERNAL_MODULES_KILL_SWITCH', '');

if (!defined(__DIR__)){
	define(__DIR__, dirname(__FILE__));
}

require_once __DIR__ . "/AbstractExternalModule.php";

if(PHP_SAPI == 'cli'){
	// This is required for redcap when running on the command line (including unit testing).
	define('NOAUTH', true);
}

// Call redcap_connect.php
if(!defined('APP_PATH_WEBROOT')){
	ExternalModules::callRedcapConnect();
}

if (class_exists('ExternalModules\ExternalModules')) {
	return;
}

use \Exception;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;

class ExternalModules
{
	const SYSTEM_SETTING_PROJECT_ID = 'NULL';
	const KEY_VERSION = 'version';
	const KEY_ENABLED = 'enabled';
	const KEY_DISCOVERABLE = 'discoverable-in-project';
	const KEY_CONFIG_USER_PERMISSION = 'config-require-user-permission';

	const KEY_RESERVED_IS_CRON_RUNNING = 'reserved-is-cron-running';
	const KEY_RESERVED_LAST_LONG_RUNNING_CRON_NOTIFICATION_TIME = 'reserved-last-long-running-cron-notification-time';

	const TEST_MODULE_PREFIX = 'UNIT-TESTING-PREFIX';

	const DISABLE_EXTERNAL_MODULE_HOOKS = 'disable-external-module-hooks';
	const RICH_TEXT_UPLOADED_FILE_LIST = 'rich-text-uploaded-file-list';

	const OVERRIDE_PERMISSION_LEVEL_SUFFIX = '_override-permission-level';
	const OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS = 'design';

	// We can't write values larger than this to the database, or they will be truncated.
	const SETTING_KEY_SIZE_LIMIT = 255;
	const SETTING_SIZE_LIMIT = 16777215;

	const EXTERNAL_MODULES_TEMPORARY_RECORD_ID = 'external-modules-temporary-record-id';

	const LONG_RUNNING_CRON_EMAIL_SUBJECT = 'External Module Long-Running Cron';
	const CRON_EXCEPTION_EMAIL_SUBJECT = 'External Module Exception in Cron Job';

	// The minimum required PHP version for External Modules to run
	const MIN_PHP_VERSION = '5.4.0';

	// Copy WordPress's time conveneience constants
	const MINUTE_IN_SECONDS = 60;
	const HOUR_IN_SECONDS = 60 * self::MINUTE_IN_SECONDS;
	const DAY_IN_SECONDS = 24 * self::HOUR_IN_SECONDS;
	const WEEK_IN_SECONDS = 7 * self::DAY_IN_SECONDS;
	const MONTH_IN_SECONDS = 30 * self::DAY_IN_SECONDS;
	const YEAR_IN_SECONDS = 365 * self::DAY_IN_SECONDS;

	private static $SERVER_NAME;

	# base URL for external modules
	public static $BASE_URL;

	# URL for the modules directory
	public static $BASE_PATH;
	public static $MODULES_URL;

	# path for the modules directory
	public static $MODULES_BASE_PATH;
	public static $MODULES_PATH;

	private static $USERNAME;

	# index is hook $name, then $prefix, then $version
	private static $delayed;
	private static $delayedLastRun;
	private static $INCLUDED_RESOURCES;

	private static $exitAfterHook = false;
	private static $hookStartTime;
	private static $hookBeingExecuted;
	private static $versionBeingExecuted;
	private static $temporaryRecordId;
	private static $disablingModuleDueToException = false;

	private static $initialized = false;
	private static $activeModulePrefix;
	private static $instanceCache = array();
	private static $idsByPrefix;

	private static $systemwideEnabledVersions;
	private static $projectEnabledDefaults;
	private static $projectEnabledOverrides;
	
	private static $deletedModules;

	private static $configs = array();

	

	# two reserved settings that are there for each project
	# KEY_VERSION, if present, denotes that the project is enabled system-wide
	# KEY_ENABLED is present when enabled for each project
	# Modules can be enabled for all projects (system-wide) if KEY_ENABLED == 1 for system value
	private static $RESERVED_SETTINGS = array(
		array(
			'key' => self::KEY_VERSION,
			'hidden' => true,
		),
		array(
			'key' => self::KEY_ENABLED,
			'name' => '<b>Enable module on all projects by default:</b><br>Unchecked (default) = Module must be enabled in each project individually',
			'type' => 'checkbox',
		),
		array(
			'key' => self::KEY_DISCOVERABLE,
			'name' => '<b>Make module discoverable by users:</b><br>Display info on External Modules page in all projects',
			'type' => 'checkbox'
		),
		array(
			'key' => self::KEY_CONFIG_USER_PERMISSION,
			'name' => '<b>Module configuration permissions in projects:</b><br>By default, users with Project Setup/Design privileges can modify this module\'s project-level configuration settings. Alternatively, project users can be given explicit module-level permission (via User Rights page) in order to do so',
			'type' => 'dropdown',
			"choices" => array(
				array("value" => "", "name" => "Require Project Setup/Design privilege"),
				array("value" => "true", "name" => "Require module-specific user privilege")
			)
		)
	);

	# defines criteria to judge someone is on a development box or not
	private static function isLocalhost()
	{
		$host = @$_SERVER['HTTP_HOST'];
		
		$is_dev_server = (isset($GLOBALS['is_development_server']) && $GLOBALS['is_development_server'] == '1');

		return $host == 'localhost' || $is_dev_server;
	}

	static function getAllFileSettings($config) {
		$fileFields = [];
		foreach($config as $row) {
			if($row['type'] && $row['type'] == 'sub_settings') {
				$fileFields = array_merge(self::getAllFileSettings($row['sub_settings']),$fileFields);
			}
			else if ($row['type'] && ($row['type'] == "file")) {
				$fileFields[] = $row['key'];
			}
		}
		return $fileFields;
	}

	static function formatRawSettings($moduleDirectoryPrefix, $pid, $rawSettings){
		# for screening out files below
		$config = self::getConfig($moduleDirectoryPrefix, null, $pid);
		$files = array();
		foreach(['system-settings', 'project-settings'] as $settingsKey){
			$files = array_merge(self::getAllFileSettings($config[$settingsKey]),$files);
		}

		$settings = array();

		# returns boolean
		$isExternalModuleFile = function($key, $fileKeys) {
			if (in_array($key, $fileKeys)) {
				return true;
			}
			foreach ($fileKeys as $fileKey) {
				if (preg_match('/^'.$fileKey.'____\d+$/', $key)) {
					return true;
				}
			}
			return false;
		};

		# store everything BUT files and multiple instances (after the first one)
		foreach($rawSettings as $key=>$value){
			# files are stored in a separate $.ajax call
			# numeric value signifies a file present
			# empty strings signify non-existent files (systemValues or empty)
			if (!$isExternalModuleFile($key, $files) || !is_numeric($value)) {
				if($value === '') {
					$value = null;
				}

				if (preg_match("/____/", $key)) {
					$parts = preg_split("/____/", $key);
					$shortKey = array_shift($parts);

					if(!isset($settings[$shortKey])){
						$settings[$shortKey] = [];
					}

					$thisInstance = &$settings[$shortKey];
					foreach($parts as $thisIndex) {
						if(!isset($thisInstance[$thisIndex])) {
							$thisInstance[$thisIndex] = [];
						}
						$thisInstance = &$thisInstance[$thisIndex];
					}

					$thisInstance = $value;
				} else {
					$settings[$key] = $value;
				}
			}
		}

		return $settings;
	}

	static function saveSettings($moduleDirectoryPrefix, $pid, $rawSettings)
	{
		$settings = self::formatRawSettings($moduleDirectoryPrefix, $pid, $rawSettings);

		$saveSqlByField = [];
		foreach($settings as $key => $values) {
			$sql = self::setSetting($moduleDirectoryPrefix, $pid, $key, $values);

			if(!empty($sql)){
				$saveSqlByField[$key] = $sql;
			}
		}

		return $saveSqlByField;
	}

	// Allow the addition of further module directories on a server.  For example, you may want to have
	// a folder used for local development or controlled by a local version control repository (e.g. modules_internal, or modules_staging)
	// $external_module_alt_paths, if defined, is a pipe-delimited array of paths stored in redcap_config.
	public static function getAltModuleDirectories()
	{
		global $external_module_alt_paths;
		$modulesDirectories = array();
		if (!empty($external_module_alt_paths)) {
			$paths = explode('|',$external_module_alt_paths);
			foreach ($paths as $path) {
				$path = trim($path);
				if($valid_path = realpath($path)) {
					array_push($modulesDirectories, $valid_path . DS);
				} else {
					// Try pre-pending APP_PATH_DOCROOT in case the path is relative to the redcap root
					$path = dirname(APP_PATH_DOCROOT) . DS . $path;
					if($valid_path = realpath($path)) {
						array_push($modulesDirectories, $valid_path . DS);
					}
				}
			}
		}
		return $modulesDirectories;
	}

	// Return array of all directories where modules are stored (including any alternate directories)
	public static function getModuleDirectories()
	{
		// Get module directories
		if (defined("APP_PATH_EXTMOD")) {
			$modulesDirectories = [dirname(APP_PATH_DOCROOT).DS.'modules'.DS, APP_PATH_EXTMOD.'example_modules'.DS];
		} else {
			$modulesDirectories = [dirname(APP_PATH_DOCROOT).DS.'modules'.DS, dirname(APP_PATH_DOCROOT).DS.'external_modules'.DS.'example_modules'.DS];
		}		
		// Add any alternate module directories
		$modulesDirectoriesAlt = self::getAltModuleDirectories();
		foreach ($modulesDirectoriesAlt as $thisDir) {
			array_push($modulesDirectories, $thisDir);
		}
		// Return directories array
		return $modulesDirectories;
	}

	// Return array of all module sub-directories located in directories where modules are stored (including any alternate directories)
	public static function getModulesInModuleDirectories()
	{
		$modules = array();
		// Get module sub-directories
		$modulesDirectories = self::getModuleDirectories();
		foreach ($modulesDirectories as $dir) {
			foreach (getDirFiles($dir) as $module) {
			    // Use the module directory as a key to prevent duplicates from alternate module directories.
				$modules[$module] = true;
			}
		}
		// Return directories array
		return array_keys($modules);
	}

	# initializes the External Module aparatus
	static function initialize()
	{
		if(self::isLocalhost()){
			// Assume this is a developer's machine and enable errors.
			ini_set('display_errors', 1);
			ini_set('display_startup_errors', 1);
			error_reporting(E_ALL);
		}
		
		// Get module directories
		$modulesDirectories = self::getModuleDirectories();

		$modulesDirectoryName = '/modules/';
		if(strpos($_SERVER['REQUEST_URI'], $modulesDirectoryName) === 0){
			// We used to throw an exception here, but we got sick of those emails (especially when bots triggered them).
			echo '<pre>';
			echo 'Requests directly to module version directories are disallowed.  Please use the getUrl() method to build urls to your module pages instead.<br><br>';
			var_dump(debug_backtrace());
			echo '</pre>';
			die();
		}

		self::$SERVER_NAME = SERVER_NAME;

		// We must use APP_PATH_WEBROOT_FULL here because some REDCap installations are hosted under subdirectories.
		self::$BASE_URL = defined("APP_URL_EXTMOD") ? APP_URL_EXTMOD : APP_PATH_WEBROOT_FULL.'external_modules/';
		self::$MODULES_URL = APP_PATH_WEBROOT_FULL.'modules/';
		self::$BASE_PATH = defined("APP_PATH_EXTMOD") ? APP_PATH_EXTMOD : APP_PATH_DOCROOT . '../external_modules/';
		self::$MODULES_BASE_PATH = dirname(APP_PATH_DOCROOT) . DS;
		self::$MODULES_PATH = $modulesDirectories;
		self::$INCLUDED_RESOURCES = [];

		# runs whenever a cron/hook functions
		register_shutdown_function(function () {
			// Get the error before doing anything else, since it would be overwritten by any potential errors/warnings in this function.
			$error = error_get_last();

			$activeModulePrefix = self::getActiveModulePrefix();

			if ($activeModulePrefix == null) {
				// A fatal error did not occur in the middle of a module operation.
				return;
			}

			if($error && $error['type'] === E_NOTICE){
				// This is just a notice, which likely means it occurred BEFORE an offending die/exit call.
				// Ignore this notice and show the general die/exit call warning instead.
				$error = null;
			}

			if (empty(self::$hookBeingExecuted)) {
				// PHP must have died in the middle of getModuleInstance()
				$message = 'Could not instantiate';
			} else {
				$message = "The '" . self::$hookBeingExecuted . "' hook did not complete for";

				// If the current "hook" was a cron, we need to unlock it so it can run again.
				$config = self::getConfig($activeModulePrefix);
				foreach ($config['crons'] as $cron) {
					if ($cron['cron_name'] == self::$hookBeingExecuted) {
						self::unlockCron($activeModulePrefix);
						break;
					}
				}
			}

			$message .= " the '$activeModulePrefix' module";

			$sendAdminEmail = true;
			if($error){
				$message .= " because of the following error:\n\n";
				$message .= 'Error Message: ' . $error['message'] . "\n";
				$message .= 'File: ' . $error['file'] . "\n";
				$message .= 'Line: ' . $error['line'] . "\n";
			} else {
				$output = ob_get_contents();
				if(strpos($output, "multiple browser tabs of the same REDCap page") !== false){
					// REDCap detected and killed a duplicate request/query.
					// The is expected behavior.  Do not report this error.
					return;
				}
				else{
					$message .= ", but a specific cause could not be detected.  This could be caused by a die() or exit() call in the module which needs to be replaced with \$module->exitAfterHook() to allow other modules to execute for the current hook.";
				}
			}

			if (basename($_SERVER['REQUEST_URI']) == 'enable-module.php') {
				// An admin was attempting to enable a module.
				// Simply display the error to the current user, instead of sending an email to all admins about it.
				echo $message;
				return;
			}

			if (self::isSuperUser() && !self::isLocalhost()) {
				$message .= "\nThe current user is a super user, so this module will be automatically disabled.\n";

				// We can't just call disable() from here because the database connection has been destroyed.
				// Disable this module via AJAX instead.
				?>
				<br>
				<h4 id="external-modules-message">
					A fatal error occurred while loading the "<?=$activeModulePrefix?>" external module.<br>
					Disabling that module...
				</h4>
				<script type="text/javascript">
					var request = new XMLHttpRequest();
					request.onreadystatechange = function () {
						if (request.readyState == XMLHttpRequest.DONE) {
							var messageElement = document.getElementById('external-modules-message')
							if (request.responseText == 'success') {
								messageElement.innerHTML = 'The "<?=$activeModulePrefix?>" external module was automatically disabled in order to allow REDCap to function properly.  The REDCap administrator has been notified.  Please save a copy of the above error and fix it before re-enabling the module.';
							}
							else {
								messageElement.innerHTML += '<br>An error occurred while disabling the "<?=$activeModulePrefix?>" module: ' + request.responseText;
							}
						}
					};

					request.open("POST", "<?=self::$BASE_URL?>manager/ajax/disable-module.php?<?=self::DISABLE_EXTERNAL_MODULE_HOOKS?>");
					request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
					request.send("module=<?=$activeModulePrefix?>");
				</script>
				<?php
			}

			error_log($message);
			if ($sendAdminEmail) {
				ExternalModules::sendAdminEmail("REDCap External Module Error - $activeModulePrefix", $message, $activeModulePrefix);
			}
		});
	}

	private static function isSuperUser()
	{
		return defined("SUPER_USER") && SUPER_USER == 1;
	}

	# controls which module is currently being manipulated
	private static function setActiveModulePrefix($prefix)
	{
		self::$activeModulePrefix = $prefix;
	}

	# returns which module is currently being manipulated
	private static function getActiveModulePrefix()
	{
		return self::$activeModulePrefix;
	}

	private static function lastTwoNodes($hostname) {
		$nodes = preg_split("/\./", $hostname);
		$count = count($nodes);
		return $nodes[$count - 2].".".$nodes[$count - 1];
	}

	private static function isVanderbilt()
	{
		// We don't use REDCap's isVanderbilt() function any more because it is
		// based on $_SERVER['SERVER_NAME'], which is not set during cron jobs.
		return (strpos(self::$SERVER_NAME, "vanderbilt.edu") !== false);
	}

	private static function getAdminEmailMessage($subject, $message, $prefix)
	{
		$message .= "<br><br>URL: " . (isset($_SERVER['HTTPS']) ? "https" : "http") . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "<br>";
		$message .= "Server: " . SERVER_NAME . " (" . gethostname() . ")<br>";
		
		if(defined('USERID')){
			$message .= "User: " . USERID . "<br>";
		}

		if (self::isVanderbilt()) {
			$from = 'datacore@vanderbilt.edu';
			$to = self::getDatacoreEmails([]);
		}
		else{
			global $project_contact_email;
			$from = $project_contact_email;
			$to = [$project_contact_email];

			if ($prefix) {
				try {
					$config = self::getConfig($prefix);
					$authorEmails = [];
					foreach ($config['authors'] as $author) {
						if (isset($author['email']) && preg_match("/@/", $author['email'])) {
							$parts = preg_split("/@/", $author['email']);
							if (count($parts) >= 2) {
								$domain = $parts[1];
								$authorEmail = $author['email'];
								$authorEmails[] = $authorEmail;

								if (self::lastTwoNodes(self::$SERVER_NAME) == $domain) {
									$to[] = $authorEmail;
								}
							}
						}
					}

					$message .= "Module Name: " . $config['name'] . " ($prefix)<br>";
					$message .= "Module Author(s): " . implode(', ', $authorEmails) . "<br>";
				} catch (Exception $e) {
					// The problem is likely due to loading the configuration.  Ignore this Exception.
				}
			}
		}

		if (!empty(self::$hookBeingExecuted)) {
			$seconds = time() - self::$hookStartTime;
			$message .= "Run Time: $seconds seconds<br>";
		}

		$email = new \Message();
		$email->setFrom($from);
		$email->setTo(implode(',', $to));
		$email->setSubject($subject);

		$message = str_replace("\n", "<br>", $message);
		$email->setBody($message, true);

		return $email;
	}

	public static function sendAdminEmail($subject, $message, $prefix = null)
	{
		if(self::isTesting()){
			// Report back to our test class instead of sending an email.
			ExternalModulesTest::$lastSendAdminEmailArgs = func_get_args();
			return;
		}

		if(self::isVanderbilt() && in_array(gethostname(), ['ori1007lt', 'ori1007lr', 'ori1007lp', 'ori1008lp', 'ori3007lp', 'ori3008lp'])){
			// This is one of the new Vandy REDCap servers that are just being tested currently.
			// Log errors on these servers for now instead of emailing (until they're stable).
			error_log("ExternalModules - sendAdminEmail() - $subject - $prefix - $message");
			return;
		}

		$email = self::getAdminEmailMessage($subject, $message, $prefix);
		$email->send();
	}

	# there are two situations which external modules are displayed
	# under a project or under the control center

	# this gets the project header
	static function getProjectHeaderPath()
	{
		return APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
	}

	static function getProjectFooterPath()
	{
		return APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	}

	# disables a module system-wide
	static function disable($moduleDirectoryPrefix, $dueToException)
	{
		$version = self::getModuleVersionByPrefix($moduleDirectoryPrefix);

		// When a module is disabled due to certain exceptions (like invalid config.json syntax),
		// calling the disable hook would cause an infinite loop.
		if (!$dueToException) {
			self::callHook('redcap_module_system_disable', array($version), $moduleDirectoryPrefix);
		}

		// Disable any cron jobs in the crons table
		self::removeCronJobs($moduleDirectoryPrefix);
		
		// This flag allows the version system setting to be removed if the current user is not a superuser.
		// Without it, a secondary exception would occur saying that the user doesn't have access to remove this setting.
		self::$disablingModuleDueToException = $dueToException;
		self::removeSystemSetting($moduleDirectoryPrefix, self::KEY_VERSION);
	}

	# enables a module system-wide
//	static function enable($moduleDirectoryPrefix, $version)
	static function enableForProject($moduleDirectoryPrefix, $version, $project_id)
	{
		# Attempt to create an instance of the module before enabling it system wide.
		# This should catch problems like syntax errors in module code.
		$instance = self::getModuleInstance($moduleDirectoryPrefix, $version);

		if(!is_subclass_of($instance, 'ExternalModules\AbstractExternalModule')){
			throw new Exception("This module's main class does not extend AbstractExternalModule!");
		}
		
		// Ensure compatibility with PHP version and REDCap version before instantiating the module class
		self::isCompatibleWithREDCapPHP($moduleDirectoryPrefix, $version);

		if (!isset($project_id)) {
			$config = ExternalModules::getConfig($moduleDirectoryPrefix, $version);
			$enabledPrefix = self::getEnabledPrefixForNamespace($config['namespace']);
			if(!empty($enabledPrefix)){
				throw new Exception("This module cannot be enabled because a different version of the module is already enabled under the following prefix: $enabledPrefix");
			}

			$old_version = self::getModuleVersionByPrefix($moduleDirectoryPrefix);

			self::setSystemSetting($moduleDirectoryPrefix, self::KEY_VERSION, $version);
			self::cacheAllEnableData();
			self::initializeSettingDefaults($instance);

			if ($old_version) {
				self::callHook('redcap_module_system_change_version', array($version, $old_version), $moduleDirectoryPrefix);
			}
			else {
				self::callHook('redcap_module_system_enable', array($version), $moduleDirectoryPrefix);
			}

			self::initializeCronJobs($instance, $moduleDirectoryPrefix);
		} else {
			self::initializeSettingDefaults($instance, $project_id);
			self::setProjectSetting($moduleDirectoryPrefix, $project_id, self::KEY_ENABLED, true);
			self::cacheAllEnableData();
			self::callHook('redcap_module_project_enable', array($version, $project_id), $moduleDirectoryPrefix);
		}
	}

	private static function getEnabledPrefixForNamespace($namespace){
		$versionsByPrefix = ExternalModules::getEnabledModules();
		foreach ($versionsByPrefix as $prefix => $version) {
			$config = ExternalModules::getConfig($prefix, $version);
			if($config['namespace'] === $namespace){
				return $prefix;
			}
		}

		return null;
	}

	static function enable($moduleDirectoryPrefix, $version)
	{
		self::enableForProject($moduleDirectoryPrefix, $version, null);
	}

	static function enableAndCatchExceptions($moduleDirectoryPrefix, $version)
	{
		try {
			self::enable($moduleDirectoryPrefix, $version);
		} catch (\Exception $e) {
			self::disable($moduleDirectoryPrefix, true); // Disable the module in case the exception occurred after it was enabled in the DB.
			self::setActiveModulePrefix(null); // Unset the active module prefix, so an error email is not sent out.
			return $e;
		}

		return null;
	}

	# initializes any crons contained in the config, and adds them to the redcap_crons table
	# timed crons are read from the config, so they are not entered into any table
	static function initializeCronJobs($moduleInstance, $moduleDirectoryPrefix=null)
	{
		// First, try and remove any crons that exist for this module (just in case)
		self::removeCronJobs($moduleDirectoryPrefix);
		// Parse config to get cron info
		$config = $moduleInstance->getConfig();
		if (!isset($config['crons'])) return;
		// Loop through all defined crons
		foreach ($config['crons'] as $cron) 
		{
			// Make sure we have what we need
			self::validateCronAttributes($cron, $moduleInstance);
			// Add the cron
			self::addCronJobToTable($cron, $moduleInstance);
		}
	}

	# adds module cron jobs to the redcap_crons table
	static function addCronJobToTable($cron=array(), $moduleInstance=null)
	{
		// Get external module ID
		$externalModuleId = self::getIdForPrefix($moduleInstance->PREFIX);
		if (empty($externalModuleId) || empty($moduleInstance)) return false;

		if (self::isValidTabledCron($cron)) {
			// Add to table
			$sql = "insert into redcap_crons (cron_name, external_module_id, cron_description, cron_frequency, cron_max_run_time) values
					('".db_escape($cron['cron_name'])."', $externalModuleId, '".db_escape($cron['cron_description'])."', 
					'".db_escape($cron['cron_frequency'])."', '".db_escape($cron['cron_max_run_time'])."')";
			if (!db_query($sql)) {
				// If fails on one cron, then delete any added so far for this module
				self::removeCronJobs($moduleInstance->PREFIX);
				// Return error
				throw new Exception("One or more cron jobs for this module failed to be created.");
			}
		}
	}

	# validate module config's cron jobs' attributes. pass in the $cron job as an array of attributes.
	static function validateCronAttributes(&$cron=array(), $moduleInstance=null)
	{
		$isValidTabledCron = self::isValidTabledCron($cron);
		$isValidTimedCron = self::isValidTimedCron($cron);

		// Ensure certain attributes are integers
		if ($isValidTabledCron) {
			$cron['cron_frequency'] = (int)$cron['cron_frequency'];
			$cron['cron_max_run_time'] = (int)$cron['cron_max_run_time'];
		} else if ($isValidTimedCron) {
			$cron['cron_minute'] = (int) $cron['cron_minute'];
			if (isset($cron['cron_hour'])) {
				$cron['cron_hour'] = (int) $cron['cron_hour'];
			}
			if (isset($cron['cron_weekday'])) {
				$cron['cron_weekday'] = (int) $cron['cron_weekday'];
			}
			if (isset($cron['cron_monthday'])) {
				$cron['cron_monthday'] = (int) $cron['cron_monthday'];
			}
		}
		// Make sure we have what we need
		if (!isset($cron['cron_name']) || empty($cron['cron_name']) || !isset($cron['cron_description']) || !isset($cron['method'])) {
			throw new Exception("Some cron job attributes in the module's config file are not correct or are missing.");
		}
		if ((!isset($cron['cron_frequency']) || !isset($cron['cron_max_run_time'])) && (!isset($cron['cron_hour']) && !isset($cron['cron_minute']))) {
			throw new Exception("Some cron job attributes in the module's config file are not correct or are missing (cron_frequency/cron_max_run_time or hour/minute).");
		}

		// Name must be no more than 100 characters
		if (strlen($cron['cron_name']) > 100) {
			throw new Exception("Cron job 'name' must be no more than 100 characters.");
		}
		// Name must be alphanumeric with dashes or underscores (no spaces, dots, or special characters)
		if (!preg_match("/^([a-z0-9_-]+)$/", $cron['cron_name'])) {
			throw new Exception("Cron job 'name' can only have lower-case letters, numbers, and underscores (i.e., no spaces, dashes, dots, or special characters).");
		}

		// Make sure integer attributes are integers
		if ($isValidTabledCron && $isValidTimedCron) { 
			throw new Exception("Cron job attributes 'cron_frequency' and 'cron_max_run_time' cannot be set with 'cron_hour' and 'cron_minute'. Please choose one timing setting or the other, but not both.");
		}
		if (!$isValidTabledCron && !$isValidTimedCron) {
			throw new Exception("Cron job attributes 'cron_frequency' and 'cron_max_run_time' must be numeric and greater than zero --OR-- attributes 'cron_hour' and 'cron_minute' must be numeric and valid.");
		}

		// If method does not exist, then disable module
		if (!empty($moduleInstance) && !method_exists($moduleInstance, $cron['method'])) {
			throw new Exception("The external module \"{$moduleInstance->PREFIX}_{$moduleInstance->VERSION}\" has a cron job named \"{$cron['cron_name']}\" that is trying to call a method \"{$cron['method']}\", which does not exist in the module class.");
		}
	}

	# remove all crons for a given module
	static function removeCronJobs($moduleDirectoryPrefix=null)
	{
		if (empty($moduleDirectoryPrefix)) return false;
		// If a module directory has been deleted, then we have to use this alternative way to remove its crons			
		$externalModuleId = self::getIdForPrefix($moduleDirectoryPrefix);
		// Remove crons from db table
		$sql = "delete from redcap_crons where external_module_id = '".db_escape($externalModuleId)."'";
		return db_query($sql);
	}

	# validate EVERY module config's cron jobs' attributes. fix them in the redcap_crons table if incorrect/out-of-date.
	static function validateAllModuleCronJobs()
	{
		// Set array of modules that got fixed
		$fixedModules = array();
		// Get all enabled modules
		$enabledModules = self::getEnabledModules();
		// Cron items to check in db table
		$cronAttrCheck = array('cron_frequency', 'cron_max_run_time', 'cron_description');
		// Parse each enabled module's config, and see if any have cron jobs
		foreach ($enabledModules as $moduleDirectoryPrefix=>$version) {
			try {
				// First, make sure the module directory exists. If not, then disable the module.
				$modulePath = self::getModuleDirectoryPath($moduleDirectoryPrefix, $version);
				if (!$modulePath) {
					// Delete the cron jobs to prevent issues
					self::removeCronJobs($moduleDirectoryPrefix);
					// Continue with next module
					continue;
				}
				// Parse the module config to get the cron info
				$moduleInstance = self::getModuleInstance($moduleDirectoryPrefix, $version);
				$config = $moduleInstance->getConfig();
				if (!isset($config['crons'])) continue;

				// Get external module ID
				$externalModuleId = self::getIdForPrefix($moduleInstance->PREFIX);
				// Validate each cron attributes
				foreach ($config['crons'] as $cron) {
					// Validate the cron's attributes
					self::validateCronAttributes($cron, $moduleInstance);
					if (self::isValidTabledCron($cron)) {
						// Ensure the cron job's info in the db table are all correct
						$cronInfoTable = self::getCronJobFromTable($cron['cron_name'], $externalModuleId);
						if (empty($cronInfoTable)) {
							// If this cron is somehow missing, then add it to the redcap_crons table
							self::addCronJobToTable($cron, $moduleInstance);
						}
						// If any info is different, then correct it in table
						foreach ($cronAttrCheck as $attr) {
							if ($cron[$attr] != $cronInfoTable[$attr]) {
								// Fix the cron
								if (self::updateCronJobInTable($cron, $externalModuleId)) {
									$fixedModules[] = "\"$moduleDirectoryPrefix\"";
								}
								// Go to next cron
								continue;
							}
						}
					}
				}
			} catch (Exception $e){
				// Disable the module and send email to admin
				self::disable($moduleDirectoryPrefix, true);
				$message = "The '$moduleDirectoryPrefix' module was automatically disabled because of the following error:\n\n$e";
				error_log($message);
				ExternalModules::sendAdminEmail("REDCap External Module Automatically Disabled - $moduleDirectoryPrefix", $message, $moduleDirectoryPrefix);
			}
		}
		// Return array of fixed modules
		return array_unique($fixedModules);
	}

	# obtain the info of a cron job for a module in the redcap_crons table
	static function getCronJobFromTable($cron_name, $externalModuleId)
	{
		$sql = "select cron_frequency, cron_max_run_time, cron_description from redcap_crons 
				where cron_name = '".db_escape($cron_name)."' and external_module_id = '".db_escape($externalModuleId)."'";
		$q = db_query($sql);
		return (db_num_rows($q) > 0) ? db_fetch_assoc($q) : array();
	}

	# prerequisite: is a valid tabled cron
	# obtain the info of a cron job for a module in the redcap_crons table
	static function updateCronJobInTable($cron=array(), $externalModuleId)
	{
		if (empty($cron) || empty($externalModuleId)) return false;
		$sql = "update redcap_crons set cron_frequency = '".db_escape($cron['cron_frequency'])."', cron_max_run_time = '".db_escape($cron['cron_max_run_time'])."', 
				cron_description = '".db_escape($cron['cron_description'])."'
				where cron_name = '".db_escape($cron['cron_name'])."' and external_module_id = '".db_escape($externalModuleId)."'";
		return db_query($sql);
	}

	# initializes the system settings
	static function initializeSettingDefaults($moduleInstance, $pid=null)
	{
		$config = $moduleInstance->getConfig();
		$settings = empty($pid) ? $config['system-settings'] : $config['project-settings'];
		foreach($settings as $details){
			$key = $details['key'];
			$default = @$details['default'];
			$existingValue = empty($pid) ? $moduleInstance->getSystemSetting($key) : $moduleInstance->getProjectSetting($key, $pid);
			if(isset($default) && $existingValue == null){
				if (empty($pid)) {
					$moduleInstance->setSystemSetting($key, $default);
				} else {
					$moduleInstance->setProjectSetting($key, $default, $pid);
				}
			}
		}
	}

	static function getSystemSetting($moduleDirectoryPrefix, $key)
	{
		return self::getSetting($moduleDirectoryPrefix, self::SYSTEM_SETTING_PROJECT_ID, $key);
	}

	static function getSystemSettings($moduleDirectoryPrefixes, $keys = null)
	{
		return self::getSettings($moduleDirectoryPrefixes, self::SYSTEM_SETTING_PROJECT_ID, $keys);
	}

	static function setSystemSetting($moduleDirectoryPrefix, $key, $value)
	{
		self::setProjectSetting($moduleDirectoryPrefix, self::SYSTEM_SETTING_PROJECT_ID, $key, $value);
	}

	static function removeSystemSetting($moduleDirectoryPrefix, $key)
	{
		self::removeProjectSetting($moduleDirectoryPrefix, self::SYSTEM_SETTING_PROJECT_ID, $key);
	}

	static function setProjectSetting($moduleDirectoryPrefix, $projectId, $key, $value)
	{
		self::setSetting($moduleDirectoryPrefix, $projectId, $key, $value);
	}

	# value is edoc ID
	static function setSystemFileSetting($moduleDirectoryPrefix, $key, $value)
	{
		self::setFileSetting($moduleDirectoryPrefix, self::SYSTEM_SETTING_PROJECT_ID, $key, $value);
	}

	# value is edoc ID
	static function setFileSetting($moduleDirectoryPrefix, $projectId, $key, $value)
	{
		// The string type parameter is only needed because of some incorrect handling on the js side that needs to be refactored.
		self::setSetting($moduleDirectoryPrefix, $projectId, $key, $value, 'string');
	}

	# returns boolean
	public static function isProjectSettingDefined($prefix, $key)
	{
		$config = self::getConfig($prefix);
		foreach($config['project-settings'] as $setting){
			if($setting['key'] == $key){
				return true;
			}
		}

		return false;
	}

	private static function isReservedSettingKey($key)
	{
		foreach(self::$RESERVED_SETTINGS as $setting){
			if($setting['key'] == $key){
				return true;
			}
		}

		return false;
	}

	private static function areSettingPermissionsUserBased($moduleDirectoryPrefix, $key)
	{
		if(self::isReservedSettingKey($key)){
			// Require user based setting permissions for reserved keys.
			// We don't want modules to be able to override permissions for enabling/disabling/updating modules.
			return true;
		}

		if(!empty(self::$hookBeingExecuted)){
			// We're inside a hook.  Disable user based setting permissions, leaving control up to the module author.
			// There are many cases where modules might want to use settings to track state based on the actions
			// of survey respondents or users without design rights.
			return false;
		}

		// The following might be removed in the future (since disableUserBasedSettingPermissions() has been deprecated).
		// If that happens, we should make sure to return true here to cover calls within the framework (like setting project settings via the settings dialog).
		$module = self::getModuleInstance($moduleDirectoryPrefix);
		return $module->areSettingPermissionsUserBased();
	}

	private static function isManagerUrl()
	{
		$currentUrl = (SSL ? "https" : "http") . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		return strpos($currentUrl, self::$BASE_URL . 'manager') !== false;
	}

	public static function getLockName($moduleId, $projectId)
	{
		return db_real_escape_string("external-module-setting-$moduleId-$projectId");
	}

	# this is a helper method
	# call set [System,Project] Setting instead of calling this method
	private static function setSetting($moduleDirectoryPrefix, $projectId, $key, $value, $type = "")
	{
		$externalModuleId = self::getIdForPrefix($moduleDirectoryPrefix);
		$lockName = self::getLockName($externalModuleId, $projectId);

		// The natural solution to prevent duplicates would be a unique key.
		// That unfortunately doesn't work for the settings table since the total length of the appropriate key columns is longer than the maximum unique key length.
		// Instead, we use GET_LOCK() and check the existing value before inserting/updating to prevent duplicates.
		// This seems to work better than transactions since it has no risk of deadlock, and allows for limiting mutual exclusion to a per module and project basis (using the lock name).
		$result = self::query("SELECT GET_LOCK('$lockName', 5)");
		$row = $result->fetch_row();
		$releaseLockSql = "SELECT RELEASE_LOCK('$lockName')";
		if($row[0] !== '1'){
			throw new Exception("Lock acquisition timed out while setting a setting for module $moduleDirectoryPrefix and project $projectId.  This should not happen under normal circumstances.  However, the following query may be used to manually release the lock if necessary: $releaseLockSql");
		}

		$releaseLock = function() use ($lockName, $releaseLockSql) {
			ExternalModules::query($releaseLockSql);
		};

		try{
			if (self::areSettingPermissionsUserBased($moduleDirectoryPrefix, $key)) {
				$errorMessageSuffix = "You may want to use the disableUserBasedSettingPermissions() method to disable this check and leave permissions up the the module's code.";

				if ($projectId == self::SYSTEM_SETTING_PROJECT_ID) {
					if (!defined("CRON") && !self::hasSystemSettingsSavePermission($moduleDirectoryPrefix)) {
						throw new Exception("You don't have permission to save system settings!  $errorMessageSuffix");
					}
			}
			else if (!defined("CRON") && !self::hasProjectSettingSavePermission($moduleDirectoryPrefix, $key)) {
					throw new Exception("You don't have permission to save project settings!  $errorMessageSuffix");
				}
			}

			$oldValue = self::getSetting($moduleDirectoryPrefix, $projectId, $key);

			$projectId = db_real_escape_string($projectId);
			$key = db_real_escape_string($key);

			$oldType = gettype($oldValue);
			if ($oldType == 'array' || $oldType == 'object') {
				$oldValue = json_encode($oldValue);
			}

			# if $value is an array or object, then encode as JSON
			# else store $value as type specified in gettype(...)
			if ($type === "") {
				$type = gettype($value);
			}

			if ($type == "array" || $type == "object") {
				// TODO: ideally we would also include a sql statement to update all existing type='json' module settings to json-array
				// to clean up existing entries using the non-specific 'json' format.
				$type = "json-$type";
				$value = json_encode($value);
			}

			// Triple equals includes type checking, and even order checking for complex nested arrays!
			if ($value === $oldValue) {
				// Nothing changed, so we don't need to do anything.
				$releaseLock();
				return;
			}

			if (!$projectId || $projectId == "" || strtoupper($projectId) === 'NULL') {
				$pidString = "NULL";
			}
			else{
				// Require an integer to prevent sql injection.
				$pidString = self::requireInteger($projectId);
			}

			if ($type == "boolean") {
				$value = ($value) ? 'true' : 'false';
			}

			if ($value === null) {
				$event = "DELETE";
				$sql = "DELETE FROM redcap_external_module_settings
						WHERE
							external_module_id = $externalModuleId
							AND " . self::getSqlEqualClause('project_id', $pidString) . "
							AND `key` = '$key'";
			} else {
				$value = db_real_escape_string($value);

				if (strlen($key) > self::SETTING_KEY_SIZE_LIMIT) {
					throw new Exception("Cannot save the setting for prefix '$moduleDirectoryPrefix' and key '$key' because the key is longer than the " . self::SETTING_KEY_SIZE_LIMIT . " character limit.");
				}

				if (strlen($value) > self::SETTING_SIZE_LIMIT) {
					throw new Exception("Cannot save the setting for prefix '$moduleDirectoryPrefix' and key '$key' because the value is larger than the " . self::SETTING_SIZE_LIMIT . " character limit.");
				}

				if ($oldValue === null) {
					$event = "INSERT";
					$sql = "INSERT INTO redcap_external_module_settings
								(
									`external_module_id`,
									`project_id`,
									`key`,
									`type`,
									`value`
								)
							VALUES
							(
								$externalModuleId,
								$pidString,
								'$key',
								'$type',
								'$value'
							)";
				} else {
					if ($key == self::KEY_ENABLED && $value == "false" && $pidString != "NULL") {
						$version = self::getModuleVersionByPrefix($moduleDirectoryPrefix);
						self::callHook('redcap_module_project_disable', array($version, $projectId), $moduleDirectoryPrefix);
					}

					$event = "UPDATE";
					$sql = "UPDATE redcap_external_module_settings
							SET value = '$value',
								type = '$type'
							WHERE
								external_module_id = $externalModuleId
								AND " . self::getSqlEqualClause('project_id', $pidString) . "
								AND `key` = '$key'";
				}
			}

			self::query($sql);

			$affectedRows = db_affected_rows();

			if ($affectedRows != 1) {
				throw new Exception("Unexpected number of affected rows ($affectedRows) on External Module setting query: $sql");
			}

			$releaseLock();

			return $sql;
		}
		catch(Exception $e){
			$releaseLock();
			throw $e;
		}
	}

	# getSystemSettingsAsArray and getProjectSettingsAsArray

	# get all the settings as an array instead of one by one
	# returns an associative array with index of key and value of value
	# arrays of values (e.g., repeatble) will be returned as arrays
	# As in,
	# 	$ary['key'] = 'string';
	#	$ary['key2'] = 123;
	#	$ary['key3'] = [ 1, 'abc', 3 ];
	#	$ary['key3'][0] = 1;
	#	$ary['key3'][1] = 'abc';
	#	$ary['key3'][2] = 3;

	static function getSystemSettingsAsArray($moduleDirectoryPrefixes)
	{
		return self::getSettingsAsArray($moduleDirectoryPrefixes);
	}

	static function getProjectSettingsAsArray($moduleDirectoryPrefixes, $projectId)
	{
		if (!$projectId) {
			throw new Exception("The Project Id cannot be null!");
		}
		return self::getSettingsAsArray($moduleDirectoryPrefixes, $projectId);
	}

	private static function getSettingsAsArray($moduleDirectoryPrefixes, $projectId = NULL)
	{
		if(empty($moduleDirectoryPrefixes)){
			throw new Exception('One or more module prefixes must be specified!');
		}

		if ($projectId === NULL) {
			$result = self::getSettings($moduleDirectoryPrefixes, self::SYSTEM_SETTING_PROJECT_ID);
		} else {
			$result = self::getSettings($moduleDirectoryPrefixes, array(self::SYSTEM_SETTING_PROJECT_ID, $projectId));
		}

		$settings = array();
		while($row = self::validateSettingsRow(db_fetch_assoc($result))){
			$key = $row['key'];
			$value = $row['value'];

			$setting =& $settings[$key];
			if(!isset($setting)){
				$setting = array();
				$settings[$key] =& $setting;
			}

			if($row['project_id'] === null){
				$setting['system_value'] = $value;

				if(!isset($setting['value'])){
					$setting['value'] = $value;
				}
			}
			else{
				$setting['value'] = $value;
			}
		}

		return $settings;
	}

	static function getSettings($moduleDirectoryPrefixes, $projectIds, $keys = array())
	{
		$whereClauses = array();

		if (!empty($moduleDirectoryPrefixes)) {
			$whereClauses[] = self::getSQLInClause('m.directory_prefix', $moduleDirectoryPrefixes);
		}

		if (!empty($projectIds)) {
			$whereClauses[] = self::getSQLInClause('s.project_id', $projectIds);
		}
		else if($projectIds !== null) {
			$whereClauses[] = self::getSQLInClause('s.project_id', ["NULL"]);
		}

		if (!empty($keys)) {
			$whereClauses[] = self::getSQLInClause('s.key', $keys);
		}

		return self::query("SELECT directory_prefix, s.project_id, s.project_id, s.key, s.value, s.type
							FROM redcap_external_modules m
							JOIN redcap_external_module_settings s
								ON m.external_module_id = s.external_module_id
							WHERE " . implode(' AND ', $whereClauses));
	}

	static function getEnabledProjects($prefix)
	{
		$prefix = db_real_escape_string($prefix);

		return self::query("SELECT s.project_id, p.app_title as name
							FROM redcap_external_modules m
							JOIN redcap_external_module_settings s
								ON m.external_module_id = s.external_module_id
							JOIN redcap_projects p
								ON s.project_id = p.project_id
							WHERE m.directory_prefix = '$prefix'
								and p.date_deleted IS NULL
								and `key` = '" . self::KEY_ENABLED . "'
								and value = 'true'");
	}

	# row contains the data type in field 'type' and the value in field 'value'
	# this makes sure that the data returned in 'value' is of that correct type
	static function validateSettingsRow($row)
	{
		if ($row == null) {
			return null;
		}

		$type = $row['type'];
		$value = $row['value'];

		if ($type == 'file') {
			// This is a carry over from the old way edoc IDs were stored.  Convert it to the new way.
			// Really this should be 'integer', but it must be 'string' currently because of some incorrect handling on the js side that needs to be corrected.
			$type = 'string';
		}

		if ($type == "json" || $type == "json-array") {
			$json = json_decode($value,true);
			if ($json !== false) {
				$value = $json;
			}
		}
		else if ($type == "boolean") {
			if ($value === "true") {
				$value = true;
			} else if ($value === "false") {
				$value = false;
			}
		}
		else if ($type == "json-object") {
			$value = json_decode($value,false);
		}
		else if (!settype($value, $type)) {
			throw new Exception('Unable to set the type of "' . $value . '" to "' . $type . '"!  This should never happen, as it means unexpected/inconsistent values exist in the database.');
		}

		$row['value'] = $value;

		return $row;
	}

	private static function getSetting($moduleDirectoryPrefix, $projectId, $key)
	{
		if(empty($key)){
			throw new Exception('The setting key cannot be empty!');
		}

		$result = self::getSettings($moduleDirectoryPrefix, $projectId, $key);

		$numRows = db_num_rows($result);
		if($numRows == 1) {
			$row = self::validateSettingsRow(db_fetch_assoc($result));

			return $row['value'];
		}
		else if($numRows == 0){
			return null;
		}
		else{
			throw new Exception("More than one ($numRows) External Module setting exists for prefix '$moduleDirectoryPrefix', project ID '$projectId', and key '$key'!  This should never happen!");
		}
	}

	static function getProjectSetting($moduleDirectoryPrefix, $projectId, $key)
	{
		if (!$projectId) {
			throw new Exception("The Project Id cannot be null!");
		}

		$value = self::getSetting($moduleDirectoryPrefix, $projectId, $key);

		if($value === null){
			$value =  self::getSystemSetting($moduleDirectoryPrefix, $key);
		}

		return $value;
	}

	static function removeProjectSetting($moduleDirectoryPrefix, $projectId, $key){
		self::setProjectSetting($moduleDirectoryPrefix, $projectId, $key, null);
	}

	# directory name is [institution]_[module]_v[X].[Y]
	# prefix is [institution]_[module]
	# gets stored in database as module_id number
	# translates prefix string into a module_id number
	public static function getIdForPrefix($prefix)
	{
		if(!isset(self::$idsByPrefix)){
			$result = self::query("SELECT external_module_id, directory_prefix FROM redcap_external_modules");

			$idsByPrefix = array();
			while($row = db_fetch_assoc($result)){
				$idsByPrefix[$row['directory_prefix']] = $row['external_module_id'];
			}

			self::$idsByPrefix = $idsByPrefix;
		}

		$id = @self::$idsByPrefix[$prefix];
		if($id == null){
			self::query("INSERT INTO redcap_external_modules (directory_prefix) VALUES ('$prefix')");
			$id = db_insert_id();
			self::$idsByPrefix[$prefix] = $id;
		}

		return $id;
	}

	# translates a module_id number into a prefix string
	public static function getPrefixForID($id){
		$id = db_real_escape_string($id);

		$result = self::query("SELECT directory_prefix FROM redcap_external_modules WHERE external_module_id = '$id'");

		$row = db_fetch_assoc($result);
		if($row){
			return $row['directory_prefix'];
		}

		return null;
	}
	
	# gets the currently installed module's version based on the module prefix string
	public static function getModuleVersionByPrefix($prefix){
		$prefix = db_real_escape_string($prefix);
		
		$sql = "SELECT s.value FROM redcap_external_modules m, redcap_external_module_settings s 
				WHERE m.external_module_id = s.external_module_id AND m.directory_prefix = '$prefix'
				AND s.project_id IS NULL AND s.`key` = '" . self::KEY_VERSION . "' LIMIT 1";
		
		$result = self::query($sql);

		return db_result($result, 0);
	}

	# executes a database query and returns the result
	public static function query($sql)
	{
		$result = db_query($sql);

		if($result == FALSE){
			$message = "An error occurred while running an External Module query";

			error_log($message . ": \nDB Error: " . db_error() . "\nSQL: $sql");

			// Do not show sql or error details to minimize risk of exploitation.
			throw new Exception($message . " (see the server error log for more details).");
		}

		return $result;
	}

	# converts an equals clause into SQL
	private static function getSQLEqualClause($columnName, $value)
	{
		$columnName = db_real_escape_string($columnName);
		$value = db_real_escape_string($value);

		if($value == 'NULL'){
			return "$columnName IS NULL";
		}
		else{
			return "$columnName = '$value'";
		}
	}

	# converts an IN array clause into SQL
	public static function getSQLInClause($columnName, $array)
	{
		if(!is_array($array)){
			$array = array($array);
		}

		if(empty($array)){
			return '(false)';
		}

		$columnName = db_real_escape_string($columnName);

		$valueListSql = "";
		$nullSql = "";

		foreach($array as $item){
			$item = db_real_escape_string($item);

			if($item == 'NULL'){
				$nullSql = "$columnName IS NULL";
			}
			else{
				if(!empty($valueListSql)){
					$valueListSql .= ', ';
				}

				$valueListSql .= "'$item'";
			}
		}

		$parts = array();

		if(!empty($valueListSql)){
			$parts[] = "$columnName IN ($valueListSql)";
		}

		if(!empty($nullSql)){
			$parts[] = $nullSql;
		}

		return "(" . implode(" OR ", $parts) . ")";
	}

    /**
     * begins execution of hook
     * helper method
     * should call callHook
     *
     * @param $prefix
     * @param $version
     * @param $arguments
     * @return mixed|void|null  result from hook or null
     * @throws Exception
     */
    private static function startHook($prefix, $version, $arguments) {
		
		// Get the hook's root name
		if (substr(self::$hookBeingExecuted, 0, 5) == 'hook_') {
			$hookName = substr(self::$hookBeingExecuted, 5);
		} else {
			$hookName = substr(self::$hookBeingExecuted, 7);
		}

		$recordId = null;
		if (in_array($hookName, ['data_entry_form_top', 'data_entry_form', 'save_record', 'survey_page_top', 'survey_page', 'survey_complete'])) {
			$recordId = $arguments[1];
		}

		$hookNames = array('redcap_'.$hookName, 'hook_'.$hookName);
		
		if(!self::hasPermission($prefix, $version, 'redcap_'.$hookName) && !self::hasPermission($prefix, $version, 'hook_'.$hookName)){
			// To prevent unnecessary class conflicts (especially with old plugins), we should avoid loading any module classes that don't actually use this hook.
			return;
		}

		$pid = self::getProjectIdFromHookArguments($arguments);
		if(empty($pid) && strpos($hookName, 'every_page') === 0){
			// An every page hook is running on a system (non-project) page.
			$config = self::getConfig($prefix, $version);
			if(@$config['enable-every-page-hooks-on-system-pages'] !== true){
				return;
			}
		}
		
		self::$versionBeingExecuted = $version;

		$instance = self::getModuleInstance($prefix, $version);
		$instance->setRecordId($recordId);

		$result = null; // Default result value

		foreach ($hookNames as $thisHook) {
			if(method_exists($instance, $thisHook)){
				self::setActiveModulePrefix($prefix);

				// Buffer output so we can access for killed query detection using register_shutdown_function().
				ob_start();

				try{
					$result = call_user_func_array(array($instance,$thisHook), $arguments);
				}
				catch(Exception $e){
					$message = "The '" . $prefix . "' module threw the following exception when calling the hook method '".$thisHook."':\n\n" . $e;
					error_log($message);
					ExternalModules::sendAdminEmail("REDCap External Module Hook Exception - $prefix", $message, $prefix);
				}

				echo ob_get_clean();

				self::setActiveModulePrefix(null);
				continue; // No need to check for the alternate hook name.
			}
		}

		$instance->setRecordId(null);

        return $result;
	}

	private static function getProjectIdFromHookArguments($arguments)
	{
		$pid = null;
		if(!empty($arguments)){
			$firstArg = $arguments[0];
			if (is_numeric($firstArg)) {
				// As of REDCap 6.16.8, the above checks allow us to safely assume the first arg is the pid for all hooks.
				$pid = $firstArg;
			}
		}

		return $pid;
	}

	# calls a hooke via startHook
	static function callHook($name, $arguments, $prefix = null)
	{
		if (isset($_GET[self::DISABLE_EXTERNAL_MODULE_HOOKS]) || defined('EXTERNAL_MODULES_KILL_SWITCH')) {
			return;
		}

		# We must initialize this static class here, since this method actually gets called before anything else.
		# We can't initialize sooner than this because we have to wait for REDCap to initialize it's functions and variables we depend on.
		# This method is actually called many times (once per hook), so we should only initialize once.
		if (!self::$initialized) {
			self::initialize();
			self::$initialized = true;
		}

		/**
		 * We call this to make sure the initial caching is performed outside the try catch so that any framework exceptions get thrown
		 * and prevent the page from loading instead of getting caught and emailed.  These days the only time a framework exception
		 * typically gets thrown is when there is a database connectivity issue.  We don't want to flood the admin email in that case,
		 * since they are almost certainly aware of the issue already.
		 */
		self::getSystemwideEnabledVersions();

		# Hold results for hooks that return a value
		$resultsByPrefix = array();

		try {
			if(!defined('PAGE')){
				$page = ltrim($_SERVER['REQUEST_URI'], '/');
				define('PAGE', $page);
			}
	
			$name = str_replace('redcap_', '', $name);
	
			$templatePath = APP_PATH_EXTMOD . "manager/templates/hooks/$name.php";
			if(file_exists($templatePath)){
				self::safeRequire($templatePath, $arguments);
			}
	
			$pid = self::getProjectIdFromHookArguments($arguments);

			self::$hookStartTime = time();
			self::$hookBeingExecuted = "hook_$name";
	
			if (!self::$delayed) {
				self::$delayed = array();
			}
			self::$delayed[self::$hookBeingExecuted] = array();

			self::$delayedLastRun = false;

			if($prefix){
				$versionsByPrefix = [$prefix => self::getEnabledVersion($prefix)];
			}
			else{
				$versionsByPrefix = self::getEnabledModules($pid);
			}

			$startHook = function($prefix, $version) use ($arguments, &$resultsByPrefix){
				$result = self::startHook($prefix, $version, $arguments);

				// The following check assumes hook return values will always be arrays.
				// That's totally fine for now since our only hook that returns a value does return an array.
				// There may come a day when we'd want to support other types as well.
				if (!empty($result) && is_array($result)) {
					// Lets preserve order of execution by order entered into the results array
					$resultsByPrefix[] = array(
						"prefix" => $prefix,
						"result" => $result
					);
				}
			};

			foreach($versionsByPrefix as $prefix=>$version){
				$startHook($prefix, $version);
			}

			$callDelayedHooks = function($lastRun) use ($startHook){
				$prevDelayed = self::$delayed[self::$hookBeingExecuted];
				self::$delayed[self::$hookBeingExecuted] = array();
				self::$delayedLastRun = $lastRun;
				foreach ($prevDelayed as $prefix=>$version) {
					// Modules that call delayModuleExecution() normally just "return;" afterward, effectively returning null.
					// However, they could potentially return a value after delaying, which would result in multiple entries in $resultsByPrefix for the same module.
					// This could cause filterHookResults() to trigger unnecessary warning emails, but likely won't be an issue in practice.
					$startHook($prefix, $version);
				}
			};
	
			# runs delayed modules
			# terminates if queue is 0 or if it is the same as in the previous iteration
			# (i.e., no modules completing)
			$prevNumDelayed = count($versionsByPrefix) + 1;
			while (($prevNumDelayed > count(self::$delayed[self::$hookBeingExecuted])) && (count(self::$delayed[self::$hookBeingExecuted]) > 0)) {
			 	$prevNumDelayed = count(self::$delayed[self::$hookBeingExecuted]);
				$callDelayedHooks(false);
			}

			$callDelayedHooks(true);

			self::$hookBeingExecuted = "";
			self::$versionBeingExecuted = "";
		} catch(Exception $e) {
			// We ignore this MySQL error because it seems to trigger during normal database maintenance.
			// If the database was actually down, we'd find out pretty darn quickly anyway.
			if(strpos($e->getMessage(), 'MySQL server has gone away') == false){
				$message = "REDCap External Modules threw the following exception:\n\n" . $e;
				error_log($message);
				ExternalModules::sendAdminEmail("REDCap External Module Exception", $message, $prefix);
			}
		}

        // As this is currently written, any function that returns a value cannot also exit.
        // TODO: Should we move this to a shutdown function for this hook so we can return a value?
		if(self::$exitAfterHook){
			exit();
		}

		// We must resolve cases where there are multiple return values.
        // We can assume we only support a single return value (easier) or we can expand our definition of hooks
        // to handle multiple return values as an array of values.  For now, let's shoot simple and just take
        // the latest one and throw a warning to the admin
		return self::filterHookResults($resultsByPrefix, $name);
	}

    /**
     * Handle cases where there are multiple results for a hook
     * @param $results     | An array where each element is a result array from an EM with keys 'result' and 'prefix'
     * @param $hookName    | The hook where the results were generated.
     * @return array|null
     */
	private static function filterHookResults($results, $hookName) {
        if (empty($results)) return null;

        // Take the last result
        end($results);
        $last_result = current($results);

        // Throw a warning if there is more than one result
        if (count($results) > 1) {
            $message =  "<p>" . count($results) . " return values were generated from hook $hookName " .
                "by the following external modules:</p>";
            foreach ($results as $result) {
                $message .= "<p><b><u>{$result['prefix']}</u></b> => <code>" . htmlentities(json_encode($result['result'])) . "</code></div></p>";
            }
            $message .= "<p>Only the last result from <b><u>" . $last_result['prefix'] . "</u></b> will be used " .
                "by REDCap.  Consider disabling or refactoring the other external modules so this does not occur.</p>";

            ExternalModules::sendAdminEmail("REDCap External Module Results Warning", $message);
        }

        return $last_result['result'];
    }


	public static function exitAfterHook(){
		self::$exitAfterHook = true;
	}

	# places module in delaying queue to be executed after all others are executed
	public static function delayModuleExecution() {
		self::$delayed[self::$hookBeingExecuted][self::$activeModulePrefix] = self::$versionBeingExecuted;
		return !self::$delayedLastRun;
	}

	# This function exists solely to provide a scope where we don't care if local variables get overwritten by code in the required file.
	# Use the $arguments variable to pass data to the required file.
	static function safeRequire($path, $arguments = array()){
		if (file_exists(APP_PATH_EXTMOD . $path)) {
			require APP_PATH_EXTMOD . $path;
		} else {
			require $path;
		}
	}

	# This function exists solely to provide a scope where we don't care if local variables get overwritten by code in the required file.
	# Use the $arguments variable to pass data to the required file.
	static function safeRequireOnce($path, $arguments = array()){
		if (file_exists(APP_PATH_EXTMOD . $path)) {
			$path = APP_PATH_EXTMOD . $path;
		}

		/**
		 * The current directory could be a few different things at this point.
		 * We temporarily set it to the module directory to avoid relative paths from incorrectly referencing the wrong directory.
		 * This fixed a real world case where a require call for 'vendor/autoload.php' in the module
		 * was loading the autoload.php file from somewhere other than the module.
		 */
		$originalDir = getcwd();
		chdir(dirname($path));
		require_once $path;
		chdir($originalDir);
	}

	# Ensure compatibility with PHP version and REDCap version during module installation using config values
	private static function isCompatibleWithREDCapPHP($moduleDirectoryPrefix, $version)
	{
		$config = self::getConfig($moduleDirectoryPrefix, $version);
		if (!isset($config['compatibility'])) return;
		$Exceptions = array();
		$compat = $config['compatibility'];
		if (isset($compat['php-version-max']) && !empty($compat['php-version-max']) && !version_compare(PHP_VERSION, $compat['php-version-max'], '<=') && self::isCompatibleFormatCorrect($compat['php-version-max'])) {
			$Exceptions[] = "This module's maximum compatible PHP version is {$compat['php-version-max']}, but you are currently running PHP " . PHP_VERSION . ".";
		}
		elseif (isset($compat['php-version-min']) && !empty($compat['php-version-min']) && !version_compare(PHP_VERSION, $compat['php-version-min'], '>=') && self::isCompatibleFormatCorrect($compat['php-version-min'])) {
			$Exceptions[] = "This module's minimum required PHP version is {$compat['php-version-min']}, but you are currently running PHP " . PHP_VERSION . ".";
		}
		if (isset($compat['redcap-version-max']) && !empty($compat['redcap-version-max']) && !version_compare(REDCAP_VERSION, $compat['redcap-version-max'], '<=') && self::isCompatibleFormatCorrect($compat['redcap-version-max'])) {
			$Exceptions[] = "This module's maximum compatible REDCap version is {$compat['redcap-version-max']}, but you are currently running REDCap " . REDCAP_VERSION . ".";
		}
		elseif (isset($compat['redcap-version-min']) && !empty($compat['redcap-version-min']) && !version_compare(REDCAP_VERSION, $compat['redcap-version-min'], '>=') && self::isCompatibleFormatCorrect($compat['redcap-version-min'])) {
			$Exceptions[] = "This module's minimum required REDCap version is {$compat['redcap-version-min']}, but you are currently running REDCap " . REDCAP_VERSION . ".";
		}

		if (!empty($Exceptions)) {
			throw new Exception("COMPATIBILITY ERROR: This version of the module \"".$config['name']."\"
								is not compatible with your current version of PHP and/or REDCap, so cannot be installed on your 
								REDCap server at this time. Details:<ul><li>" . implode("</li><li>", $Exceptions) . "</li></ul>");
		}
	}

	private static function isCompatibleFormatCorrect($compatibility){
        $version = explode('.',$compatibility);
        if(count($version) == 3){
            foreach ($version as $number){
                if($number == ""){
                    return false;
                }
            }
            return true;
        }
        return false;
    }

	# this is where a module has its code loaded
	public static function getModuleInstance($prefix, $version = null)
	{
		$previousActiveModulePrefix = self::getActiveModulePrefix();
		self::setActiveModulePrefix($prefix);

		if($version == null){
			$version = self::getEnabledVersion($prefix);

			if($version == null){
				throw new Exception("Cannot create module instance, since the module with the following prefix is not enabled: $prefix");
			}
		}

		$modulePath = self::getModuleDirectoryPath($prefix, $version);
		if (!$modulePath) return false;
		
		$instance = @self::$instanceCache[$prefix][$version];
		if(!isset($instance)){
			$config = self::getConfig($prefix, $version);

			$namespace = @$config['namespace'];
			if(empty($namespace)) {
				throw new Exception("The '$prefix' module MUST specify a 'namespace' in it's config.json file.");
			}

			$parts = explode('\\', $namespace);
			$className = end($parts);

			$classNameWithNamespace = "\\$namespace\\$className";

			$classFilePath = "$modulePath/$className.php";

			if(!file_exists($classFilePath)){
				throw new Exception("Could not find the module class file '$classFilePath' for the module with prefix '$prefix'.");
			}

			self::safeRequireOnce($classFilePath);

			if (!class_exists($classNameWithNamespace)) {
				throw new Exception("The file '$className.php' file must define the '$classNameWithNamespace' class for the '$prefix' module.");
			}

			$instance = new $classNameWithNamespace();
			self::$instanceCache[$prefix][$version] = $instance;
		}

		// Restore the active module prefix to what it was before.
		// Calling getModuleInstance() while a module is active (inside a hook) should probably be disallowed,
		// even if it's for the same prefix that is currently active.
		// However, this seems to happen on occasion with the email alerts module,
		// so we restore what was there before just to be safe.
		self::setActiveModulePrefix($previousActiveModulePrefix);

		return $instance;
	}

	# parses the prefix and turns it into a class name
	# convention is [institution]_[module]_v[X].[Y]
	# module is converted into camelCase, has its first letter capitalized, and is appended with "ExternalModule"
	# note well that if [module] contains an underscore (_), only the first chain link will be dealt with
	# E.g., vanderbilt_example_v1.0 yields a class name of "ExampleExternalModule"
	# vanderbilt_pdf_modify_v1.2 yields a class name of "PdfExternalModule"
	private static function getMainClassName($prefix)
	{
		$parts = explode('_', $prefix);
		$parts = explode('-', $parts[1]);

		$className = '';
		foreach($parts as $part){
			$className .= ucfirst($part);
		}

		$className .= 'ExternalModule';

		return $className;
	}

	# Accepts a project id as the first parameter.
	# If the project id is null, all system-wide enabled module instances are returned.
	# Otherwise, only instances enabled for the current project id are returned.
	static function getEnabledModules($pid = null)
	{
		if($pid == null){
			return self::getSystemwideEnabledVersions();
		}
		else{
			return self::getEnabledModuleVersionsForProject($pid);
		}
	}

	static function getSystemwideEnabledVersions()
	{
		if(!isset(self::$systemwideEnabledVersions)){
			self::cacheAllEnableData();
		}

		return self::$systemwideEnabledVersions;
	}

	private static function getProjectEnabledDefaults()
	{
		if(!isset(self::$projectEnabledDefaults)){
			self::cacheAllEnableData();
		}

		return self::$projectEnabledDefaults;
	}

	private static function getProjectEnabledOverrides()
	{
		if(!isset(self::$projectEnabledOverrides)){
			self::cacheAllEnableData();
		}

		return self::$projectEnabledOverrides;
	}

	# get all versions enabled for a given project
	private static function getEnabledModuleVersionsForProject($pid)
	{
		$projectEnabledOverrides = self::getProjectEnabledOverrides();

		$enabledPrefixes = self::getProjectEnabledDefaults();
		$overrides = @$projectEnabledOverrides[$pid];
		if(isset($overrides)){
			foreach($overrides as $prefix => $value){
				if($value){
					$enabledPrefixes[$prefix] = true;
				}
				else{
					unset($enabledPrefixes[$prefix]);
				}
			}
		}

		$systemwideEnabledVersions = self::getSystemwideEnabledVersions();

		$enabledVersions = array();
		foreach(array_keys($enabledPrefixes) as $prefix){
			$version = @$systemwideEnabledVersions[$prefix];

			// Check the version to make sure the module is not systemwide disabled.
			if(isset($version)){
				$enabledVersions[$prefix] = $version;
			}
		}

		return $enabledVersions;
	}

	private static function shouldExcludeModule($prefix, $version = null)
	{
		if ($version && strpos($_SERVER['REQUEST_URI'], '/manager/ajax/enable-module.php') !== false && $prefix == $_POST['prefix'] && $_POST['version'] != $version) {
            // We are in the process of switching an already enabled module from one version to another.
            // We need to exclude the old version of the module to ensure that the hook for the new version is the one that is executed.
			return true;
		}

		// The fake unit testing modules are not currently ever enabled in the DB,
		// but we may as well leave this check in place in case that changes in the future.
		$isTestPrefix = strpos($prefix, self::TEST_MODULE_PREFIX) === 0;
		if($isTestPrefix && !self::isTesting()){
			// This php process is not running unit tests.
			// Ignore the test prefix so it doesn't interfere with this process.
			return true;
		}

		return false;
	}

	private static function isTesting()
	{
		return PHP_SAPI == 'cli' && strpos($_SERVER['argv'][0], 'phpunit') !== FALSE;
	}

	# calling this method stores a local cache of all relevant data from the database
	private static function cacheAllEnableData()
	{
		$systemwideEnabledVersions = array();
		$projectEnabledOverrides = array();
		$projectEnabledDefaults = array();

		$result = self::getSettings(null, null, array(self::KEY_VERSION, self::KEY_ENABLED));

		// Split results into version and enabled arrays: this seems wasteful, but using one
		// query above, we can then validate which EMs/versions are valid before we build
		// out which are enabled and how they are enabled
		$result_versions = array();
		$result_enabled = array();
		while($row = self::validateSettingsRow(db_fetch_assoc($result))) {
			$key = $row['key'];
			if ($key == self::KEY_VERSION) {
				$result_versions[] = $row;
			} else if($key == self::KEY_ENABLED) {
				$result_enabled[] = $row;
			} else {
				throw new Exception("Unexpected key: $key");
			}
		}

		// For each version, verify if the module folder exists and is valid
		foreach ($result_versions as $row) {
			$prefix = $row['directory_prefix'];
			$value = $row['value'];
			if (self::shouldExcludeModule($prefix, $value)) {
				continue;
			} else {
				$systemwideEnabledVersions[$prefix] = $value;
			}
		}

		// Set enabled arrays for EMs
		foreach ($result_enabled as $row) {
			$pid = $row['project_id'];
			$prefix = $row['directory_prefix'];
			$value = $row['value'];

			// If EM was not valid above, then skip
			if (!isset($systemwideEnabledVersions[$prefix])) {
				continue;
			}

			// Set enabled global or project
			if (isset($pid)) {
				$projectEnabledOverrides[$pid][$prefix] = $value;
			} else if ($value) {
				$projectEnabledDefaults[$prefix] = true;
			}
		}

		// Overwrite any previously cached results
		self::$systemwideEnabledVersions = $systemwideEnabledVersions;
		self::$projectEnabledDefaults = $projectEnabledDefaults;
		self::$projectEnabledOverrides = $projectEnabledOverrides;
	}

	# echo's HTML for adding an approriate resource; also prepends appropriate directory structure
	static function addResource($path)
	{
		$extension = pathinfo($path, PATHINFO_EXTENSION);

		if(substr($path,0,8) == "https://" || substr($path,0,7) == "http://") {
			$url = $path;
		}
		else {
			$path = "manager/$path";
			$fullLocalPath = __DIR__ . "/../$path";

			// Add the filemtime to the url for cache busting.
			clearstatcache(true, $fullLocalPath);
			$url = ExternalModules::$BASE_URL . $path . '?' . filemtime($fullLocalPath);
		}

		if(in_array($url, self::$INCLUDED_RESOURCES)) return;

		if ($extension == 'css') {
			echo "<link rel='stylesheet' type='text/css' href='" . $url . "'>";
		}
		else if ($extension == 'js') {
			echo "<script type='text/javascript' src='" . $url . "'></script>";
		}
		else {
			throw new Exception('Unsupported resource added: ' . $path);
		}

		self::$INCLUDED_RESOURCES[] = $url;
	}

	# returns an array of links requested by the config.json
	static function getLinks($prefix = null, $version = null)
	{
		$pid = self::getPID();

		if(isset($pid)){
			$type = 'project';
		}
		else{
			$type = 'control-center';
		}

		$links = array();

		if ($prefix === null || $version === null) {
			$versionsByPrefix = self::getEnabledModules($pid);
		} else {
			$versionsByPrefix = [$prefix => $version];
		}

		foreach($versionsByPrefix as $prefix=>$version){
			$config = ExternalModules::getConfig($prefix, $version);

			foreach($config['links'][$type] as $link){
				$name = $link['name'];
				$link['url'] = self::getUrl($prefix, $link['url']);
				$link['prefix'] = $prefix;
				$links[$name] = $link;
			}
		}

		ksort($links);

		return $links;
	}

	# returns the pid from the $_GET array
	private static function getPID()
	{
		return @$_GET['pid'];
	}

	# for an internal request for a project URL, transforms the request into a URL
	static function getUrl($prefix, $page, $useApiEndpoint=false)
	{
		$getParams = array();
		if (preg_match("/\.php\?.+$/", $page, $matches)) {
			$getChain = preg_replace("/\.php\?/", "", $matches[0]);
			$page = preg_replace("/\?.+$/", "", $page);
			$getPairs = explode("&", $getChain);
			foreach ($getPairs as $pair) {
				$a = explode("=", $pair);
				# implode unlikely circumstance of multiple ='s
				$b = array();
				for ($i = 1; $i < count($a); $i++) {
					$b[] = $a[$i];
				}
				$value = implode("=", $b);
				$getParams[$a[0]] = $value;
			}
			if (isset($getParams['prefix'])) {
				unset($getParams['prefix']);
			}
			if (isset($getParams['page'])) {
				unset($getParams['page']);
			}
		}
		$page = preg_replace('/\.php$/', '', $page); // remove .php extension if it exists
		$get = "";
		foreach ($getParams as $key => $value) {
			$get .= "&$key=$value";
		}

		$base = $useApiEndpoint ? self::getModuleAPIUrl() : self::$BASE_URL."?";
		return $base . "prefix=$prefix&page=" . urlencode($page) . $get;
	}

	static function getModuleAPIUrl()
	{
		return APP_PATH_WEBROOT_FULL."api/?type=module&";
	}
	
	# Returns boolean regarding if the module is an example module in the example_modules directory.
	# $version can be provided as a string or as an array of version strings, in which it will return TRUE 
	# if at least ONE of them is in the example_modules directory.
	static function isExampleModule($prefix, $version=array())
	{
		if (!is_array($version) && $version == '') return false;
		if (!is_array($version)) $version = array($version);
		foreach ($version as $this_version) {
			$moduleDirName = APP_PATH_EXTMOD . 'example_modules' . DS . $prefix . "_" . $this_version;
			if (file_exists($moduleDirName) && is_dir($moduleDirName)) return true;
		}
		return false;
	}

	# returns the configs for disabled modules
	static function getDisabledModuleConfigs($enabledModules)
	{
		$dirs = self::getModulesInModuleDirectories();

		$disabledModuleVersions = array();
		foreach ($dirs as $dir) {
			if ($dir[0] == '.') {
			    // This line was added back when we had to exclude the '.' and '..' results from scandir().
                // It is only being left in place in case any existing REDCap installations have
                // come to expect "hidden" directories to be ignored.
				continue;
			}

			list($prefix, $version) = self::getParseModuleDirectoryPrefixAndVersion($dir);

			if($prefix && @$enabledModules[$prefix] != $version) {
				$versions = @$disabledModuleVersions[$prefix];
				if(!isset($versions)){
					$versions = array();
				}

				// Use array_merge_recursive() to show newest versions first.
				$disabledModuleVersions[$prefix] = array_merge_recursive(
					array($version => self::getConfig($prefix, $version)),
					$versions
				);
			}
		}
		
		// Make sure the version numbers for each module get sorted naturally
		foreach ($disabledModuleVersions as &$versions) {
			natcaseksort($versions, true);
		}

		return $disabledModuleVersions;
	}

	# Parses [institution]_[module]_v[X].[Y] into [ [institution]_[module], v[X].[Y] ]
	# e.g., vanderbilt_example_v1.0 becomes [ "vanderbilt_example", "v1.0" ]
	static function getParseModuleDirectoryPrefixAndVersion($directoryName){
		$directoryName = basename($directoryName);

		$parts = explode('_', $directoryName);

		$version = array_pop($parts);
		$versionParts = explode('v', $version);
		$versionNumberParts = explode('.', @$versionParts[1]);
		if(count($versionParts) != 2 || $versionParts[0] != '' || count($versionNumberParts) > 3){
			// The version is invalid.  Return null to prevent this folder from being listed.
			$version = null;
		}

		foreach($versionNumberParts as $part){
			if(!is_numeric($part)){
				$version = null;
			}
		}

		$prefix = implode('_', $parts);

		return array($prefix, $version);
	}

	# returns the config.json for a given module
	static function getConfig($prefix, $version = null, $pid = null)
	{
		if(empty($prefix)){
			throw new Exception("You must specify a prefix!");
		}

		if($version == null){
			$version = self::getEnabledVersion($prefix);
		}

		$configFilePath = self::getModuleDirectoryPath($prefix, $version)."/config.json";
		$config = @self::$configs[$prefix][$version];
		if($config === null){
			$fileTesting = file_get_contents($configFilePath);
			$config = json_decode($fileTesting, true);

			if($fileTesting == "") {
				return [];
			}

			if($config == null){
				// Disable the module to prevent repeated errors, especially those that prevent the External Modules menu items from appearing.
				self::disable($prefix, true);

				throw new Exception("An error occurred while parsing a configuration file!  The following file is likely not valid JSON: $configFilePath");
			}

			foreach(['permissions', 'system-settings', 'project-settings', 'no-auth-pages'] as $key){
				if(!isset($config[$key])){
					$config[$key] = array();
				}
			}

			self::$configs[$prefix][$version] = $config;
		}

		## Pull form and field list for choice list of project-settings field-list and form-list settings
		if(!empty($pid)) {
			foreach($config['project-settings'] as $configKey => $configRow) {
				$config['project-settings'][$configKey] = self::getAdditionalFieldChoices($configRow,$pid);
			}
		}

		if($pid === null) {
			$config = self::addReservedSettings($config);
		}

		return $config;
	}

	# specialty field lists include: user-role-list, user-list, dag-list, field-list, and form-list
	public static function getAdditionalFieldChoices($configRow,$pid) {
		if ($configRow['type'] == 'user-role-list') {
				$choices = [];

				$sql = "SELECT role_id,role_name
						FROM redcap_user_roles
						WHERE project_id = '" . db_real_escape_string($pid) . "'
						ORDER BY role_id";
				$result = self::query($sql);

				while ($row = db_fetch_assoc($result)) {
						$choices[] = ['value' => $row['role_id'], 'name' => strip_tags(nl2br($row['role_name']))];
				}

				$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'user-list') {
				$choices = [];

				$sql = "SELECT ur.username,ui.user_firstname,ui.user_lastname
						FROM redcap_user_rights ur, redcap_user_information ui
						WHERE ur.project_id = '" . db_real_escape_string($pid) . "'
								AND ui.username = ur.username
						ORDER BY ui.ui_id";
				$result = self::query($sql);

				while ($row = db_fetch_assoc($result)) {
						$choices[] = ['value' => strtolower($row['username']), 'name' => $row['user_firstname'] . ' ' . $row['user_lastname']];
				}

				$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'dag-list') {
				$choices = [];

				$sql = "SELECT group_id,group_name
						FROM redcap_data_access_groups
						WHERE project_id = '" . db_real_escape_string($pid) . "'
						ORDER BY group_id";
				$result = self::query($sql);

				while ($row = db_fetch_assoc($result)) {
						$choices[] = ['value' => $row['group_id'], 'name' => strip_tags(nl2br($row['group_name']))];
				}

				$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'field-list') {
			$choices = [];

			$sql = "SELECT field_name,element_label
					FROM redcap_metadata
					WHERE project_id = '" . db_real_escape_string($pid) . "'
					ORDER BY field_order";
			$result = self::query($sql);

			while ($row = db_fetch_assoc($result)) {
				$row['element_label'] = strip_tags(nl2br($row['element_label']));
				if (strlen($row['element_label']) > 30) {
					$row['element_label'] = substr($row['element_label'], 0, 20) . "... " . substr($row['element_label'], -8);
				}
				$choices[] = ['value' => $row['field_name'], 'name' => $row['field_name'] . " - " . htmlspecialchars($row['element_label'])];
			}

			$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'form-list') {
			$choices = [];

			$sql = "SELECT DISTINCT form_name
					FROM redcap_metadata
					WHERE project_id = '" . db_real_escape_string($pid) . "'
					ORDER BY field_order";
			$result = self::query($sql);

			while ($row = db_fetch_assoc($result)) {
				$choices[] = ['value' => $row['form_name'], 'name' => strip_tags(nl2br($row['form_name']))];
			}

			$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'arm-list') {
			$choices = [];

			$sql = "SELECT a.arm_id, a.arm_name
					FROM redcap_events_arms a
					WHERE a.project_id = '" . db_real_escape_string($pid) . "'
					ORDER BY a.arm_id";
			$result = self::query($sql);

			while ($row = db_fetch_assoc($result)) {
				$choices[] = ['value' => $row['arm_id'], 'name' => $row['arm_name']];
			}

			$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'event-list') {
			$choices = [];

			$sql = "SELECT e.event_id, e.descrip, a.arm_id, a.arm_name
					FROM redcap_events_metadata e, redcap_events_arms a
					WHERE a.project_id = '" . db_real_escape_string($pid) . "'
						AND e.arm_id = a.arm_id
					ORDER BY e.event_id";
			$result = self::query($sql);

			while ($row = db_fetch_assoc($result)) {
				$choices[] = ['value' => $row['event_id'], 'name' => "Arm: ".strip_tags(nl2br($row['arm_name']))." - Event: ".strip_tags(nl2br($row['descrip']))];
			}

			$configRow['choices'] = $choices;
		}
		else if($configRow['type'] == 'sub_settings') {
			foreach ($configRow['sub_settings'] as $subConfigKey => $subConfigRow) {
				$configRow['sub_settings'][$subConfigKey] = self::getAdditionalFieldChoices($subConfigRow,$pid);
				if($configRow['super-users-only']) {
					$configRow['sub_settings'][$subConfigKey]['super-users-only'] = $configRow['super-users-only'];
				}
				if(!isset($configRow['source']) && $configRow['sub_settings'][$subConfigKey]['source']) {
					$configRow['source'] = "";
				}
				$configRow["source"] .= ($configRow["source"] == "" ? "" : ",").$configRow['sub_settings'][$subConfigKey]['source'];
			}
		}
		else if($configRow['type'] == 'project-id') {
			$escaped_pid = strtolower(db_real_escape_string($pid));
			$sql = "SELECT p.project_id, p.app_title
					FROM redcap_projects p, redcap_user_rights u
					WHERE p.project_id = u.project_id
						AND u.username = '".db_real_escape_string(USERID)."'
						AND (LOWER(p.app_title) LIKE '%$escaped_pid%' OR p.project_id = '$escaped_pid')";

			$result = db_query($sql);

			$matchingProjects = [
				[
					"id" => "",
					"text" => "--- None ---"
				]
			];

			while($row = db_fetch_assoc($result)) {
				$projectName = utf8_encode($row["app_title"]);

				// Required to display things like single quotes correctly
				$projectName = htmlspecialchars_decode($projectName, ENT_QUOTES);

				$matchingProjects[] = [
					"id" => $row["project_id"],
					"text" => "(" . $row["project_id"] . ") " . $projectName,
				];
			}
			$configRow['choices'] = $matchingProjects;
		}

		return $configRow;
	}

	# gets the version of a module
	public static function getEnabledVersion($prefix)
	{
		$versionsByPrefix = self::getSystemwideEnabledVersions();
		return @$versionsByPrefix[$prefix];
	}

	# adds the RESERVED_SETTINGS (above) to the config
	private static function addReservedSettings($config)
	{
		$systemSettings = $config['system-settings'];
		$projectSettings = $config['project-settings'];

		$existingSettingKeys = array();
		foreach($systemSettings as $details){
			$existingSettingKeys[$details['key']] = true;
		}

		foreach($projectSettings as $details){
			$existingSettingKeys[$details['key']] = true;
		}

		$visibleReservedSettings = array();
		foreach(self::$RESERVED_SETTINGS as $details){
			$key = $details['key'];
			if(isset($existingSettingKeys[$key])){
				throw new Exception("The '$key' setting key is reserved for internal use.  Please use a different setting key in your module.");
			}
			
			// If project has no project-level configuration, then do not add the reserved setting 
			// to require special user right in project to modify project config
			if ($key == self::KEY_CONFIG_USER_PERMISSION && empty($projectSettings)) {
				continue;
			}

			if(@$details['hidden'] != true){
				$visibleReservedSettings[] = $details;
			}
		}

		// Merge arrays so that reserved settings always end up at the top of the list.
		$config['system-settings'] = array_merge($visibleReservedSettings, $systemSettings);

		return $config;
	}

	# formats directory name from $prefix and $version
	static function getModuleDirectoryPath($prefix, $version = null){
		if(self::isTesting() && $prefix == TEST_MODULE_PREFIX){
			return true;
		}
		
		// If the modules path is not set, then there's nothing we can do here.
		// This should never happen, but Rob encountered a case where it did, likely due to initialize() being called too late.
		// The initialize() was moved up in a later commit, but we wanted to leave this line here just in case.
		if (empty(self::$MODULES_PATH)) return false;

		if(empty($version)){
			$version = self::getModuleVersionByPrefix($prefix);
		}

		// Look in the main modules dir and the example modules dir
		$directoryToFind = $prefix . '_' . $version;
		foreach(self::$MODULES_PATH as $pathDir) {
			$modulePath = $pathDir . $directoryToFind;
			if (is_dir($modulePath)) {
				// If the module was downloaded from the central repo and then deleted via UI and still was found in the server,
				// that means that load balancing is happening, so we need to delete the directory on this node too.
				if (self::wasModuleDeleted($modulePath) && !self::wasModuleDownloadedFromRepo($directoryToFind)) {
					// Delete the directory on this node
					self::deleteModuleDirectory($directoryToFind, true);
					// Return false since this module should not even be on the server
					return false;
				}
				// Return path
				return $modulePath;
			}
		}
		// If module could not be found, it may be due to load balancing, so check if it was downloaded
		// from the central ext mod repository, and redownload it
		if (!defined("REPO_EXT_MOD_DOWNLOAD") && self::wasModuleDownloadedFromRepo($directoryToFind)) {
			$moduleId = self::getRepoModuleId($directoryToFind);
			if ($moduleId !== false && isDirWritable(dirname(APP_PATH_DOCROOT).DS.'modules'.DS)) { // Make sure "modules" directory is writable before attempting to auto-download this module
				// Download the module from the repo
				$status = self::downloadModule($moduleId, true);
				if (!is_numeric($status)) {
					// Return the modules directory path
					return dirname(APP_PATH_DOCROOT).DS.'modules'.DS.$directoryToFind;
				}
			}
		}		
		// Still could not find it, so return false
		return false;
	}

	static function getModuleDirectoryUrl($prefix, $version)
	{
		$filePath = ExternalModules::getModuleDirectoryPath($prefix, $version);
		
		$url = APP_PATH_WEBROOT_FULL . str_replace("\\", "/", substr($filePath, strlen(dirname(APP_PATH_DOCROOT)."/"))) . "/";

		return $url;
	}

	static function hasProjectSettingSavePermission($moduleDirectoryPrefix, $key = null)
	{
		if(self::hasSystemSettingsSavePermission($moduleDirectoryPrefix)){
			return true;
		}

		$settingDetails = self::getSettingDetails($moduleDirectoryPrefix, $key);
		if(@$settingDetails['super-users-only']){
			return false;
		}
		
		$moduleRequiresConfigUserRights = self::moduleRequiresConfigPermission($moduleDirectoryPrefix);
		$userCanConfigureModule = ((!$moduleRequiresConfigUserRights && self::hasDesignRights()) 
									|| ($moduleRequiresConfigUserRights && self::hasModuleConfigurationUserRights($moduleDirectoryPrefix)));

		if($userCanConfigureModule){
			if(!self::isSystemSetting($moduleDirectoryPrefix, $key)){
				return true;
			}

			$level = self::getSystemSetting($moduleDirectoryPrefix, $key . self::OVERRIDE_PERMISSION_LEVEL_SUFFIX);
			return $level == self::OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS;
		}

		return false;
	}

	public static function hasPermission($prefix, $version, $permissionName)
	{
		return in_array($permissionName, self::getConfig($prefix, $version)['permissions']);
	}

	static function isSystemSetting($moduleDirectoryPrefix, $key)
	{
		$config = self::getConfig($moduleDirectoryPrefix);

		foreach($config['system-settings'] as $details){
			if($details['key'] == $key){
				return true;
			}
		}

		return false;
	}

	static function getSettingDetails($prefix, $key)
	{
		$config = self::getConfig($prefix);

		$settingGroups = [
			$config['system-settings'],
			$config['project-settings'],

			// The following was added so that the recreateAllEDocs() function would work on Email Alerts module settings.
			// Adding module specific code in the framework is not a good idea, but the fixing the duplicate edocs issue
			// for the Email Alerts module seemed like the right think to do since it's so popular.
			$config['email-dashboard-settings']
		];

		$handleSettingGroup = function($group) use ($prefix, $key, &$handleSettingGroup){
			foreach($group as $details){
				if($details['key'] == $key){
					return $details;
				}
				else if($details['type'] === 'sub_settings'){
					$returnValue = $handleSettingGroup($details['sub_settings']);
					if($returnValue){
						return $returnValue;
					}
				}
			}

			return null;
		};

		foreach($settingGroups as $group){
			$returnValue = $handleSettingGroup($group);
			if($returnValue){
				return $returnValue;
			}
		}

		return null;
	}

	# returns boolean if design rights are given by REDCap for current user
	static function hasDesignRights()
	{
		if(SUPER_USER){
			return true;
		}

		if(!isset($_GET['pid'])){
			// REDCap::getUserRights() will crash if no pid is set, so just return false.
			return false;
		}

		$rights = \REDCap::getUserRights();
		return $rights[USERID]['design'] == 1;
	}

	# returns boolean if current user explicitly has project-level user rights to configure a module 
	# (assuming it requires explicit privileges based on system-level configuration of module)
	static function hasModuleConfigurationUserRights($prefix=null)
	{
		if(SUPER_USER){
			return true;
		}

		if(!isset($_GET['pid'])){
			// REDCap::getUserRights() will crash if no pid is set, so just return false.
			return false;
		}

		$rights = \REDCap::getUserRights();
		return in_array($prefix, $rights[USERID]['external_module_config']);
	}

	static function hasSystemSettingsSavePermission()
	{
		return self::isTesting() || SUPER_USER == 1 || self::$disablingModuleDueToException;
	}

	# there is no getInstance because settings returns an array of repeated elements
	# getInstance would merely consist of dereferencing the array; Ockham's razor

	# sets the instance to a JSON string into the database
	# $instance is 0-based index for array
	# if the old value is a number/string, etc., this function will transform it into a JSON
	# fills is with null values for non-expressed positions in the JSON before instance
	# JSON is a 0-based, one-dimensional array. It can be filled with associative arrays in
	# the form of other JSON-encoded strings.
	# This method is currently used in the Selective Email module (so don't remove it).
	static function setInstance($prefix, $projectId, $key, $instance, $value) {
		$instance = (int) $instance;
		$oldValue = self::getSetting($prefix, $projectId, $key);
		$json = array();
		if (gettype($oldValue) != "array") {
			if ($oldValue !== null) {
				$json[] = $oldValue;
			}
		}

		# fill in with prior values
		for ($i=count($json); $i < $instance; $i++) {
			if ((gettype($oldValue) == "array") && (count($oldValue) > $i)) {
				$json[$i] = $oldValue[$i];
			} else {
				# pad with null for prior values when $n is ahead; should never be used
				$json[$i] = null;
			}
		}

		# do not set null values for current instance; always set to empty string
		if ($value !== null) {
			$json[$instance] = $value;
		} else {
			$json[$instance] = "";
		}

		# fill in remainder if extant
		if (gettype($oldValue) == "array") {
			for ($i = $instance + 1; $i < count($oldValue); $i++) {
				$json[$i] = $oldValue[$i];
			}
		}

		#single-element JSONs are simply data values
		if (count($json) == 1) {
			self::setSetting($prefix, $projectId, $key, $json[0]);
		} else {
			self::setSetting($prefix, $projectId, $key, $json);
		}
	}

	static function getManagerJSDirectory() {
		return "js/";
		# just in case absolute path is needed, I have documented it here
		// return APP_PATH_WEBROOT_PARENT."/external_modules/manager/js/";
	}

	static function getManagerCSSDirectory() {
		return "css/";
		# just in case absolute path is needed, I have documented it here
		// return APP_PATH_WEBROOT_PARENT."/external_modules/manager/css/";
	}

	/**
	 * This is used by the EmailTriggerModule
	 */
	public static function getGlobalJSURL()
	{
		return self::$BASE_URL . '/manager/js/globals.js';
	}

	public static function deleteEDoc($edocId){
		// Prevent SQL injection
		$edocId = intval($edocId);

		if(!$edocId){
			throw new Exception("The EDoc ID specified is not valid: $edocId");
		}

		# flag for deletion in the edocs database
		$sql = "UPDATE `redcap_edocs_metadata`
				SET `delete_date` = NOW()
				WHERE doc_id = $edocId";

		self::query($sql);
	}
	
	// Display alert message in Control Center if any modules have updates in the REDCap Repo
	public static function renderREDCapRepoUpdatesAlert()
	{
		if(!ExternalModules::haveUnsafeEDocReferencesBeenChecked()) {
			?>
			<div class='yellow repo-updates'>
				<b>WARNING:</b> Unsafe references exist to files uploaded for modules. See <a href="<?=self::$BASE_URL?>/manager/show-duplicated-edocs.php">this page</a> for more details.
			</div>
			<?php
		}

		global $lang, $external_modules_updates_available;
		$moduleUpdates = json_decode($external_modules_updates_available, true);
		if (!is_array($moduleUpdates) || empty($moduleUpdates)) return false;
		$links = "";
		$moduleData = array();
		$countModuleUpdates = count($moduleUpdates);
		foreach ($moduleUpdates as $id=>$module) {
			$moduleData[] = $thisModuleData = "{$id},{$module['name']},v{$module['version']}";
			$links .= "<div id='repo-updates-modid-$id'><button class='update-single-module btn btn-success btn-xs' data-module-info=\"$thisModuleData\">"
				   .  "<span class='fas fa-download'></span> {$lang['global_125']}</button> {$module['title']} v{$module['version']}</div>";
		}
		$moduleData = implode(";", $moduleData);
		// Output JS resource and div
		?><script type="text/javascript">var ext_mod_base_url = '<?=self::$BASE_URL?>';</script><?php
		self::addResource(ExternalModules::getManagerJSDirectory().'update-modules.js');
		print  "<div class='yellow repo-updates'>
					<div style='color:#A00000;'>
						<i class='fas fa-bell'></i> <span style='margin-left:3px;font-weight:bold;'>
						<span id='repo-updates-count'>$countModuleUpdates</span>
						".($countModuleUpdates == 1 ? "External Module</span> has" : "External Modules</span> have")." 
						updates available for download from the REDCap Repo.
						<button onclick=\"$(this).hide();$('.repo-updates-list').show();\" class='btn btn-danger btn-xs ml-2'>View updates</a>
					</div>
					<div class='repo-updates-list'>
						Updates are available for the modules listed below. You may click the button(s) to upgrade them all at once or individually. 
						<div class='mt-3 mb-4'>
							<button id='update-all-modules' class='btn btn-primary btn-sm' data-module-info=\"$moduleData\"><span class='fas fa-download'></span> Update All</button>
						</div>
						$links
					</div>
				</div>";
	}
	
	// Store any json-encoded module updates passed in the URL from the REDCap Repo
	public static function storeREDCapRepoUpdatesInConfig($json="", $redirect=false)
	{
		if (!function_exists('updateConfig')) return false;
		if (empty($json)) return false;
		$json = rawurldecode(urldecode($json));
		$moduleUpdates = json_decode($json, true);
		if (!is_array($moduleUpdates)) return false;
		updateConfig('external_modules_updates_available', $json);
		updateConfig('external_modules_updates_available_last_check', NOW);
		if ($redirect) redirect(APP_URL_EXTMOD."manager/control_center.php");
		return true;
	}
	
	// Remove a specific module from the JSON-encoded REDCap Repo updates config variable
	public static function removeModuleFromREDCapRepoUpdatesInConfig($module_id=null)
	{
		global $external_modules_updates_available;
		if (!is_numeric($module_id)) return false;
		if (!function_exists('updateConfig')) return false;
		$moduleUpdates = json_decode($external_modules_updates_available, true);
		if (!is_array($moduleUpdates) || !isset($moduleUpdates[$module_id])) return false;
		unset($moduleUpdates[$module_id]);
		$json = json_encode($moduleUpdates);
		updateConfig('external_modules_updates_available', $json);
		updateConfig('external_modules_updates_available_last_check', NOW);
		return true;
	}

	public static function downloadModule($module_id=null, $bypass=false, $sendUserInfo=false){
		// Ensure user is super user
		if (!$bypass && (!defined("SUPER_USER") || !SUPER_USER)) return "0";
		// Set modules directory path
		$modulesDir = dirname(APP_PATH_DOCROOT).DS.'modules'.DS;
		// Validate module_id
		if (empty($module_id) || !is_numeric($module_id)) return "0";
		$module_id = (int)$module_id;
		// Also obtain the folder name of the module
		$moduleFolderName = http_get(APP_URL_EXTMOD_LIB . "download.php?module_id=$module_id&name=1");

		if(empty($moduleFolderName) || $moduleFolderName == "ERROR"){
			throw new Exception("The request to retrieve the name for module $module_id from the repo failed.");
		}

		// The following concurrent download detect was added to prevent a download/delete loop that we believe
		// brought the production server & specific modules down a few times:
		// https://github.com/vanderbilt/redcap-external-modules/issues/136
		$tempDir = $modulesDir . $moduleFolderName . '_tmp';
		if(file_exists($tempDir)){
			if(filemtime($tempDir) > time()-30){
				// The temp dir was just created.  Assume another process is still actively downloading this module
				// Simply tell the user to retry if this request came from the UI.
				return 4;
			}
			else{
				// The last download process likely failed.  Removed the folder and try again.
				self::rrmdir($tempDir);
			}
		}

		if(!mkdir($tempDir)){
			// Another process just created this directory and is actively downloading the module.
			// Simply tell the user to retry if this request came from the UI.
			return 4;
		}

		$logDescription = "Download external module \"$moduleFolderName\" from repository";
		// This event must be allowed twice within any time frame (once for each webserver node at Vandy as of this writing).
		// The time frame is semi-arbitrary and is meant to catch the scenarios documented here:
		// https://github.com/vanderbilt/redcap-external-modules/issues/136
		// Even if #136 is completely solved, we should leave this in place to ensure possible future issues are immediately detected.
		self::throttleEvent($logDescription, 2, 3);
		\REDCap::logEvent($logDescription);

		// Send user info?
		if ($sendUserInfo) {
			$postParams = array('user'=>USERID, 'name'=>$GLOBALS['user_firstname']." ".$GLOBALS['user_lastname'], 
								'email'=>$GLOBALS['user_email'], 'institution'=>$GLOBALS['institution'], 'server'=>SERVER_NAME);
		} else {
			$postParams = array('institution'=>$GLOBALS['institution'], 'server'=>SERVER_NAME);
		}
		// Call the module download service to download the module zip
		$moduleZipContents = http_post(APP_URL_EXTMOD_LIB . "download.php?module_id=$module_id", $postParams);
		// Errors?
		if ($moduleZipContents == 'ERROR') {
			// 0 = Module does not exist in library
			return "0";
		}
		// Place the file in the temp directory before extracting it
		$filename = APP_PATH_TEMP . date('YmdHis') . "_externalmodule_" . substr(sha1(rand()), 0, 6) . ".zip";
		if (file_put_contents($filename, $moduleZipContents) === false) {
			// 1 = Module zip couldn't be written to temp
			return "1";
		}
		// Extract the module to /redcap/modules
		$zip = new \ZipArchive;
		if ($zip->open($filename) !== TRUE) {
		  return "2";
		}
		// First, we need to rename the parent folder in the zip because GitHub has it as something else
		$i = 0;
		while ($item_name = $zip->getNameIndex($i)){
			$item_name_end = substr($item_name, strpos($item_name, "/"));
			$zip->renameIndex($i++, $moduleFolderName . $item_name_end);
		}
		$zip->close();
		// Now extract the zip to the modules folder
		$zip = new \ZipArchive;
		if ($zip->open($filename) === TRUE) {
			$zip->extractTo($tempDir);
			$zip->close();
		}
		// Remove temp file
		unlink($filename);

		// Move the extracted directory to it's final location
		$moduleFolderDir = $modulesDir . $moduleFolderName . DS;
		rename($tempDir.DS.$moduleFolderName, $moduleFolderDir);

		// Remove temp dir
		rmdir($tempDir);

		// Now double check that the new module directory got created
		if (!(file_exists($moduleFolderDir) && is_dir($moduleFolderDir))) {
		   return "3";
		}
		// Add row to redcap_external_modules_downloads table
		$sql = "insert into redcap_external_modules_downloads (module_name, module_id, time_downloaded) 
				values ('".db_escape($moduleFolderName)."', '".db_escape($module_id)."', '".NOW."')
				on duplicate key update 
				module_id = '".db_escape($module_id)."', time_downloaded = '".NOW."', time_deleted = null";
		db_query($sql);
		// Remove module_id from external_modules_updates_available config variable		
		self::removeModuleFromREDCapRepoUpdatesInConfig($module_id);

		// Give success message
		return "<div class='clearfix'><div class='float-left'><img src='".APP_PATH_IMAGES."check_big.png'></div><div class='float-left' style='width:360px;margin:8px 0 0 20px;color:green;font-weight:600;'>The module was successfully downloaded to the REDCap server, and can now be enabled.</div></div>";
	}

	public static function deleteModuleDirectory($moduleFolderName=null, $bypass=false){
		$logDescription = "Delete external module \"$moduleFolderName\" from system";
		// This event must be allowed twice within any time frame (once for each webserver node at Vandy as of this writing).
		// The time frame is semi-arbitrary and is meant to catch the scenarios documented here:
		// https://github.com/vanderbilt/redcap-external-modules/issues/136
		// Even if #136 is completely solved, we should leave this in place to ensure possible future issues are immediately detected.
		self::throttleEvent($logDescription, 2, 15);
		\REDCap::logEvent($logDescription);

		if(empty($moduleFolderName)){
			// Prevent the entire modules directory from being deleted.
			throw new Exception("You must specify a module to delete!");
		}

		// Ensure user is super user
		if (!$bypass && (!defined("SUPER_USER") || !SUPER_USER)) return "0";
		// Set modules directory path
		$modulesDir = dirname(APP_PATH_DOCROOT).DS.'modules'.DS;
		// First see if the module directory already exists
		$moduleFolderDir = $modulesDir . $moduleFolderName . DS;
		if (!(file_exists($moduleFolderDir) && is_dir($moduleFolderDir))) {
		   return "1";
		}
		// Delete the directory
		self::rrmdir($moduleFolderDir);
		// Return error if not deleted
		if (file_exists($moduleFolderDir) && is_dir($moduleFolderDir)) {
		   return "0";
		}
		// Add to deleted modules array
		self::$deletedModules[basename($moduleFolderDir)] = time();
		// Remove row from redcap_external_modules_downloads table
		$sql = "update redcap_external_modules_downloads set time_deleted = '".NOW."' 
				where module_name = '".db_escape($moduleFolderName)."'";
		db_query($sql);

		// Give success message
		return "The module and its corresponding directory were successfully deleted from the REDCap server.";
	}

	# Was this module originally downloaded from the central repository of ext mods? Exclude it if the module has already been marked as deleted via the UI.
	private static function wasModuleDownloadedFromRepo($moduleFolderName=null){
		$sql = "select 1 from redcap_external_modules_downloads 
				where module_name = '".db_escape($moduleFolderName)."' and time_deleted is null";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}

	# Was this module, which was downloaded from the central repository of ext mods, deleted via the UI?
	private static function wasModuleDeleted($modulePath){
		$moduleFolderName = basename($modulePath);

		$deletionTimesByFolderName = self::getDeletedModules();
		$deletionTime = @$deletionTimesByFolderName[$moduleFolderName];

		if($deletionTime !== null){
			if($deletionTime > filemtime($modulePath)){
				return true;
			}
			else{
				// The directory was re-created AFTER deletion.
				// This likely means a developer recreated the directory manually via git clone instead of using the REDCap Repo to download the module.
				// We should remove this row from the module downloads table since this module is no longer managed via the REDCap Rep.
				self::query("delete from redcap_external_modules_downloads where module_name = '$moduleFolderName'");
			}
		}

		return false;
	}
	
	# Obtain array of all DELETED modules (deleted via UI) that were originally downloaded from the REDCap Repo.
	private static function getDeletedModules(){
		if(!isset(self::$deletedModules)){
			$sql = "select module_name, time_deleted from redcap_external_modules_downloads 
					where time_deleted is not null";
			$q = db_query($sql);
			self::$deletedModules = array();
			while ($row = db_fetch_assoc($q)) {
				self::$deletedModules[$row['module_name']] = strtotime($row['time_deleted']);
			}
		}
		return self::$deletedModules;
	}

	# If module was originally downloaded from the central repository of ext mods,
	# then return its module_id (from the repo)
	public static function getRepoModuleId($moduleFolderName=null){
		$sql = "select module_id from redcap_external_modules_downloads where module_name = '".db_escape($moduleFolderName)."'";
		$q = db_query($sql);
		return (db_num_rows($q) > 0 ? db_result($q, 0) : false);
	}
	
	# general method to delete a directory by first deleting all files inside it
	# Copied from https://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it
	private static function rrmdir($dir)
	{
		$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it,
					 RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) {
			if ($file->isDir()){
				rmdir($file->getRealPath());
			} else {
				unlink($file->getRealPath());
			}
		}
		rmdir($dir);
	}
	
	// Find the redcap_connect.php file and require it
	public static function callRedcapConnect()
	{
		if(!defined('PLUGIN')){
			// Since a change to redcap_connect.php on 4/6/18, this is required to make sure REDCap is initialized for command line calls like cron jobs.
			define('PLUGIN', true);
		}

		$connectPath = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . "redcap_connect.php";
		if (!file_exists($connectPath)) {
		    // We must be using the "external_modules" folder to override the version of the framework bundled with REDCap.
			$connectPath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "redcap_connect.php";
		}

		require_once $connectPath;
	}
	
	// Return array of module dir prefixes for modules with a system-level value of TRUE for discoverable-in-project
	public static function getDiscoverableModules()
	{
		$modules = array();
		$sql = "select m.directory_prefix, x.`value` from redcap_external_modules m, 
				redcap_external_module_settings s, redcap_external_module_settings x
				where m.external_module_id = s.external_module_id and s.project_id is null
				and s.`value` = 'true' and s.`key` = '".db_escape(self::KEY_DISCOVERABLE)."'
                and m.external_module_id = x.external_module_id and x.project_id is null
				and x.`key` = '".db_escape(self::KEY_VERSION)."'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$modules[$row['directory_prefix']] = $row['value'];
		}
		return $modules;
	}
	
	// Return boolean if any projects have a system-level value of TRUE for discoverable-in-project
	public static function hasDiscoverableModules()
	{
		$modules = self::getDiscoverableModules();
		return !empty($modules);
	}

	# Return array all all module prefixes where the module requires that regular users have project-level 
	# permissions in order to configure it for the project. First provide an array of dir prefixes that you want to check.
	public static function getModulesRequireConfigPermission($prefixes=array())
	{
		$modules = array();
		if (empty($prefixes)) return $modules;
		$sql = "SELECT m.directory_prefix FROM redcap_external_modules m, redcap_external_module_settings s 
				WHERE m.external_module_id = s.external_module_id AND s.value = 'true'
				AND s.`key` = '" . self::KEY_CONFIG_USER_PERMISSION . "' AND m.directory_prefix in (" . prep_implode($prefixes) . ")";
		$q = self::query($sql);
		while ($row = db_fetch_assoc($q)) {
			$modules[] = $row['directory_prefix'];
		}
		return $modules;
	}
	
	# Return boolean if module requires that regular users have project-level 
	# permissions in order to configure it for the project.
	public static function moduleRequiresConfigPermission($prefix=null)
	{
		$module = self::getModulesRequireConfigPermission(array($prefix));
		return !empty($module);
	}
	
	# Return array all all modules enabled in a project where the module requires that regular users have project-level 
	# permissions in order to configure it for the project. Array also contains module title.
	public static function getModulesWithCustomUserRights($project_id=null)
	{
		// Place modules into an array
		$modulesAttributes = $titles = array();
		// Get modules enabled for this project
		$enabledModules = self::getEnabledModules($project_id);
		// Of the enabled projects, find those that require user permissions to configure in project
		$enabledModulesReqConfigPerm = self::getModulesRequireConfigPermission(array_keys($enabledModules));
		// Obtain the title of each module from its config
		foreach (array_keys($enabledModules) as $thisModule) {		
			$config = self::getConfig($thisModule, null, $project_id);
			if (!isset($config['name'])) continue;
			// Add attributes to array
			$title = trim(strip_tags($config['name']));
			$modulesAttributes[$thisModule] = array('name'=>$title, 
													'has-project-config'=>((isset($config['project-settings']) && !empty($config['project-settings'])) ? 1 : 0), 
													'require-config-perm'=>(in_array($thisModule, $enabledModulesReqConfigPerm) ? 1 : 0));
			// Add title to another array so we can sort by title
			$titles[] = $title;
		}
		// Sort modules by title
		array_multisort($titles, SORT_REGULAR, $modulesAttributes);
		// Return modules with attributes
		return $modulesAttributes;
	}

	public static function getDocumentationUrl($prefix)
	{
		$config = self::getConfig($prefix);
		$documentation = @$config['documentation'];
		if(filter_var($documentation, FILTER_VALIDATE_URL)){
			return $documentation;
		}

		if(empty($documentation)){
			$documentation = self::detectDocumentationFilename($prefix);
		}

		if(is_file(self::getModuleDirectoryPath($prefix) . "/$documentation")){
			// Use the module url function so that raw URLs can be returned (for PDFs, etc.).
			$module = self::getModuleInstance($prefix);

			// Temporarily remove the PID while getting the URL so that the URL
			// return will still work even if the module is not yet enabled.
			$originalPid = @$_GET['pid'];
			$_GET['pid'] = null;
			$url = $module->getUrl($documentation);
			$_GET['pid'] = $originalPid;

			return $url;
		}

		return null;
	}

	private static function detectDocumentationFilename($prefix)
	{
		foreach(glob(self::getModuleDirectoryPath($prefix) . '/*') as $path){
			$filename = basename($path);
			$lowercaseFilename = strtolower($filename);
			if(strpos($lowercaseFilename, 'readme.') === 0){
				return $filename;
			}
		}

		return null;
	}

	private static function getDatacoreEmails($to){
		if (self::isVanderbilt()) {
			$to[] = 'mark.mcever@vanderbilt.edu';
			$to[] = 'kyle.mcguffin@vanderbilt.edu';

			if (self::$SERVER_NAME == 'redcap.vanderbilt.edu') {
				$to[] = 'datacore@vanderbilt.edu';
			}
		}

		return $to;
	}

	// This method is deprecated, but is still used in a couple of modules at Vandy.
	// We should likely refactor those modules to use sendAdminEmail() instead, then remove this method.
	public static function sendErrorEmail($email_error,$subject,$body){
		global $project_contact_email;
		$from = $project_contact_email;

		if (is_array($email_error)) {
			$emails = preg_split("/[;,]+/", $email_error);
			foreach ($emails as $to) {
				\REDCap::email($to, $from, $subject, $body);
			}
		} else if ($email_error) {
			\REDCap::email($email_error, $from, $subject, $body);
		} else if($email_error == ""){
			$emails = self::getDatacoreEmails();
			foreach ($emails as $to){
				\REDCap::email($to, $from, $subject, $body);
			}
		}
	}

	public static function getContentType($extension)
	{
		$extension = strtolower($extension);

		// The following list came from https://gist.github.com/raphael-riel/1253986
		$types = array(
		    'ai'      => 'application/postscript',
		    'aif'     => 'audio/x-aiff',
		    'aifc'    => 'audio/x-aiff',
		    'aiff'    => 'audio/x-aiff',
		    'asc'     => 'text/plain',
		    'atom'    => 'application/atom+xml',
		    'atom'    => 'application/atom+xml',
		    'au'      => 'audio/basic',
		    'avi'     => 'video/x-msvideo',
		    'bcpio'   => 'application/x-bcpio',
		    'bin'     => 'application/octet-stream',
		    'bmp'     => 'image/bmp',
		    'cdf'     => 'application/x-netcdf',
		    'cgm'     => 'image/cgm',
		    'class'   => 'application/octet-stream',
		    'cpio'    => 'application/x-cpio',
		    'cpt'     => 'application/mac-compactpro',
		    'csh'     => 'application/x-csh',
		    'css'     => 'text/css',
		    'csv'     => 'text/csv',
		    'dcr'     => 'application/x-director',
		    'dir'     => 'application/x-director',
		    'djv'     => 'image/vnd.djvu',
		    'djvu'    => 'image/vnd.djvu',
		    'dll'     => 'application/octet-stream',
		    'dmg'     => 'application/octet-stream',
		    'dms'     => 'application/octet-stream',
		    'doc'     => 'application/msword',
		    'dtd'     => 'application/xml-dtd',
		    'dvi'     => 'application/x-dvi',
		    'dxr'     => 'application/x-director',
		    'eps'     => 'application/postscript',
		    'etx'     => 'text/x-setext',
		    'exe'     => 'application/octet-stream',
		    'ez'      => 'application/andrew-inset',
		    'gif'     => 'image/gif',
		    'gram'    => 'application/srgs',
		    'grxml'   => 'application/srgs+xml',
		    'gtar'    => 'application/x-gtar',
		    'hdf'     => 'application/x-hdf',
		    'hqx'     => 'application/mac-binhex40',
		    'htm'     => 'text/html',
		    'html'    => 'text/html',
		    'ice'     => 'x-conference/x-cooltalk',
		    'ico'     => 'image/x-icon',
		    'ics'     => 'text/calendar',
		    'ief'     => 'image/ief',
		    'ifb'     => 'text/calendar',
		    'iges'    => 'model/iges',
		    'igs'     => 'model/iges',
		    'jpe'     => 'image/jpeg',
		    'jpeg'    => 'image/jpeg',
		    'jpg'     => 'image/jpeg',
		    'js'      => 'application/x-javascript',
		    'json'    => 'application/json',
		    'kar'     => 'audio/midi',
		    'latex'   => 'application/x-latex',
		    'lha'     => 'application/octet-stream',
		    'lzh'     => 'application/octet-stream',
		    'm3u'     => 'audio/x-mpegurl',
		    'man'     => 'application/x-troff-man',
		    'mathml'  => 'application/mathml+xml',
		    'me'      => 'application/x-troff-me',
		    'mesh'    => 'model/mesh',
		    'mid'     => 'audio/midi',
		    'midi'    => 'audio/midi',
		    'mif'     => 'application/vnd.mif',
		    'mov'     => 'video/quicktime',
		    'movie'   => 'video/x-sgi-movie',
		    'mp2'     => 'audio/mpeg',
		    'mp3'     => 'audio/mpeg',
		    'mpe'     => 'video/mpeg',
		    'mpeg'    => 'video/mpeg',
		    'mpg'     => 'video/mpeg',
		    'mpga'    => 'audio/mpeg',
		    'ms'      => 'application/x-troff-ms',
		    'msh'     => 'model/mesh',
		    'mxu'     => 'video/vnd.mpegurl',
		    'nc'      => 'application/x-netcdf',
		    'oda'     => 'application/oda',
		    'ogg'     => 'application/ogg',
		    'pbm'     => 'image/x-portable-bitmap',
		    'pdb'     => 'chemical/x-pdb',
		    'pdf'     => 'application/pdf',
		    'pgm'     => 'image/x-portable-graymap',
		    'pgn'     => 'application/x-chess-pgn',
		    'png'     => 'image/png',
		    'pnm'     => 'image/x-portable-anymap',
		    'ppm'     => 'image/x-portable-pixmap',
		    'ppt'     => 'application/vnd.ms-powerpoint',
		    'ps'      => 'application/postscript',
		    'qt'      => 'video/quicktime',
		    'ra'      => 'audio/x-pn-realaudio',
		    'ram'     => 'audio/x-pn-realaudio',
		    'ras'     => 'image/x-cmu-raster',
		    'rdf'     => 'application/rdf+xml',
		    'rgb'     => 'image/x-rgb',
		    'rm'      => 'application/vnd.rn-realmedia',
		    'roff'    => 'application/x-troff',
		    'rss'     => 'application/rss+xml',
		    'rtf'     => 'text/rtf',
		    'rtx'     => 'text/richtext',
		    'sgm'     => 'text/sgml',
		    'sgml'    => 'text/sgml',
		    'sh'      => 'application/x-sh',
		    'shar'    => 'application/x-shar',
		    'silo'    => 'model/mesh',
		    'sit'     => 'application/x-stuffit',
		    'skd'     => 'application/x-koan',
		    'skm'     => 'application/x-koan',
		    'skp'     => 'application/x-koan',
		    'skt'     => 'application/x-koan',
		    'smi'     => 'application/smil',
		    'smil'    => 'application/smil',
		    'snd'     => 'audio/basic',
		    'so'      => 'application/octet-stream',
		    'spl'     => 'application/x-futuresplash',
		    'src'     => 'application/x-wais-source',
		    'sv4cpio' => 'application/x-sv4cpio',
		    'sv4crc'  => 'application/x-sv4crc',
		    'svg'     => 'image/svg+xml',
		    'svgz'    => 'image/svg+xml',
		    'swf'     => 'application/x-shockwave-flash',
		    't'       => 'application/x-troff',
		    'tar'     => 'application/x-tar',
		    'tcl'     => 'application/x-tcl',
		    'tex'     => 'application/x-tex',
		    'texi'    => 'application/x-texinfo',
		    'texinfo' => 'application/x-texinfo',
		    'tif'     => 'image/tiff',
		    'tiff'    => 'image/tiff',
		    'tr'      => 'application/x-troff',
		    'tsv'     => 'text/tab-separated-values',
		    'txt'     => 'text/plain',
		    'ustar'   => 'application/x-ustar',
		    'vcd'     => 'application/x-cdlink',
		    'vrml'    => 'model/vrml',
		    'vxml'    => 'application/voicexml+xml',
		    'wav'     => 'audio/x-wav',
		    'wbmp'    => 'image/vnd.wap.wbmp',
		    'wbxml'   => 'application/vnd.wap.wbxml',
		    'wml'     => 'text/vnd.wap.wml',
		    'wmlc'    => 'application/vnd.wap.wmlc',
		    'wmls'    => 'text/vnd.wap.wmlscript',
		    'wmlsc'   => 'application/vnd.wap.wmlscriptc',
		    'wrl'     => 'model/vrml',
		    'xbm'     => 'image/x-xbitmap',
		    'xht'     => 'application/xhtml+xml',
		    'xhtml'   => 'application/xhtml+xml',
		    'xls'     => 'application/vnd.ms-excel',
		    'xml'     => 'application/xml',
		    'xpm'     => 'image/x-xpixmap',
		    'xsl'     => 'application/xml',
		    'xslt'    => 'application/xslt+xml',
		    'xul'     => 'application/vnd.mozilla.xul+xml',
		    'xwd'     => 'image/x-xwindowdump',
		    'xyz'     => 'chemical/x-xyz',
		    'zip'     => 'application/zip'
		);

		return @$types[$extension];
	}

	public static function getUsername()
	{
		if (!empty(self::$USERNAME)) {
			return self::$USERNAME;
		} else if (defined('USERID')) {
			return USERID;
		} else {
			return null;
		}
	}

	public static function setUsername($username)
	{
		if (!self::isTesting()) {
			throw new Exception("This method can only be used in unit tests.");
		}

		self::$USERNAME = $username;
	}

	public static function getTemporaryRecordId()
	{
		return self::$temporaryRecordId;
	}

	private static function setTemporaryRecordId($temporaryRecordId)
	{
		self::$temporaryRecordId = $temporaryRecordId;
	}

	public function sharedSurveyAndDataEntryActions($recordId)
	{
		if (empty($recordId) && (self::isSurveyPage() || self::isDataEntryPage())) {
			// We're creating a new record, but don't have an id yet.
			// We must create a temporary record id and include it in the form so it can be used to retroactively change logs to the actual record id once it exists.
			$temporaryRecordId = implode('-', [self::EXTERNAL_MODULES_TEMPORARY_RECORD_ID, time(), rand()]);
			self::setTemporaryRecordId($temporaryRecordId);
			?>
			<script>
				(function () {
					$('#form').append($('<input>').attr({
						type: 'hidden',
						name: <?=json_encode(ExternalModules::EXTERNAL_MODULES_TEMPORARY_RECORD_ID)?>,
						value: <?=json_encode($temporaryRecordId)?>
					}))
				})()
			</script>
			<?php
		}
	}

	public static function isTemporaryRecordId($recordId)
	{
		return strpos($recordId, self::EXTERNAL_MODULES_TEMPORARY_RECORD_ID) === 0;
	}

	public function isSurveyPage()
	{
		$url = $_SERVER['REQUEST_URI'];

		return strpos($url, APP_PATH_SURVEY) === 0 &&
			strpos($url, '__passthru=DataEntry%2Fimage_view.php') === false; // Prevent hooks from firing for survey logo URLs (and breaking them).
	}

	private function isDataEntryPage()
	{
		return strpos($_SERVER['REQUEST_URI'], APP_PATH_WEBROOT . 'DataEntry') === 0;
	}

	# for crons specified to run at a specific time
	public static function isValidTimedCron($cronAttr) {
		$hour = $cronAttr['cron_hour'];
		$minute = $cronAttr['cron_minute'];
		$weekday = $cronAttr['cron_weekday'];
		$monthday = $cronAttr['cron_monthday'];

		if (!self::isValidCron($cronAttr)) {
			return FALSE;
		}

		if (!empty($cronAttr['cron_frequency']) || !empty($cronAttr['cron_max_run_time'])) {
			return FALSE;
		}

		if (empty($minute)) {
			return FALSE;
		}
		if (!is_numeric($hour) || !is_numeric($minute)) {
			return FALSE;
		}
		if ($weekday && !is_numeric($weekday)) {
			return FALSE;
		}
		if ($monthday && !is_numeric($monthday)) {
			return FALSE;
		}

		if (!empty($hour) && (($hour < 0) || ($hour >= 24))) {
			return FALSE;
		}
		if (($minute < 0) || ($minute >= 60)) { 
			return FALSE;
		}

		return TRUE;
	}

	# for all generic crons; all must have the following attributes
	public static function isValidCron($cronAttr) {
		$name = $cronAttr['cron_name'];
		$descr = $cronAttr['cron_description'];
		$method = $cronAttr['method'];

		if (!isset($name) || !isset($descr) || !isset($method)) {
			return FALSE; 
		}

		return TRUE;
	}

	# only for crons stored in redcap_crons table
	private static function isValidTabledCron($cronAttr) {
		$frequency = $cronAttr['cron_frequency'];
		$maxRunTime = $cronAttr['cron_max_run_time'];

		if (!self::isValidCron($cronAttr)) {
			return FALSE;
		}

		if (!isset($frequency) || !isset($maxRunTime)) {
			return FALSE;
		}

		if (isset($cronAttr['cron_hour']) || isset($cronAttr['cron_minute'])) {
			return FALSE;
		}

		if (!is_numeric($frequency) || !is_numeric($maxRunTime)) {
			return FALSE;
		}

		if ($frequency <= 0) {
			return FALSE;
		}
		if ($maxRunTime <= 0) {
			return FALSE;
		}

		return TRUE;
	}

	# only for timed crons
	public static function isTimeToRun($cronAttr, $cronStartTime=NULL) {
		$hour = $cronAttr['cron_hour'];
		$minute = $cronAttr['cron_minute'];
		$weekday = $cronAttr['cron_weekday'];
		$monthday = $cronAttr['cron_monthday'];

		if(!self::isValidTimedCron($cronAttr)){
			return FALSE;
		}

		$hour = (int) $hour;
		$minute = (int) $minute;
		$weekday = (int) $weekday;
		$monthday = (int) $monthday;

		// We check the cron start time instead of the current time
		// in case another module's cron job ran us into the next minute.
		if (!$cronStartTime) {
			$cronStartTime = self::getLastTimeRun();
		}
		$currentHour = (int) date('G', $cronStartTime);
		$currentMinute = (int) date('i', $cronStartTime);  // The cast is especially important here to get rid of a possible leading zero.
		$currentWeekday = (int) date('w', $cronStartTime);
		$currentMonthday = (int) date('j', $cronStartTime);

		if (isset($cronAttr['cron_weekday'])) {
			if ($currentWeekday != $weekday) {
				return FALSE;
			}
		}

		if (isset($cronAttr['cron_monthday'])) {
			if ($currentMonthday != $monthday) {
				return FALSE;
			}
		}

		return ($hour === $currentHour) && ($minute === $currentMinute);
	}

	private static function getLastTimeRun() {
		return $_SERVER["REQUEST_TIME_FLOAT"];
	}

	private static function makeTimestamp() {
		return date("Y-m-d H:i:s");
	}

	public static function callTimedCronMethods() {
		# get array of modules
		$enabledModules = self::getEnabledModules();
		$returnMessages = array();

		foreach ($enabledModules as $moduleDirectoryPrefix=>$version) {
			try{
				$cronName = "";

				# do not run twice in the same minute
				$cronAttrs = self::getCronSchedules($moduleDirectoryPrefix);
				$moduleId = self::getIdForPrefix($moduleDirectoryPrefix);
				if (!empty($moduleInstance) && !empty($moduleId) && !empty($cronAttrs)) {
					foreach ($cronAttrs as $cronAttr) {
						$cronName = $cronAttr['cron_name'];
						if (self::isValidTimedCron($cronAttr) && self::isTimeToRun($cronAttr)) {
							# if isTimeToRun, run method
							$cronMethod = $cronAttr['method'];
							array_push($returnMessages, "Timed cron running $cronName->$cronMethod (".self::makeTimestamp().")");
							$mssg = self::callTimedCronMethod($moduleDirectoryPrefix, $cronName);
							if ($mssg) {
								array_push($returnMessages, $mssg." (".self::makeTimestamp().")");
							}
						}
					}
				}
			} catch(Exception $e) {
				$currentReturnMessage = "Timed Cron job \"$cronName\" failed for External Module \"{$moduleDirectoryPrefix}\"";
				$emailMessage = "$currentReturnMessage with the following Exception: $e";

				self::sendAdminEmail('External Module Exception in Timed Cron Job ', $emailMessage, $moduleDirectoryPrefix);
				array_push($returnMessages, $currentReturnMessage);
			}
		}
		
		return $returnMessages;
	}

	private static function callTimedCronMethod($moduleDirectoryPrefix, $cronName)
	{
		$lockInfo = self::getCronLockInfo($moduleDirectoryPrefix);
		if($lockInfo){
			self::checkForALongRunningCronJob($moduleDirectoryPrefix, $cronName, $lockInfo);
			return "Skipping cron '$cronName' for module '$moduleDirectoryPrefix' because an existing job is already running for this module.";
		}

		try{
			self::lockCron($moduleDirectoryPrefix);

			$moduleId = self::getIdForPrefix($moduleDirectoryPrefix);
			return $returnMessage = self::callCronMethod($moduleId, $cronName);
		}
		finally{
			self::unlockCron($moduleDirectoryPrefix);
		}
	}

	// This method is called both internally and by the REDCap Core code.
	public static function callCronMethod($moduleId, $cronName)
	{
		$moduleDirectoryPrefix = self::getPrefixForID($moduleId);
		self::setActiveModulePrefix($moduleDirectoryPrefix);
		self::$hookBeingExecuted = "$cronName (cron)";

		$returnMessage = null;
		try{
			// Call cron for this External Module
			$moduleInstance = self::getModuleInstance($moduleDirectoryPrefix);
			if (!empty($moduleInstance)) {
				$config = $moduleInstance->getConfig();
				if (isset($config['crons']) && !empty($config['crons'])) {
					// Loop through all crons to find the one we're looking for
					foreach ($config['crons'] as $cronKey=>$cronAttr) {
						if ($cronAttr['cron_name'] != $cronName) continue;

						// Find and validate the cron method in the module class
						$cronMethod = $config['crons'][$cronKey]['method'];

						// Execute the cron method in the module class
						$returnMessage = $moduleInstance->$cronMethod($cronAttr);
					}
				}
			}
		}
		catch(Exception $e){
			$returnMessage = "Cron job \"$cronName\" failed for External Module \"{$moduleDirectoryPrefix}\"";
			$emailMessage = "$returnMessage with the following Exception: $e";

			self::sendAdminEmail(self::CRON_EXCEPTION_EMAIL_SUBJECT, $emailMessage, $moduleDirectoryPrefix);
		}

		self::setActiveModulePrefix(null);
		self::$hookBeingExecuted = "";
		
		return $returnMessage;
	}

	private static function checkForALongRunningCronJob($moduleDirectoryPrefix, $cronName, $lockInfo) {
		/* There are currently two scenarios under which this method will get called:
		 *
		 * 1. A long running cron module method delays the start time of another cron module method in the same cron process,
		 * and that method ends up running concurrently with itself in a later cron process.  This scenario can safely be ignored.
		 *
		 * 2. A cron module method has run longer than the $notificationThreshold below.  No matter how often a job is scheduled to run,
		 * notifications for long running jobs will not be sent more often than the following threshold.  It's currently set
		 * to a little less than 24 hours to ensure that a notification is sent at least once a day for long running daily jobs
		 * (even if they were started a little late due to a previous job).
		 */
		$notificationThreshold = time() - 23*self::HOUR_IN_SECONDS;
		$jobRunningLong = $lockInfo['time'] <= $notificationThreshold;
		if($jobRunningLong){
			$lastNotificationTime = self::getSystemSetting($moduleDirectoryPrefix, self::KEY_RESERVED_LAST_LONG_RUNNING_CRON_NOTIFICATION_TIME);
			$notificationNeeded = !$lastNotificationTime || $lastNotificationTime <= $notificationThreshold;
			if($notificationNeeded) {
				$emailMessage = "The '$cronName' cron job is being skipped for the '$moduleDirectoryPrefix' module because a previous cron for this module did not complete.  Please make sure this module's configuration is correct for every project, and that it should not cause crons to run more than $x past their next start time.  The previous process id was {$lockInfo['process-id']}.  If that process is no longer running, it was likely manually killed, and can be manually marked as complete by running the following SQL query:<br><br>DELETE FROM redcap_external_module_settings WHERE external_module_id = '$moduleId' AND `key` = '" . self::KEY_RESERVED_IS_CRON_RUNNING . "'<br><br>In addition, if several crons run at the same time, please consider rescheduling some of them via the <a href='".APP_URL_EXTMOD."manager/crons.php'>Manager for Timed Crons</a>";
				self::sendAdminEmail(self::LONG_RUNNING_CRON_EMAIL_SUBJECT, $emailMessage, $moduleDirectoryPrefix);
				self::setSystemSetting($moduleDirectoryPrefix, self::KEY_RESERVED_LAST_LONG_RUNNING_CRON_NOTIFICATION_TIME, time());
			}
		}
	}

	private static function getCronLockInfo($modulePrefix) {
		return self::getSystemSetting($modulePrefix, self::KEY_RESERVED_IS_CRON_RUNNING);
	}

	private static function unlockCron($modulePrefix) {
		self::removeSystemSetting($modulePrefix, self::KEY_RESERVED_IS_CRON_RUNNING);
	}

	private static function lockCron($modulePrefix) {
		self::setSystemSetting($modulePrefix, self::KEY_RESERVED_IS_CRON_RUNNING, [
			'process-id' => getmypid(),
			'time' => time()
		]);

		self::removeSystemSetting($modulePrefix, self::KEY_RESERVED_LAST_LONG_RUNNING_CRON_NOTIFICATION_TIME);
	}

	// Throttles actions by using the redcap_log_event.description.
	// An exception is thrown if the $description occurs more than $maximumOccurrences within the past specified number of $seconds.
	private function throttleEvent($description, $maximumOccurrences, $seconds)
	{
		$description = db_escape($description);

		$ts = date('YmdHis', time()-$seconds);

		$result = db_query("
			select count(*) as count
			from redcap_log_event l
			where description = '$description'
			and ts >= $ts
		");

		$row = $result->fetch_assoc();

		$occurrences = $row['count'];

		if($occurrences > $maximumOccurrences){
			throw new Exception("The following action has been throttled because it is only allowed to happen $maximumOccurrences times within $seconds seconds, but it happened $occurrences times: $description");
		}
	}

	// Copied from the first comment here:
	// http://php.net/manual/en/function.array-merge-recursive.php
	function array_merge_recursive_distinct ( array &$array1, array &$array2 )
	{
	  $merged = $array1;

	  foreach ( $array2 as $key => &$value )
	  {
	    if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) )
	    {
	      $merged [$key] = self::array_merge_recursive_distinct ( $merged [$key], $value );
	    }
	    else
	    {
	      $merged [$key] = $value;
	    }
	  }

	  return $merged;
	}

	public static function dump($o){
		echo "<pre>";
		var_dump($o);
		echo "</pre>";
	}

	public static function initializeFramework($module)
	{
		$version = self::getFrameworkVersion($module);

		if($version === 1){
			// Do nothing since there's no framework object in this version.
			return;
		}

		$path = __DIR__ . "/framework/v$version/Framework.php";

		global $redcap_version;
		if($version === 3 && version_compare($redcap_version, '9.0.3', '<')){
			// This line and surrounding 'if' can be removed once the LTS release is greater than 9.0.3.
			$path = null;
		}

		if(!file_exists($path)) {
			throw new Exception("The {$module->getModuleName()} module requires framework version $version, which is not available on your REDCap instance.");
		}

		require_once $path;
		$className = "\\ExternalModules\\FrameworkVersion$version\\Framework";
		$module->framework = new $className($module);
	}

	public static function getFrameworkVersion($module)
	{
		$config = self::getConfig($module->PREFIX, $module->VERSION);
		$version = @$config['framework-version'];

		if($version === null){
			$version = 1;
		}
		else if(gettype($version) != 'integer'){
			throw new Exception("The framework version must be specified as an integer (not a string) for the $prefix module.");
		}

		return $version;
	}

	public static function requireInteger($mixed){
		$integer = filter_var($mixed, FILTER_VALIDATE_INT);
		if($integer === false){
			throw new Exception("An integer was required but the following value was specified instead: $mixed");
		}

		return $integer;
	}

	public static function getJavascriptModuleObjectName($moduleInstance){
		$jsObjectParts = explode('\\', get_class($moduleInstance));

		// Remove the class name, since it's always the same as it's parent namespace.
		array_pop($jsObjectParts);

		// Prepend "ExternalModules" to contain all module namespaces.
		array_unshift($jsObjectParts, 'ExternalModules');

		return implode('.', $jsObjectParts);
	}

	public static function isRoute($routeName){
		return $_GET['route'] === $routeName;
	}

	public static function getLinkIconHtml($module, $link){
		$icon = $link['icon'];

		$style = 'width: 16px; height: 16px; text-align: center;';

		$getImageIconElement = function($iconUrl) use ($style){
			return "<img src='$iconUrl' style='$style'>";
		};

		if(ExternalModules::getFrameworkVersion($module) >= 3){
			$iconPath = $module->framework->getModulePath() . '/' . $icon;
			if(file_exists($iconPath)){
				$iconElement = $getImageIconElement($module->getUrl($icon));
			}
			else{
				// Assume it is a font awesome class.
				$iconElement = "<i class='$icon' style='$style'></i>";
			}
		}
		else{
			$iconPathSuffix = 'images/' . $icon . '.png';

			if(file_exists(ExternalModules::$BASE_PATH . $iconPathSuffix )){
				$iconUrl = ExternalModules::$BASE_URL . $iconPathSuffix;
			}
			else{
				$iconUrl = APP_PATH_WEBROOT . 'Resources/' . $iconPathSuffix;
			}

			$iconElement = $getImageIconElement($iconUrl);
		}

		$linkUrl = $link['url'];
		$projectId = $module->getProjectId();
		if($projectId){
			$linkUrl .= "&pid=$projectId";
		}

		return "
			<div>
				$iconElement
				<a href='$linkUrl' target='{$link["target"]}'>{$link["name"]}</a>
			</div>
		";
	}

	static function copySettings($sourceProjectId, $destinationProjectId){
		// Prevent SQL Injection
		$sourceProjectId = (int) $sourceProjectId;
		$destinationProjectId = (int) $destinationProjectId;

		self::copySettingValues($sourceProjectId, $destinationProjectId);
		self::recreateAllEDocs($destinationProjectId);
	}

	private static function copySettingValues($sourceProjectId, $destinationProjectId){
		self::query("
			insert into redcap_external_module_settings (external_module_id, project_id, `key`, type, value)
			select external_module_id, '$destinationProjectId', `key`, type, value from redcap_external_module_settings
		  	where project_id = $sourceProjectId and `key` != '" . ExternalModules::KEY_ENABLED . "'
		");
	}

	// We recreate edocs when copying settings between projects so that edocs removed from
	// one project are not also removed from other projects.
	// This method is currently undocumented/unsupported in modules.
	// It is public because it is used by Carl's settings import/export module.
	static function recreateAllEDocs($pid)
	{
		$richTextSettingsByPrefix = self::recreateEDocSettings($pid);
		self::recreateRichTextEDocs($pid, $richTextSettingsByPrefix);
	}

	private static function recreateEDocSettings($pid)
	{
		// Prevent SQL Injection
		$pid = (int) $pid;

		$handleValue = function($value) use ($pid, &$handleValue){
			if(gettype($value) === 'array'){
				for($i=0; $i<count($value); $i++){
					$value[$i] = $handleValue($value[$i]);
				}
			}
			else{
				list($oldPid, $value) = self::recreateEdoc($pid, $value);
			}

			return $value;
		};

		$result = self::query("select * from redcap_external_module_settings where project_id = $pid");
		$richTextSettingsByPrefix = [];
		while($row = db_fetch_assoc($result)){
			$prefix = self::getPrefixForID($row['external_module_id']);
			$key = $row['key'];

			$details = self::getSettingDetails($prefix, $key);

			$type = $details['type'];
			if($type === 'file'){
				$value = self::getProjectSetting($prefix, $pid, $key);
				$value = $handleValue($value);
				self::setProjectSetting($prefix, $pid, $key, $value);
			}
			else if($type === 'rich-text'){
				// Replace the value with the version returned by getProjectSetting() to handle arrays for subsettings/repeatables.
				$row['value'] = self::getProjectSetting($prefix, $pid, $key);;
				$richTextSettingsByPrefix[$prefix][] = $row;
			}
		}

		return $richTextSettingsByPrefix;
	}

	private static function recreateRichTextEDocs($pid, $richTextSettingsByPrefix)
	{
		$results = ExternalModules::query("select * from redcap_external_module_settings where `key` = '" . ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST . "' and project_id = $pid");
		while($row = db_fetch_assoc($results)){
			$prefix = ExternalModules::getPrefixForID($row['external_module_id']);
			$files = ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST);
			$settings = &$richTextSettingsByPrefix[$prefix];

			foreach($files as &$file){
				$name = $file['name'];

				$oldId = $file['edocId'];
				list($oldPid, $newId) = self::recreateEdoc($pid, $oldId);
				if(empty($newId)){
					// The edocId was either invalid or the file has been deleted.  Just skip this one.
					continue;
				}

				$file['edocId'] = $newId;

				$handleValue = function($value) use ($pid, $prefix, $oldPid, $oldId, $newId, $name, &$handleValue){
					if(gettype($value) === 'array'){
						for($i=0; $i<count($value); $i++){
							$value[$i] = $handleValue($value[$i]);
						}
					}
					else{ // it's a string
						$search = htmlspecialchars(ExternalModules::getRichTextFileUrl($prefix, $oldPid, $oldId, $name));
						$replace = htmlspecialchars(ExternalModules::getRichTextFileUrl($prefix, $pid, $newId, $name));
						$value = str_replace($search, $replace, $value);
					}

					return $value;
				};

				foreach($settings as $i=>$setting){
					$setting['value'] = $handleValue($setting['value']);
					$settings[$i] = $setting;
				}
			}

			ExternalModules::setProjectSetting($prefix, $pid, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST, $files);
		}

		foreach($richTextSettingsByPrefix as $prefix=>$settings){
			foreach($settings as $setting){
				ExternalModules::setProjectSetting($prefix, $pid, $setting['key'], $setting['value']);
			}
		}
	}

	private static function recreateEdoc($pid, $edocId)
	{
		if(empty($edocId)){
			// The stored id is already empty.
			return '';
		}

		$sql = "select * from redcap_edocs_metadata where doc_id = $edocId and date_deleted_server is null";
		$result = self::query($sql);
		$row = db_fetch_assoc($result);
		if(!$row){
			return '';
		}

		$oldPid = $row['project_id'];
		if($oldPid === $pid){
			// This edoc is already associated with this project.  No need to recreate it.
			$newEdocId = $edocId;
		}
		else{
			$newEdocId = copyFile($edocId, $pid);
		}

		return [
			$oldPid,
			(string)$newEdocId // We must cast to a string to avoid an issue on the js side when it comes to handling file fields if stored as integers.
		];
	}

	# timespan is number of seconds
	public static function getCronConflictTimestamps($timespan) {
		$currTime = time();
		$conflicts = array();

		// keep these for debugging purposes
		$timesRun = array();
		$skipped = array();

		$enabledModules = self::getEnabledModules();
		foreach ($enabledModules as $moduleDirectoryPrefix=>$version) {
			$cronAttrs = self::getCronSchedules($moduleDirectoryPrefix);
			foreach ($cronAttrs as $cronAttr) {
				# check every minute
				for ($i = 0; $i < $timespan; $i += 60) {
					$timeToCheck = $currTime + $i;
					if (self::isTimeToRun($cronAttr, $timeToCheck)) {
						if (in_array($timeToCheck, $timesRun)) {
							array_push($conflicts, $timeToCheck);
						} else {
							array_push($timesRun, $timeToCheck);
						}
					} else {
						array_push($skipped, $timeToCheck);
					}
				}
			}
		}
		return $conflicts;
	}

	public function getRichTextFileUrl($prefix, $pid, $edocId, $name)
	{
		self::requireNonEmptyValues(func_get_args());

		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$url = ExternalModules::getModuleAPIUrl() . "page=/manager/rich-text/get-file.php&file=$edocId.$extension&prefix=$prefix&pid=$pid";

		return $url;
	}

	private function requireNonEmptyValues($a){
		foreach($a as $key=>$value){
			if(empty($value)){
				throw new Exception("The array value for key '$key' was unexpectedly empty!");
			}
		}
	}

	public function haveUnsafeEDocReferencesBeenChecked()
	{
		$fieldName = 'external_modules_unsafe_edoc_references_checked';
		if(isset($GLOBALS[$fieldName])){
			return true;
		}

		if(empty(ExternalModules::getUnsafeEDocReferences())){
			self::query("insert into redcap_config values ('$fieldName', 1)");
			return true;
		}

		return false;
	}

	public function getUnsafeEDocReferences()
	{
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
			if(empty($edocId)){
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

		$result = ExternalModules::query("select * from redcap_edocs_metadata where " . ExternalModules::getSQLInClause('doc_id', array_keys($edocs)));
		$sourceProjectsByEdocId = [];
		while($row = db_fetch_assoc($result)){
			$sourceProjectsByEdocId[$row['doc_id']] = $row['project_id'];
		}

		$unsafeReferences = [];
		ksort($edocs);
		foreach($edocs as $edocId=>$references){
			foreach($references as $reference){
				$sourcePid = $sourceProjectsByEdocId[$edocId];
				$referencePid = $reference['pid'];
				if($referencePid === $sourcePid){
					continue;
				}

				$reference['edocId'] = $edocId;
				$reference['sourcePid'] = $sourcePid;
				$unsafeReferences[$referencePid][] = $reference;
			}
		}

		return $unsafeReferences;
	}

	public static function removeModifiedCrons($modulePrefix) {
		$module = self::getModuleInstance($modulePrefix);
		if ($module) {
			if ($module->getModifiedCrons($modulePrefix)) {
				$module->removeSystemSetting(self::$RESERVED_CRON_MODIFICATION_NAME);
			}
		} else {
			throw new \Exception("Could not instantiate module '$modulePrefix'!");
		}
	}

	public static function getModifiedCrons($modulePrefix) {
		$module = self::getModuleInstance($modulePrefix);
		if ($module) {
			return $module->getSystemSetting(self::$RESERVED_CRON_MODIFICATION_NAME);
		} else {
			throw new \Exception("Could not instantiate module '$modulePrefix'!");
		}
		return array();
	}

	# overwrites previously saved version
	public static function setModifiedCrons($modulePrefix, $cronSchedule) {
		$module = self::getModuleInstance($modulePrefix);
		if ($module) {
			foreach ($cronSchedule as $cronAttr) {
				if (!ExternalModules::isValidCron($cronAttr)) {
					throw new \Exception("The following cron is not valid! ".json_encode($cronAttr));
				}
			}
			$module->setSystemSetting(self::$RESERVED_CRON_MODIFICATION_NAME, $cronSchedule);
		} else {
			throw new \Exception("Could not instantiate module '$modulePrefix'!");
		}
	}

	public static function getCronSchedules($modulePrefix) {
		$module = self::getModuleInstance($modulePrefix);
		if ($module) {
			$modifications = $module->getModifiedCrons();
			$config = $module->getConfig();
			$finalVersion = array();
			if (isset($config['crons'])) {
				foreach ($config['crons'] as $cronAttr) {
					$finalVersion[$cronAttr['name']] = $cronAttr;
				}
			}
			if ($modifications) {
				foreach ($modifications as $cronAttr) {
					# overwrite config's if modifications exist
					$finalVersion[$cronAttr['name']] = $cronAttr;
				}
			}
			return array_values($finalVersion);
		} else {
			throw new \Exception("Could not instantiate module '$modulePrefix'!");
		}
		return array();
	}
}
