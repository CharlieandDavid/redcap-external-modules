<?php
namespace ExternalModules\FrameworkVersion3;

require_once __DIR__ . '/../v2/Framework.php';

use Exception;
use ExternalModules\ExternalModules;
use SplFileInfo;

class Framework extends \ExternalModules\FrameworkVersion2\Framework
{
	function createQuery(){
		return ExternalModules::createQuery();
	}

	function getEventId($projectId = null){
		$eventId = @$_GET['event_id'];
		if($eventId){
			return $eventId;
		}

        if(!$projectId){
            $projectId = $this->module->getProjectId();
		}
		
		$sql = '
			select event_id
			from redcap_events_arms a
			join redcap_events_metadata m
				on m.arm_id = a.arm_id
			where project_id = ?
		';

		$result = $this->query($sql, $projectId);
		$row = $result->fetch_assoc();

		if($result->fetch_assoc()){
			throw new Exception("Multiple event IDs found from project $projectId");
		}

		return $row['event_id'];
    }

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

		if(!file_exists($root)){
			//= The specified root ({0}) does not exist as either an absolute path or a relative path to the module directory.
			throw new Exception(ExternalModules::tt("em_errors_103", $root));
		}

		$root = realpath($root);

		$fullPath = "$root/$path";

		if(file_exists($fullPath)){
			$fullPath = realpath($fullPath);
		}
		else{
			// Also support the case where this is a path to a new file that doesn't exist yet and check it's parents.
			$dirname = dirname($fullPath);
				
			if(!file_exists($dirname)){
				//= The parent directory ({0}) does not exist.  Please create it before calling getSafePath() since the realpath() function only works on directories that exist.
				throw new Exception(ExternalModules::tt("em_errors_104", $dirname));
			}

			$fullPath = realpath($dirname) . DIRECTORY_SEPARATOR . basename($fullPath);
		}

		if(strpos($fullPath, $root) !== 0){
			//= You referenced a path ({0}) that is outside of your allowed parent directory ({1}).
			throw new Exception(ExternalModules::tt("em_errors_105", $fullPath, $root));
		}

		return $fullPath;
	}
}