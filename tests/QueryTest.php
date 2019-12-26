<?php namespace ExternalModules;

class QueryTest extends \PHPUnit\Framework\TestCase{
    function testOldQueryMethod(){
        $query = ExternalModules::createQuery();
        $query->add('select 1');

        $assert = function($expected) use ($query){
            $result = $query->execute();
            $row = $result->fetch_row();
            $this->assertSame($expected, $row[0]);
        };

        $assert(1);

        $query->setOldQueryMethod(true);
        $assert('1');
    }
}