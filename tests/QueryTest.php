<?php
namespace ExternalModules;
require_once 'BaseTest.php';

use \Exception;

class QueryTest extends BaseTest
{
    // The Query class is already heavily tested through other tests.

	protected function getReflectionClass()
	{
		return $this->getInstance()->framework;
	}

    function testAddInClause(){
        $assert = function($values, $expectedValues){
            $q = $this->createQuery();
            $q->add("select * from (select 1 as column_name union select 2 union select null) as fake_table where ");
            $q->addInClause('column_name', $values);
            $r = $q->execute();
            
            foreach($expectedValues as $expectedValue){
                $actualRow = $r->fetch_row();
                $this->assertSame([$expectedValue], $actualRow);
            }
            
            $this->assertNull($r->fetch_row());
        };

        $assert([1], [1]);
        $assert([2], [2]);
        $assert([null], [null]);
        $assert([1,2,3], [1,2]);
        $assert([4,5,6], []);
    }
}