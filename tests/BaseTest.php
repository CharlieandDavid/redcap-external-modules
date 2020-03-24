<?php
namespace ExternalModules;

// These were added simply to avoid warnings from REDCap code.
$_SERVER['SERVER_NAME'] = 'unit testing';
$_SERVER['REMOTE_ADDR'] = 'unit testing';
if(!defined('PAGE')){
	define('PAGE', 'unit testing');
}

require_once __DIR__ . '/../classes/ExternalModules.php';
require_once __DIR__ . '/BaseTestExternalModule.php';

use PHPUnit\Framework\TestCase;
use \Exception;
use REDCap;

const TEST_MODULE_PREFIX = ExternalModules::TEST_MODULE_PREFIX;
const TEST_MODULE_VERSION = 'v1.0.0';
const TEST_LOG_MESSAGE = 'This is a unit test log message';
const TEST_SETTING_KEY = 'unit-test-setting-key';
const FILE_SETTING_KEY = 'unit-test-file-setting-key';

$testPIDs = ExternalModules::getTestPIDs();
define('TEST_SETTING_PID', $testPIDs[0]);
define('TEST_SETTING_PID_2', $testPIDs[1]);

abstract class BaseTest extends TestCase
{
	protected $backupGlobals = FALSE;

	private static $testModuleInstance;

