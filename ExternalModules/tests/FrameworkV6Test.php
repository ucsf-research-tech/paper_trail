<?php
namespace ExternalModules;

class FrameworkV6Test extends FrameworkV5Test
{
	function testQueryLogs_parametersArgumentRequirement(){
        $this->assertThrowsException(function(){
            // Omitting the parameters argument should throw an exception
            $this->queryLogs('select 1');
        }, ExternalModules::tt('em_errors_117'));
    }
    
    function testRemoveLogs_parametersArgumentRequirement()
	{
        $this->assertThrowsException(function(){
            // Omitting the parameters argument should throw an exception
            $this->removeLogs('1 = 2');
        }, ExternalModules::tt('em_errors_117'));
	}
}