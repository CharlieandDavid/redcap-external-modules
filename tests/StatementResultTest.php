<?php namespace ExternalModules;

class StatementResultTest extends BaseTest
{
    protected function getReflectionClass()
	{
		return 'ExternalModules\ExternalModules';
    }
    
    function test_num_rows(){
        $r = $this->query('select ? union select ? union select ?', [1, 2, 3]);
        $this->assertSame(3, $r->num_rows);

        // empty result set
        $r = $this->query('select ? from redcap_data where 2=3', [1]);
        $this->assertSame(0, $r->num_rows);
    }

    function test_fetch_assoc(){
        $r = $this->query('select ? as foo union select ?', [1, 2]);
        $this->assertSame(['foo'=>1], $r->fetch_assoc());
        $this->assertSame(['foo'=>2], $r->fetch_assoc());
        $this->assertNull($r->fetch_assoc());

        // empty result set
        $r = $this->query('select ? from redcap_data where 2=3', [1]);
        $this->assertNull($r->fetch_assoc());
    }

    function test_fetch_row(){
        $r = $this->query('select ? union select ?', [1, 2]);
        $this->assertSame([0=>1], $r->fetch_row());
        $this->assertSame([0=>2], $r->fetch_row());
        $this->assertNull($r->fetch_row());

        // empty result set
        $r = $this->query('select ? from redcap_data where 2=3', [1]);
        $this->assertNull($r->fetch_row());
    }
}