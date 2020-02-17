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

	function testQuery_noParameters(){
		$value = (string)rand();
		$result = $this->query("select $value", []);
		$row = $result->fetch_row();
		$this->assertSame($value, $row[0]);

		$frameworkVersion = $this->getFrameworkVersion();
		if($frameworkVersion < 4){
			$value = (string)rand();
			$result = $this->query("select $value");
			$row = $result->fetch_row();
			$this->assertSame($value, $row[0]);	
		}
		else{
			$this->assertThrowsException((function(){
				$this->query("select 1");
			}), ExternalModules::tt('em_errors_117'));
		}
	}
}