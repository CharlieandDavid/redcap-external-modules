<?php
namespace ExternalModules;
require_once 'BaseTest.php';

use \Exception;

class AbstractExternalModuleTest extends BaseTest
{
	protected function setUp()
	{
		parent::setUp();

		$m = self::getInstance();

		// To delete all logs, we use a fake parameter to create a where clause that applies to all rows
		// (since removeLogs() requires a where clause).
		$m->removeLogs("some_fake_parameter is null");
	}

	/**
	 * @doesNotPerformAssertions
	 */
	function testCheckSettings_emptyConfig()
	{
		self::assertConfigValid([]);
	}

	function testCheckCronModifications() {
		$m = self::getInstance();
		$cronAttr1 = array("cron_name" => "Test Cron 1", "cron_method" => "testMethod1", "cron_hour" => 1, "cron_minute" => 0);
		$cronAttr2 = array("cron_name" => "Test Cron 2", "cron_method" => "testMethod2", "cron_hour" => 2, "cron_minute" => 0);
		$cronAttr3 = array("cron_name" => "Test Cron 3", "cron_method" => "testMethod3", "cron_hour" => 3);
		$cronAttr4 = array("cron_name" => "Test Cron 4", "cron_method" => "testMethod4", "cron_weekday" => "2", "cron_hour" => 4, "cron_minute" => 0);
		$cronAttr5 = array("cron_name" => "Test Cron 5", "cron_method" => "testMethod5", "cron_monthday" => "1", "cron_hour" => 5, "cron_minute" => 0);
		$validCrons = array($cronAttr, $cronAttr2, $cronAttr4, $cronAttr5);
		$invalidCrons = array($cronAttr, $cronAttr2, $cronAttr3, $cronAttr4, $cronAttr5);

		$m->setModifiedCrons($validCrons);
		$crons = $m->getModifiedCrons();
		$this->assertTrue($crons == $validCrons);

		$this->assertThrowsException(function() use ($invalidCrons){
			$m->setModifiedCrons($invalidCrons);
		}, $exceptionExcerpt);
		$crons = $m->getModifiedCrons();
		$this->assertTrue($crons != $invalidCrons);

		$m->removeModifiedCrons();
		$crons = $m->getModifiedCrons();
		$this->assertTrue(empty($crons));

		# check for config backup
                $config = [
                        'system-settings' => [
                                ['key' => 'key1']
                        ],
                        'project-settings' => [
                                ['key' => 'key-two']
                        ],
			'crons' => [
				[
					'cron_name' => 'Test Cron 10',
					'cron_method' => 'testMethod10',
					'cron_hour' => 10,
					'cron_minute' => 0,
				],
			],
                ];

		$m->removeModifiedCrons();
		$this->setConfig($config);
		$crons = $m->getCronSchedule();
		$this->assertTrue($crons == $config['crons']);
		$m->setModifiedCrons($validCrons);
		$crons = $m->getCronSchedule();
		$this->assertTrue($crons != $config['crons']);
		$this->assertTrue($crons == $validCrons);
	}

