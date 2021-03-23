<?php namespace ExternalModules;

class HookRunner
{
    private $name;
    private $startTime;
    private $exitAfterHook = false;
    
    # index is hook $name, then $prefix, then $version
    private $delayed = [];
    private $delayedLastRun = false;

    function __construct($name)
    {
        $this->name = $name;
        $this->startTime = time();
    }

    public function getName(){
        return $this->name;
    }

    public function getStartTime(){
        return $this->startTime;
    }

    public function isExitAfterHook(){
        return $this->exitAfterHook;
    }

    public function setExitAfterHook($value){
        $this->exitAfterHook = $value;
    }

    public function getDelayed(){
        return $this->delayed;
    }

    public function clearDelayed(){
        $this->delayed = [];
    }

    public function setDelayedLastRun($delayedLastRun){
        $this->delayedLastRun = $delayedLastRun;
    }

    # places module in delaying queue to be executed after all others are executed
	public function delayModuleExecution($prefix, $version) {
		$this->delayed[$prefix] = $version;
		return !$this->delayedLastRun;
	}
}