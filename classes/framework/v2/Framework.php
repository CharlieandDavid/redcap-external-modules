<?php
namespace ExternalModules\FrameworkVersion2;

require_once __DIR__ . '/Records.php';
require_once __DIR__ . '/User.php';

use Exception;
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class Framework
{
	function __construct($module){
		if(!($module instanceof AbstractExternalModule)){
			throw new Exception("Initializing the framework requires a module instance.");
		}

		$this->module = $module;

		$this->records = new Records($module);
	}

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
		$module = $this->module;

		if(method_exists($module, $name)){
			return call_user_func_array([$module, $name], $arguments);
		}

		throw new Exception("The following method does not exist: $name()");
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

				$recursionCheck = function($value){
					// Only recurse if this is an array, and not a leaf.
					// If index '0' is not defined, we know it's a leaf since only setting key names will be used as array keys (not numeric indexes).
					// Using array_key_exists() instead of isset() is important since there could be a null value set.
					return is_array($value) && array_key_exists(0, $value);
				};
			}

			$formatValues = function($values) use ($subSettingKey, $recursionCheck, &$formatValues){
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
				throw new Exception('A username was not specified and could not be automatically detected.');
			}

			$username = USERID;
		}

		return new User($this, $username);
	}
}