    function testCheckSettings_duplicateKeys()
    {
    	$assertMultipleSettingException = function($config){
			self::assertConfigInvalid($config, 'setting multiple times!');
		};

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key']
			],
			'project-settings' => [
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key']
			],
			'project-settings' => [
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key']
			],
			'project-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
			'project-settings' => [
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key'],
				['key' => 'some-key'],
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key'],
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				],
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				[
					'key' => 'some-key',
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		$assertMultipleSettingException([
			'project-settings' => [
				['key' => 'some-key'],
				['key' => 'some-key'],
			],
		]);

		$assertMultipleSettingException([
			'project-settings' => [
				['key' => 'some-key'],
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		$assertMultipleSettingException([
			'project-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				],
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'project-settings' => [
				[
					'key' => 'some-key',
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		// Assert a double nested sub_settings
		$assertMultipleSettingException([
			'project-settings' => [
				[
					'key' => 'some-key',
					'type' => 'sub_settings',
					'sub_settings' => [
						[
							'key' => 'some-other-key',
							'type' => 'sub_settings',
							'sub_settings' => [
								[
									'key' => 'some-other-key'
								]
							]
						]
					]
				]
			],
		]);
    }

	/**
	 * @doesNotPerformAssertions
	 */
	function testCheckSettingKey_valid()
	{
		self::assertConfigValid([
			'system-settings' => [
				['key' => 'key1']
			],
			'project-settings' => [
				['key' => 'key-two']
			],
		]);
	}

	function testCheckSettingKey_invalidChars()
	{
		$expected = 'contains invalid characters';

		self::assertConfigInvalid([
			'system-settings' => [
				['key' => 'A']
			]
		], $expected);

		self::assertConfigInvalid([
			'project-settings' => [
				['key' => '!']
			]
		], $expected);
	}

	function testIsSettingKeyValid()
	{
		$m = self::getInstance();

		$isSettingKeyValid = function($key) use ($m){
			return $this->callPrivateMethodForClass($m, 'isSettingKeyValid', $key);
		};

		$this->assertTrue($isSettingKeyValid('a'));
		$this->assertTrue($isSettingKeyValid('2'));
		$this->assertTrue($isSettingKeyValid('-'));
		$this->assertTrue($isSettingKeyValid('_'));

		$this->assertFalse($isSettingKeyValid('A'));
		$this->assertFalse($isSettingKeyValid('!'));
		$this->assertFalse($isSettingKeyValid('"'));
		$this->assertFalse($isSettingKeyValid('\''));
		$this->assertFalse($isSettingKeyValid(' '));
	}

	function assertConfigValid($config)
	{
		$this->setConfig($config);

		// Attempt to make a new instance of the module (which throws an exception on any config issues).
		new BaseTestExternalModule();
	}

	function assertConfigInvalid($config, $exceptionExcerpt)
	{
		$this->assertThrowsException(function() use ($config){
			self::assertConfigValid($config);
		}, $exceptionExcerpt);
	}

	function testSettingKeyPrefixes()
	{
		$normalValue = 1;
		$prefixedValue = 2;

		$this->setSystemSetting($normalValue);
		$this->setProjectSetting($normalValue);

		$m = $this->getInstance();
		$m->setSettingKeyPrefix('test-setting-prefix-');
		$this->assertNull($this->getSystemSetting());
		$this->assertNull($this->getProjectSetting());

		$this->setSystemSetting($prefixedValue);
		$this->setProjectSetting($prefixedValue);
		$this->assertSame($prefixedValue, $this->getSystemSetting());
		$this->assertSame($prefixedValue, $this->getProjectSetting());

		$this->removeSystemSetting();
		$this->removeProjectSetting();
		$this->assertNull($this->getSystemSetting());
		$this->assertNull($this->getProjectSetting());

		$m->setSettingKeyPrefix(null);
		$this->assertSame($normalValue, $this->getSystemSetting());
		$this->assertSame($normalValue, $this->getProjectSetting());

		// Prefixes with sub-settings are tested in testSubSettings().
	}

	function testSystemSettings()
	{
		$value = rand();
		$this->setSystemSetting($value);
		$this->assertSame($value, $this->getSystemSetting());

		$this->removeSystemSetting();
		$this->assertNull($this->getSystemSetting());
	}

	function testProjectSettings()
	{
		$projectValue = rand();
		$systemValue = rand();

		$this->setProjectSetting($projectValue);
		$this->assertSame($projectValue, $this->getProjectSetting());

		$this->removeProjectSetting();
		$this->assertNull($this->getProjectSetting());

		$this->setSystemSetting($systemValue);
		$this->assertSame($systemValue, $this->getProjectSetting());

		$this->setProjectSetting($projectValue);
		$this->assertSame($projectValue, $this->getProjectSetting());
	}

	function testSubSettings()
	{
		$_GET['pid'] = TEST_SETTING_PID;

		$groupKey = 'group-key';
		$settingKey = 'setting-key';
		$settingValues = [1, 2];

		$this->setConfig([
			'project-settings' => [
				[
					'key' => $groupKey,
					'type' => 'sub_settings',
					'sub_settings' => [
						[
							'key' => $settingKey
						]
					]
				]
			]
		]);

		$m = $this->getInstance();
		$m->setProjectSetting($settingKey, $settingValues);

		// Make sure prefixing makes the values we just set inaccessible.
		$m->setSettingKeyPrefix('some-prefix');
		$instances = $m->getSubSettings($groupKey);
		$this->assertEmpty($instances);
		$m->setSettingKeyPrefix(null);

		$instances = $m->getSubSettings($groupKey);
		$this->assertSame(count($settingValues), count($instances));
		for($i=0; $i<count($instances); $i++){
			$this->assertSame($settingValues[$i], $instances[$i][$settingKey]);
		}

		$m->removeProjectSetting($settingKey);
	}

	private function assertReturnedSettingType($value, $expectedType)
	{
		// We call set twice to make sure change detection is working properly, and we don't get an exception from trying to set the same value twice.
		$this->setProjectSetting($value);
		$this->setProjectSetting($value);

		$savedValue = $this->getProjectSetting();

		// We check the type separately from assertEquals() instead of using assertSame() because that wouldn't work for objects like stdClass.
		$savedType = gettype($savedValue);
		$this->assertEquals($expectedType, $savedType);
		$this->assertEquals($value, $savedValue);
	}

	function testSettingTypeConsistency()
	{
		$this->assertReturnedSettingType(true, 'boolean');
		$this->assertReturnedSettingType(false, 'boolean');
		$this->assertReturnedSettingType(1, 'integer');
		$this->assertReturnedSettingType(1.1, 'double');
		$this->assertReturnedSettingType("1", 'string');
		$this->assertReturnedSettingType([1], 'array');
		$this->assertReturnedSettingType([1,2,3], 'array');
		$this->assertReturnedSettingType(['a' => 'b'], 'array');
		$this->assertReturnedSettingType(null, 'NULL');

		$object = new \stdClass();
		$object->someProperty = true;
		$this->assertReturnedSettingType($object, 'object');
	}

	function testSettingTypeChanges()
	{
		$this->assertReturnedSettingType('1', 'string');
		$this->assertReturnedSettingType(1, 'integer');
	}

	function testArrayKeyPreservation()
	{
		$array = [1 => 2];
		$this->setProjectSetting($array);
		$this->assertSame($array, $this->getProjectSetting());
	}

	function testArrayNullValues()
	{
		$array = [0 => null];
		$this->setProjectSetting($array);
		$this->assertSame($array, $this->getProjectSetting());
	}

	function testSettingSizeLimit()
	{
		$data = str_repeat('a', ExternalModules::SETTING_SIZE_LIMIT);
		$this->setProjectSetting($data);
		$this->assertSame($data, $this->getProjectSetting());

		$this->assertThrowsException(function() use ($data){
			$data .= 'a';
			$this->setProjectSetting($data);
		}, 'value is larger than');
	}

	function testSettingKeySizeLimit()
	{
		$m = $this->getInstance();

		$key = str_repeat('a', ExternalModules::SETTING_KEY_SIZE_LIMIT);
		$value = rand();
		$m->setSystemSetting($key, $value);
		$this->assertSame($value, $m->getSystemSetting($key));
		$m->removeSystemSetting($key);

		$this->assertThrowsException(function() use ($m, $key){
			$key .= 'a';
			$m->setSystemSetting($key, '');
		}, 'key is longer than');
	}

	function testRequireAndDetectParameters()
	{
		$testRequire = function($param, $requireFunctionName){
			$this->assertThrowsException(function() use ($requireFunctionName){
				$this->callPrivateMethod($requireFunctionName);
			}, 'You must supply');

			$value = rand();
			$this->assertSame($value, $this->callPrivateMethod($requireFunctionName, $value));

			$_GET[$param] = $value;
			$this->assertSame($value, $this->callPrivateMethod($requireFunctionName, null));
			unset($_GET[$param]);
		};

		$testDetect = function($param, $detectFunctionName){
			$m = $this->getInstance();
			$detect = function($value) use ($m, $detectFunctionName){
				return $this->callPrivateMethodForClass($m, $detectFunctionName, $value);
			};

			$this->assertSame(null, $detect(null));

			$value = rand();
			$this->assertSame($value, $detect($value));

			$_GET[$param] = $value;
			$this->assertSame($value, $detect(null));
			unset($_GET[$param]);
		};

		$testParameter = function($param, $functionNameSuffix) use ($testRequire, $testDetect){
			$testRequire($param, 'require' . $functionNameSuffix);
			$testDetect($param, 'detect' . $functionNameSuffix);
		};

		$testParameter('pid', 'ProjectId');
		$testParameter('event_id', 'EventId');
		$testParameter('instance', 'InstanceId');
	}

	function testDetectParamter_sqlInjection(){
		$_GET['pid'] = 'delete * from an_important_table';
		$this->assertEquals(0, $this->callPrivateMethod('detectParameter', 'pid'));
	}

	protected function getReflectionClass()
	{
		return $this->getInstance();
	}

	function testHasPermission()
	{
		$m = $this->getInstance();

		$testPermission = 'some_test_permission';
		$config = ['permissions' => []];

		$this->setConfig($config);
		$this->assertFalse($m->hasPermission($testPermission));

		$config['permissions'][] = $testPermission;
		$this->setConfig($config);
		$this->assertTrue($m->hasPermission($testPermission));
	}

	function testGetUrl()
	{
		$m = $this->getInstance();

		$base = APP_PATH_WEBROOT_FULL . 'external_modules/?prefix=' . $m->PREFIX . '&page=';
		$apiBase = APP_PATH_WEBROOT_FULL . 'api/?type=module&prefix=' . $m->PREFIX . '&page=';
		$moduleBase = ExternalModules::getModuleDirectoryUrl($m->PREFIX, $m->VERSION);

		$this->assertSame($base . 'test', $m->getUrl('test.php'));
		$this->assertSame($base . 'test&NOAUTH', $m->getUrl('test.php', true));
		$this->assertSame($apiBase . 'test', $m->getUrl('test.php', false, true));

		$pid = 123;
		$_GET['pid'] = $pid;
		$this->assertSame($base . 'test&pid=' . $pid, $m->getUrl('test.php'));

		$this->assertSame($moduleBase . 'images/foo.png?', $m->getUrl('images/foo.png'));
		$this->assertSame($apiBase . 'images%2Ffoo.png', $m->getUrl('images/foo.png', false, true));
	}

	function testIsPHPGreaterThan()
	{
		$isPHPGreaterThan = function($requiredVersion){
			return $this->callPrivateMethod('isPHPGreaterThan', $requiredVersion);
		};

		$versionParts = explode('.', PHP_VERSION);
		$lastNumber = $versionParts[2];

		$versionParts[2] = $lastNumber-1;
		$lowerVersion = implode('.', $versionParts);

		$versionParts[2] = $lastNumber+1;
		$higherVersion = implode('.', $versionParts);

		$this->assertTrue($isPHPGreaterThan(PHP_VERSION));
		$this->assertFalse($isPHPGreaterThan($higherVersion));
		$this->assertTrue($isPHPGreaterThan($lowerVersion));
	}

	private function getUnitTestingModuleId()
	{
		$id = ExternalModules::getIdForPrefix(TEST_MODULE_PREFIX);
		$this->assertTrue(ctype_digit($id));
		
		return $id;
	}

	function testLogAndQueryLog()
	{
		$m = $this->getInstance();
		$testingModuleId = $this->getUnitTestingModuleId();

		// Remove left over messages in case this test previously failed
		$m->query('delete from redcap_external_modules_log where external_module_id = ' . $testingModuleId);

		$message = TEST_LOG_MESSAGE;
		$paramName1 = 'testParam1';
		$paramValue1 = rand();
		$paramName2 = 'testParam2';
		$paramValue2 = rand();
		$paramName3 = 'testParam3';

		$query = function () use ($m, $testingModuleId, $message, $paramName1, $paramName2) {
			$results = $m->queryLogs("
				select log_id,timestamp,username,ip,external_module_id,record,message,$paramName1,$paramName2
				where
					message = '$message'
					and timestamp > '2017-07-07'
				order by log_id asc
			");

			$timestampThreshold = 5;

			$rows = [];
			while ($row = db_fetch_assoc($results)) {
				$currentUTCTime = $date_utc = new \DateTime("now", new \DateTimeZone("UTC"));
				$timeSinceLog = $currentUTCTime - strtotime($row['timestamp']);

				$this->assertTrue(ctype_digit($row['log_id']));
				$this->assertTrue($timeSinceLog < $timestampThreshold);
				$this->assertEquals($testingModuleId, $row['external_module_id']);
				$this->assertEquals($message, $row['message']);

				$rows[] = $row;
			}

			return $rows;
		};

		ExternalModules::setUsername(null);
		$_SERVER['HTTP_CLIENT_IP'] = null;
		$m->setRecordId(null);
		$m->log($message);

		$username = $this->getRandomUsername();

		ExternalModules::setUsername($username);
		$_SERVER['HTTP_CLIENT_IP'] = '1.2.3.4';
		$m->setRecordId('abc-' . rand()); // We prepend a string to make sure alphanumeric record ids work.
		$m->log($message, [
			$paramName1 => $paramValue1,
			$paramName2 => $paramValue2,
			$paramName3 => null
		]);

		$rows = $query();
		$this->assertEquals(2, count($rows));

		$row = $rows[0];
		$this->assertNull($row['username']);
		$this->assertNull($row['ip']);
		$this->assertNull($row['record']);
		$this->assertFalse(isset($row[$paramName1]));
		$this->assertFalse(isset($row[$paramName2]));

		$row = $rows[1];
		$this->assertEquals($username, $row['username']);
		$this->assertEquals($_SERVER['HTTP_CLIENT_IP'], $row['ip']);
		$this->assertEquals($m->getRecordId(), $row['record']);
		$this->assertEquals($paramValue1, $row[$paramName1]);
		$this->assertEquals($paramValue2, $row[$paramName2]);
		$this->assertNull($row[$paramName3]);

		$m->removeLogs("$paramName1 is null");
		$rows = $query();
		$this->assertEquals(1, count($rows));
		$this->assertEquals($paramValue1, $rows[0][$paramName1]);

		$m->removeLogs("message = '$message'");
		$rows = $query();
		$this->assertEquals(0, count($rows));
	}

	function testLog_pid()
	{
		$m = $this->getInstance();
		$message = 'test';
		$whereClause = "message = '$message'";
		$expectedPid = rand();

		$assertRowCount = function($expectedCount) use ($m, $message, $whereClause, $expectedPid){
			$result = $m->queryLogs('select pid where ' . $whereClause);
			$rows = [];
			while($row = db_fetch_assoc($result)){
				$rows[] = $row;

				$pid = @$_GET['pid'];
				if(!empty($pid)){
					$this->assertEquals($expectedPid, $pid);
				}
			}

			$this->assertEquals($expectedCount, count($rows));
		};

		$m->log($message);
		$_GET['pid'] = $expectedPid;
		$m->log($message);

		// A pid is still set, so only that row should be returned.
		$assertRowCount(1);

		// Unset the pid and make sure both rows are returned.
		$_GET['pid'] = null;
		$assertRowCount(2);

		// Re-set the pid and attempt to remove only the pid row
		$_GET['pid'] = $expectedPid;
		$m->removeLogs($whereClause);

		// Unset the pid and make sure only the row without the pid is returned
		$_GET['pid'] = null;
		$assertRowCount(1);

		// Make sure removeLogs() now removes the row without the pid.
		$m->removeLogs($whereClause);
		$assertRowCount(0);
	}

	function testLog_emptyMessage()
	{
		$m = $this->getInstance();

		foreach ([null, ''] as $value) {
			$this->assertThrowsException(function () use ($m, $value) {
				$m->log($value);
			}, 'A message is required for log entries.');
		}
	}

	function testLog_reservedParameterNames()
	{
		$m = $this->getInstance();

		$reservedParameterNames = AbstractExternalModule::$RESERVED_LOG_PARAMETER_NAMES;

		foreach ($reservedParameterNames as $name) {
			$this->assertThrowsException(function () use ($m, $name) {
				$m->log('test', [
					$name => 'test'
				]);
			}, 'parameter name is set automatically and cannot be overridden');
		}
	}

	function testLog_recordId()
	{
		$m = $this->getInstance();

		$m->setRecordId(null);
		$logId = $m->log('test');
		$this->assertLogValues($logId, [
			'record' => null
		]);

		$generateRecordId = function(){
			return 'some prefix to make sure string record ids work - ' . rand();
		};

		$message = TEST_LOG_MESSAGE;
		$recordId1 = $generateRecordId();
		$m->setRecordId($recordId1);

		$logId = $m->log($message);
		$this->assertLogValues($logId, ['record' => $recordId1]);

		// Make sure the detected record id can be overridden by developers
		$params = ['record' => $generateRecordId()];
		$logId = $m->log($message, $params);
		$this->assertLogValues($logId, $params);
	}

	// Verifies that the specified values are stored in the database under the given log id.
	private function assertLogValues($logId, $expectedValues = [])
	{
		$columnNamesSql = implode(',', array_keys($expectedValues));
		$selectSql = "select $columnNamesSql where log_id = $logId";

		$m = $this->getInstance();
		$result = $m->queryLogs($selectSql);
		$log = db_fetch_assoc($result);

		foreach($expectedValues as $name=>$expectedValue){
			$actualValue = $log[$name];
			$this->assertSame($expectedValue, $actualValue, "For the '$name' log parameter:");
		}
	}

	function testLog_escapedCharacters()
	{
		$m = $this->getInstance();
		$maliciousSql = "'; delete from everything";
		$m->log($maliciousSql, [
			"malicious_param" => $maliciousSql
		]);

		$selectSql = 'select message, malicious_param order by timestamp desc limit 1';
		$result = $m->queryLogs($selectSql);
		$row = db_fetch_assoc($result);
		$this->assertSame($maliciousSql, $row['message']);
		$this->assertSame($maliciousSql, $row['malicious_param']);
	}

	function testLog_spacesInParameterNames()
	{
		$m = $this->getInstance();

		$paramName = "some param";
		$paramValue = "some value";

		$m->log('test', [
			$paramName => $paramValue
		]);

		$selectSql = "select `$paramName` where `$paramName` is not null order by `$paramName`";
		$result = $m->queryLogs($selectSql);
		$row = db_fetch_assoc($result);
		$this->assertSame($paramValue, $row[$paramName]);

		$m->removeLogs("`$paramName` is not null");
		$result = $m->queryLogs($selectSql);
		$this->assertNull(db_fetch_assoc($result));
	}

	function testLog_unsupportedTypes()
	{
		$this->assertThrowsException(function(){
			$m = $this->getInstance();
			$m->log('foo', [
				'some-unsupported-type' => new \stdClass()
			]);
		}, "The type 'object' for the 'some-unsupported-type' parameter is not supported");
	}

	function getRandomUsername()
	{
		$result = db_query('select username from redcap_user_information order by rand() limit 1');
		$username =  db_fetch_assoc($result)['username'];

		return $username;
	}

	function testLog_overridableParameters()
	{
		$m = $this->getInstance();

		$testValues = [
			'timestamp' => date("Y-m-d H:i:s"),
			'username' => $this->getRandomUsername(),
			'project_id' => '1'
		];

		foreach(AbstractExternalModule::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE as $name){

			$value = $testValues[$name];
			if(empty($value)){
				$value = 'foo';
			}

			$params = [
				$name => $value
			];

			$logId = $m->log('foo', $params);
			$this->assertLogValues($logId, $params);

			// Make sure a parameter table entry was NOT made, since the value should only be stored in the main log table.
			$result = $m->query("select * from redcap_external_modules_log_parameters where log_id = $logId");
			$row = $result->fetch_assoc();
			$this->assertNull($row);
		}
	}

	function testGetIPSQL()
	{
		$ip = '1.2.3.4';
		$_SERVER['HTTP_CLIENT_IP'] = $ip;
		$username = 'jdoe';
		ExternalModules::setUsername($username);

		$ipParameter = '2.3.4.5';
		$this->assertSame("'$ipParameter'", $this->callPrivateMethod('getIPSQL', $ipParameter));

		$assertIp = function($ip){
			if(empty($ip)){
				$ip = 'null';
			}
			else{
				$ip = "'$ip'";
			}

			$this->assertSame($ip, $this->callPrivateMethod('getIPSQL', null));
		};

		$assertIp($ip);

		$_SERVER['REQUEST_URI'] = '/surveys/';
		$assertIp(null);

		$_SERVER['REQUEST_URI'] = '';
		$assertIp($ip);

		ExternalModules::setUsername(null);
		$assertIp(null);

		ExternalModules::setUsername($username);
		$assertIp($ip);

		unset($_SERVER['HTTP_CLIENT_IP']);
		$assertIp(null);
	}

	function assertLogAjax($data)
	{
		$data['message'] = TEST_LOG_MESSAGE;

		$m = $this->getInstance();
		$m->setRecordId(null);

		$logId = $m->logAjax($data);
		$this->assertLogValues($logId, [
			'record' => $data['parameters']['record']
		]);

		// TODO - At some point, it would be nice to test the survey hash parameters here.
	}

	function testLogAjax_overridableParameters()
	{
		foreach(AbstractExternalModule::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE as $name){
			$this->assertThrowsException(function() use ($name){
				$this->assertLogAjax([
					'parameters' => [
						$name => 'foo'
					]
				]);
			}, "'$name' parameter cannot be overridden via AJAX log requests");
		}
	}

	function testLogAjax_record()
	{
		// Make sure these don't throw an exception
		$this->assertLogAjax([
			'noAuth' => true
		]);
		$this->assertLogAjax([
			'noAuth' => true,
			'parameters' => [
				'record' => ExternalModules::EXTERNAL_MODULES_TEMPORARY_RECORD_ID . '-123'
			]
		]);

		$this->assertThrowsException(function(){
			$this->assertLogAjax([
				'noAuth' => true,
				'parameters' => [
					'record' => '123'
				]
			]);
		}, "'record' parameter cannot be overridden via AJAX log requests");
	}

	function testQueryLogs_complexStatements()
	{
		$m = $this->getInstance();

		// Just make sure this query is parsable, and runs without an exception.
		$m->queryLogs("select 1 where a = 1 and (b = 2 or c = 3)");

		$this->assertTrue(true); // Each test requires an assertion
	}

	function testQueryLogs_multipleReferencesToSameColumn()
	{
		$m = $this->getInstance();

		// Just make sure this query is parsable, and runs without an exception.
		$m->queryLogs("select 1 where a > 1 and a < 5");

		$this->assertTrue(true); // Each test requires an assertion
	}

	function testQueryLogs_orderBy(){

		$m = $this->getInstance();

		// Just make sure this query is parsable, and runs without an exception.
		$m->queryLogs("select 1 where 1 = 2 order by log_id");

		$this->assertTrue(true); // Each test requires an assertion
	}

	function testQueryLogs_stars(){
		$m = $this->getInstance();

		// "select count(*)" should be allowed
		$result = $m->queryLogs("select count(*) as count where some_fake_parameter = 1");
		$row = $result->fetch_assoc();
		$this->assertSame('0', $row['count']);

		// "select *" should not be allowed
		$this->assertThrowsException(function() use ($m){
			$m->queryLogs('select * where some_fake_parameter = 1');
		}, "Columns must be explicitly defined in all log queries");
	}

	function testGetQueryLogsSql_moduleId()
	{
		$m = $this->getInstance();

		$columnName = 'external_module_id';

		// Make sure that when no where clause is present, a where clause for the current module is added
		$sql = $m->getQueryLogsSql("select log_id");
		$this->assertEquals(1, substr_count($sql, AbstractExternalModule::EXTERNAL_MODULE_ID_STANDARD_WHERE_CLAUSE_PREFIX . " = '" . TEST_MODULE_PREFIX . "')"));

		$moduleId = rand();
		$overrideClause = "$columnName = $moduleId";
		$sql = $m->getQueryLogsSql("select 1 where $overrideClause");

		// Make sure there is only one clause related to the module id.
		$this->assertEquals(1, substr_count($sql, $columnName));

		// Make sure our override clause has replaced the the clause for the current module.
		$this->assertEquals(1, substr_count($sql, $overrideClause));
	}

	function testGetQueryLogsSql_overrideProjectId()
	{
		$m = $this->getInstance();

		$columnName = 'project_id';

		// Make sure that when no where clause is present, a where clause for the current project is added
		$projectId = '1';
		$_GET['pid'] = $projectId;
		$sql = $m->getQueryLogsSql("select log_id");
		$this->assertEquals(1, substr_count($sql, "$columnName = $projectId"));

		$projectId = '2';
		$overrideClause = "$columnName = $projectId";
		$sql = $m->getQueryLogsSql("select 1 where $overrideClause");

		// Make sure there is only one clause related to the project id.
		$this->assertEquals(1, substr_count($sql, $columnName));

		// Make sure our override clause has replaced the the clause for the current project.
		$this->assertEquals(1, substr_count($sql, $overrideClause));
	}

	function testRemoveLogs()
	{
		$m = $this->getInstance();
		$logId1 = $m->log('one');
		$logId2 = $m->log('two');

		$m->removeLogs("log_id = $logId1");

		$this->assertThrowsException(function() use ($m){
			$m->removeLogs('');
		}, 'must specify a where clause');

		$this->assertThrowsException(function() use ($m){
			$m->removeLogs('external_module_id = 1');
		}, 'not allowed to prevent modules from accidentally removing logs for other modules');
	}

	function testExceptionOnMissingMethod()
	{
		// We use the __call() magic method, which prevents the default missing method error.
		// The following asserts that we are throwing our own exception from __call().
		$this->assertThrowsException(function(){
			$m = $this->getInstance();
			$m->someMethodThatDoesntExist();
		}, 'method does not exist');
	}

	function testGetSubSettings()
	{
		$pid = 1;
		$_GET['pid'] = $pid;
		$m = $this->getInstance();

		$settingValues = [
			// Make sure the first setting is no longer being used to detect any lengths by simulating a new/empty setting.
			'key1' => [],

			// These settings each intentionally have difference lengths to make sure they're still returned appropriately.
			'key2' => ['a', 'b', 'c'],
			'key3' => [1,2,3,4,5],
			'key4' => [true, false]
		];

		$subSettingsConfig = [];
		foreach($settingValues as $key=>$values){
			$m->setProjectSetting($key, $values);

			$subSettingsConfig[] = [
				'key' => $key
			];
		}

		$subSettingsKey = 'sub-settings-key';
		$this->setConfig([
			'project-settings' => [
				[
					'key' => $subSettingsKey,
					'type' => 'sub_settings',
					'sub_settings' => $subSettingsConfig
				]
			]
		]);

		$assertSubSettings = function($pid) use ($m, $subSettingsKey, $settingValues) {
			$subSettingResults = $m->getSubSettings($subSettingsKey, $pid);
			foreach($settingValues as $key=>$values){
				for($i=0; $i<count($values); $i++){
					$this->assertSame($settingValues[$key][$i], $subSettingResults[$i][$key]);
				}
			}
		};

		$assertSubSettings(null);

		unset($_GET['pid']);

		$this->assertThrowsException(function() use ($assertSubSettings) {
			$assertSubSettings(null);
		}, 'argument to this method: pid');

		$assertSubSettings($pid);
	}

	function testSetSetting_concurrency()
	{
		// This test spins off a second phpunit process in order to test concurrency and locking in setSetting().
		// If you comment out the GET_LOCK call in setSetting(), an exception should occur within a fraction of $maxIterations.
		$iterations = 0;
		$maxIterations = 1000;

		$concurrentOperations = function(){
			$this->setProjectSetting('some value');
			$this->removeProjectSetting();
		};

		$parentAction = function ($isChildRunning) use ($concurrentOperations, $iterations, $maxIterations) {
			while($isChildRunning()){
				$concurrentOperations();
				$iterations++;
			}

			// The parent will generally run more iterations than the child, but apparently not always.
			// Consider the text successful if $iterations is at least 90% of $maxIterations.
			$this->assertGreaterThan($maxIterations * 0.9, $iterations);
		};

		$childAction = function () use ($iterations, $maxIterations, $concurrentOperations) {
			while ($iterations < $maxIterations) {
				$concurrentOperations();
				$iterations++;
			}

			$this->assertSame($iterations, $maxIterations);
		};

		$this->runConcurrentTestProcesses(__FUNCTION__, $parentAction, $childAction);
	}
}
