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
const TEST_SETTING_PID = 1;
const TEST_SETTING_PID_2 = 2;

abstract class BaseTest extends TestCase
{
	protected $backupGlobals = FALSE;

	private $testModuleInstance;

	public static function setUpBeforeClass(){
		ExternalModules::initialize();
	}

	protected function setUp(){
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

		$m->query("SELECT GET_LOCK('$lockName', 5)");
		$m->query("delete from redcap_external_module_settings where external_module_id = $moduleId");
		$m->query("SELECT RELEASE_LOCK('$lockName')");

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
		if($this->testModuleInstance == null){
			$instance = new BaseTestExternalModule();
			$this->setExternalModulesProperty('instanceCache', [TEST_MODULE_PREFIX => [TEST_MODULE_VERSION => $instance]]);

			$this->testModuleInstance = $instance;
		}

		return $this->testModuleInstance;
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
		$this->setExternalModulesProperty('systemwideEnabledVersions', [TEST_MODULE_PREFIX => TEST_MODULE_VERSION]);
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

	protected function callPrivateMethodForClass($classInstanceOrName, $methodName)
	{
		if(gettype($classInstanceOrName) == 'string'){
			$instance = null;
		}
		else{
			$instance = $classInstanceOrName;
		}

		$args = func_get_args();
		array_shift($args); // remove the $classInstanceOrName
		array_shift($args); // remove the $methodName

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

	function getModuleDirectoryName()
	{
		return ExternalModules::getModuleDirectoryPath($this->PREFIX, $this->VERSION);
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

	function redcap_test_call_function($function){
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
