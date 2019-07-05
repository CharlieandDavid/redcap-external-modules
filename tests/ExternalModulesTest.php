<?php
namespace ExternalModules;
require_once 'BaseTest.php';

use \Exception;

class ExternalModulesTest extends BaseTest
{
	function testInitializeSettingDefaults()
	{
		$defaultValue = rand();

		$this->setConfig([
			'system-settings' => [
				[
					'key' => TEST_SETTING_KEY,
					'default' => $defaultValue
				]
			]
		]);

		$m = $this->getInstance();

		$this->assertNull($this->getSystemSetting());
		ExternalModules::initializeSettingDefaults($m);
		$this->assertSame($defaultValue, $this->getSystemSetting());

		// Make sure defaults do NOT overwrite any existing settings.
		$this->setSystemSetting(rand());
		ExternalModules::initializeSettingDefaults($m);
		$this->assertNotEquals($defaultValue, $this->getSystemSetting());
	}

	function testGetProjectSettingsAsArray_systemOnly()
	{
		$value = rand();
		$this->setSystemSetting($value);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($value, $array[TEST_SETTING_KEY]['value']);
		$this->assertSame($value, $array[TEST_SETTING_KEY]['system_value']);
	}

	function testGetProjectSettingsAsArray_projectOnly()
	{
		$value = rand();
		$this->setProjectSetting($value);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($value, $array[TEST_SETTING_KEY]['value']);
		$this->assertSame(null, @$array[TEST_SETTING_KEY]['system_value']);
	}

	function testGetProjectSettingsAsArray_both()
	{
		$systemValue = rand();
		$projectValue = rand();

		$this->setSystemSetting($systemValue);
		$this->setProjectSetting($projectValue);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($projectValue, $array[TEST_SETTING_KEY]['value']);
		$this->assertSame($systemValue, $array[TEST_SETTING_KEY]['system_value']);

		// Re-test reversing the insert order to make sure it doesn't matter.
		$this->setProjectSetting($projectValue);
		$this->setSystemSetting($systemValue);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($projectValue, $array[TEST_SETTING_KEY]['value']);
		$this->assertSame($systemValue, $array[TEST_SETTING_KEY]['system_value']);
	}

	function testGetSystemSettingsAsArray_noPrefix()
	{
		$this->assertThrowsException(function(){
			ExternalModules::getSystemSettingsAsArray(null);
		}, 'One or more module prefixes must be specified!');
	}

	function testIsTimeToRun()
	{
		$method = 'isTimeToRun';

		$offsets = array(
				0 => "assertTrue",
				3600 => "assertFalse",
				-3600 => "assertFalse",
				60 => "assertFalse",
				-60 => "assertFalse",
				24 * 3600 => "assertTrue",
				-24 * 3600 => "assertTrue",
				);
		$defaultCron = array(
					'cron_name' => 'test_name',
					'cron_description' => 'This is a test',
					'method' => 'test_method',
					);

		foreach ($offsets as $offset => $validationMethod) {
			$time = time() + $offset;
			$cron = array(
                			'cron_hour' => date("G", $time),
                			'cron_minute' => date("i", $time),
					);
			$this->$validationMethod(self::callPrivateMethod($method, array_merge($defaultCron, $cron)));
		}

		$time2 = time() + 24 * 3600;
		$cron2 = array(
				'cron_hour' => date("G", $time2),
				'cron_minute' => date("i", $time2),
				'cron_weekday' => date("w", $time2),
				);
		echo json_encode($cron2)."\n";
		$this->assertFalse(self::callPrivateMethod($method, array_merge($defaultCron, $cron2)));

		$time3 = time() + 7 * 24 * 3600;
		$cron3 = array(
				'cron_hour' => date("G", $time3),
				'cron_minute' => date("i", $time3),
				'cron_weekday' => date("w", $time3),
				);
		echo json_encode($cron3)."\n";
		$this->assertTrue(self::callPrivateMethod($method, array_merge($defaultCron, $cron3)));

		$cron3_2 = array(
				'cron_hour' => date("G", $time3),
				'cron_minute' => date("i", $time3),
				'cron_monthday' => date("j", $time3),
				);
		echo json_encode($cron3_2)."\n";
		$this->assertFalse(self::callPrivateMethod($method, array_merge($defaultCron, $cron3_2)));
	}

