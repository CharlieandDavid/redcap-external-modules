<?php
namespace ExternalModules\FrameworkVersion3;

require_once __DIR__ . '/../v2/Framework.php';

use Exception;
use ExternalModules\ExternalModules;
use SplFileInfo;

class Framework extends \ExternalModules\FrameworkVersion2\Framework
{
	function getRepeatingForms($eventId = null, $projectId = null){
		if($eventId === null){
			$eventId = $this->getEventId($projectId);
		}

        $result = $this->query('select * from redcap_events_repeat where event_id = ?', $eventId);

        $forms = [];
        while($row = $result->fetch_assoc()){
            $forms[] = $row['form_name'];
        }

        return $forms;
    }

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

	function convertIntsToStrings($row){
		return ExternalModules::convertIntsToStrings($row);
	}
}