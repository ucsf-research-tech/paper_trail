<?php
namespace ExternalModules;

const TEST_FORM = 'test_form';
const TEST_RECORD_ID = 'test_record_id';
const TEST_TEXT_FIELD = 'test_text_field';
const TEST_SQL_FIELD = 'test_sql_field';
const TEST_REPEATING_FORM = 'test_repeating_form';
const TEST_REPEATING_FIELD_1 = 'test_repeating_field_1';
const TEST_REPEATING_FIELD_2 = 'test_repeating_field_2';
const TEST_REPEATING_FIELD_3 = 'test_repeating_field_3';

// These were added simply to avoid warnings from REDCap code.
$_SERVER['SERVER_NAME'] = 'unit testing';
$_SERVER['REMOTE_ADDR'] = 'unit testing';
if(!defined('PAGE')){
	define('PAGE', 'unit testing');
}

require_once __DIR__ . '/../redcap_connect.php';

use PHPUnit\Framework\TestCase;
use \Exception;
use REDCap;

const TEST_MODULE_PREFIX = ExternalModules::TEST_MODULE_PREFIX;
const TEST_MODULE_VERSION = ExternalModules::TEST_MODULE_VERSION;
const TEST_LOG_MESSAGE = 'This is a unit test log message';
const TEST_SETTING_KEY = 'unit-test-setting-key';
const FILE_SETTING_KEY = 'unit-test-file-setting-key';

require_once ExternalModules::getTestModuleDirectoryPath() . '/TestModule.php';

$testPIDs = ExternalModules::getTestPIDs();
define('TEST_SETTING_PID', $testPIDs[0]);
define('TEST_SETTING_PID_2', $testPIDs[1]);

abstract class BaseTest extends TestCase
{
	protected $backupGlobals = FALSE;

	private static $testModuleInstance;
	private static $testProjectsInitialized;