	function testRunInLastMinute()
	{
		$method = 'runInLastMinute';

		$this->assertTrue(self::callPrivateMethod($method, array(time())));
		$this->assertTrue(!self::callPrivateMethod($method, array(time() - 3600)));
	}

	function testAddReservedSettings()
	{
		$method = 'addReservedSettings';

		$this->assertThrowsException(function() use ($method){
			self::callPrivateMethod($method, array(
				'system-settings' => array(
					array('key' => ExternalModules::KEY_VERSION)
				)
			));
		}, 'reserved for internal use');

		$this->assertThrowsException(function() use ($method){
			self::callPrivateMethod($method, array(
				'project-settings' => array(
					array('key' => ExternalModules::KEY_ENABLED)
				)
			));
		}, 'reserved for internal use');

		// Make sure other settings are passed through without exception.
		$key = 'some-non-reserved-settings';
		$config = self::callPrivateMethod($method, array(
			'system-settings' => array(
				array('key' => $key)
			)
		));

		$systemSettings = $config['system-settings'];
		$this->assertSame(3, count($systemSettings));
		$this->assertSame(ExternalModules::KEY_ENABLED, $systemSettings[0]['key']);
		$this->assertSame(ExternalModules::KEY_DISCOVERABLE, $systemSettings[1]['key']);
		$this->assertSame($key, $systemSettings[2]['key']);
	}

	function testCacheAllEnableData()
	{
		$m = $this->getInstance();

		$version = rand();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, $version);

		self::callPrivateMethod('cacheAllEnableData');
		$this->assertSame($version, self::callPrivateMethod('getSystemwideEnabledVersions')[TEST_MODULE_PREFIX]);

		$m->removeSystemSetting(ExternalModules::KEY_VERSION);

