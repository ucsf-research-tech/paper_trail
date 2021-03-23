<?php namespace ExternalModules;
require_once ExternalModules::getTestVendorPath() . 'autoload.php';

use Exception;

// This class can be used by modules themselves to write their own tests.
abstract class ModuleBaseTest extends \PHPUnit\Framework\TestCase{
    public function setUp():void{
        if(!$this->module){
            $reflector = new \ReflectionClass(static::class);
            $moduleDirName = basename(dirname(dirname($reflector->getFileName())));
            list($prefix, $version) = ExternalModules::getParseModuleDirectoryPrefixAndVersion($moduleDirName);
            
            $this->module = ExternalModules::getModuleInstance($prefix, $version);
        }
    }

    function __call($methodName, $args){
        $returnValue = call_user_func_array(array($this->module, $methodName), $args);

        if($returnValue === false){
            throw new Exception("Either the '$methodName' does not exist, or it's return value is 'false'.  If it's return value is false, reference it directly using '\$this->module->$methodName()' instead of implicitly using '\$this->$methodName()'.");
        }

        return $returnValue;
	}
}
