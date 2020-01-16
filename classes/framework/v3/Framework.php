<?php
namespace ExternalModules\FrameworkVersion3;

require_once __DIR__ . '/../v2/Framework.php';

use Exception;
use ExternalModules\ExternalModules;
use SplFileInfo;

class Framework extends \ExternalModules\FrameworkVersion2\Framework
{
	function getRecordIdField($pid = null){
		$pid = db_escape($this->requireProjectId($pid));

		$result = $this->query("
			select field_name
			from redcap_metadata
			where project_id = $pid
			order by field_order
			limit 1
		");

		$row = $result->fetch_assoc();

		return $row['field_name'];
	}

	function getSafePath($path, $root=null){
		$moduleDirectory = $this->module->getModulePath();
		if(!$root){
			$root = $moduleDirectory;
		}
		else if(!file_exists($root)){
			$root = "$moduleDirectory/$root";
		}

		return ExternalModules::getSafePath($path, $root);
	}
}