	public static function setUpBeforeClass(){
		ExternalModules::initialize();

		$m = new BaseTestExternalModule();
		list($surveyId, $formName) = $m->getSurveyId(TEST_SETTING_PID);
		if(empty($surveyId)){
			ExternalModules::query("
				insert into redcap_surveys (project_id, form_name)
				values (?, (
					select form_name from redcap_metadata where project_id = ? limit 1
				))	
			", [TEST_SETTING_PID, TEST_SETTING_PID]);
		}
	}

	protected function setUp(){
		self::$testModuleInstance = new BaseTestExternalModule();
		
		self::setExternalModulesProperty('instanceCache', [TEST_MODULE_PREFIX => [TEST_MODULE_VERSION => self::$testModuleInstance]]);
		self::setExternalModulesProperty('systemwideEnabledVersions', [TEST_MODULE_PREFIX => TEST_MODULE_VERSION]);
		
		self::cleanupSettings();
	}

	function getFrameworkVersion(){
		return 3;
	}

	protected function tearDown()
	{
		self::cleanupSettings();
	}

	private function cleanupSettings()
	{
		$this->setConfig([
			'framework-version' => $this->getFrameworkVersion()
		]);

		$this->getInstance()->testHookArguments = null;

		$m = self::getInstance();
		$moduleId = ExternalModules::getIdForPrefix(TEST_MODULE_PREFIX);
		$lockName = ExternalModules::getLockName($moduleId, TEST_SETTING_PID);

		$m->query("SELECT GET_LOCK(?, 5)", [$lockName]);
		$m->query("delete from redcap_external_module_settings where external_module_id = ?", [$moduleId]);
		$m->query("SELECT RELEASE_LOCK(?)", [$lockName]);

		$_GET = [];
		$_POST = [];
	}

	protected function setSystemSetting($value)
	{
		self::getInstance()->setSystemSetting(TEST_SETTING_KEY, $value);
	}

	protected function getSystemSetting()
	{
		return self::getInstance()->getSystemSetting(TEST_SETTING_KEY);
	}

	protected function removeSystemSetting()
	{
		self::getInstance()->removeSystemSetting(TEST_SETTING_KEY);
	}

	protected function setProjectSetting($value)
	{
		self::getInstance()->setProjectSetting(TEST_SETTING_KEY, $value, TEST_SETTING_PID);
	}

	protected function getProjectSetting()
	{
		return self::getInstance()->getProjectSetting(TEST_SETTING_KEY, TEST_SETTING_PID);
	}

	protected function removeProjectSetting()
	{
		self::getInstance()->removeProjectSetting(TEST_SETTING_KEY, TEST_SETTING_PID);
	}

	protected function getInstance()
	{
		return self::$testModuleInstance;
	}

	protected function setConfig($config)
	{
		if(gettype($config) === 'string'){
			$config = json_decode($config, true);
			if($config === null){
				throw new Exception("Error parsing json configuration (it's likely not valid json).");
			}
		}

		$this->setExternalModulesProperty('configs', [TEST_MODULE_PREFIX => [TEST_MODULE_VERSION => $config]]);

		// Re-initialize the framework in case the version changed.
		ExternalModules::initializeFramework(self::$testModuleInstance);
	}

	private function setExternalModulesProperty($name, $value)
	{
		$externalModulesClass = new \ReflectionClass("ExternalModules\\ExternalModules");
		$configsProperty = $externalModulesClass->getProperty($name);
		$configsProperty->setAccessible(true);
		$configsProperty->setValue($value);
	}

	protected function assertThrowsException($callable, $exceptionExcerpt)
	{
		$exceptionThrown = false;
		try{
			$callable();
		}
		catch(Exception $e){
			if(empty($exceptionExcerpt)){
				throw new Exception('You must specify an exception excerpt!  Here\'s a hint: ' . $e->getMessage());
			}
			else if(strpos($e->getMessage(), $exceptionExcerpt) === false){
				throw new Exception("Could not find the string '$exceptionExcerpt' in the following exception message: " . $e->getMessage());
			}

			$exceptionThrown = true;
		}

		$this->assertTrue($exceptionThrown, "An exception was not thrown where one was expected containing the following text: $exceptionExcerpt");
	}

	protected function callPrivateMethod($methodName)
	{
		$args = func_get_args();
		array_unshift($args, $this->getReflectionClass());

		return call_user_func_array([$this, 'callPrivateMethodForClass'], $args);
	}

	protected function callPrivateMethodForClass()
	{
		$args = func_get_args();
		$classInstanceOrName = array_shift($args); // remove the $classInstanceOrName
		$methodName = array_shift($args); // remove the $methodName

		if(gettype($classInstanceOrName) == 'string'){
			$instance = null;
		}
		else{
			$instance = $classInstanceOrName;
		}

		$class = new \ReflectionClass($classInstanceOrName);
		$method = $class->getMethod($methodName);
		$method->setAccessible(true);

		return $method->invokeArgs($instance, $args);
	}

	protected function getPrivateVariable($name)
	{
		$class = new \ReflectionClass($this->getReflectionClass());
		$property = $class->getProperty($name);
		$property->setAccessible(true);

		return $property->getValue($this->getReflectionClass());
	}

	protected function setPrivateVariable($name, $value)
	{
		$class = new \ReflectionClass($this->getReflectionClass());
		$property = $class->getProperty($name);
		$property->setAccessible(true);

		return $property->setValue($this->getReflectionClass(), $value);
	}

	protected abstract function getReflectionClass();

	protected function runConcurrentTestProcesses($functionName, $parentAction, $childAction)
	{
		// The parenthesis are included in the argument and check below so we can still filter for this function manually (WITHOUT the parenthesis)  when testing for testing and avoid triggering the recursion.
		$functionName .= '()';

		global $argv;
		if(end($argv) === $functionName){
			// This is the child process.
			$childAction();
		}
		else{
			// This is the parent process.

			$cmd = "vendor/phpunit/phpunit/phpunit --filter " . escapeshellarg($functionName);
			$childProcess = proc_open(
				$cmd, [
					0 => ['pipe', 'r'],
					1 => ['pipe', 'w'],
					2 => ['pipe', 'w'],
				],
				$pipes
			);

			// Gets the child status, but caches the final result since calling proc_get_status() multiple times
			// after a process ends will incorrectly return -1 for the exit code.
			$getChildStatus = function() use ($childProcess, &$lastStatus){
				if(!$lastStatus || $lastStatus['running']){
					$lastStatus = proc_get_status($childProcess);
				}

				return $lastStatus;
			};

			$isChildRunning = function() use ($getChildStatus){
				$status = $getChildStatus();
				return $status['running'];
			};

			$parentAction($isChildRunning);

			while($isChildRunning()){
				// The parent finished before the child.
				// Wait for the child to finish before continuing so that the exit code can be checked below.
				sleep(.1);
			}

			$status = $getChildStatus();
			$exitCode = $status['exitcode'];
			if($exitCode !== 0){
				$output = stream_get_contents($pipes[1]);
				throw new Exception("The child phpunit process for the $functionName test failed with exit code $exitCode and the following output: $output");
			}
		}
	}

	function assertIsInt($i){
		$this->assertInternalType('int', $i);
	}

	function ensureRecordExists($recordId, $pid = TEST_SETTING_PID){
		REDCap::saveData($pid, 'json', json_encode([[
			$this->getFramework()->getRecordIdField($pid) => $recordId,
		]]));
	}

	function getFramework(){
		$i = $this->getInstance();

		if(property_exists($i, 'framework')){
			return $i->framework;
		}
		else{
			return $i;
		}
	}

	function __call($methodName, $args){
		if(
			!method_exists($this->getReflectionClass(), $methodName) &&
			!in_array($methodName, ['query', 'log'])
		){
			throw new Exception("Method does not exist: $methodName");
		}

		return call_user_func_array(array($this->getReflectionClass(), $methodName), $args);
	}
}
