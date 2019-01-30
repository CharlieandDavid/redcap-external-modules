<?php
namespace ExternalModules\FrameworkVersion2;

require_once __DIR__ . '/Records.php';

use Exception;
use ExternalModules\AbstractExternalModule;

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
}