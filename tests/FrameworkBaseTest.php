<?php
namespace ExternalModules;

abstract class FrameworkBaseTest extends BaseTest
{
	function __construct(){
		parent::__construct();

		preg_match('/[0-9]+/', get_class($this), $matches);
		$version = $matches[0];
		require_once __DIR__ . "/../classes/framework/v$version/Framework.php";
		$frameworkClass = "ExternalModules\\FrameworkVersion$version\\Framework";
		$this->framework = new $frameworkClass(new BaseTestExternalModule());
	}

	protected function getReflectionClass()
	{
		return $this->framework;
	}
}