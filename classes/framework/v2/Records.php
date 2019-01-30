<?php
namespace ExternalModules\FrameworkVersion2;

class Records
{
	function __construct($module){
		$this->module = $module;
	}

	function lock($recordIds){
		$recordIdSql = '';
		foreach($recordIds as $recordId){
			if(!empty($recordIdSql)){
				$recordIdSql .= ',';
			}

			$recordId = db_real_escape_string($recordId);
			$recordIdSql .= "'$recordId'";
		}

		$pid = $this->module->getProjectId();

		$results = $this->module->query("
			select
				record,
				event_id,
				instance,
				form_name
			from redcap_data d
			join redcap_metadata m
				on
					d.project_id = m.project_id 
					and d.field_name = m.field_name
			where
				d.project_id = $pid
				and record in ($recordIdSql)
			group by record, event_id, instance, form_name
		");

		$lockValuesSql = '';
		while($row = $results->fetch_assoc()){
			if(!empty($lockValuesSql)){
				$lockValuesSql .= ",\n";
			}

			$record = $row['record'];
			$eventId = $row['event_id'];
			$formName = $row['form_name'];
			$instance = $row['instance'];

			if($instance === null){
				$instance = 1;
			}

			$lockValuesSql .= "($pid, '$record', $eventId, '$formName', $instance , now())";
		}

		$this->module->query("
			insert ignore into redcap_locking_data (project_id, record, event_id, form_name, instance, timestamp)
			values $lockValuesSql
		");
	}
}