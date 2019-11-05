<?php
namespace ExternalModules\FrameworkVersion2;

require_once __DIR__ . '/Project.php';
require_once __DIR__ . '/Records.php';
require_once __DIR__ . '/User.php';

use Exception;
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class Framework
{
	/**
	 * Constructor
	 * @param AbstractExternalModule $module The module for which the framework is initialized.
	 */
	function __construct($module){
		if(!($module instanceof AbstractExternalModule)){
			throw new Exception(ExternalModules::tt("em_errors_70")); //= Initializing the framework requires a module instance.
		}

		$this->module = $module;

		// Initialize language support (parse language files etc.).
		ExternalModules::initializeLocalizationSupport($module->PREFIX, $module->VERSION);

		$this->records = new Records($module);
	}

	//region Language features

	/**
	 * Returns the translation for the given language file key.
	 * 
	 * @param string $key The language file key.
	 * 
	 * Note: Any further arguments are used for interpolation. When the first additional parameter is an array, it's members will be used and any further parameters ignored. 
	 * 
	 * @return string The translation (with interpolations).
	 */
	public function tt($key) {
		// Get all arguments and send off for processing.
		return ExternalModules::tt_process(func_get_args(), $this->module->PREFIX, false);
	}

	/**
	 * Transfers one (interpolated) or many strings (without interpolation) to the module's JavaScript object.
	 * 
	 * @param mixed $key (optional) The language key or an array of language keys.
	 * 
	 * Note: When a single language key is given, any number of arguments can be supplied and these will be used for interpolation. When an array of keys is passed, then any further arguments will be ignored and the language strings will be transfered without interpolation. If no key or null is passed, all language strings will be transferred.
	 */
	public function tt_transferToJavascriptModuleObject($key = null) {
		// Get all arguments and send off for processing.
		ExternalModules::tt_prepareTransfer(func_get_args(), $this->module->PREFIX);
	}

	/**
	 * Adds a key/value pair directly to the language store for use in the JavaScript module object. 
	 * Value can be anything (string, boolean, array).
	 * 
	 * @param string $key The language key.
	 * @param mixed $value The corresponding value.
	 */
	public function tt_addToJavascriptModuleObject($key, $value) {
		ExternalModules::tt_addToJSLanguageStore($key, $value, $this->module->PREFIX, $key);
	}

	//endregion

	function getProjectsWithModuleEnabled(){
		$results = $this->query("
			select project_id
			from redcap_external_modules m
			join redcap_external_module_settings s
				on m.external_module_id = s.external_module_id
			where
				m.directory_prefix = '" . $this->module->PREFIX . "'
				and s.value = 'true'
				and s.`key` = 'enabled'
		");

		$pids = [];
		while($row = $results->fetch_assoc()) {
			$pids[] = $row['project_id'];
		}

		return $pids;
	}

	function __call($name, $arguments){
		return call_user_func_array([$this->module, $name], $arguments);
	}

	function getSubSettings($key, $pid = null)
	{
		$settingsAsArray = ExternalModules::getProjectSettingsAsArray($this->module->PREFIX, $this->requireProjectId($pid));

		$settingConfig = $this->getSettingConfig($key);

		return $this->getSubSettings_internal($settingsAsArray, $settingConfig);
	}

	private function getSubSettings_internal($settingsAsArray, $settingConfig)
	{
		$subSettings = [];
		foreach($settingConfig['sub_settings'] as $subSettingConfig){
			$subSettingKey = $subSettingConfig['key'];

			if($subSettingConfig['type'] === 'sub_settings'){
				// Handle nested sub_settings recursively
				$values = $this->getSubSettings_internal($settingsAsArray, $subSettingConfig);

				$recursionCheck = function($value){
					// We already know the value must be an array.
					// Recurse until we're two levels away from the leaves, then wrap in $subSettingKey.
					// If index '0' is not defined, we know it's a leaf since only setting key names will be used as array keys (not numeric indexes).
					return isset($value[0][0]);
				};
			}
			else{
				$values = $settingsAsArray[$this->prefixSettingKey($subSettingKey)]['value'];
				if($values === null){
					continue;
				}

				$recursionCheck = function($value) use ($subSettingConfig){
					// Only recurse if this is an array, and not a leaf.
					// If index '0' is not defined, we know it's a leaf since only setting key names will be used as array keys (not numeric indexes).
					// Using array_key_exists() instead of isset() is important since there could be a null value set.
					return !@$subSettingConfig['repeatable'] && is_array($value) && array_key_exists(0, $value);
				};
			}

			$formatValues = function($values) use ($subSettingConfig, $subSettingKey, $recursionCheck, &$formatValues){
				for($i=0; $i<count($values); $i++){
					$value = $values[$i];
					
					if($recursionCheck($value)){
						$values[$i] = $formatValues($value);
					}
					else{
						$values[$i] = [
							$subSettingKey => $value
						];
					}
				}

				return $values;
			};

			$values = $formatValues($values);

			$subSettings = ExternalModules::array_merge_recursive_distinct($subSettings, $values);
		}

		return $subSettings;
	}

	function getSQLInClause($columnName, $values){
		return ExternalModules::getSQLInClause($columnName, $values);
	}

	function getUser($username = null){
		if(empty($username)){
			if(!defined('USERID')){
				throw new Exception(ExternalModules::tt("em_errors_71")); //= A username was not specified and could not be automatically detected.
			}

			$username = USERID;
		}

		return new User($this, $username);
	}

	function getProject($project_id = null){
		$project_id = $this->requireProjectId($project_id);
		return new Project($this, $project_id);
	}

	function requireInteger($mixed){
		return ExternalModules::requireInteger($mixed);
	}

	function getJavascriptModuleObjectName(){
		return ExternalModules::getJavascriptModuleObjectName($this->module);
	}

	function isRoute($routeName){
		return ExternalModules::isRoute($routeName);
	}
}