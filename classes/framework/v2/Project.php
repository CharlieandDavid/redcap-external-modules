<?php
namespace ExternalModules\FrameworkVersion2;

class Project
{
	function __construct($framework, $project_id){
		$this->framework = $framework;
		$this->project_id = $framework->requireInteger($project_id);
	}

	function getUsers(){
		$results = $this->framework->query("
			select username
			from redcap_user_rights
			where project_id = {$this->project_id}
			order by username
		");

		$users = [];
		while($row = $results->fetch_assoc()){
			$users[] = new User($this->framework, $row['username']);
		}

		return $users;
	}
}