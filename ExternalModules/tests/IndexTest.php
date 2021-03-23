<?php
namespace ExternalModules;
require_once 'BaseTest.php';

class IndexTest extends BaseTest
{
    private function assert($expectedExcerpt = null){
        $require = function(){
            require __DIR__ . '/../index.php';
        };

        if($expectedExcerpt){
            parent::assertThrowsException($require, $expectedExcerpt);
        }
        else{
            $require();
        }
    }

    function testIndex(){
        $this->assert(ExternalModules::tt('em_errors_123'));

        $prefix = 'some_disabled_prefix';
        $_GET['prefix'] = $prefix;
        $this->assert(ExternalModules::tt('em_errors_124', $prefix));

        $prefix = TEST_MODULE_PREFIX;
        $_GET['prefix'] = TEST_MODULE_PREFIX;
        $_GET['NOAUTH'] = '';
        $this->assert(ExternalModules::tt('em_errors_125', $prefix));

        $pid = TEST_SETTING_PID;
        $_GET['pid'] = TEST_SETTING_PID;
        unset($_GET['NOAUTH']);
        $this->assert(ExternalModules::tt('em_errors_126', $prefix, $pid));

        unset($_GET['pid']);
        $page = 'some_page_that_does_not_exist';
        $_GET['page'] = $page;
        $this->assert(ExternalModules::tt('em_errors_127', $prefix, $page));

        $page = 'unit_test_page';
        $_GET['page'] = $page;
        $this->assert();
        
        $m = $this->getInstance();
        $m->setLinkCheckDisplayReturnValue(false);
        $this->assert();

        $this->setConfig([
            'links' => [
                'control-center' => [
                    [
                        'name' => 'Unit Test Page',
                        'url' => $page
                    ]
                ]
            ]
        ]);
        $this->assert(ExternalModules::tt('em_errors_128'));

        $m->setLinkCheckDisplayReturnValue(true);
        $this->assert();
    }
}