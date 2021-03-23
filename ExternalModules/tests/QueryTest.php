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

    function testAffectedRows(){
        $assert = function($expected, $sql, $params){
            $q = $this->createQuery();
            $q->add($sql, $params);
            $q->execute();
            $this->assertSame($expected, $q->affected_rows);
        };

        // Should behave like $num_rows for selects.
        $assert(1, 'select ?', 1);
        $assert(0, 'select ? from (select 1) as fake_table where 1=2', 1);
        
        $moduleId = ExternalModules::getIdForPrefix(TEST_MODULE_PREFIX);
        $assertUpdate = function($expected, $value) use ($moduleId, $assert){
            $assert($expected, '
                update redcap_external_module_settings
                set value = ?
                where
                    external_module_id = ?
                    and project_id = ?
                    and `key` = ?
            ', [$value, $moduleId, TEST_SETTING_PID, TEST_SETTING_KEY]);
        };

        $this->setProjectSetting(1);
        $assertUpdate(0, 1);
        $assertUpdate(1, 2);
        
        $this->removeProjectSetting();
        $assertUpdate(0, 1);
    }
}