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

    function test_fetch_array(){
        $r = $this->query('select ? union select ? union select ? union select ?', [1, 2, 3, 4]);
        $this->assertSame([0=>1, '?'=>1], $r->fetch_array());
        $this->assertSame([0=>2, '?'=>2], $r->fetch_array(MYSQLI_BOTH));
        $this->assertSame([0=>3], $r->fetch_array(MYSQLI_NUM));
        $this->assertSame(['?'=>4], $r->fetch_array(MYSQLI_ASSOC));
        $this->assertNull($r->fetch_row());

        // empty result set
        $r = $this->query('select ? from redcap_data where 2=3', [1]);
        $this->assertNull($r->fetch_array());
    }

    function test_fetch_fields(){
        $fetchFields = function($sql, $params){
            $result = $this->query($sql, $params);
            $fields = $result->fetch_fields();

            foreach($fields as $field){
                // These values are different when using a prepared statement.
                unset($field->length);
                unset($field->max_length);
            }
        };

        $expected = $fetchFields('select 1 as a', []);
        $actual = $fetchFields('select ? as a', [1]);

        $this->assertSame($expected, $actual);
    }

    function test_free_result(){
        $result = $this->query('select ?', 1);

        // Just make sure it runs without exception.
        $result->free_result();
    }
}