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
	function __construct($module){
		if(!($module instanceof AbstractExternalModule)){
			throw new Exception("Initializing the framework requires a module instance.");
		}

		$this->module = $module;

		$this->records = new Records($module);
	}

	//region Language features

	/**
	 * Returns the translation for the given language file key.
	 * 
	 * @param string $key The language file key.
	 * @param mixed $values The values to be used for interpolation. If the first parameter is a (sequential) array, it's members will be used and any further parameters ignored.
	 * 
	 * @return string The translation (with interpolations).
	 */
	public function tt($key, ...$values) {
		
		global $lang;

		// Get the full key for $lang.
		$lang_key = ExternalModules::constructLanguageKey($this->module->PREFIX, $key);
		// Now get the corresponding text.
		$text = $lang[$lang_key];

		// Do we need to do interpolation?
		if (count($values)) {
			// Use array if supplied. 
			if (is_array($values[0])) $values = $values[0];
			// Regular expression to find places where replacements need to be done.
			// Placeholers are in curly braces, e.g. {0}. Optionally, a type hint can be present after a colon (e.g. {0:Date}) which is ignored however.
			// To not replace a placeholder, the first curly can be escaped with a backslash like so: '\{1}' (this will leave '{1}' in the text).
			// When the an even number of backslashes is before the curly, e.g. '\\{0}' with value x this will result in '\x'.
			// Placeholder names can be strings (a-Z0-9_), too (need associative array then). 
			$re = '/(?\'all\'((?\'escape\'\\\\*){|{)(?\'index\'[\d_A-Za-z]+)(:(?\'hint\'.*))?})/mU';
			preg_match_all($re, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, 0);
			// Build resulting string.
			$prevEnd = 0;
			if (count($matches)) {
				$result = "";
				foreach ($matches as $match) {
					$start = $match["all"][1];
					$all = $match["all"][0];
					$len = strlen($all);
					$key = $match["index"][0];
					// Add text between previous end and the match and reset end.
					$result .= substr($text, $prevEnd, $start - $prevEnd);
					$prevEnd = $start + $len;
					// Escaped?
					$nSlashes = strlen($match["escape"][0]);
					if ($nSlashes % 2 == 0) {
						// Even number means they escaped themselves, so we add half of them and replace.
						$result .= str_repeat("\\", $nSlashes / 2);
						if (array_key_exists($key, $values)) {
							$result .= $values[$key];
						}
						else {
							// When the key doesn't exist, just leave it unchanged (but remove the backslashes).
							$result .= ltrim($all, "\\");
						}
					}
					else {
						// Uneven number - means to not replace.
						$result .= str_repeat("\\", ($nSlashes - 1) / 2);
						$result .= ltrim($all, "\\");
					}
				}
				// Add rest of original.
				$result .= substr($text, $prevEnd);
				$text = $result;
			}
		}
		return $text;
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
				throw new Exception('A username was not specified and could not be automatically detected.');
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