		// the other values set by cacheAllEnableData() are tested via testGetEnabledModuleVersionsForProject()
	}

	function testOverwriteBlankSetting()
	{
		$m = $this->getInstance();

		$str = 'abc';
		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, '');
		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $str);

		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($str, $array[TEST_SETTING_KEY]['value']);
	}

	function testGetEnabledModules()
	{
		$this->cacheAllEnableData();
		$versionsByPrefix = ExternalModules::getEnabledModules();
		$this->assertNull(@$versionsByPrefix[TEST_MODULE_PREFIX]);
		$versionsByPrefix = ExternalModules::getEnabledModules(TEST_SETTING_PID);
		$this->assertNull(@$versionsByPrefix[TEST_MODULE_PREFIX]);

		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);

		$this->cacheAllEnableData();
		$versionsByPrefix = ExternalModules::getEnabledModules();
		$this->assertSame(TEST_MODULE_VERSION, $versionsByPrefix[TEST_MODULE_PREFIX]);
		$versionsByPrefix = ExternalModules::getEnabledModules(TEST_SETTING_PID);
		$this->assertNull(@$versionsByPrefix[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);

		$this->cacheAllEnableData();
		$versionsByPrefix = ExternalModules::getEnabledModules();
		$this->assertSame(TEST_MODULE_VERSION, $versionsByPrefix[TEST_MODULE_PREFIX]);
		$versionsByPrefix = ExternalModules::getEnabledModules(TEST_SETTING_PID);
		$this->assertSame(TEST_MODULE_VERSION, $versionsByPrefix[TEST_MODULE_PREFIX]);
	}

	function testGetEnabledModuleVersionsForProject_multiplePrefixesAndVersions()
	{
		$prefix1 = TEST_MODULE_PREFIX . '-1';
		$prefix2 = TEST_MODULE_PREFIX . '-2';

		ExternalModules::setSystemSetting($prefix1, ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		ExternalModules::setSystemSetting($prefix2, ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		ExternalModules::setSystemSetting($prefix1, ExternalModules::KEY_ENABLED, true);
		ExternalModules::setSystemSetting($prefix2, ExternalModules::KEY_ENABLED, true);

		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[$prefix1]);
		$this->assertNotNull($prefixes[$prefix2]);

		ExternalModules::removeSystemSetting($prefix2, ExternalModules::KEY_VERSION);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[$prefix1]);
		$this->assertNull(@$prefixes[$prefix2]);

		ExternalModules::removeSystemSetting($prefix1, ExternalModules::KEY_ENABLED);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull(@$prefixes[$prefix1]);

		ExternalModules::removeSystemSetting($prefix1, ExternalModules::KEY_VERSION);
		ExternalModules::removeSystemSetting($prefix2, ExternalModules::KEY_ENABLED);
	}

	function testGetEnabledModuleVersionsForProject_overrides()
	{
		$m = self::getInstance();

		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull(@$prefixes[TEST_MODULE_PREFIX]);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);


		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull(@$prefixes[TEST_MODULE_PREFIX]);

		$m->removeProjectSetting(ExternalModules::KEY_ENABLED, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, false);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull(@$prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull(@$prefixes[TEST_MODULE_PREFIX]);
	}

	function testGetFileSettings() {
		$m = self::getInstance();					

		$edocIdSystem = (string) rand();
		$edocIdProject = (string) rand();

                # system
		ExternalModules::setSystemFileSetting($this->getInstance()->PREFIX, FILE_SETTING_KEY, $edocIdSystem);

                # project
		ExternalModules::setFileSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, FILE_SETTING_KEY, $edocIdProject);

		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($edocIdProject, $array[FILE_SETTING_KEY]['value']);
		$this->assertSame($edocIdSystem, $array[FILE_SETTING_KEY]['system_value']);

		ExternalModules::removeProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, FILE_SETTING_KEY);
		ExternalModules::removeSystemSetting($this->getInstance()->PREFIX, FILE_SETTING_KEY);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);

		$this->assertNull(@$array[FILE_SETTING_KEY]['value']);
		$this->assertNull(@$array[FILE_SETTING_KEY]['system_value']);
	}

	function testGetLinks()
	{
		$controlCenterLinkName = "Test Control Center Link Name";
		$controlCenterLinkUrl = "some/control/center/url";
		$projectLinkName = "Test Project Link Name";
		$projectLinkUrl = "some/project/url";

		$this->setConfig([
			'links' => [
				'control-center' => [
					[
						'name'=>$controlCenterLinkName,
						'url'=>$controlCenterLinkUrl
					]
				],
				'project' => [
					[
						'name'=>$projectLinkName,
						'url'=>$projectLinkUrl
					]
				]
			]
		]);

		$links = $this->getLinks();
		$this->assertNull($links[$controlCenterLinkName]);
		$this->assertNull($links[$projectLinkName]);

		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);

		$assertUrl = function($pageExpected, $actual){
			$externalModulesClass = new \ReflectionClass("ExternalModules\\ExternalModules");
			$method = $externalModulesClass->getMethod('getUrl');
			$method->setAccessible(true);
			$expected = $method->invoke(null, TEST_MODULE_PREFIX, $pageExpected);

			$this->assertSame($expected, $actual);
		};

		$links = $this->getLinks();
		$assertUrl($controlCenterLinkUrl, $links[$controlCenterLinkName]['url']);
		$this->assertNull($links[$projectLinkName]);

		$_GET['pid'] = TEST_SETTING_PID;

		$links = $this->getLinks();
		$this->assertNull($links[$controlCenterLinkName]);
		$this->assertNull($links[$projectLinkName]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true);

		$links = $this->getLinks();
		$this->assertNull($links[$controlCenterLinkName]);
		$assertUrl($projectLinkUrl, $links[$projectLinkName]['url']);
	}

	function testCallHook_enabledStates()
	{
		$pid = TEST_SETTING_PID;
		$m = $this->getInstance();
		$hookName = 'redcap_test';
		$this->setConfig(['permissions' => [$hookName]]);

		$assertHookCalled = function($called, $pid = null) use ($hookName){
			$this->assertHookCalled($hookName, $called, $pid);
		};

		$assertHookCalled(false);

		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$assertHookCalled(true);

		$assertHookCalled(false, $pid);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$assertHookCalled(true, $pid);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, $pid);
		$assertHookCalled(false, $pid);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, false);
		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, $pid);
		$assertHookCalled(true, $pid);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, $pid);
		$assertHookCalled(false, $pid);
	}

	function testCallHook_delay()
	{
		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$this->cacheAllEnableData();

		$this->setConfig(['permissions' => ['hook_test_delay']]);

        $exceptionThrown = false;
        $throwException = function($message) use (&$exceptionThrown){
            $exceptionThrown = true;
            throw new Exception($message);
        };

        $hookExecutionsExpected = 3;
        $executionNumber = 0;
        $delayTestFunction = function($delaySuccessful) use (&$executionNumber, $hookExecutionsExpected, $throwException){
            $executionNumber++;

            if($executionNumber < $hookExecutionsExpected){
                if(!$delaySuccessful){
                    $throwException("The first hook run and the first attempt at re-running after delaying should both successfully delay.");
                }
            }
            else if($executionNumber == $hookExecutionsExpected){
                if($delaySuccessful){
                    $throwException("The final run that gives modules a last chance to run if they have been delaying should NOT successfully delay.");
                }
            }
        };

		ExternalModules::callHook('redcap_test_delay', [$delayTestFunction]);
        $this->assertFalse($exceptionThrown);
		$this->assertEquals($hookExecutionsExpected, $executionNumber);
	}

	function testCallHook_arguments()
	{
		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$this->cacheAllEnableData();

		$this->setConfig(['permissions' => ['hook_test']]);

		$argOne = 1;
		$argTwo = 'a';
		ExternalModules::callHook('redcap_test', [$argOne, $argTwo]);
		$this->assertSame($argOne, $m->testHookArguments[0]);
		$this->assertSame($argTwo, $m->testHookArguments[1]);
	}

	function testCallHook_permissions()
	{
		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);

		$hookName = 'redcap_test';
		$this->setConfig(['permissions' => [$hookName]]);
		$this->assertHookCalled($hookName, true);

		$this->setConfig([]);
		$this->assertHookCalled($hookName, false);

		$pid = TEST_SETTING_PID;
		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, $pid);

		$hookName = 'redcap_every_page_test';
		$config = ['permissions' => [$hookName]];
		$this->setConfig($config);
		$this->assertHookCalled($hookName, true, $pid);
		$this->assertHookCalled($hookName, false);

		$config['enable-every-page-hooks-on-system-pages'] = true;
		$this->setConfig($config);
		$this->assertHookCalled($hookName, true);
	}

	function testCallHook_setRecordId()
	{
		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$this->cacheAllEnableData();

		$hookName = 'redcap_save_record';
		$this->setConfig(['permissions' => [$hookName]]);

		$projectId = 1;
		$recordId = rand();
		ExternalModules::callHook($hookName, [$projectId, $recordId]);
		$this->assertSame($recordId, $m->recordIdFromGetRecordId);

		// The record id should be set back to null once the hook finishes.
		$this->assertNull($m->getRecordId());
	}

	private function assertHookCalled($hookName, $called, $pid = null)
	{
		$arguments = [];
		if($pid){
			$arguments[] = $pid;
		}

		$this->cacheAllEnableData();
		$m = $this->getInstance();

		$m->testHookArguments = null;
		ExternalModules::callHook($hookName, $arguments);
		if($called){
			$this->assertNotNull($m->testHookArguments, 'The hook was expected to run but did not.');
		}
		else{
			$this->assertNull($m->testHookArguments, 'The hook was not expected to run but did.');
		}
	}

	private function getLinks()
	{
		self::callPrivateMethod('cacheAllEnableData');
		return ExternalModules::getLinks();
	}

	// Calling this will effectively clear/reset the cache.
	private function cacheAllEnableData()
	{
		self::callPrivateMethod('cacheAllEnableData');
	}

	private function getEnabledModuleVersionsForProjectIgnoreCache()
	{
		$this->cacheAllEnableData();
		return self::callPrivateMethod('getEnabledModuleVersionsForProject', TEST_SETTING_PID);
	}

	protected function getReflectionClass()
	{
		return 'ExternalModules\ExternalModules';
	}

	function testSaveSettings()
	{
		$settings = [];
		$settings[TEST_SETTING_KEY] = rand();

		$repeatableSettingKey = 'test-repeatable';
		$repeatableExpected = [rand(), 'some string', 1.23];

		for($i = 0; $i<count($repeatableExpected); $i++){
			$settings[$repeatableSettingKey . '____' . $i] = $repeatableExpected[$i];
		}

		ExternalModules::saveSettings(TEST_MODULE_PREFIX, TEST_SETTING_PID, $settings);

		$this->assertSame($settings[TEST_SETTING_KEY], ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY));
		$this->assertSame($repeatableExpected, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $repeatableSettingKey));

		// cleanup
		ExternalModules::removeProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $repeatableSettingKey);
	}

	function testInstance()
	{
		$value1 = rand();
		$value2 = rand();
		$value3 = rand();
		$value4 = rand();
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 0, $value1);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertNotNull(json_encode($array));
		$this->assertSame($value1, $array[TEST_SETTING_KEY]['value']);

		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $value1);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 1, $value2);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 2, $value3);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 3, $value4);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertNotNull(json_encode($array));
		$this->assertSame($value1, $array[TEST_SETTING_KEY]['value'][0]);
		$this->assertSame($value2, $array[TEST_SETTING_KEY]['value'][1]);
		$this->assertSame($value3, $array[TEST_SETTING_KEY]['value'][2]);
		$this->assertSame($value4, $array[TEST_SETTING_KEY]['value'][3]);

		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $value1);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 1, $value2);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 2, $value3);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 3, $value4);
		$array = ExternalModules::getProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID,  TEST_SETTING_KEY);
		$this->assertNotNull(json_encode($array));
		$this->assertSame($value1, $array[0]);
		$this->assertSame($value2, $array[1]);
		$this->assertSame($value3, $array[2]);
		$this->assertSame($value4, $array[3]);

		ExternalModules::removeProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY);
	}

	function testIsLocalhost()
	{
		$assertLocalhost = function($expected, $host){
			$_SERVER['HTTP_HOST'] = $host;
			$this->assertSame($expected, $this->callPrivateMethod('isLocalhost'));
		};

		$assertLocalhost(true, 'localhost');
		$assertLocalhost(false, 'redcap.vanderbilt.edu');
		$assertLocalhost(false, 'redcap.somewhere-else.edu');

		$GLOBALS['is_development_server'] = true;
		$assertLocalhost(true, 'redcap.somewhere-else.edu');
	}

	function testGetAdminEmailMessage()
	{
		global $project_contact_email;

		$assertToEquals = function ($expectedTo, $expectedModuleEmail = null) {
			if ($expectedModuleEmail) {
				$this->setConfig([
					'authors' => [
						[
							'email' => $expectedModuleEmail
						],
						[
							'email' => 'someone@somewhere.edu' // we assert that this email is NOT included, because the domain doesn't match.
						]
					]
				]);
			}

			$expectedTo = implode(',', $expectedTo);

			$message = $this->callPrivateMethod('getAdminEmailMessage', '', '', TEST_MODULE_PREFIX);
			$this->assertEquals($expectedTo, $message->getTo());
		};

		$this->setPrivateVariable('SERVER_NAME', 'redcaptest.vanderbilt.edu');
		$assertToEquals(['mark.mcever@vanderbilt.edu', 'kyle.mcguffin@vanderbilt.edu']);

		$this->setPrivateVariable('SERVER_NAME', 'redcap.vanderbilt.edu');
		$expectedTo = ['mark.mcever@vanderbilt.edu', 'kyle.mcguffin@vanderbilt.edu', 'datacore@vanderbilt.edu'];
		$assertToEquals($expectedTo);

		// Assert that vanderbilt module author address is NOT included, since it's always going to be datacore anyway.
		$assertToEquals($expectedTo, 'someone@vanderbilt.edu');

		$otherDomain = 'other.edu';
		$this->setPrivateVariable('SERVER_NAME', "redcap.$otherDomain");
		$assertToEquals([$project_contact_email]);

		$expectedModuleEmail = "someone@$otherDomain";
		$expectedTo = [$project_contact_email, $expectedModuleEmail];
		$assertToEquals($expectedTo, $expectedModuleEmail);
	}

	function testAreSettingPermissionsUserBased()
	{
		$m = $this->getInstance();
		$methodName = 'areSettingPermissionsUserBased';
		$hookName = 'redcap_test_call_function';

		$this->setConfig(['permissions' => [$hookName]]);

		// permissions should not be user based during hook calls
		$value = null;
		ExternalModules::callHook($hookName, [function() use ($methodName, &$value){
			$value = self::callPrivateMethod($methodName, TEST_MODULE_PREFIX, TEST_SETTING_KEY);
		}]);
		$this->assertFalse($value);

		// We'd ideally test to ensure permissions were not user based on module pages here,
		// but we don't have a good way to test that currently.

		// modules should require user based permissions by default in other contexts (like saving settings in the settings dialog)
		$this->assertTrue(self::callPrivateMethod($methodName, TEST_MODULE_PREFIX, TEST_SETTING_KEY));

		$m->disableUserBasedSettingPermissions();
		$this->assertFalse(self::callPrivateMethod($methodName, TEST_MODULE_PREFIX, TEST_SETTING_KEY));

		// reserved values should always require user based permissions
		$this->assertTrue(self::callPrivateMethod($methodName, TEST_MODULE_PREFIX, ExternalModules::KEY_ENABLED));
	}

	function testGetUrl()
	{
		$m = $this->getInstance();

		$url = $m->getUrl("index.php");
		$this->assertNotNull($url);
		$url = $m->getUrl("dir/index.php");
		$this->assertNotNull($url);
	}

	function testGetParseModuleDirectoryPrefixAndVersion()
	{
		$assert = function($expected, $directoryName){
			$this->assertSame($expected, ExternalModules::getParseModuleDirectoryPrefixAndVersion($directoryName));
		};

		$assert(['somedir', 'v1'], 'somedir_v1');
		$assert(['somedir', 'v1.1'], 'somedir_v1.1');
		$assert(['somedir', 'v1.1.1'], 'somedir_v1.1.1');

		// Test underscores and dashes.
		$assert(['some_dir', 'v1.1'], 'some_dir_v1.1');
		$assert(['some-dir', 'v1.1'], 'some-dir_v1.1');

		// Test invalid values.
		$assert(['some_dir', null], 'some_dir_');
		$assert(['some_dir', null], 'some_dir_v');
		$assert(['', 'v1.0'], '_v1.0');
		$assert(['somedir', null], 'somedir_v1A');
		$assert(['somedir', null], 'somedir_vA');
		$assert(['somedir', null], 'somedir_1');
		$assert(['somedir', null], 'somedir_A');
		$assert(['somedir', null], 'somedir_v1.1.1.1');
	}

	function testGetModuleInstance()
	{
		$prefix = 'some_fake_prefix';
		$variableName = 'activeModulePrefix';
		$this->setPrivateVariable($variableName, $prefix);

		$m = ExternalModules::getModuleInstance(TEST_MODULE_PREFIX);

		$this->assertSame(BaseTestExternalModule::class, get_class($m));
		$this->assertSame($prefix, $this->getPrivateVariable($variableName));

		// Prevent issues in other tests.
		$this->setPrivateVariable($variableName, null);
	}

	function testGetFrameworkVersion()
	{
		$doNotIncludeFrameworkVersionValue = 'do-not-include-framework-version';

		$assertFrameworkVersion = function($jsonValue, $expected = null) use ($doNotIncludeFrameworkVersionValue){
			$config = [];
			if($jsonValue !== $doNotIncludeFrameworkVersionValue){
				$config['framework-version'] = $jsonValue;
			}

			$this->setConfig($config);

			$actual = ExternalModules::getFrameworkVersion($this->getInstance());
			$this->assertSame($expected, $actual);
		};

		$assertFrameworkVersion($doNotIncludeFrameworkVersionValue, 1);
		$assertFrameworkVersion(null, 1);
		$assertFrameworkVersion(1, 1);
		$assertFrameworkVersion(2, 2);

		$exceptionValues = ['', 'a', '1', '2', 1.1, true, false, []];

		foreach($exceptionValues as $value){
			$this->assertThrowsException(function() use ($assertFrameworkVersion, $value){
				$assertFrameworkVersion($value);
			}, 'must be specified as an integer');
		}
	}

	function testCopySettingValues()
	{
		$value = [rand(), rand()];
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $value);

		self::callPrivateMethod('copySettingValues', TEST_SETTING_PID, TEST_SETTING_PID_2);

		$this->assertSame($value, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID_2, TEST_SETTING_KEY));
	}

	function testRecreateAllEDocs_fileSettings()
	{
		$edocIds = [];
		$edocFilenames = [];

		$minEdocs = 5;
		$result = ExternalModules::query("select * from redcap_edocs_metadata where date_deleted_server is null and doc_size < 1000000 limit $minEdocs");
		while($row = db_fetch_assoc($result)){
			$edocIds[] = $row['doc_id'];
			$edocFilenames[] = $row['stored_name'];
		}

		$edocsNeeded = $minEdocs - count($edocIds);
		if($edocsNeeded !== 0){
			throw new Exception("Please upload $edocsNeeded more edocs to any project in order for unit tests to run properly.");
		}

		$key1 = 'test-key-1';
		$key2 = 'test-key-2';
		$key3 = 'test-key-3';

		$this->setConfig([
			'project-settings' => [
					[
						'key' => $key1,
						'type' => 'file'
					],
					[
						'key' => $key2,
						'type' => 'file'
					],
					[
						'key' => $key3,
						'type' => 'text'
					]
				]
			]
		);

		$value1 = $edocIds[0];

		// simulate repeatable sub-settings
		$value2 = [
			[
				$edocIds[1],
				$edocIds[2],
				$edocIds[3],
			],
			[
				$edocIds[4],
			]
		];

		ExternalModules::setFileSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key1, $value1);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key2, $value2);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key3, $value2);

		ExternalModules::recreateAllEDocs(TEST_SETTING_PID);

		$newValue1 = ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key1);
		$newValue2 = ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key2);

		$newEdocIds = array_merge([$newValue1], $newValue2[0], $newValue2[1]);
		for($i=0; $i<$minEdocs; $i++){
			$oldId = $edocIds[$i];
			$newId = $newEdocIds[$i];

			$this->assertEdocsEqual($oldId, $newId);
		}

		// Make sure non-file settings are not touched.
		$this->assertSame($value2, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key3));

		foreach($newEdocIds as $id){
			ExternalModules::deleteEDoc($id);
		}
	}

	private function assertEdocsEqual($expected, $actual)
	{
		// If the expected and actual edoc IDs are the same, something in the calling test isn't right.
		$this->assertNotSame($expected, $actual);

		$this->assertFileEquals(self::getEdocPath($expected), self::getEdocPath($actual));
	}

	private function getEdocPath($edocId)
	{
		$row = db_fetch_assoc(ExternalModules::query("select * from redcap_edocs_metadata where doc_id = " . $edocId));
		return EDOC_PATH . $row['stored_name'];
	}

	function testRecreateAllEDocs_richText()
	{
		$row = db_fetch_assoc(ExternalModules::query("select * from redcap_external_module_settings where `key` = '" . ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST . "' limit 1"));
		if(empty($row)){
			throw new Exception("Please upload at least one image to any 'rich-text' module setting (like the inline popups module) to allow this unit test to run.");
		}

		$prefix = ExternalModules::getPrefixForID($row['external_module_id']);
		$oldFiles = ExternalModules::getProjectSetting($prefix, $row['project_id'], ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST);


		$key1 = 'test-key-1';
		$key2 = 'test-key-2';
		$this->setConfig([
			'project-settings' => [
					[
						'key' => $key1,
						'type' => 'rich-text'
					],
					[
						'key' => $key2,
						'type' => 'text'
					]
				]
			]
		);

		$getRichTextExampleContent = function($pid, $edocId) use ($oldFiles){
			return '<p><img src="' . htmlspecialchars(ExternalModules::getRichTextFileUrl(TEST_MODULE_PREFIX, $pid, $edocId, $oldFiles[0]['name'])) . '" alt="" width="150" height="190" /></p>';
		};

		$oldRichTextContent = $getRichTextExampleContent($row['project_id'], $oldFiles[0]['edocId']);

		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key1, $oldRichTextContent);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key2, $oldRichTextContent);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST, $oldFiles);
		ExternalModules::recreateAllEDocs(TEST_SETTING_PID);
		$newFiles = ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST);

		$newRichTextContent = $getRichTextExampleContent(TEST_SETTING_PID, $newFiles[0]['edocId']);
		$this->assertSame($newRichTextContent, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key1));

		// Make sure non-rich-text fields are not changed
		$this->assertSame($oldRichTextContent, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key2));

		$this->assertSame(count($oldFiles), count($newFiles));
		for($i=0; $i<count($oldFiles); $i++){
			$oldFile = $oldFiles[$i];
			$newFile = $newFiles[$i];

			$oldEdocId = $oldFile['edocId'];
			$newEdocId = $newFile['edocId'];

			$this->assertEdocsEqual($oldEdocId, $newEdocId);
			$this->assertSame($oldFile['name'], $newFile['name']);

			ExternalModules::deleteEDoc($newEdocId);
		}
	}
}
