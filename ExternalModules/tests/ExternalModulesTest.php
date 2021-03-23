<?php
namespace ExternalModules;
require_once 'BaseTest.php';

use \Exception;

class ExternalModulesTest extends BaseTest
{
	public static $exitAfterHookAction;

	const TABLED_CRON_EXAMPLE = '{
		"cron_name": "schedulednotifications",
		"cron_description": "Daily cron to generate new notifications",
		"method": "scheduledNotifications",
		"cron_frequency": "3600",
		"cron_max_run_time": "300"
	}';

	const TIMED_CRON_EXAMPLE = '{
		"cron_name": "cron4",
		"cron_description": "Cron that runs on Mondays at 2:25 pm to do YYYY",
		"method": "some_other_method_4",
		"cron_hour": 14,
		"cron_minute": 25,
		"cron_weekday": 1
	}';

	public static $lastSendAdminEmailArgs;

	protected function getReflectionClass()
	{
		return 'ExternalModules\ExternalModules';
	}

	protected function setUp():void
	{
		parent::setUp();

		// Loading this dependency doesn't work at the top of this file.  Not sure why...
		require_once __DIR__ . '/../vendor/squizlabs/php_codesniffer/autoload.php';
	}

	protected function tearDown():void
	{
		self::$lastSendAdminEmailArgs = null;
		parent::tearDown();
	}

	function testInitializeSettingDefaults()
	{
		$defaultValue = rand();

		$this->setConfig([
			'system-settings' => [
				[
					'key' => TEST_SETTING_KEY,
					'default' => $defaultValue
				]
			]
		]);

		$m = $this->getInstance();

		$this->assertNull($this->getSystemSetting());
		ExternalModules::initializeSettingDefaults($m);
		$this->assertSame($defaultValue, $this->getSystemSetting());

		// Make sure defaults do NOT overwrite any existing settings.
		$this->setSystemSetting(rand());
		ExternalModules::initializeSettingDefaults($m);
		$this->assertNotEquals($defaultValue, $this->getSystemSetting());
	}

	function testQuery()
	{
		// The query() method is more thoroughly tested in FrameworkV1Test.
		// We do a basic test here just to make sure any refactoring is caught.
		$value = rand();
		$r = ExternalModules::query('select ?', $value);
		$this->assertSame($value, $r->fetch_row()[0]);
	}

	function testCheckCronModifications()
	{
		$prefix = self::getInstance()->PREFIX;
		$cronAttr1 = array("cron_name" => "Test Cron 1", "cron_description" => "Test", "method" => "testMethod1", "cron_hour" => 1, "cron_minute" => 0);
		$cronAttr2 = array("cron_name" => "Test Cron 2", "cron_description" => "Test", "method" => "testMethod2", "cron_hour" => 2, "cron_minute" => 0);
		$cronAttr3 = array("cron_name" => "Test Cron 3", "cron_description" => "Test", "method" => "testMethod3", "cron_hour" => 3);
		$cronAttr4 = array("cron_name" => "Test Cron 4", "cron_description" => "Test", "method" => "testMethod4", "cron_weekday" => "2", "cron_hour" => 4, "cron_minute" => 0);
		$cronAttr5 = array("cron_name" => "Test Cron 5", "cron_description" => "Test", "method" => "testMethod5", "cron_monthday" => "1", "cron_hour" => 5, "cron_minute" => 0);
		$validCrons = array($cronAttr1, $cronAttr2, $cronAttr4, $cronAttr5);
		$invalidCrons = array($cronAttr1, $cronAttr2, $cronAttr3, $cronAttr4, $cronAttr5);

		ExternalModules::setModifiedCrons($prefix, $validCrons);
		$crons = ExternalModules::getModifiedCrons($prefix);
		$this->assertTrue($crons == $validCrons);

		$this->assertThrowsException(function() use ($invalidCrons, $prefix){
			ExternalModules::setModifiedCrons($prefix, $invalidCrons);
		}, "A cron is not valid! ".json_encode($cronAttr3));
		$crons = ExternalModules::getModifiedCrons($prefix);
		$this->assertTrue($crons != $invalidCrons);

		ExternalModules::removeModifiedCrons($prefix);
		$crons = ExternalModules::getModifiedCrons($prefix);
		$this->assertTrue(empty($crons));

		# check for config backup
		$config = [
			'system-settings' => [
				['key' => 'key1']
			],
			'project-settings' => [
				['key' => 'key-two']
			],
			'crons' => [
				[
					'cron_name' => 'Test Cron 10',
					'method' => 'testMethod10',
					'cron_description' => "descript",
					'cron_hour' => 10,
					'cron_minute' => 0,
				],
			],
		];

		$newCron = [
			'cron_name' => 'Test Cron 11',
			'method' => 'testMethod11',
			'cron_description' => "descript",
			'cron_hour' => 11,
			'cron_minute' => 0,
		];

		ExternalModules::removeModifiedCrons($prefix);
		$this->setConfig($config);
		$crons = ExternalModules::getCronSchedules($prefix);
		$this->assertTrue($crons == $config['crons']);
		ExternalModules::setModifiedCrons($prefix, $validCrons);
		$crons = ExternalModules::getCronSchedules($prefix);
		$modifiedCrons = ExternalModules::getModifiedCrons($prefix);
		$this->assertTrue($crons != $config['crons']);
		$this->assertTrue(in_array($cronAttr1, $crons));
		$this->assertTrue(in_array($cronAttr2, $crons));
		$this->assertTrue(in_array($cronAttr4, $crons));
		$this->assertTrue(in_array($cronAttr5, $crons));

		# set new crons
		array_push($config['crons'], $newCron);
		$this->setConfig($config);
		$crons = ExternalModules::getCronSchedules($prefix);
		$this->assertTrue($crons != $config['crons']);
		$this->assertTrue(in_array($cronAttr1, $crons));
		$this->assertTrue(in_array($cronAttr2, $crons));
		$this->assertTrue(in_array($cronAttr4, $crons));
		$this->assertTrue(in_array($cronAttr5, $crons));
	}


	function testGetProjectSettingsAsArray_systemOnly()
	{
		$value = rand();
		$this->setSystemSetting($value);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($value, $array[TEST_SETTING_KEY]['value']);
		$this->assertSame($value, $array[TEST_SETTING_KEY]['system_value']);
	}

	function testGetProjectSettingsAsArray_projectOnly()
	{
		$value = rand();
		$this->setProjectSetting($value);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($value, $array[TEST_SETTING_KEY]['value']);
		$this->assertSame(null, @$array[TEST_SETTING_KEY]['system_value']);
	}

	function testGetProjectSettingsAsArray_both()
	{
		$systemValue = rand();
		$projectValue = rand();

		$this->setSystemSetting($systemValue);
		$this->setProjectSetting($projectValue);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($projectValue, $array[TEST_SETTING_KEY]['value']);
		$this->assertSame($systemValue, $array[TEST_SETTING_KEY]['system_value']);

		// Re-test reversing the insert order to make sure it doesn't matter.
		$this->setProjectSetting($projectValue);
		$this->setSystemSetting($systemValue);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($projectValue, $array[TEST_SETTING_KEY]['value']);
		$this->assertSame($systemValue, $array[TEST_SETTING_KEY]['system_value']);
	}

	function testGetSystemSettingsAsArray_noPrefix()
	{
		$this->assertThrowsException(function(){
			ExternalModules::getSystemSettingsAsArray(null);
		}, 'One or more module prefixes must be specified!');
	}

	function testIsTimeToRun()
	{
		$method = 'isTimeToRun';

		$offsets = array(
				"nul" => "assertTrue",
				"addPT1H" => "assertFalse",
				"subPT1H" => "assertFalse",
				"addPT1M" => "assertFalse",
				"subPT1M" => "assertFalse",
				"addP1D" => "assertTrue",
				"subP1D" => "assertTrue",
				);
		$defaultCron = array(
					'cron_name' => 'test_name',
					'cron_description' => 'This is a test',
					'method' => 'test_method',
					);

		foreach ($offsets as $displacement => $validationMethod) {
			$currentTime = time();
			if($currentTime%60 >= 59){
				// We don't want the clock to turn over to the next minute in the middle of this test.
				// Go ahead and wait for the next minute to come to ensure the test always passes.
				sleep(1);
				$currentTime = time();
			}

			// Simulate the process starting now.
			$_SERVER["REQUEST_TIME_FLOAT"] = microtime(true);

			$func = substr($displacement, 0, 3);
			$offset = substr($displacement, 3);

			$datetime = new \DateTime();
			if ($func != "nul") {
				$datetime->$func(new \DateInterval($offset));
			}
			$cron = array(
					'cron_hour' => $datetime->format("G"),
					'cron_minute' => $datetime->format("i"),
					);
			$this->$validationMethod(self::callPrivateMethod($method, array_merge($defaultCron, $cron)));
		}

		# move forward one day => should fail on weekday
		$datetime2 = new \DateTime();
		$datetime2->add(new \DateInterval("P1D"));
		$cron2 = array(
				'cron_hour' => $datetime2->format("G"),
				'cron_minute' => $datetime2->format("i"),
				'cron_weekday' => $datetime2->format("w"),
				);
		$this->assertFalse(self::callPrivateMethod($method, array_merge($defaultCron, $cron2)));

		# move forward one week => should call cron on weekday but not monthday
		$datetime3 = new \DateTime();
		$datetime3->add(new \DateInterval("P7D"));
		$cron3 = array(
				'cron_hour' => $datetime3->format("G"),
				'cron_minute' => $datetime3->format("i"),
				'cron_weekday' => $datetime3->format("w"),
				);
		$this->assertTrue(self::callPrivateMethod($method, array_merge($defaultCron, $cron3)));

		$cron3_2 = array(
				'cron_hour' => $datetime3->format("G"),
				'cron_minute' => $datetime3->format("i"),
				'cron_monthday' => $datetime3->format("j"),
				);
		$this->assertFalse(self::callPrivateMethod($method, array_merge($defaultCron, $cron3_2)));
	}

	function testCallTimedCronMethod_concurrency()
	{
		$methodName = 'redcap_test_call_function';

		$this->setConfig(['crons' => json_decode(self::TIMED_CRON_EXAMPLE)]);

		$callCronMethod = function($action) use ($methodName){
			$m = $this->getInstance();
			$m->function = $action;

			self::callPrivateMethod('callTimedCronMethod', TEST_MODULE_PREFIX, $methodName);
		};

		$childAction = function() use ($callCronMethod){
			$assertConcurrentCallSkipped = function($expectedEmailSubject) use ($callCronMethod){
				$callCronMethod(function(){
					throw new Exception('This cron call should have been automatically skipped due to another recent cron call running.');
				});

				$this->assertSame($expectedEmailSubject, ExternalModulesTest::$lastSendAdminEmailArgs[0]);
			};

			$assertConcurrentCallSkipped(null);

			// See the comment in checkForALongRunningCronJob() to understand why we test a little less than a day long period.
			$aLittleLessThanADay = ExternalModules::DAY_IN_SECONDS - ExternalModules::MINUTE_IN_SECONDS*5;

			$lockInfo = self::callPrivateMethod('getCronLockInfo', TEST_MODULE_PREFIX);
			$lockInfo['time'] = time() - $aLittleLessThanADay;
			ExternalModules::setSystemSetting(TEST_MODULE_PREFIX, ExternalModules::KEY_RESERVED_IS_CRON_RUNNING, $lockInfo);
			//= External Module Long-Running Cron
			$assertConcurrentCallSkipped(ExternalModules::tt("em_errors_100")); 
		};

		$parentAction = function($isChildRunning) use ($callCronMethod){
			$callCronMethod(function() use ($isChildRunning){
				while($isChildRunning()){
					sleep(.1);
				}
			});
		};

		$this->runConcurrentTestProcesses(__FUNCTION__, $parentAction, $childAction);
		$this->expectNotToPerformAssertions();
	}

	function testCallCronMethod_unlockOnException()
	{
		$this->runTestCronMethod(function(){
			throw new Exception();
		});

		//= External Module Exception in Cron Job
		$emailSubject = ExternalModules::tt("em_errors_56"); 
		$this->assertSame($emailSubject, ExternalModulesTest::$lastSendAdminEmailArgs[0]);

		$secondCronRan = false;
		$this->runTestCronMethod(function() use (&$secondCronRan){
			$secondCronRan = true;
		});
		$this->assertTrue($secondCronRan); // Make sure the second cron ran, meaning the cron was unlocked after the exception.
	}

	function testCallCronMethod_parameterSafety()
	{
		$this->runTestCronMethod(function(){
			// Simulate a module setting the pid within a cron, so we can assert that it doesn't make a difference outside the cron method.
			$_GET['pid'] = '123';
		});

		$this->assertFalse(isset($_GET['pid']));
	}

	private function runTestCronMethod($function)
	{
		$methodName = 'redcap_test_call_function';

		$this->setConfig(['crons' => [[
			'cron_name' => $methodName,
			'cron_description' => 'Test Cron',
			'method' => $methodName,
		]]]);

		$m = $this->getInstance();
		$m->function = $function;

		$moduleId = ExternalModules::getIdForPrefix(TEST_MODULE_PREFIX);
		ExternalModules::callCronMethod($moduleId, $methodName);
	}

	function testCheckForALongRunningCronJob()
	{
		//= External Module Long-Running Cron
		$longRunningCronEmailSubject = ExternalModules::tt("em_errors_100"); 
		$assertLongRunningCronEmailSent = function($expected, $lockTime) use ($longRunningCronEmailSubject){
			ExternalModulesTest::$lastSendAdminEmailArgs = null;
			self::callPrivateMethod('checkForALongRunningCronJob', TEST_MODULE_PREFIX, null, ['time' => $lockTime]);
			$this->assertSame($expected, ExternalModulesTest::$lastSendAdminEmailArgs[0] === $longRunningCronEmailSubject);
		};

		// See the comment in checkForALongRunningCronJob() to understand why we test a little less than a day long period.
		$aLittleLessThanADayAgo = time() - ExternalModules::DAY_IN_SECONDS + ExternalModules::MINUTE_IN_SECONDS*5;

		$assertLongRunningCronEmailSent(false, time() - ExternalModules::HOUR_IN_SECONDS*22);
		$assertLongRunningCronEmailSent(true, $aLittleLessThanADayAgo);
		$assertLongRunningCronEmailSent(false, $aLittleLessThanADayAgo); // The email should not send again (yet).

		ExternalModules::setSystemSetting(TEST_MODULE_PREFIX, ExternalModules::KEY_RESERVED_LAST_LONG_RUNNING_CRON_NOTIFICATION_TIME, $aLittleLessThanADayAgo);
		$assertLongRunningCronEmailSent(true, $aLittleLessThanADayAgo);
	}

	function testResetCron() {
		// initial: no long-running rows deleted
		$affectedRows1 = ExternalModules::resetCron(TEST_MODULE_PREFIX);
		$this->assertSame($affectedRows1, 0);

		// long-running tripped; one long-running row deleted
		self::callPrivateMethod('lockCron', TEST_MODULE_PREFIX, null, ['time' => $initialLockTime]);
		$affectedRows2 = ExternalModules::resetCron(TEST_MODULE_PREFIX);
		$this->assertSame($affectedRows2, 1);

		// afterwards; no long-running rows deleted
		$affectedRows3 = ExternalModules::resetCron(TEST_MODULE_PREFIX);
		$this->assertSame($affectedRows3, 0);
	}

	function testAddReservedSettings()
	{
		$method = 'addReservedSettings';

		$this->assertThrowsException(function() use ($method){
			self::callPrivateMethod($method, array(
				'system-settings' => array(
					array('key' => ExternalModules::KEY_VERSION)
				)
			));
		}, 'reserved for internal use');

		$this->assertThrowsException(function() use ($method){
			self::callPrivateMethod($method, array(
				'project-settings' => array(
					array('key' => ExternalModules::KEY_ENABLED)
				)
			));
		}, 'reserved for internal use');

		// Make sure other settings are passed through without exception.
		$key = 'some-non-reserved-settings';
		$config = self::callPrivateMethod($method, array(
			'system-settings' => array(
				array('key' => $key)
			)
		));

		$systemSettings = $config['system-settings'];
		$this->assertSame(4, count($systemSettings));
		$this->assertSame(ExternalModules::KEY_ENABLED, $systemSettings[0]['key']);
		$this->assertSame(ExternalModules::KEY_DISCOVERABLE, $systemSettings[1]['key']);
		$this->assertSame(ExternalModules::KEY_USER_ACTIVATE_PERMISSION, $systemSettings[2]['key']);
		$this->assertSame($key, $systemSettings[3]['key']);

		// Ensure that empty configs aren't modified.
		$this->assertSame([], self::callPrivateMethod($method, []));
	}

	function testCacheAllEnableData()
	{
		$m = $this->getInstance();

		$version = rand();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, $version);

		self::callPrivateMethod('cacheAllEnableData');
		$this->assertSame($version, self::callPrivateMethod('getSystemwideEnabledVersions')[TEST_MODULE_PREFIX]);

		$m->removeSystemSetting(ExternalModules::KEY_VERSION);

		// the other values set by cacheAllEnableData() are tested via testGetEnabledModuleVersionsForProject()
	}

	function testOverwriteBlankSetting()
	{
		$m = $this->getInstance();

		$str = 'abc';
		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, '');
		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $str);

		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($str, $array[TEST_SETTING_KEY]['value']);
	}

	function testGetEnabledModules()
	{
		$this->cacheAllEnableData();
		$versionsByPrefix = ExternalModules::getEnabledModules();
		$this->assertNull(@$versionsByPrefix[TEST_MODULE_PREFIX]);
		$versionsByPrefix = ExternalModules::getEnabledModules(TEST_SETTING_PID);
		$this->assertNull(@$versionsByPrefix[TEST_MODULE_PREFIX]);

		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);

		$this->cacheAllEnableData();
		$versionsByPrefix = ExternalModules::getEnabledModules();
		$this->assertSame(TEST_MODULE_VERSION, $versionsByPrefix[TEST_MODULE_PREFIX]);
		$versionsByPrefix = ExternalModules::getEnabledModules(TEST_SETTING_PID);
		$this->assertNull(@$versionsByPrefix[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);

		$this->cacheAllEnableData();
		$versionsByPrefix = ExternalModules::getEnabledModules();
		$this->assertSame(TEST_MODULE_VERSION, $versionsByPrefix[TEST_MODULE_PREFIX]);
		$versionsByPrefix = ExternalModules::getEnabledModules(TEST_SETTING_PID);
		$this->assertSame(TEST_MODULE_VERSION, $versionsByPrefix[TEST_MODULE_PREFIX]);
	}

	function testGetEnabledModuleVersionsForProject_multiplePrefixesAndVersions()
	{
		$prefix1 = TEST_MODULE_PREFIX . '-1';
		$prefix2 = TEST_MODULE_PREFIX . '-2';

		ExternalModules::setSystemSetting($prefix1, ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		ExternalModules::setSystemSetting($prefix2, ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		ExternalModules::setSystemSetting($prefix1, ExternalModules::KEY_ENABLED, true);
		ExternalModules::setSystemSetting($prefix2, ExternalModules::KEY_ENABLED, true);

		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[$prefix1]);
		$this->assertNotNull($prefixes[$prefix2]);

		ExternalModules::removeSystemSetting($prefix2, ExternalModules::KEY_VERSION);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[$prefix1]);
		$this->assertNull(@$prefixes[$prefix2]);

		ExternalModules::removeSystemSetting($prefix1, ExternalModules::KEY_ENABLED);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull(@$prefixes[$prefix1]);

		ExternalModules::removeSystemSetting($prefix1, ExternalModules::KEY_VERSION);
		ExternalModules::removeSystemSetting($prefix2, ExternalModules::KEY_ENABLED);
	}

	function testGetEnabledModuleVersionsForProject_overrides()
	{
		$m = self::getInstance();

		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull(@$prefixes[TEST_MODULE_PREFIX]);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);


		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull(@$prefixes[TEST_MODULE_PREFIX]);

		$m->removeProjectSetting(ExternalModules::KEY_ENABLED, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, false);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull(@$prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNull(@$prefixes[TEST_MODULE_PREFIX]);
	}

	function testGetFileSettings() {
		$m = self::getInstance();					

		$edocIdSystem = (string) rand();
		$edocIdProject = (string) rand();

                # system
		ExternalModules::setSystemFileSetting($this->getInstance()->PREFIX, FILE_SETTING_KEY, $edocIdSystem);

                # project
		ExternalModules::setFileSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, FILE_SETTING_KEY, $edocIdProject);

		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($edocIdProject, $array[FILE_SETTING_KEY]['value']);
		$this->assertSame($edocIdSystem, $array[FILE_SETTING_KEY]['system_value']);

		ExternalModules::removeProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, FILE_SETTING_KEY);
		ExternalModules::removeSystemSetting($this->getInstance()->PREFIX, FILE_SETTING_KEY);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);

		$this->assertNull(@$array[FILE_SETTING_KEY]['value']);
		$this->assertNull(@$array[FILE_SETTING_KEY]['system_value']);
	}

	function testGetLinks()
	{
		$config = [
			'links' => [
				'control-center' => [
					[
						'name'=>"Keyless Control Center Link Name",
						'url'=>"some/control/center/keyless-url",
						'icon'=>rand()
					],
					[
						'name'=>"Keyed (and duplicated) Control Center Link Name",
						'key'=>"control-key-1",
						'url'=>"some/control/center/keyed-url-1",
						'icon'=>rand()
					],
					[
						'name'=>"Keyed (and duplicated) Control Center Link Name",
						'key'=>"control-key-2",
						'url'=>"some/control/center/keyed-url-2",
					],
					[
						'name'=>"Link With Invalid Key",
						'key'=>"key with invalid characters!!!",
						'url'=>"some-url",
					]
				],
				'project' => [
					[
						'name'=>"Keyless Project Link Name",
						'url'=>"some/project/keyless-url",
						'icon'=>rand()
					],
					[
						'name'=>"Keyed (and duplicated) Project Link Name",
						'key'=>"keyed-project-link-1",
						'url'=>"some/project/keyed-url-1",
						'icon'=>rand()
					],
					[
						'name'=>"Keyed (and duplicated) Project Link Name",
						'key'=>"keyed-project-link-2",
						'url'=>"some/project/keyed-url-2",
					],
					[
						'name'=>"External Link",
						'key'=>"external-link",
						'url'=>"https://projectredcap.org"
					],
					[
						'name'=>"JavaScript Call",
						'key'=>"javascript-link",
						'url'=>"javascript:alert('Hi there');"
					]
				]
			]
		];

		$assert = function($type, $expectEmptyMenuLinks = false) use ($config){
			$this->cacheAllEnableData();

			$expectedLinks = [];
			$linkCounter = 0;
			foreach($config['links'][$type] as $link){
				$linkCounter++;

				$url = $link['url'];
				$linkType = $this->getLinkType($url);
				if($linkType === 'page'){
					$link['url'] = ExternalModules::getPageUrl(TEST_MODULE_PREFIX, $url);
				}
				else if($linkType === 'ext'){
					$link['target'] = '_blank';
				}

				$link['prefix'] = TEST_MODULE_PREFIX;

				$prefixedKey = @$link['key'];
				if(!$this->isLinkKeyValid($prefixedKey)){
					$link['name'] .= '<br>' . $this->tt('em_errors_140');
					$prefixedKey = null;
				}

				if($prefixedKey === null){
					$prefixedKey = "link_{$type}_{$linkCounter}";
				}

				$link['prefixedKey'] = TEST_MODULE_PREFIX . '-' . $prefixedKey;

				$linkArrayKey = TEST_MODULE_PREFIX . '-' . $linkCounter;
				$expectedLinks[$linkArrayKey] = $link;
			}

			$actualModuleLinks = $this->getLinks(TEST_MODULE_PREFIX, TEST_MODULE_VERSION);
			$this->assertSame($expectedLinks, $actualModuleLinks, 'Assert permission checking when a link is accessed');

			$allActualLinksFiltered = [];
			foreach($this->getLinks() as $key=>$link){
				if(strpos($key, TEST_MODULE_PREFIX) === 0){
					$allActualLinksFiltered[$key] = $link;
				}
			}

			if($expectEmptyMenuLinks){
				$expectedLinks = [];
			}

			$this->assertSame($expectedLinks, $allActualLinksFiltered, 'Assert that links appear (or not) in the left menu');
		};

		$this->setConfig($config);
		$assert('control-center', true);

		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$assert('control-center');

		$_GET['pid'] = TEST_SETTING_PID;
		$assert('project', true);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true);
		$assert('project');
	}

	function testGetLinks_exceptions(){
		$assert = function($links, $expectedException){
			$this->assertThrowsException(function() use ($links){
				foreach(['control-center', 'project'] as $type){
					$this->setConfig([
						'links' => [
							$type => $links
						]
					]);

					$this->cacheAllEnableData();
					$this->getLinks(TEST_MODULE_PREFIX, TEST_MODULE_VERSION);
				}
			}, $expectedException);
		};

		$duplicateKey = "duplicate-key";
		$assert([
			[
				'name'=>"Some Link",
				'key'=>$duplicateKey,
				'url'=>"some-url"
			],
			[
				'name'=>"Some Link",
				'key'=>$duplicateKey,
				'url'=>"some-url"
			]
		], $this->tt('em_errors_141', $duplicateKey));
	}

	function testCallHook_enabledStates()
	{
		$pid = TEST_SETTING_PID;
		$m = $this->getInstance();
		$hookName = 'redcap_test';
		$this->setConfig(['permissions' => [$hookName]]);

		$assertHookCalled = function($called, $pid = null) use ($hookName){
			$this->assertHookCalled($hookName, $called, $pid);
		};

		$assertHookCalled(false);

		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$assertHookCalled(true);

		$assertHookCalled(false, $pid);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$assertHookCalled(true, $pid);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, $pid);
		$assertHookCalled(false, $pid);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, false);
		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, $pid);
		$assertHookCalled(true, $pid);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, $pid);
		$assertHookCalled(false, $pid);
	}

	function testCallHook_previousActiveModulePrefix()
	{
		$previousPrefix = 'some_fake_prefix';
		$this->setActiveModulePrefix($previousPrefix);

		$prefixInsideHook = null;
		ExternalModules::callHook('redcap_test_call_function', [function() use (&$prefixInsideHook){
			$prefixInsideHook = $this->getActiveModulePrefix();
		}]);

		// Assert that the active module prefix did indeed change.
		$this->assertSame(TEST_MODULE_PREFIX, $prefixInsideHook);

		// Assert that the previous prefix was restored (for cases like one module triggering the email hook in a second module).
		$this->assertSame($previousPrefix, $this->getActiveModulePrefix());
	}

	function testCallHook_arguments()
	{
		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$this->cacheAllEnableData();

		$this->setConfig(['permissions' => ['hook_test']]);

		$argOne = 1;
		$argTwo = 'a';
		ExternalModules::callHook('redcap_test', [$argOne, $argTwo]);
		$this->assertSame($argOne, $m->testHookArguments[0]);
		$this->assertSame($argTwo, $m->testHookArguments[1]);
	}

	function testCallHook_permissions()
	{
		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);

		$hookName = 'redcap_test';
		$this->setConfig(['permissions' => [$hookName]]);
		$this->assertHookCalled($hookName, true);

		$this->setConfig([]);
		$this->assertHookCalled($hookName, false);

		$pid = TEST_SETTING_PID;
		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, $pid);

		$hookName = 'redcap_every_page_test';
		$config = ['permissions' => [$hookName]];
		$this->setConfig($config);
		$this->assertHookCalled($hookName, true, $pid);
		$this->assertHookCalled($hookName, false);

		$config['enable-every-page-hooks-on-system-pages'] = true;
		$this->setConfig($config);
		$this->assertHookCalled($hookName, true);
	}

	function testCallHook_setRecordId()
	{
		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$this->cacheAllEnableData();

		$hookName = 'redcap_save_record';
		$this->setConfig(['permissions' => [$hookName]]);

		$projectId = 1;
		$recordId = rand();
		ExternalModules::callHook($hookName, [$projectId, $recordId]);
		$this->assertSame($recordId, $m->recordIdFromGetRecordId);

		// The record id should be set back to null once the hook finishes.
		$this->assertNull($m->getRecordId());
	}

	private function assertHookCalled($hookName, $called, $pid = null)
	{
		$arguments = [];
		if($pid){
			$arguments[] = $pid;
		}

		$this->cacheAllEnableData();
		$m = $this->getInstance();

		$m->testHookArguments = null;
		ExternalModules::callHook($hookName, $arguments);
		if($called){
			$this->assertNotNull($m->testHookArguments, 'The hook was expected to run but did not.');
		}
		else{
			$this->assertNull($m->testHookArguments, 'The hook was not expected to run but did.');
		}
	}

	// Calling this will effectively clear/reset the cache.
	private function cacheAllEnableData()
	{
		self::callPrivateMethod('cacheAllEnableData');
	}

	private function getEnabledModuleVersionsForProjectIgnoreCache()
	{
		$this->cacheAllEnableData();
		return self::callPrivateMethod('getEnabledModuleVersionsForProject', TEST_SETTING_PID);
	}

	function testSaveSettings()
	{
		$settings = [];
		$settings[TEST_SETTING_KEY] = rand();

		$repeatableSettingKey = 'test-repeatable';
		$repeatableExpected = [rand(), 'some string', 1.23];

		for($i = 0; $i<count($repeatableExpected); $i++){
			$settings[$repeatableSettingKey . '____' . $i] = $repeatableExpected[$i];
		}

		ExternalModules::saveSettings(TEST_MODULE_PREFIX, TEST_SETTING_PID, $settings);

		$this->assertSame($settings[TEST_SETTING_KEY], ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY));
		$this->assertSame($repeatableExpected, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $repeatableSettingKey));

		// cleanup
		ExternalModules::removeProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $repeatableSettingKey);
	}

	function testInstance()
	{
		$value1 = rand();
		$value2 = rand();
		$value3 = rand();
		$value4 = rand();
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 0, $value1);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertNotNull(json_encode($array));
		$this->assertSame($value1, $array[TEST_SETTING_KEY]['value']);

		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $value1);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 1, $value2);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 2, $value3);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 3, $value4);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertNotNull(json_encode($array));
		$this->assertSame($value1, $array[TEST_SETTING_KEY]['value'][0]);
		$this->assertSame($value2, $array[TEST_SETTING_KEY]['value'][1]);
		$this->assertSame($value3, $array[TEST_SETTING_KEY]['value'][2]);
		$this->assertSame($value4, $array[TEST_SETTING_KEY]['value'][3]);

		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $value1);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 1, $value2);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 2, $value3);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 3, $value4);
		$array = ExternalModules::getProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID,  TEST_SETTING_KEY);
		$this->assertNotNull(json_encode($array));
		$this->assertSame($value1, $array[0]);
		$this->assertSame($value2, $array[1]);
		$this->assertSame($value3, $array[2]);
		$this->assertSame($value4, $array[3]);

		ExternalModules::removeProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY);
	}

	function testIsLocalhost()
	{
		$assertLocalhost = function($expected, $host){
			$_SERVER['HTTP_HOST'] = $host;
			$this->assertSame($expected, $this->callPrivateMethod('isLocalhost'));
		};

		$GLOBALS['is_development_server'] = false;
		$assertLocalhost(true, 'localhost');
		$assertLocalhost(false, 'redcap.vanderbilt.edu');
		$assertLocalhost(false, 'redcap.somewhere-else.edu');

		$GLOBALS['is_development_server'] = true;
		$assertLocalhost(true, 'redcap.somewhere-else.edu');
	}

	function testGetAdminEmailMessage()
	{
		global $project_contact_email;

		$assertToEquals = function ($expectedTo, $expectedModuleEmail = null) {
			if ($expectedModuleEmail) {
				$this->setConfig([
					'authors' => [
						[
							'email' => $expectedModuleEmail
						],
						[
							'email' => 'someone@somewhere.edu' // we assert that this email is NOT included, because the domain doesn't match.
						]
					]
				]);
			}

			$expectedTo = implode(',', $expectedTo);

			$message = $this->callPrivateMethod('getAdminEmailMessage', '', '', TEST_MODULE_PREFIX);
			$this->assertEquals($expectedTo, $message->getTo());
		};

		$this->setPrivateVariable('SERVER_NAME', 'redcaptest.vanderbilt.edu');
		$assertToEquals(['mark.mcever@vumc.org', 'kyle.mcguffin@vumc.org']);

		$this->setPrivateVariable('SERVER_NAME', 'redcap.vanderbilt.edu');
		$expectedTo = ['mark.mcever@vumc.org', 'kyle.mcguffin@vumc.org', 'datacore@vumc.org'];
		$assertToEquals($expectedTo);

		// Assert that vanderbilt module author address is NOT included, since it's always going to be datacore anyway.
		$assertToEquals($expectedTo, 'someone@vumc.org');

		$otherDomain = 'other.edu';
		$this->setPrivateVariable('SERVER_NAME', "redcap.$otherDomain");
		$assertToEquals([$project_contact_email]);

		$expectedModuleEmail = "someone@$otherDomain";
		$expectedTo = [$project_contact_email, $expectedModuleEmail];
		$assertToEquals($expectedTo, $expectedModuleEmail);
	}

	function testAreSettingPermissionsUserBased()
	{
		$m = $this->getInstance();
		$methodName = 'areSettingPermissionsUserBased';
		$hookName = 'redcap_test_call_function';

		// permissions should not be user based during hook calls
		$value = null;
		ExternalModules::callHook($hookName, [function() use ($methodName, &$value){
			$value = self::callPrivateMethod($methodName, TEST_MODULE_PREFIX, TEST_SETTING_KEY);
		}]);
		$this->assertFalse($value);

		// We'd ideally test to ensure permissions were not user based on module pages here,
		// but we don't have a good way to test that currently.

		// modules should require user based permissions by default in other contexts (like saving settings in the settings dialog)
		$this->assertTrue(self::callPrivateMethod($methodName, TEST_MODULE_PREFIX, TEST_SETTING_KEY));

		$m->disableUserBasedSettingPermissions();
		$this->assertFalse(self::callPrivateMethod($methodName, TEST_MODULE_PREFIX, TEST_SETTING_KEY));

		// reserved values should always require user based permissions
		$this->assertTrue(self::callPrivateMethod($methodName, TEST_MODULE_PREFIX, ExternalModules::KEY_ENABLED));
	}

	function testGetUrl()
	{
		$m = $this->getInstance();

		$url = $m->getUrl("index.php");
		$this->assertNotNull($url);
		$url = $m->getUrl("dir/index.php");
		$this->assertNotNull($url);
	}

	function testGetParseModuleDirectoryPrefixAndVersion()
	{
		$assert = function($expected, $directoryName){
			$this->assertSame($expected, ExternalModules::getParseModuleDirectoryPrefixAndVersion($directoryName));
		};

		$assert(['somedir', 'v1'], 'somedir_v1');
		$assert(['somedir', 'v1.1'], 'somedir_v1.1');
		$assert(['somedir', 'v1.1.1'], 'somedir_v1.1.1');

		// Test underscores and dashes.
		$assert(['some_dir', 'v1.1'], 'some_dir_v1.1');
		$assert(['some-dir', 'v1.1'], 'some-dir_v1.1');

		// Test invalid values.
		$assert(['some_dir', null], 'some_dir_');
		$assert(['some_dir', null], 'some_dir_v');
		$assert(['', 'v1.0'], '_v1.0');
		$assert(['somedir', null], 'somedir_v1A');
		$assert(['somedir', null], 'somedir_vA');
		$assert(['somedir', null], 'somedir_1');
		$assert(['somedir', null], 'somedir_A');
		$assert(['somedir', null], 'somedir_v1.1.1.1');
	}

	function testGetModuleInstance()
	{
		$prefix = 'some_fake_prefix';
		$this->setActiveModulePrefix($prefix);

		$m = ExternalModules::getModuleInstance(TEST_MODULE_PREFIX);

		$this->assertSame(TestModule::class, get_class($m));

		// Although it perhaps shouldn't be, it is sometimes possible for getModuleInstance() to
		// be called while inside a hook (it sometimes happens in the email alerts module).
		// The getModuleInstance() function used to set the active module prefix to null on every call,
		// which is problematic for anything that relies on the active prefix.
		// This used to cause 'You must specify a prefix!' exceptions.
		// Assert that the previous prefix is restored to make sure this does not happen again.
		$this->assertSame($prefix, $this->getActiveModulePrefix());
	}

	function testGetFrameworkVersion()
	{
		$doNotIncludeFrameworkVersionValue = 'do-not-include-framework-version';

		$assertFrameworkVersion = function($jsonValue, $expected = null) use ($doNotIncludeFrameworkVersionValue){
			$config = [];
			if($jsonValue !== $doNotIncludeFrameworkVersionValue){
				$config['framework-version'] = $jsonValue;
			}

			$this->setConfig($config);

			$actual = ExternalModules::getFrameworkVersion($this->getInstance());
			$this->assertSame($expected, $actual);
		};

		$assertFrameworkVersion($doNotIncludeFrameworkVersionValue, 1);
		$assertFrameworkVersion(null, 1);
		$assertFrameworkVersion(1, 1);
		$assertFrameworkVersion(2, 2);

		$exceptionValues = ['', 'a', '1', '2', 1.1, true, false, []];

		foreach($exceptionValues as $value){
			$this->assertThrowsException(function() use ($assertFrameworkVersion, $value){
				$assertFrameworkVersion($value);
			}, 'must be specified as an integer');
		}
	}

	function testCopySettingValues()
	{
		$value = [rand(), rand()];
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $value);

		self::callPrivateMethod('copySettingValues', TEST_SETTING_PID, TEST_SETTING_PID_2);

		$this->assertSame($value, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID_2, TEST_SETTING_KEY));
	}

	function testRecreateAllEDocs_fileSettings()
	{
		$edocPath = $this->getEdocPathRoot();
		if(!is_dir($edocPath)){
			throw new Exception('The EDOC_PATH (' . $edocPath . ') is not valid!  If you\'re switching between environments (ex: Windows & Cygwin) you may want to remove the "edoc_path" value in the "redcap_config" table so it can be automatically detected.');
		}

		$edocIds = [];
		$edocFilenames = [];

		$minEdocs = 5;
		$result = ExternalModules::query("
			select * from redcap_edocs_metadata
			where
				date_deleted_server is null
				and doc_size < 1000000
				and project_id not in (?, ?)
			limit ?
		", [TEST_SETTING_PID, TEST_SETTING_PID_2, $minEdocs]);

		while($row = $result->fetch_assoc()){
			// We must cast to a string because there is an issue on js handling side for file fields stored as integers.
			$edocIds[] = (string)$row['doc_id'];
			$edocFilenames[] = $row['stored_name'];
		}

		$edocsNeeded = $minEdocs - count($edocIds);
		if($edocsNeeded !== 0){
			throw new Exception("Please upload $edocsNeeded more edocs to any project in order for unit tests to run properly.");
		}

		$key1 = 'test-key-1';
		$key2 = 'test-key-2';
		$key3 = 'test-key-3';

		$this->setConfig([
			'project-settings' => [
					[
						'key' => $key1,
						'type' => 'file'
					],
					[
						'key' => 'sub-settings-key',
						'type' => 'sub_settings',
						'sub_settings' => [
							[
								'key' => $key2,
								'type' => 'file'
							]
						]
					],
					[
						'key' => $key3,
						'type' => 'text'
					]
				]
			]
		);

		$value1 = $edocIds[0];

		// simulate repeatable sub-settings
		$value2 = [
			[
				$edocIds[1],
				$edocIds[2],
				$edocIds[3],
			],
			[
				$edocIds[4],
			]
		];

		ExternalModules::setFileSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key1, $value1);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key2, $value2);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key3, $value2);

		ExternalModules::recreateAllEDocs(TEST_SETTING_PID);

		$newValue1 = ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key1);
		$newValue2 = ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key2);

		$newEdocIds = array_merge([$newValue1], $newValue2[0], $newValue2[1]);
		for($i=0; $i<$minEdocs; $i++){
			$oldId = $edocIds[$i];
			$newId = $newEdocIds[$i];

			$this->assertEdocsEqual($oldId, $newId);
		}

		// Make sure non-file settings are not touched.
		$this->assertSame($value2, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key3));

		foreach($newEdocIds as $id){
			ExternalModules::deleteEDoc($id);
		}
	}

	private function assertEdocsEqual($expected, $actual)
	{
		// Make sure edoc IDs are stored as strings because of a bug on the js processing side for file fields that prevents integers from working.
		$this->assertSame('string', gettype($expected));
		$this->assertSame('string', gettype($actual));

		// If the expected and actual edoc IDs are the same, something in the calling test isn't right.
		$this->assertNotEquals($expected, $actual);

		$this->assertFileEquals(self::getEdocPath($expected), self::getEdocPath($actual));
	}

	private function findAnEdoc()
	{
		$row = ExternalModules::query("select * from redcap_edocs_metadata where date_deleted_server is null limit 1", [])->fetch_assoc();
		if(empty($row)){
			throw new Exception("Please upload at least one edoc to allow this unit test to run.");
		}

		return $row;
	}

	function testRecreateAllEDocs_richText()
	{
		$row = $this->findAnEdoc();

		$oldProjectId = $row['project_id'];
		$oldEdocId = $row['doc_id'];
		$edocName = $row['doc_name'];
		$oldFiles = [
			[
				'edocId' => $oldEdocId,
				'name' => $edocName
			]
		];

		$key1 = 'test-key-1';
		$key2 = 'test-key-2';
		$key3 = 'test-key-3';
		$this->setConfig([
			'project-settings' => [
					[
						'key' => $key1,
						'type' => 'rich-text'
					],
					[
						'key' => $key2,
						'type' => 'text'
					],
					[
						'key' => 'sub-settings-key',
						'type' => 'sub_settings',
						'sub_settings' => [
							[
								'key' => $key3,
								'type' => 'rich-text'
							]
						]
					],
				]
			]
		);

		$getRichTextExampleContent = function($pid, $edocId) use ($edocName){
			return '<p><img src="' . htmlspecialchars(ExternalModules::getRichTextFileUrl(TEST_MODULE_PREFIX, $pid, $edocId, $edocName)) . '" alt="" width="150" height="190" /></p>';
		};

		$oldRichTextContent = $getRichTextExampleContent($oldProjectId, $oldEdocId);

		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key1, $oldRichTextContent);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key2, $oldRichTextContent);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key3, [$oldRichTextContent]);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST, $oldFiles);
		ExternalModules::recreateAllEDocs(TEST_SETTING_PID);
		$newFiles = ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST);

		$newRichTextContent = $getRichTextExampleContent(TEST_SETTING_PID, $newFiles[0]['edocId']);
		$this->assertSame($newRichTextContent, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key1));

		// Make sure non-rich-text fields are not changed
		$this->assertSame($oldRichTextContent, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key2));

		// Rich text content within sub_settings is also JSON escaped.  Make sure we are still able replace URLs properly.
		$this->assertSame([$newRichTextContent], ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key3));

		$this->assertSame(count($oldFiles), count($newFiles));
		for($i=0; $i<count($oldFiles); $i++){
			$oldFile = $oldFiles[$i];
			$newFile = $newFiles[$i];

			$oldEdocId = (string)$oldFile['edocId'];
			$newEdocId = $newFile['edocId'];

			$this->assertEdocsEqual($oldEdocId, $newEdocId);
			$this->assertSame($oldFile['name'], $newFile['name']);

			ExternalModules::deleteEDoc($newEdocId);
		}
	}

	function testIsValidTabledCron(){
		$assertTabledCron = function($valid, $json){
			$this->assertSame($valid, self::callPrivateMethod('isValidTabledCron', json_decode($json, true)));
		};

		$assertTabledCron(true, self::TABLED_CRON_EXAMPLE);
		$assertTabledCron(false, self::TIMED_CRON_EXAMPLE);
	}

	function testIsValidTimedCron(){
		$assertTimedCron = function($valid, $json){
			$this->assertSame($valid, self::callPrivateMethod('isValidTimedCron', json_decode($json, true)));
		};

		$assertTimedCron(false, self::TABLED_CRON_EXAMPLE);
		$assertTimedCron(true, self::TIMED_CRON_EXAMPLE);
	}

	function testGetSQLInClause(){
		$assert = function($expected, $columnName, $array){
			$this->assertSame("($expected)", ExternalModules::getSQLInClause($columnName, $array));
		};

		$assert("column_name IN ('1')", 'column_name', 1);
		$assert("column_name IN ('1')", 'column_name', '1');
		$assert("column_name IN ('1', '2')", 'column_name', [1, 2]);
		$assert("column_name IN ('1') OR column_name IS NULL", 'column_name', [1, 'NULL']);
		$assert("column_name\\' IN ('value\\'')", 'column_name\'', ['value\'']); // make sure quotes are escaped
		$assert("false", 'column_name', []);
	}

	function testGetSQLInClause_preparedStatements(){
		$assert = function($expectedSql, $expectedParams, $columnName, $array){
			list($actualSql, $actualParams) = ExternalModules::getSQLInClause($columnName, $array, true);
			
			$this->assertSame("($expectedSql)", $actualSql);
			$this->assertSame($expectedParams, $actualParams);
		};

		$assert("column_name IN (?)", [1], 'column_name', 1);
		$assert("column_name IN (?)", ['1'], 'column_name', '1');
		$assert("column_name IN (?, ?)", [1, 2], 'column_name', [1, 2]);
		$assert("column_name IN (?) OR column_name IS NULL", [1], 'column_name', [1, null]);
		$assert("column_name IN (?)", ['NULL'], 'column_name', ['NULL']);
		$assert("column_name\\' IN (?)", ['value\''], 'column_name\'', ['value\'']); // make sure quotes are escaped
		$assert("false", [], 'column_name', []);
	}

	function testIsCompatibleWithREDCapPHP_minVersions(){
		$versionTypes = [
			'PHP' => PHP_VERSION,
			'REDCap' => REDCAP_VERSION
		];
		
		foreach($versionTypes as $versionType=>$systemVersion){
			$settingKey = strtolower($versionType) . "-version-min";

			$test = function($configMinVersion) use ($settingKey, $systemVersion){
				$this->setConfig([
					'compatibility' => [
						$settingKey => $configMinVersion
					]
				]);
	
				$this->callPrivateMethod('isCompatibleWithREDCapPHP', TEST_MODULE_PREFIX, TEST_MODULE_VERSION);
			};

			$assertValid = function($configMinVersion) use ($settingKey, $test){
				// Simply make sure the following call completes without an Exception.
				$test($configMinVersion);
			};
	
			$assertInvalid = function($configMinVersion) use ($settingKey, $test, $versionType){
				$expectedMessage = "minimum required $versionType version";

				$this->assertThrowsException(function() use ($configMinVersion, $test){
					$test($configMinVersion);
				}, $expectedMessage);
			};
	
			list($major, $minor, $patch) = explode('.', $systemVersion);

			$assertValid("$major.$minor.$patch");
			$assertInvalid("$major.$minor." . ($patch+1));
			$assertValid($major);
			$assertValid($major-1);
			$assertInvalid($major+1);
		}
	}

	function testTranslateConfig()
	{
		$settingOneTranslationKey = 'setting_one_name';
		$settingTwoTranslationKey = 'setting_two_name';
		$settingOneTranslatedName =  'Establecer Uno';
		$settingTwoTranslatedName =  'Establecer Two';

		$this->spoofTranslation(TEST_MODULE_PREFIX, $settingOneTranslationKey, $settingOneTranslatedName);
		$this->spoofTranslation(TEST_MODULE_PREFIX, $settingTwoTranslationKey, $settingTwoTranslatedName);

		$config = [
			'project-settings' => [
				[
					'key' => 'setting-one',
					'name' => 'Setting One',
					'tt_name' => $settingOneTranslationKey
				],
				[
					'key' => 'sub-settings-key',
					'type' => 'sub_settings',
					'sub_settings' => [
						[
							'key' => 'setting-two',
							'name' => 'Setting Two',
							'tt_name' => $settingTwoTranslationKey
						]
					]
				]
			]
		];

		// callPrivateMethod() didn't work here in PHP 5.6 due to a quirk of passing parameters by references.
		// There might be a way to fix it so we don't need the wordaround below.
		$class = new \ReflectionClass($this->getReflectionClass());
		$method = $class->getMethod('translateConfig');
		$method->setAccessible(true);

		$translatedConfig = $method->invokeArgs($instance, [&$config, TEST_MODULE_PREFIX]);
		
		// set expected changes
		$config['project-settings'][0]['name'] = $settingOneTranslatedName;
		$config['project-settings'][1]['sub_settings'][0]['name'] = $settingTwoTranslatedName;

		$this->assertSame($translatedConfig, $config);
	}

	function testTt_basic()
	{
		$key1 = 'key1';
		$value = rand();
		$this->spoofTranslation(null, $key1, $value);

		$this->assertSame($value, ExternalModules::tt($key1));

		$key2 = 'key2';

		$this->assertSame(ExternalModules::getLanguageKeyNotDefinedMessage($key2, null), ExternalModules::tt($key2));
	}

	function testTt_specialChars()
	{
		$key = 'key';
		$valuePrefix = 'Test - ';
		$languageFileEntry = $valuePrefix . '{0}';
		$this->spoofTranslation(null, $key, $languageFileEntry);
		
		$param = '<script>alert("This code should be escaped and displayed (NOT executed).")</script>';
		$expected = $valuePrefix . htmlspecialchars($param);
		$this->assertSame($expected, ExternalModules::tt($key, $param));
	}

	function testQuery_noParameters(){
		$value = (string)rand();
		$result = ExternalModules::query("select $value", []);
		$row = $result->fetch_row();
		$this->assertSame($value, $row[0]);

		$this->assertThrowsException(function(){
			// Assert that passing a parameter array is required (even if it's empty).
			ExternalModules::query("foo");
		}, ExternalModules::tt('em_errors_117'));
	}

	function testAddSurveyParticipantAndResponse(){
		$m = $this->getInstance();
		list($surveyId, $formName) = $m->getSurveyId(TEST_SETTING_PID);

		$participantId = ExternalModules::addSurveyParticipant($surveyId, $m->framework->getEventId(TEST_SETTING_PID), $m->generateUniqueRandomSurveyHash());
		$this->assertIsInt($participantId);

		$responseId = ExternalModules::addSurveyResponse($participantId, 1, generateRandomHash());
		$this->assertIsInt($responseId);

		// The following delete cascades and deletes the redcap_surveys_response row as well.
		ExternalModules::query('delete from redcap_surveys_participants where participant_id = ?', $participantId);
	}

	function testGetSettingsQuery_projectIds(){
		$assert = function($projectIds, $hasInClause, $hasNullClause){
			$query = ExternalModules::getSettingsQuery(null, $projectIds);
			$sql = $query->getSQL();
			
			$this->assertSame($hasInClause, strpos($sql, 'project_id IN ') !== false);
			$this->assertSame($hasNullClause, strpos($sql, 'project_id IS NULL') !== false);
		};
		
		$assert(null, false, false);
		$assert(ExternalModules::SYSTEM_SETTING_PROJECT_ID, false, true);
		$assert([ExternalModules::SYSTEM_SETTING_PROJECT_ID], false, true);
		$assert([ExternalModules::SYSTEM_SETTING_PROJECT_ID, 1], true, true);
		$assert([1], true, false);
		$assert(1, true, false);

		// I'm not sure if these cases are actually ever used.
		// If they are, perhaps they shouldn't be.
		$assert('', false, true);
		$assert(0, false, true);
	}

	function testCronJobMethods(){
		$m = $this->getInstance();
		$prefix = $m->PREFIX;
		$moduleId = ExternalModules::getIdForPrefix($prefix);

		$name = "UnitTestCron";
		$expectedCron = [
			"cron_name" => $name,
			"cron_description" => "This is only a test.",
			"method" =>"some_method",
			"cron_frequency" =>  "99999",
			"cron_max_run_time" => "1"
		];

		$getCron = function() use ($name, $moduleId){
			return ExternalModules::getCronJobFromTable($name, $moduleId);
		};

		try{
			ExternalModules::addCronJobToTable($expectedCron, $this->getInstance());

			unset($expectedCron['method']);
			
			$actualCron = $getCron();
			$this->assertSame($expectedCron, $actualCron);
			
			$expectedCron['cron_description'] = 'A new description.';
			ExternalModules::updateCronJobInTable($expectedCron, $moduleId);
			$actualCron = $getCron();

			$this->assertSame($expectedCron, $actualCron);
		}
		finally{
			ExternalModules::removeCronJobs($prefix);
			$this->assertTrue(empty($getCron()));
		}
	}

	function testGetPrefixForID(){
		$id = ExternalModules::getIDForPrefix(TEST_MODULE_PREFIX);
		$this->assertSame(TEST_MODULE_PREFIX, ExternalModules::getPrefixForID($id));
	}

	function testGetModuleVersionByPrefix(){
		$row = ExternalModules::query("
			SELECT m.directory_prefix, s.value
			FROM redcap_external_modules m, redcap_external_module_settings s 
			WHERE
				m.external_module_id = s.external_module_id
				AND s.project_id IS NULL AND s.`key` = ?
			LIMIT 1
		", [ExternalModules::KEY_VERSION])->fetch_assoc();

		$version = ExternalModules::getModuleVersionByPrefix($row['directory_prefix']);
		
		$this->assertSame($row['value'], $version);
	}

	function testLimitDirectFileAccess(){
		$originalHost = $_SERVER['HTTP_HOST'];
		$originalSelf = $_SERVER['PHP_SELF'];

		$_SERVER['HTTP_HOST'] = parse_url(APP_URL_EXTMOD, PHP_URL_HOST);
		$_SERVER['PHP_SELF'] = parse_url(APP_URL_EXTMOD, PHP_URL_PATH) . 'manager/templates/some-template.php';

		$this->assertThrowsException(function(){
			ExternalModules::limitDirectFileAccess();
		}, self::tt('em_errors_121'));
		
		$_SERVER['PHP_SELF'] = $originalSelf;
		$_SERVER['HTTP_HOST'] = $originalHost;

		// This method should not throw an exception any more.
		ExternalModules::limitDirectFileAccess();
	}

	function testRequireInteger(){
		foreach([1, '1'] as $value){
			$intValue = $this->requireInteger(1);
			$this->assertEquals($value, $intValue);
		}

		foreach([1.1, '1.1'] as $value){
			$this->assertThrowsException(function() use ($value){
				$this->requireInteger($value);
			}, self::tt("em_errors_60", $value));
		}
	}
	
	function testSetRecordCompleteStatus(){
		$projectId = TEST_SETTING_PID;
		$recordId = 1;
		$eventId = $this->getFramework()->getEventId($projectId);
		$formName = ExternalModules::getFormNames($projectId)[0];

		$getValue = function() use ($projectId, $recordId, $eventId, $formName){
			return ExternalModules::getRecordCompleteStatus($projectId, $recordId, $eventId, $formName);
		};

		if($getValue() === null){
			$this->query(
				"insert into redcap_data values (?,?,?,?,?,null)",
				[$projectId, $eventId, $recordId, "{$formName}_complete", 1]
			);
		}
		else{
			ExternalModules::setRecordCompleteStatus($projectId, $recordId, $eventId, $formName, 1);
		}

		$this->assertSame('1', $getValue());
		ExternalModules::setRecordCompleteStatus($projectId, $recordId, $eventId, $formName, 0);
		$this->assertSame('0', $getValue());
	}

	function testGetRepoModuleId(){
		$r = $this->query("select cast(module_id as char) as module_id, module_name from redcap_external_modules_downloads order by rand() limit ?", 1);
		$row = $r->fetch_assoc();
		$this->assertSame($row['module_id'], ExternalModules::getRepoModuleId($row['module_name']));
	}

	function testInitializeFramework_unsupportedVersions(){
		// We only test the exception case here.
		// FrameworkV1Test tests actual use cases well.
		$unsupportedFrameworkVersion = ExternalModules::getMaxSupportedFrameworkVersion() + 1;
		
		$this->assertThrowsException(function() use ($unsupportedFrameworkVersion){
			$this->setConfig([
				'framework-version' => $unsupportedFrameworkVersion
			]);
		}, self::tt('em_errors_130', $unsupportedFrameworkVersion));
	}

	function testEnsureAllFrameworkVersionsTested(){
		// Ideally we'd find a way to test all framework versions without actually having a test class for each,
		// but this will work for now.
		$version = ExternalModules::getMaxSupportedFrameworkVersion();

		while($version > 1){
			$filename = "FrameworkV{$version}Test.php";
			$this->assertFileExists(__DIR__ . "/$filename", "A $filename file must be created to ensure that unit tests run for that framework version.");

			$version--;
		}
	}

	function testIsHookCallAllowed(){
		$assert = function($expected, $previous, $current){
			if($previous){
				$previous = new HookRunner("hook_$previous");
			}

			$current = new HookRunner("hook_$current");

			$this->assertSame($expected, $this->callPrivateMethod('isHookCallAllowed', $previous, $current));
		};
		
		$assert(true, null, 'email');
		$assert(true, 'foo', 'email');
		$assert(false, 'email', 'email');
	}

	function testExitAfterHook(){
		$methodName = 'redcap_test_call_function';

		$exited = false;
		self::$exitAfterHookAction = function() use (&$exited){
			$exited = true;
		};

		ExternalModules::callHook($methodName, [function(){
			// do nothing
		}]);
		$this->assertFalse($exited);

		ExternalModules::callHook($methodName, [function(){
			$this->exitAfterHook();
		}]);
		$this->assertTrue($exited);
	}

	function testGetAdditionalFieldChoices_project(){
		$project = $this->getFramework()->getProject(TEST_SETTING_PID);

		$username = $this->getRandomUsername();
		ExternalModules::setUsername($username);
		ExternalModules::setSuperUser(null);

		$assert = function($expected) use ($project, $username){
			$choices = ExternalModules::getAdditionalFieldChoices(['type' => 'project-id'], null)['choices'];

			$actual = false;
			foreach($choices as $choice){
				if($choice['value'] === (string)$project->getProjectId()){
					$actual = true;
					break;
				}
			}

			$this->assertSame($expected, $actual, "Failed on user '$username'");
		};

		$project->removeUser($username);
		$assert(false);

		$project->addUser($username, [
			'design' => 1,
		]);
		$assert(true);

		$project->setRights($username, [
			'design' => 0,
		]);
		$assert(false);

		$roleName = 'Some Role';
		$project->removeRole($roleName); // remove roles added from previous tests
		$project->addRole($roleName, [
			'design' => 1,
		]);
		$project->setRoleForUser($roleName, $username);
		$assert(true);

		$project->removeRole($roleName);
		$project->addRole($roleName, [
			'design' => 0,
		]);
		$project->setRoleForUser($roleName, $username);
		$assert(false);

		ExternalModules::setSuperUser(true);
		$assert(true);
	}

	private function getEdocPathRoot(){
		// Get this edoc path a funny way so that this doesn't count as a reference to th EDOC_PATH constant.
		return constant('EDOC_PATH');
	}

	function testGetEdocPath(){
		$edoc = $this->findAnEdoc();
		$this->assertSame(
			$this->getEdocPathRoot() . $edoc['stored_name'],
			$this->getEdocPath($edoc['doc_id'])
		);
	}

	function testErrorLog(){
		$expected = (string) rand();

		ob_start();
		ExternalModules::errorLog($expected);
		$actual = ob_get_clean();

		$this->assertSame($expected . "\n", $actual);
	}

	function testErrorLog_chunks(){
		$expected1 = '';
		$expected2 = '';
		$expected3 = '';

		$max = 2001;
		for($i=1; $i<=$max; $i++){
			if($i <= 1000){
				$expected1 .= 1;
			}
			else if($i <= 2000){
				$expected2 .= 2;
			}
			else{
				$expected3 .= 3;
			}
		}

		$expected = 
			ExternalModules::getChunkPrefix(1, 3) . $expected1 . "\n" . 
			ExternalModules::getChunkPrefix(2, 3) . $expected2 . "\n" .
			ExternalModules::getChunkPrefix(3, 3) . $expected3 . "\n"
		;

		ob_start();
		ExternalModules::errorLog($expected1 . $expected2 . $expected3);
		$actual = ob_get_clean();

		$this->assertSame($expected, $actual);
	}
	
	private function getLatestFrameworkIntroVersion(){
		$lines = explode("\n", file_get_contents(__DIR__ . '/../docs/framework/intro.md'));
		foreach($lines as $line){
			if(strpos($line, '|[Version ') === 0){
				$parts = explode(' ', $line);
				$parts = explode(']', $parts[1]);
				return (int) $parts[0];
			}
		}
	}

	function testFrameworkIntroVersionsUpToDate(){
		$this->assertSame(
			ExternalModules::getMaxSupportedFrameworkVersion(),
			$this->getLatestFrameworkIntroVersion()
		);
	}
}