	public static function setUpBeforeClass():void{
		ExternalModules::initialize();

		$m = new TestModule();
		list($surveyId, $formName) = $m->getSurveyId(TEST_SETTING_PID);
		if(empty($surveyId)){
			ExternalModules::query("
				insert into redcap_surveys (project_id, form_name)
				values (?, (
					select form_name from redcap_metadata where project_id = ? limit 1
				))	
			", [TEST_SETTING_PID, TEST_SETTING_PID]);
		}
	}

	private function getEventId($projectId){	
		$sql = '	
			select event_id	
			from redcap_events_arms a	
			join redcap_events_metadata m	
				on m.arm_id = a.arm_id	
			where project_id = ?	
		';	

		$result = self::query($sql, $projectId);	
		$row = $result->fetch_assoc();	

		if($result->fetch_assoc()){	
			throw new Exception("Multiple event IDs found from project $projectId");	
		}	

		return $row['event_id'];	
    }

	protected function setUp():void{
		self::$testModuleInstance = new TestModule();
		self::setExternalModulesProperty('systemwideEnabledVersions', [TEST_MODULE_PREFIX => TEST_MODULE_VERSION]);
		self::cleanupSettings();

		if(!self::$testProjectsInitialized){
			$this->getFramework()->importDataDictionary(TEST_SETTING_PID, __DIR__ . '/test-project-data-dictionary.csv');
			$this->getFramework()->importDataDictionary(TEST_SETTING_PID_2, __DIR__ . '/test-project-data-dictionary.csv');

			$eventId = $this->getEventId(TEST_SETTING_PID);
			ExternalModules::query('delete from redcap_events_repeat where event_id = ? and form_name = ?', [$eventId, TEST_REPEATING_FORM]);
			ExternalModules::query('insert into redcap_events_repeat values (?, ?, null)', [$eventId, TEST_REPEATING_FORM]);
			
			self::$testProjectsInitialized = true;
		}

		$m = $this->getInstance();

		// Clear the data between tests
		foreach(ExternalModules::getTestPIDs() as $pid){
			self::query('delete from redcap_data where project_id = ?', $pid);
			self::query('delete from redcap_record_list where project_id = ?', $pid);
			self::query("update redcap_record_counts set record_count = 0, time_of_count = ? where project_id = ?", [NOW, $pid]);

			// This will only remove logs for this module.
			$m->removeLogs('1 = 1', []);
		}
	}

	function getFrameworkVersion(){
		return 3;
	}

	protected function tearDown():void
	{
		self::cleanupSettings();
		$this->setActiveModulePrefix(null);
	}

	private function cleanupSettings()
	{
		$this->setConfig([
			'framework-version' => $this->getFrameworkVersion(),
			'permissions' => ['redcap_test_call_function'] // Give permissions to a hook used by multiple tests.
		]);

		$this->getInstance()->testHookArguments = null;

		$m = self::getInstance();
		$moduleId = ExternalModules::getIdForPrefix(TEST_MODULE_PREFIX);
		$lockName = ExternalModules::getLockName($moduleId, TEST_SETTING_PID);

		$m->query("SELECT GET_LOCK(?, 5)", [$lockName]);
		$m->query("delete from redcap_external_module_settings where external_module_id = ?", [$moduleId]);
		$m->query("SELECT RELEASE_LOCK(?)", [$lockName]);

		$_GET = [];
		$_POST = [];

		ExternalModules::setSuperUser(true);
		ExternalModules::setUsername(null);
	}

	protected function setSystemSetting($value)
	{
		self::getInstance()->setSystemSetting(TEST_SETTING_KEY, $value);
	}

	protected function getSystemSetting()
	{
		return self::getInstance()->getSystemSetting(TEST_SETTING_KEY);
	}

	protected function removeSystemSetting()
	{
		self::getInstance()->removeSystemSetting(TEST_SETTING_KEY);
	}

	protected function setProjectSetting($value)
	{
		self::getInstance()->setProjectSetting(TEST_SETTING_KEY, $value, TEST_SETTING_PID);
	}

	protected function getProjectSetting()
	{
		return self::getInstance()->getProjectSetting(TEST_SETTING_KEY, TEST_SETTING_PID);
	}

	protected function removeProjectSetting()
	{
		self::getInstance()->removeProjectSetting(TEST_SETTING_KEY, TEST_SETTING_PID);
	}

	protected function getInstance()
	{
		return self::$testModuleInstance;
	}

	protected function setConfig($config)
	{
		if(gettype($config) === 'string'){
			$config = json_decode($config, true);
			if($config === null){
				throw new Exception("Error parsing json configuration (it's likely not valid json).");
			}
		}

		ExternalModules::setCachedConfig(TEST_MODULE_PREFIX, TEST_MODULE_VERSION, false, $config);
		ExternalModules::setCachedConfig(TEST_MODULE_PREFIX, TEST_MODULE_VERSION, true, ExternalModules::translateConfig($config, $prefix));

		// Re-initialize the framework in case the version changed.
		$frameworkInstance = ExternalModules::getFrameworkInstance(TEST_MODULE_PREFIX, TEST_MODULE_VERSION);
		$this->callPrivateMethodForClass($frameworkInstance, 'initialize');
	}

	private function setExternalModulesProperty($name, $value)
	{
		$externalModulesClass = new \ReflectionClass("ExternalModules\\ExternalModules");
		$configsProperty = $externalModulesClass->getProperty($name);
		$configsProperty->setAccessible(true);
		$configsProperty->setValue($value);
	}

	protected function assertThrowsException($callable, $exceptionExcerpt)
	{
		$exceptionThrown = false;
		try{
			$callable();
		}
		catch(Exception $e){
			if(empty($exceptionExcerpt)){
				throw new Exception('You must specify an exception excerpt!  Here\'s a hint: ' . $e->getMessage());
			}
			else if(strpos($e->getMessage(), $exceptionExcerpt) === false){
				throw new Exception("Could not find the string '$exceptionExcerpt' in the following exception message: " . $e->getMessage() . "\n\n" . $e->getTraceAsString());
			}

			$exceptionThrown = true;
		}

		$this->assertTrue($exceptionThrown, "An exception was not thrown where one was expected containing the following text: $exceptionExcerpt");
	}

	protected function callPrivateMethod($methodName)
	{
		$args = func_get_args();
		array_unshift($args, $this->getReflectionClass());

		return call_user_func_array([$this, 'callPrivateMethodForClass'], $args);
	}

	protected function callPrivateMethodForClass()
	{
		$args = func_get_args();
		$classInstanceOrName = array_shift($args); // remove the $classInstanceOrName
		$methodName = array_shift($args); // remove the $methodName

		if(gettype($classInstanceOrName) == 'string'){
			$instance = null;
		}
		else{
			$instance = $classInstanceOrName;
		}

		$class = new \ReflectionClass($classInstanceOrName);
		$method = $class->getMethod($methodName);
		$method->setAccessible(true);

		return $method->invokeArgs($instance, $args);
	}

	protected function getPrivateVariable($name)
	{
		$class = new \ReflectionClass($this->getReflectionClass());
		$property = $class->getProperty($name);
		$property->setAccessible(true);

		return $property->getValue($this->getReflectionClass());
	}

	protected function setPrivateVariable($name, $value, $target = null)
	{
		if(!$target){
			$target = $this->getReflectionClass();
		}
		
		$class = new \ReflectionClass($target);
		$property = $class->getProperty($name);
		$property->setAccessible(true);

		return $property->setValue($this->getReflectionClass(), $value);
	}

	protected function getReflectionClass()
	{
		return $this->getInstance();
    }

	protected function runConcurrentTestProcesses($functionName, $parentAction, $childAction)
	{
		// The parenthesis are included in the argument and check below so we can still filter for this function manually (WITHOUT the parenthesis)  when testing for testing and avoid triggering the recursion.
		$functionName .= '()';

		global $argv;
		if(end($argv) === $functionName){
			// This is the child process.
			$childAction();
		}
		else{
			// This is the parent process.

			$cmd = "php " . ExternalModules::getPHPUnitPath() . " --filter " . escapeshellarg($functionName);
			$childProcess = proc_open(
				$cmd, [
					0 => ['pipe', 'r'],
					1 => ['pipe', 'w'],
					2 => ['pipe', 'w'],
				],
				$pipes
			);

			// Gets the child status, but caches the final result since calling proc_get_status() multiple times
			// after a process ends will incorrectly return -1 for the exit code.
			$getChildStatus = function() use ($childProcess, &$lastStatus){
				if(!$lastStatus || $lastStatus['running']){
					$lastStatus = proc_get_status($childProcess);
				}

				return $lastStatus;
			};

			$isChildRunning = function() use ($getChildStatus){
				$status = $getChildStatus();
				return $status['running'];
			};

			$parentAction($isChildRunning);

			while($isChildRunning()){
				// The parent finished before the child.
				// Wait for the child to finish before continuing so that the exit code can be checked below.
				sleep(.1);
			}

			$status = $getChildStatus();
			$exitCode = $status['exitcode'];
			if($exitCode !== 0){
				$output = stream_get_contents($pipes[1]);
				throw new Exception("The child phpunit process for the $functionName test failed with exit code $exitCode and the following output: $output");
			}
		}
	}

	function ensureRecordExists($recordId, $pid = TEST_SETTING_PID){
		REDCap::saveData($pid, 'json', json_encode([[
			$this->getFramework()->getRecordIdField($pid) => $recordId,
		]]));
	}

	function getFramework(){
		return ExternalModules::getFrameworkInstance($this->getInstance()->PREFIX);
	}

	function __call($methodName, $args){
		// WARNING: This only works for public methods.
		// Private methods will appear to work and return false, but won't actually get called.
		return call_user_func_array(array($this->getReflectionClass(), $methodName), $args);
	}

	function getActiveModulePrefix(){
		// Call this on the ExternalModules class no matter what test it is called from.
		return $this->callPrivateMethodForClass('ExternalModules\ExternalModules', 'getActiveModulePrefix');
	}

	function setActiveModulePrefix($prefix){
		// Call this on the ExternalModules class no matter what test it is called from.
		return $this->callPrivateMethodForClass('ExternalModules\ExternalModules', 'setActiveModulePrefix', $prefix);
	}

	function getRandomUsernames($limit = 10)
	{
		$result = ExternalModules::query('select username from redcap_user_information order by rand() limit ?', [$limit]);
		
		$usernames = [];
		while($row = $result->fetch_assoc()){
			$usernames[] = $row['username'];
		}

		return $usernames;
	}

	function getRandomUsername()
	{
		return $this->getRandomUsernames(1)[0];
	}

	function spoofTranslation($prefix, $key, $value)
	{
		global $lang;

		if(!empty($prefix)){
			$key = ExternalModules::constructLanguageKey($prefix, $key);
		}

		return $lang[$key] = $value;
	}
}
