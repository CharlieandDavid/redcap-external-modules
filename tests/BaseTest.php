<?php
namespace ExternalModules;

// These were added simply to avoid warnings from REDCap code.
$_SERVER['SERVER_NAME'] = 'unit testing';
$_SERVER['REMOTE_ADDR'] = 'unit testing';
if(!defined('PAGE')){
	define('PAGE', 'unit testing');
}

require_once dirname(__FILE__) . '/../classes/ExternalModules.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPUnit\Framework\TestCase;
use \Exception;

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
			throw new Exception('Please add a survey to project ' . TEST_SETTING_PID . ' to allow all tests to run.');
		}
	}

	protected function setUp(){
		$this->setConfig([
			'framework-version' => 3
		]);
		
		self::$testModuleInstance = new BaseTestExternalModule();
		self::setExternalModulesProperty('instanceCache', [TEST_MODULE_PREFIX => [TEST_MODULE_VERSION => self::$testModuleInstance]]);
		self::setExternalModulesProperty('systemwideEnabledVersions', [TEST_MODULE_PREFIX => TEST_MODULE_VERSION]);
		
		self::cleanupSettings();
	}

	protected function tearDown()
	{
		self::cleanupSettings();
	}

	private function cleanupSettings()
	{
		$this->setConfig([]);
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
}

class BaseTestExternalModule extends AbstractExternalModule {

	public $testHookArguments;
	private $settingKeyPrefix;

	function __construct()
	{
		$this->PREFIX = TEST_MODULE_PREFIX;
		$this->VERSION = TEST_MODULE_VERSION;

		parent::__construct();
	}

	function getModulePath()
	{
		return __DIR__;
	}

	function redcap_test_delay($delayTestFunction)
	{
		// Although it perhaps shouldn't be, it is sometimes possible for getModuleInstance() to
		// be called while inside a hook (it sometimes happens in the email alerts module).
		// The getModuleInstance() function used to set the active module prefix to null on every call,
		// which is problematic since the delayModuleExecution() method relies on the active prefix.
		// This used to cause 'You must specify a prefix!' exceptions.
		// We call getModuleInstance() inside this delay test hook to make sure this bug never reoccurs.
		ExternalModules::getModuleInstance(TEST_MODULE_PREFIX);

		$delayTestFunction($this->delayModuleExecution());
	}

	function redcap_test()
	{
		$this->testHookArguments = func_get_args();
	}

	function redcap_test_call_function($function = null){
		// We must check if the arg is callable b/c it could be cron attributes for a cron job.
		if(!is_callable($function)){
			$function = $this->function;
		}

		$function();
	}
	
	function redcap_every_page_test()
	{
		call_user_func_array([$this, 'redcap_test'], func_get_args());
	}

	function redcap_save_record()
	{
		$this->recordIdFromGetRecordId = $this->getRecordId();
	}

	protected function getSettingKeyPrefix()
	{
		if($this->settingKeyPrefix){
			return $this->settingKeyPrefix;
		}
		else{
			return parent::getSettingKeyPrefix();
		}
	}

	function setSettingKeyPrefix($settingKeyPrefix)
	{
		$this->settingKeyPrefix = $settingKeyPrefix;
	}
}
