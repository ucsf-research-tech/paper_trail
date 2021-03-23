<?php namespace ExternalModules;

// move to own file, adding methods keeps confusing me
class TestModule extends AbstractExternalModule {

	public $testHookArguments;
	private $settingKeyPrefix;

	function __construct()
	{
		$this->PREFIX = TEST_MODULE_PREFIX;
		$this->VERSION = TEST_MODULE_VERSION;

		parent::__construct();
	}

	function getModulePath()
	{
		return __DIR__;
	}

	function redcap_test()
	{
		$this->testHookArguments = func_get_args();
	}

	function redcap_test_call_function($function = null){
		// We must check if the arg is callable b/c it could be cron attributes for a cron job.
		if(!is_callable($function)){
			$function = $this->function;
		}

		$function($this);
	}
	
	function redcap_every_page_test()
	{
		call_user_func_array([$this, 'redcap_test'], func_get_args());
	}

	function redcap_save_record()
	{
		$this->recordIdFromGetRecordId = $this->getRecordId();
	}

	protected function getSettingKeyPrefix()
	{
		if($this->settingKeyPrefix){
			return $this->settingKeyPrefix;
		}
		else{
			return parent::getSettingKeyPrefix();
		}
	}

	function setSettingKeyPrefix($settingKeyPrefix)
	{
		$this->settingKeyPrefix = $settingKeyPrefix;
	}

	function redcap_module_link_check_display($project_id, $link){
		if($this->linkCheckDisplayReturnValue !== null){
			return $this->linkCheckDisplayReturnValue;
		}

		return parent::redcap_module_link_check_display($project_id, $link);
	}

	function setLinkCheckDisplayReturnValue($value){
		$this->linkCheckDisplayReturnValue = $value;
	}
}
