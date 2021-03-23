<?php namespace ExternalModules;
require_once APP_PATH_DOCROOT . '/Tests/DBFunctionsTest.php';

// We extend the REDCap core DB function test class
// so that those tests run whenever external module tests run.
// They include some StatementResult assertions that this test doesn't.
class StatementResultTest extends BaseTest
{
    function test_num_rows(){
        $r = ExternalModules::query('select ? union select ? union select ?', [1, 2, 3]);
        $this->assertSame(3, $r->num_rows);

        // empty result set
        $r = ExternalModules::query('select ? from redcap_data where 2=3', [1]);
        $this->assertSame(0, $r->num_rows);
    }

    function test_current_field(){
        $result = ExternalModules::query('select ?,?', [1,2]);
        
        $this->assertSame(0, $result->current_field);
        $result->fetch_field();
        $this->assertSame(1, $result->current_field);
        $result->fetch_field();
        $this->assertSame(2, $result->current_field);
    }

    function test_field_count(){
        $result = ExternalModules::query('select ?,?,?,?,?', [1,2,3,4,5]);
        $this->assertSame(5, $result->field_count);
    }

    function test_lengths(){
        $result = ExternalModules::query("select ?,? union select ?,?", [1, 1.1, 'aa', null]);
        
        $result->fetch_row();
        $this->assertSame([1,3], $result->lengths);

        $result->fetch_row();
        $this->assertSame([2,0], $result->lengths);
    }

    function test_fetch_field(){
        $result = ExternalModules::query('select ? as a, ? as b', [1,2]);
        $this->assertSame('a', $result->fetch_field()->name);
        $this->assertSame('b', $result->fetch_field()->name);
        $this->assertNull($result->fetch_field());
    }

    function test_fetch_assoc(){
        $r = ExternalModules::query('select ? as foo union select ?', [1, 2]);
        $this->assertSame(['foo'=>1], $r->fetch_assoc());
        $this->assertSame(['foo'=>2], $r->fetch_assoc());
        $this->assertNull($r->fetch_assoc());

        // empty result set
        $r = ExternalModules::query('select ? from redcap_data where 2=3', [1]);
        $this->assertNull($r->fetch_assoc());
    }

    function test_fetch_row(){
        $r = ExternalModules::query('select ? union select ?', [1, 2]);
        $this->assertSame([0=>1], $r->fetch_row());
        $this->assertSame([0=>2], $r->fetch_row());
        $this->assertNull($r->fetch_row());

        // empty result set
        $r = ExternalModules::query('select ? from redcap_data where 2=3', [1]);
        $this->assertNull($r->fetch_row());
    }

    function test_fetch_array(){
        $r = ExternalModules::query('select ? union select ? union select ? union select ?', [1, 2, 3, 4]);
        $this->assertSame([0=>1, '?'=>1], $r->fetch_array());
        $this->assertSame([0=>2, '?'=>2], $r->fetch_array(MYSQLI_BOTH));
        $this->assertSame([0=>3], $r->fetch_array(MYSQLI_NUM));
        $this->assertSame(['?'=>4], $r->fetch_array(MYSQLI_ASSOC));
        $this->assertNull($r->fetch_array());

        // empty result set
        $r = ExternalModules::query('select ? from redcap_data where 2=3', [1]);
        $this->assertNull($r->fetch_array());
    }

    function test_db_fetch_field_direct(){
        $fetchField = function($sql, $params){
            $result = ExternalModules::query($sql, $params);
            $field = $result->fetch_field_direct(0);

            $this->normalizeField($field);

            return $field;
        };

        $expected = $fetchField('select 1 as a', []);
        $actual = $fetchField('select ? as a', [1]);

        $this->assertSame('a', $actual->name);
        $this->assertEquals($expected, $actual);
    }

    function test_fetch_fields(){
        $fetchFields = function($sql, $params){
            $result = ExternalModules::query($sql, $params);
            $fields = $result->fetch_fields();

            foreach($fields as $field){
                $this->normalizeField($field);
            }

            return $fields;
        };

        $expected = $fetchFields('select 1 as a', []);
        $actual = $fetchFields('select ? as a', [1]);

        $this->assertSame('a', $actual[0]->name);
        $this->assertEquals($expected, $actual);
    }

    function test_data_seek(){
        $r = ExternalModules::query('select ? as a', [1]);
        $this->assertSame([0=>1], $r->fetch_row());
        $r->data_seek(0);
        $this->assertSame([0=>1], $r->fetch_row());
    }

    function test_fetch_object(){
        $r = ExternalModules::query("select 'a' as b", []);
        $expected = $r->fetch_object();
        
        $r = ExternalModules::query('select ? as b', ['a']);
        $actual = $r->fetch_object();

        $this->assertEquals($expected, $actual);
        $this->assertNull($r->fetch_object());
    }

    function test_fetch_object_constructor_args(){
        $class = FetchObject::class;
        $constructorArgs = [rand(), rand(), rand()];

        $r = ExternalModules::query("select 'a' as b", []);
        $expected = $r->fetch_object($class, $constructorArgs);
        
        $r = ExternalModules::query('select ? as b', ['a']);
        $actual = $r->fetch_object($class, $constructorArgs);
        
        $this->assertEquals($expected, $actual);
    }

    private function normalizeField(&$field){
        // These values are different when using a prepared statement.
        unset($field->length);
        unset($field->max_length);
    }

    function test_free_result(){
        $result = ExternalModules::query('select ?', 1);

        // Just make sure they run without exception.
        $result->free();
        $result->close();
        $result->free_result();

        $this->expectNotToPerformAssertions();
    }
}

class FetchObject{
    function __construct(){
        $this->constructorArgs = func_get_args();
    }
}