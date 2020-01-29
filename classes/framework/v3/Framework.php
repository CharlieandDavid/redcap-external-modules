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

	function isPage($path){
        $path = APP_PATH_WEBROOT . $path;
        return strpos($_SERVER['REQUEST_URI'], $path) === 0;
    }

    function createProject($title, $purpose, $project_note=null){
        $userInfo = \User::getUserInfo(USERID);
        if (!$userInfo['allow_create_db']) throw new Exception("ERROR: You do not have Create Project privileges!");

        if ($title == "" || $title == null) throw new Exception("ERROR: Title can't be null or blank!");
        $title = \Project::cleanTitle($title);
        $new_app_name = \Project::getValidProjectName($title);

        $userid = USERID;

        if(!is_numeric($purpose) || $purpose < 0 || $purpose > 4) throw new Exception("ERROR: The purpose has to be numeric and it's value between 0 and 4.");

        $auto_inc_set = 1;

        $GLOBALS['__SALT__'] = substr(sha1(rand()), 0, 10);

        $user_id_result = ExternalModules::query("select ui_id from redcap_user_information where username = ? limit 1",[$userid]);
        $ui_id = db_fetch_assoc($user_id_result)['ui_id'];

        ExternalModules::query("insert into redcap_projects (project_name, purpose, app_title, creation_time, created_by, auto_inc_set, project_note,auth_meth,__SALT__) values(?,?,?,?,?,?,?,?,?)",
            [$new_app_name,$purpose,db_escape($title),NOW,$ui_id,$auto_inc_set,trim($project_note),'none',$GLOBALS['__SALT__']]);

        // Get this new project's project_id
        $pid = db_insert_id();

        // Insert project defaults into redcap_projects
        \Project::setDefaults($pid);

        $logDescrip = "Create project";
        \Logging::logEvent("","redcap_projects","MANAGE",$pid,"project_id = $pid",$logDescrip);

        // Give this new project an arm and an event (default)
        \Project::insertDefaultArmAndEvent($pid);
        // Now add the new project's metadata
        $form_names = createMetadata($pid, 0);
        ## USER RIGHTS
        // Insert user rights for this new project for user REQUESTING the project
        \Project::insertUserRightsProjectCreator($pid, $userid, 0, 0, $form_names);

        return $pid;
    }
}