<?php
namespace ExternalModules;

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
			where project_id = ?
			order by username
		", $this->project_id);

		$users = [];
		while($row = $results->fetch_assoc()){
			$users[] = new User($this->framework, $row['username']);
		}

		return $users;
	}

	function getProjectId() {
		return $this->project_id;
	}
}