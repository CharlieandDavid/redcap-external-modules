<?php
namespace ExternalModules;

abstract class FrameworkBaseTest extends BaseTest
{
	function __construct(){
		parent::__construct();

		preg_match('/[0-9]+/', get_class($this), $matches);
		$this->frameworkVersion = (int) $matches[0];
	}

	protected function getReflectionClass()
	{
		return $this->getInstance()->framework;
	}

	function getFrameworkVersion(){
		return $this->frameworkVersion;
	}
}