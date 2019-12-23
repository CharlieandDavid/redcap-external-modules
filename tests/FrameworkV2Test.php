<?php
namespace ExternalModules;

class FrameworkV2Test extends FrameworkBaseTest
{
	function testGetSubSettings_complexNesting()
	{
		$m = $this->getInstance();
		$_GET['pid'] = 1;

		// This json file can be copied into a module for hands on testing/modification via the settings dialog.
		$this->setConfig(json_decode(file_get_contents(__DIR__ . '/complex-nested-settings.json'), true));

		// These values were copied directly from the database after saving them through the settings dialog (as configured by the json file above).
		$m->setProjectSetting('countries', ["true","true"]);
		$m->setProjectSetting('country-name', ["USA","Canada"]);
		$m->setProjectSetting('states', [["true","true"],["true"]]);
		$m->setProjectSetting('state-name', [["Tennessee","Alabama"],["Ontario"]]);
		$m->setProjectSetting('cities', [[["true","true"],["true"]],[["true"]]]);
		$m->setProjectSetting('city-name', [[["Nashville","Franklin"],["Huntsville"]],[["Toronto"]]]);
		$m->setProjectSetting('city-size', [[["large","small"],["medium"]],[[null]]]); // The null is an important scenario to test here, as it can change output behavior.

		$expectedCountries = [
			[
				"states" => [
					[
						"state-name" => "Tennessee",
						"cities" => [
							[
								"city-name" => "Nashville",
								"city-size" => "large"
							],
							[
								"city-name" => "Franklin",
								"city-size" => "small"
							]
						]
					],
					[
						"state-name" => "Alabama",
						"cities" => [
							[
								"city-name" => "Huntsville",
								"city-size" => "medium"
							]
						]
					]
				],
				"country-name" => "USA"
			],
			[
				"states" => [
					[
						"state-name" => "Ontario",
						"cities" => [
							[
								"city-name" => "Toronto",
								"city-size" => null
							]
						]
					]
				],
				"country-name" => "Canada"
			]
		];

		$this->assertEquals($expectedCountries, $this->framework->getSubSettings('countries'));
	}

	function testGetSubSettings_plainOldRepeatableInsideSubSettings(){
		$m = $this->getInstance();
		$_GET['pid'] = 1;

		$this->setConfig('
			{
				"project-settings": [
					{
						"key": "one",
						"name": "one",
						"type": "sub_settings",
						"repeatable": true,
						"sub_settings": [
							{
								"key": "two",
								"name": "two",
								"type": "text",
								"repeatable": true
							}
						]
					}
				]
			}
		');

		$m->setProjectSetting('one', ["true"]);
		$m->setProjectSetting('two', [["value"]]);

		$this->assertEquals(
			[
				[
					'two' => [
						'value'
					]
				]
			],
			$this->framework->getSubSettings('one')
		);
	}

	function testGetProjectsWithModuleEnabled(){
		$assert = function($enableValue, $expectedPids){
			$m = $this->getInstance();
			$m->setProjectSetting(ExternalModules::KEY_ENABLED, $enableValue, TEST_SETTING_PID);
			$pids = $this->framework->getProjectsWithModuleEnabled();
			$this->assertSame($expectedPids, $pids);
		};

		$assert(true, [TEST_SETTING_PID]);
		$assert(false, []);
	}

	function testProject_getUsers(){
		$result = $this->framework->query("
			select user_email
			from redcap_user_rights r
			join redcap_user_information i
				on r.username = i.username
			where project_id = ?
			order by r.username
		", TEST_SETTING_PID);

		$actualUsers = $this->framework->getProject(TEST_SETTING_PID)->getUsers();

		$i = 0;
		while($row = $result->fetch_assoc()){
			$this->assertSame($row['user_email'], $actualUsers[$i]->getEmail());
			$i++;
		}
	}

	function testRecords_lock(){
		$_GET['pid'] = TEST_SETTING_PID;
		$recordIds = [1, 2];
		$records = $this->framework->records;
		
		$records->lock($recordIds);
		foreach($recordIds as $recordId){
			$this->assertTrue($records->isLocked($recordId));
		}

		$records->unlock($recordIds);
		foreach($recordIds as $recordId){
			$this->assertFalse($records->isLocked($recordId));
		}
	}

	function testUser_isSuperUser(){
		$result = ExternalModules::query('select username from redcap_user_information where super_user = 1 limit 1');
		$row = $result->fetch_assoc();
		$username = $row['username'];
		
		$user = $this->framework->getUser($username);
		$this->assertTrue($user->isSuperUser());
	}
}