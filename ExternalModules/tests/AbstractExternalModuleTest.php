<?php
namespace ExternalModules;
require_once 'BaseTest.php';

use \Exception;
use \REDCap;

class AbstractExternalModuleTest extends BaseTest
{
	protected function setUp():void
	{
		parent::setUp();

		$m = self::getInstance();

		// To delete all logs, we use a fake parameter to create a where clause that applies to all rows
		// (since removeLogs() requires a where clause).
		$m->removeLogs("some_fake_parameter is null");
	}

	/**
	 * @doesNotPerformAssertions
	 */
	function testCheckSettings_emptyConfig()
	{
		self::assertConfigValid([]);
	}

    function testCheckSettings_duplicateKeys()
    {
    	$assertMultipleSettingException = function($config){
			self::assertConfigInvalid($config, 'setting multiple times!');
		};

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key']
			],
			'project-settings' => [
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key']
			],
			'project-settings' => [
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key']
			],
			'project-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
			'project-settings' => [
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key'],
				['key' => 'some-key'],
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key'],
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				],
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				[
					'key' => 'some-key',
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		$assertMultipleSettingException([
			'project-settings' => [
				['key' => 'some-key'],
				['key' => 'some-key'],
			],
		]);

		$assertMultipleSettingException([
			'project-settings' => [
				['key' => 'some-key'],
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		$assertMultipleSettingException([
			'project-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				],
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'project-settings' => [
				[
					'key' => 'some-key',
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		// Assert a double nested sub_settings
		$assertMultipleSettingException([
			'project-settings' => [
				[
					'key' => 'some-key',
					'type' => 'sub_settings',
					'sub_settings' => [
						[
							'key' => 'some-other-key',
							'type' => 'sub_settings',
							'sub_settings' => [
								[
									'key' => 'some-other-key'
								]
							]
						]
					]
				]
			],
		]);
    }

	/**
	 * @doesNotPerformAssertions
	 */
	function testCheckSettingKey_valid()
	{
		self::assertConfigValid([
			'system-settings' => [
				['key' => 'key1']
			],
			'project-settings' => [
				['key' => 'key-two']
			],
		]);
	}

	function testCheckSettingKey_invalidChars()
	{
		$expected = 'contains invalid characters';

		self::assertConfigInvalid([
			'system-settings' => [
				['key' => 'A']
			]
		], $expected);

		self::assertConfigInvalid([
			'project-settings' => [
				['key' => '!']
			]
		], $expected);
	}

	function testIsSettingKeyValid()
	{
		$m = self::getInstance();

		$isSettingKeyValid = function($key) use ($m){
			return $this->callPrivateMethodForClass($m, 'isSettingKeyValid', $key);
		};

		$this->assertTrue($isSettingKeyValid('a'));
		$this->assertTrue($isSettingKeyValid('2'));
		$this->assertTrue($isSettingKeyValid('-'));
		$this->assertTrue($isSettingKeyValid('_'));

		$this->assertFalse($isSettingKeyValid('A'));
		$this->assertFalse($isSettingKeyValid('!'));
		$this->assertFalse($isSettingKeyValid('"'));
		$this->assertFalse($isSettingKeyValid('\''));
		$this->assertFalse($isSettingKeyValid(' '));
	}

	function assertConfigValid($config)
	{
		$this->setConfig($config);

		// Attempt to make a new instance of the module (which throws an exception on any config issues).
		new TestModule();
	}

	function assertConfigInvalid($config, $exceptionExcerpt)
	{
		$this->assertThrowsException(function() use ($config){
			self::assertConfigValid($config);
		}, $exceptionExcerpt);
	}

	function testSettingKeyPrefixes()
	{
		$normalValue = 1;
		$prefixedValue = 2;

		$this->setSystemSetting($normalValue);
		$this->setProjectSetting($normalValue);

		$m = $this->getInstance();
		$m->setSettingKeyPrefix('test-setting-prefix-');
		$this->assertNull($this->getSystemSetting());
		$this->assertNull($this->getProjectSetting());

		$this->setSystemSetting($prefixedValue);
		$this->setProjectSetting($prefixedValue);
		$this->assertSame($prefixedValue, $this->getSystemSetting());
		$this->assertSame($prefixedValue, $this->getProjectSetting());

		$this->removeSystemSetting();
		$this->removeProjectSetting();
		$this->assertNull($this->getSystemSetting());
		$this->assertNull($this->getProjectSetting());

		$m->setSettingKeyPrefix(null);
		$this->assertSame($normalValue, $this->getSystemSetting());
		$this->assertSame($normalValue, $this->getProjectSetting());

		// Prefixes with sub-settings are tested in testSubSettings().
	}

	function testSystemSettings()
	{
		$value = rand();
		$this->setSystemSetting($value);
		$this->assertSame($value, $this->getSystemSetting());

		$this->removeSystemSetting();
		$this->assertNull($this->getSystemSetting());
	}

	function testProjectSettings()
	{
		$projectValue = rand();
		$systemValue = rand();

		$this->setProjectSetting($projectValue);
		$this->assertSame($projectValue, $this->getProjectSetting());

		$this->removeProjectSetting();
		$this->assertNull($this->getProjectSetting());

		$this->setSystemSetting($systemValue);
		$this->assertSame($systemValue, $this->getProjectSetting());

		$this->setProjectSetting($projectValue);
		$this->assertSame($projectValue, $this->getProjectSetting());
	}

	function testSubSettings()
	{
		$_GET['pid'] = TEST_SETTING_PID;

		$groupKey = 'group-key';
		$settingKey = 'setting-key';
		$settingValues = [1, 2];

		$this->setConfig([
			'project-settings' => [
				[
					'key' => $groupKey,
					'type' => 'sub_settings',
					'sub_settings' => [
						[
							'key' => $settingKey
						]
					]
				]
			]
		]);

		$m = $this->getInstance();
		$m->setProjectSetting($settingKey, $settingValues);

		// Make sure prefixing makes the values we just set inaccessible.
		$m->setSettingKeyPrefix('some-prefix');
		$instances = $m->getSubSettings($groupKey);
		$this->assertEmpty($instances);
		$m->setSettingKeyPrefix(null);

		$instances = $m->getSubSettings($groupKey);
		$this->assertSame(count($settingValues), count($instances));
		for($i=0; $i<count($instances); $i++){
			$this->assertSame($settingValues[$i], $instances[$i][$settingKey]);
		}

		$m->removeProjectSetting($settingKey);
	}

	private function assertReturnedSettingType($value, $expectedType)
	{
		// We call set twice to make sure change detection is working properly, and we don't get an exception from trying to set the same value twice.
		$this->setProjectSetting($value);
		$this->setProjectSetting($value);

		$savedValue = $this->getProjectSetting();

		// We check the type separately from assertEquals() instead of using assertSame() because that wouldn't work for objects like stdClass.
		$savedType = gettype($savedValue);
		$this->assertEquals($expectedType, $savedType);
		$this->assertEquals($value, $savedValue);
	}

	function testSettingTypeConsistency()
	{
		$this->assertReturnedSettingType(true, 'boolean');
		$this->assertReturnedSettingType(false, 'boolean');
		$this->assertReturnedSettingType(1, 'integer');
		$this->assertReturnedSettingType(1.1, 'double');
		$this->assertReturnedSettingType("1", 'string');
		$this->assertReturnedSettingType([1], 'array');
		$this->assertReturnedSettingType([1,2,3], 'array');
		$this->assertReturnedSettingType(['a' => 'b'], 'array');
		$this->assertReturnedSettingType(null, 'NULL');

		$object = new \stdClass();
		$object->someProperty = true;
		$this->assertReturnedSettingType($object, 'object');
	}

	function testSettingTypeChanges()
	{
		$this->assertReturnedSettingType('1', 'string');
		$this->assertReturnedSettingType(1, 'integer');
	}

	function testArrayKeyPreservation()
	{
		$array = [1 => 2];
		$this->setProjectSetting($array);
		$this->assertSame($array, $this->getProjectSetting());
	}

	function testArrayNullValues()
	{
		$array = [0 => null];
		$this->setProjectSetting($array);
		$this->assertSame($array, $this->getProjectSetting());
	}

	function testSettingSizeLimit()
	{
		$result = ExternalModules::query("SHOW VARIABLES LIKE 'max_allowed_packet'", []);
		$row = $result->fetch_assoc();
		$maxAllowedPacket = $row['Value'];
		$threshold = $maxAllowedPacket - ExternalModules::SETTING_SIZE_LIMIT+1;
		$allowedThreshold = 1024; // MySQL only allows increasing 'max_allowed_packet' in increments of 1024
		
		if($threshold <= 0){
			// Don't run this test, since it will fail.
			// Skipping the test is safe since max_allowed_packet will cause an error instead of truncation (this test intends to prevent the latter).
			$this->markTestSkipped();
			return;
		}
		else if($threshold < $allowedThreshold){
			$recommendedMaxAllowedPacket = $maxAllowedPacket + $allowedThreshold;
			throw new Exception("Your MySQL server's 'max_allowed_packet' setting is very close to the maximum setting size.  Please increase this value to at least $recommendedMaxAllowedPacket for the " . __FUNCTION__ . "() test to run properly, and to avoid errors when saving large module setting values.");
		}

		$data = str_repeat('a', ExternalModules::SETTING_SIZE_LIMIT);
		$this->setProjectSetting($data);
		$this->assertSame($data, $this->getProjectSetting());

		$this->assertThrowsException(function() use ($data){
			$data .= 'a';
			$this->setProjectSetting($data);
		}, 'value is larger than');
	}

	function testSettingKeySizeLimit()
	{
		$m = $this->getInstance();

		$key = str_repeat('a', ExternalModules::SETTING_KEY_SIZE_LIMIT);
		$value = rand();
		$m->setSystemSetting($key, $value);
		$this->assertSame($value, $m->getSystemSetting($key));
		$m->removeSystemSetting($key);

		$this->assertThrowsException(function() use ($m, $key){
			$key .= 'a';
			$m->setSystemSetting($key, '');
		}, 'key is longer than');
	}

	function testRequireAndDetectParameters()
	{
		$testRequire = function($param, $requireFunctionName){
			$this->assertThrowsException(function() use ($requireFunctionName){
				$this->callPrivateMethod($requireFunctionName);
			}, 'You must supply');

			$value = rand();
			$this->assertSame($value, $this->callPrivateMethod($requireFunctionName, $value));

			$_GET[$param] = $value;
			$this->assertSame($value, $this->callPrivateMethod($requireFunctionName, null));
			unset($_GET[$param]);
		};

		$testDetect = function($param, $detectFunctionName){
			$m = $this->getInstance();
			$detect = function($value) use ($m, $detectFunctionName){
				return $this->callPrivateMethodForClass($m, $detectFunctionName, $value);
			};

			$this->assertSame(null, $detect(null));

			$value = rand();
			$this->assertSame($value, $detect($value));

			$_GET[$param] = $value;
			$this->assertSame($value, $detect(null));
			unset($_GET[$param]);
		};

		$testParameter = function($param, $functionNameSuffix) use ($testRequire, $testDetect){
			$testRequire($param, 'require' . $functionNameSuffix);
			$testDetect($param, 'detect' . $functionNameSuffix);
		};

		$testParameter('pid', 'ProjectId');
		$testParameter('event_id', 'EventId');
		$testParameter('instance', 'InstanceId');
	}

	function testDetectParamter_sqlInjection(){
		$_GET['pid'] = 'delete * from an_important_table';
		$this->assertEquals(0, $this->callPrivateMethod('detectParameter', 'pid'));
	}

	function testHasPermission()
	{
		$m = $this->getInstance();

		$testPermission = 'some_test_permission';
		$config = ['permissions' => []];

		$this->setConfig($config);
		$this->assertFalse($m->hasPermission($testPermission));

		$config['permissions'][] = $testPermission;
		$this->setConfig($config);
		$this->assertTrue($m->hasPermission($testPermission));
	}

	function testGetUrl()
	{
		$m = $this->getInstance();

		$base = APP_PATH_WEBROOT_FULL . 'external_modules/?prefix=' . $m->PREFIX . '&page=';
		$apiBase = APP_PATH_WEBROOT_FULL . 'api/?type=module&prefix=' . $m->PREFIX . '&page=';
		$moduleBase = ExternalModules::getModuleDirectoryUrl($m->PREFIX, $m->VERSION);

		$this->assertSame($base . 'test', $m->getUrl('test.php'));
		$this->assertSame($base . 'test&NOAUTH', $m->getUrl('test.php', true));
		$this->assertSame($apiBase . 'test', $m->getUrl('test.php', false, true));

		$pid = 123;
		$_GET['pid'] = $pid;
		$this->assertSame($base . 'test&pid=' . $pid, $m->getUrl('test.php'));

		$mTime = filemtime(ExternalModules::getModuleDirectoryPath($m->PREFIX) . '/images/foo.png');
		$this->assertSame($moduleBase . "images/foo.png?$mTime", $m->getUrl('images/foo.png'));
		$this->assertSame($apiBase . 'images%2Ffoo.png', $m->getUrl('images/foo.png', false, true));
	}

	private function getUnitTestingModuleId()
	{
		$id = ExternalModules::getIdForPrefix(TEST_MODULE_PREFIX);
		$this->assertTrue(ctype_digit($id));
		
		return $id;
	}

	function testLogAndQueryLog()
	{
		$m = $this->getInstance();
		$testingModuleId = $this->getUnitTestingModuleId();

		// Remove left over messages in case this test previously failed
		$m->query('delete from redcap_external_modules_log where external_module_id = ?', [$testingModuleId]);

		$message = TEST_LOG_MESSAGE;
		$paramName1 = 'testParam1';
		$paramValue1 = rand();
		$paramName2 = 'testParam2';
		$paramValue2 = rand();
		$paramName3 = 'testParam3';

		$query = function () use ($m, $testingModuleId, $message, $paramName1, $paramName2) {
			$results = $m->queryLogs("
				select log_id,timestamp,username,ip,external_module_id,record,message,$paramName1,$paramName2
				where
					message = ?
					and timestamp > ?
				order by log_id asc
			", [$message, date('Y-m-d', time()-10)]);

			$timestampThreshold = 5;

			$rows = [];
			while ($row = $results->fetch_assoc()) {
				$currentUTCTime = $date_utc = new \DateTime("now", new \DateTimeZone("UTC"));
				$timeSinceLog = $currentUTCTime - strtotime($row['timestamp']);

				$this->assertTrue(gettype($row['log_id']) === 'integer');
				$this->assertTrue($timeSinceLog < $timestampThreshold);
				$this->assertEquals($testingModuleId, $row['external_module_id']);
				$this->assertEquals($message, $row['message']);

				$rows[] = $row;
			}

			return $rows;
		};

		ExternalModules::setUsername(null);
		$_SERVER['HTTP_CLIENT_IP'] = null;
		$m->setRecordId(null);
		$m->log($message);

		$username = $this->getRandomUsername();

		ExternalModules::setUsername($username);
		$_SERVER['HTTP_CLIENT_IP'] = '1.2.3.4';
		$m->setRecordId('abc-' . rand()); // We prepend a string to make sure alphanumeric record ids work.
		$m->log($message, [
			$paramName1 => $paramValue1,
			$paramName2 => $paramValue2,
			$paramName3 => null
		]);

		$rows = $query();
		$this->assertEquals(2, count($rows));
		
		$row = $rows[0];
		$this->assertSame($message, $row['message']);
		$this->assertNull($row['username']);
		$this->assertNull($row['ip']);
		$this->assertNull($row['record']);
		$this->assertFalse(isset($row[$paramName1]));
		$this->assertFalse(isset($row[$paramName2]));

		$row = $rows[1];
		$this->assertEquals($username, $row['username']);
		$this->assertEquals($_SERVER['HTTP_CLIENT_IP'], $row['ip']);
		$this->assertEquals($m->getRecordId(), $row['record']);
		$this->assertEquals($paramValue1, $row[$paramName1]);
		$this->assertEquals($paramValue2, $row[$paramName2]);
		$this->assertNull($row[$paramName3]);

		$m->removeLogs("$paramName1 is null");
		$rows = $query();
		$this->assertEquals(1, count($rows));
		$this->assertEquals($paramValue1, $rows[0][$paramName1]);

		$m->removeLogs("message = '$message'");
		$rows = $query();
		$this->assertEquals(0, count($rows));
	}

	function testLogAndQueryLog_allowedCharacters()
	{
		$name = 'aA1 -_$';
		$value = (string) rand();
		
		$logId = $this->log('foo', [
			$name => $value,
			'goo' => 'doo'
		]);

		$whereClause = 'log_id = ?';
		$result = $this->queryLogs("select log_id, timestamp, goo, `$name` where $whereClause", $logId);
		$row = $result->fetch_assoc();
		$this->assertSame($value, $row[$name]);
		$this->removeLogs($whereClause, $logId);
	}

	function testLogAndQueryLog_disallowedCharacters()
	{
		$invalidParamName = 'sql injection ; example';
		
		$assertThrowsException = function($action) use ($invalidParamName){
			$this->assertThrowsException($action, ExternalModules::tt('em_errors_115', $invalidParamName));
		};

		$assertThrowsException(function() use ($invalidParamName){
			$this->log('foo', [
				$invalidParamName => rand()
			]);
		});
		$this->removeLogs('log_id = ?', db_insert_id());

		$assertThrowsException(function() use ($invalidParamName){
			$this->queryLogs("select 1 where `$invalidParamName` is null");
		});
	}

	function testLog_timestamp()
	{
		$m = $this->getInstance();

		$timestamp = ExternalModules::makeTimestamp(time()-ExternalModules::HOUR_IN_SECONDS);
		$logId = $m->log('test', [
			'timestamp' => $timestamp
		]);
		
		$this->assertLogValues($logId, [
			'timestamp' => $timestamp
		]);
	}

	function testLog_pid()
	{
		$m = $this->getInstance();
		$message = 'test';
		$whereClause = "message = ?";
		$expectedPid = rand();

		$assertRowCount = function($expectedCount) use ($m, $message, $whereClause, $expectedPid){
			$result = $m->queryLogs('select pid where ' . $whereClause, $message);
			$rows = [];
			while($row = $result->fetch_assoc()){
				$rows[] = $row;

				$pid = @$_GET['pid'];
				if(!empty($pid)){
					$this->assertEquals($expectedPid, $pid);
				}
			}

			$this->assertEquals($expectedCount, count($rows));
		};

		$m->log($message);
		$_GET['pid'] = $expectedPid;
		$m->log($message);

		// A pid is still set, so only that row should be returned.
		$assertRowCount(1);

		// Unset the pid and make sure both rows are returned.
		$_GET['pid'] = null;
		$assertRowCount(2);

		// Re-set the pid and attempt to remove only the pid row
		$_GET['pid'] = $expectedPid;
		$m->removeLogs($whereClause, $message);

		// Unset the pid and make sure only the row without the pid is returned
		$_GET['pid'] = null;
		$assertRowCount(1);

		// Make sure removeLogs() now removes the row without the pid.
		$m->removeLogs($whereClause, $message);
		$assertRowCount(0);
	}

	function testLog_emptyMessage()
	{
		$m = $this->getInstance();

		foreach ([null, ''] as $value) {
			$this->assertThrowsException(function () use ($m, $value) {
				$m->log($value);
			}, 'A message is required for log entries.');
		}
	}

	function testLog_reservedParameterNames()
	{
		$m = $this->getInstance();

		$reservedParameterNames = AbstractExternalModule::$RESERVED_LOG_PARAMETER_NAMES;

		foreach ($reservedParameterNames as $name) {
			$this->assertThrowsException(function () use ($m, $name) {
				$m->log('test', [
					$name => 'test'
				]);
			}, 'parameter name is set automatically and cannot be overridden');
		}
	}

	function testLog_recordId()
	{
		$m = $this->getInstance();

		$m->setRecordId(null);
		$logId = $m->log('test');
		$this->assertLogValues($logId, [
			'record' => null
		]);

		$generateRecordId = function(){
			return 'some prefix to make sure string record ids work - ' . rand();
		};

		$message = TEST_LOG_MESSAGE;
		$recordId1 = $generateRecordId();
		$m->setRecordId($recordId1);

		$logId = $m->log($message);
		$this->assertLogValues($logId, ['record' => $recordId1]);

		// Make sure the detected record id can be overridden by developers
		$params = ['record' => $generateRecordId()];
		$logId = $m->log($message, $params);
		$this->assertLogValues($logId, $params);
	}

	// Verifies that the specified values are stored in the database under the given log id.
	private function assertLogValues($logId, $expectedValues = [])
	{
		$columnNamesSql = implode(',', array_keys($expectedValues));
		$selectSql = "select $columnNamesSql where log_id = ?";

		$m = $this->getInstance();
		$result = $m->queryLogs($selectSql, $logId);
		$log = $result->fetch_assoc();

		foreach($expectedValues as $name=>$expectedValue){
			$actualValue = $log[$name];
			$this->assertSame($expectedValue, $actualValue, "For the '$name' log parameter:");
		}
	}

	function testLog_escapedCharacters()
	{
		$m = $this->getInstance();
		$maliciousSql = "'; delete from everything";
		$m->log($maliciousSql, [
			"malicious_param" => $maliciousSql
		]);

		$selectSql = 'select message, malicious_param order by timestamp desc limit 1';
		$result = $m->queryLogs($selectSql, []);
		$row = $result->fetch_assoc();
		$this->assertSame($maliciousSql, $row['message']);
		$this->assertSame($maliciousSql, $row['malicious_param']);
	}

	function testLog_spacesInParameterNames()
	{
		$m = $this->getInstance();

		$paramName = "some param";
		$paramValue = "some value";

		$m->log('test', [
			$paramName => $paramValue
		]);

		$selectSql = "select `$paramName` where `$paramName` is not null order by `$paramName`";
		$result = $m->queryLogs($selectSql, []);
		$row = $result->fetch_assoc();
		$this->assertSame($paramValue, $row[$paramName]);

		$m->removeLogs("`$paramName` is not null");
		$result = $m->queryLogs($selectSql, []);
		$this->assertNull($result->fetch_assoc());
	}

	function testLog_unsupportedTypes()
	{
		$this->assertThrowsException(function(){
			$m = $this->getInstance();
			$m->log('foo', [
				'some-unsupported-type' => new \stdClass()
			]);
		}, "The type 'object' for the 'some-unsupported-type' parameter is not supported");
	}

	function testLog_overridableParameters()
	{
		$m = $this->getInstance();

		$testValues = [
			'timestamp' => date("Y-m-d H:i:s"),
			'username' => $this->getRandomUsername(),
			'project_id' => 1
		];

		foreach(AbstractExternalModule::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE as $name){
			$value = $testValues[$name];
			if(empty($value)){
				$value = 'foo';
			}

			$params = [
				$name => $value
			];

			$logId = $m->log('foo', $params);
			$this->assertLogValues($logId, $params);

			// Make sure a parameter table entry was NOT made, since the value should only be stored in the main log table.
			$result = $m->query("select * from redcap_external_modules_log_parameters where log_id = ?", [$logId]);
			$row = $result->fetch_assoc();
			$this->assertNull($row);
		}
	}

	function testLog_emptyParamNames()
	{
		$this->assertThrowsException(function(){
			$this->log('foo', [
				'' => rand()
			]);
		}, ExternalModules::tt('em_errors_116'));

		$this->removeLogs('log_id = ?', db_insert_id());
	}

	function testGetIP()
	{
		$ip = '1.2.3.4';
		$_SERVER['HTTP_CLIENT_IP'] = $ip;
		$username = 'jdoe';
		ExternalModules::setUsername($username);

		$assertIp = function($expected, $param = null){
			$this->assertSame($expected, $this->callPrivateMethod('getIP', $param));
		};

		$ipParameter = '2.3.4.5';
		$assertIp($ipParameter, $ipParameter);

		$assertIp($ip);

		$_SERVER['REQUEST_URI'] = APP_PATH_SURVEY;
		$assertIp(null);

		$_SERVER['REQUEST_URI'] = '';
		$assertIp($ip);

		ExternalModules::setUsername(null);
		$assertIp(null);

		ExternalModules::setUsername($username);
		$assertIp($ip);

		unset($_SERVER['HTTP_CLIENT_IP']);
		$assertIp(null);
	}

	function assertLogAjax($data)
	{
		$data['message'] = TEST_LOG_MESSAGE;

		$m = $this->getInstance();
		$m->setRecordId(null);

		$logId = $m->logAjax($data);
		$this->assertLogValues($logId, [
			'record' => $data['parameters']['record']
		]);

		// TODO - At some point, it would be nice to test the survey hash parameters here.
	}

	function testLogAjax_overridableParameters()
	{
		foreach(AbstractExternalModule::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE as $name){
			$this->assertThrowsException(function() use ($name){
				$this->assertLogAjax([
					'parameters' => [
						$name => 'foo'
					]
				]);
			}, "'$name' parameter cannot be overridden via AJAX log requests");
		}
	}

	function testLogAjax_record()
	{
		// Make sure these don't throw an exception
		$this->assertLogAjax([
			'noAuth' => true
		]);
		$this->assertLogAjax([
			'noAuth' => true,
			'parameters' => [
				'record' => ExternalModules::EXTERNAL_MODULES_TEMPORARY_RECORD_ID . '-123'
			]
		]);

		$this->assertThrowsException(function(){
			$this->assertLogAjax([
				'noAuth' => true,
				'parameters' => [
					'record' => '123'
				]
			]);
		}, "'record' parameter cannot be overridden via AJAX log requests");
	}

	function testGetQueryLogsSql_moduleId()
	{
		$m = $this->getInstance();

		$columnName = 'external_module_id';

		// Make sure that when no where clause is present, a where clause for the current module is added
		$sql = $m->getQueryLogsSql("select log_id");
		$this->assertEquals(1, substr_count($sql, AbstractExternalModule::EXTERNAL_MODULE_ID_STANDARD_WHERE_CLAUSE_PREFIX . " = '" . TEST_MODULE_PREFIX . "')"));

		$moduleId = rand();
		$overrideClause = "$columnName = $moduleId";
		$sql = $m->getQueryLogsSql("select 1 where $overrideClause");

		// Make sure there is only one clause related to the module id.
		$this->assertEquals(1, substr_count($sql, $columnName));

		// Make sure our override clause has replaced the the clause for the current module.
		$this->assertEquals(1, substr_count($sql, $overrideClause));
	}

	function testGetQueryLogsSql_overrideProjectId()
	{
		$m = $this->getInstance();

		$columnName = 'project_id';

		// Make sure that when no where clause is present, a where clause for the current project is added
		$projectId = '1';
		$_GET['pid'] = $projectId;
		$sql = $m->getQueryLogsSql("select log_id");
		$this->assertEquals(1, substr_count($sql, "$columnName = $projectId"));

		$projectId = '2';
		$overrideClause = "$columnName = $projectId";
		$sql = $m->getQueryLogsSql("select 1 where $overrideClause");

		// Make sure there is only one clause related to the project id.
		$this->assertEquals(1, substr_count($sql, $columnName));

		// Make sure our override clause has replaced the the clause for the current project.
		$this->assertEquals(1, substr_count($sql, $overrideClause));
	}

	function testExceptionOnMissingMethod()
	{
		// We use the __call() magic method, which prevents the default missing method error.
		// The following asserts that we are throwing our own exception from __call().
		$this->assertThrowsException(function(){
			$m = $this->getInstance();
			$m->someMethodThatDoesntExist();
		}, 'method does not exist');
	}

	function testGetSubSettings()
	{
		$pid = TEST_SETTING_PID;
		$_GET['pid'] = $pid;
		$m = $this->getInstance();

		$settingValues = [
			// Make sure the first setting is no longer being used to detect any lengths by simulating a new/empty setting.
			'key1' => [],

			// These settings each intentionally have difference lengths to make sure they're still returned appropriately.
			'key2' => ['a', 'b', 'c'],
			'key3' => [1,2,3,4,5],
			'key4' => [true, false]
		];

		$subSettingsConfig = [];
		foreach($settingValues as $key=>$values){
			$m->setProjectSetting($key, $values);

			$subSettingsConfig[] = [
				'key' => $key
			];
		}

		$subSettingsKey = 'sub-settings-key';
		$this->setConfig([
			'project-settings' => [
				[
					'key' => $subSettingsKey,
					'type' => 'sub_settings',
					'sub_settings' => $subSettingsConfig
				]
			]
		]);

		$assertSubSettings = function($pid) use ($m, $subSettingsKey, $settingValues) {
			$subSettingResults = $m->getSubSettings($subSettingsKey, $pid);
			foreach($settingValues as $key=>$values){
				for($i=0; $i<count($values); $i++){
					$this->assertSame($settingValues[$key][$i], $subSettingResults[$i][$key]);
				}
			}
		};

		$assertSubSettings(null);

		unset($_GET['pid']);

		$this->assertThrowsException(function() use ($assertSubSettings) {
			$assertSubSettings(null);
		}, 'argument to this method: pid');

		$assertSubSettings($pid);
	}

	function testSetSetting_concurrency()
	{
		// This test spins off a second phpunit process in order to test concurrency and locking in setSetting().
		// If you comment out the GET_LOCK call in setSetting(), an exception should occur within a fraction of $maxIterations.
		$iterations = 0;
		$maxIterations = 1000;

		$concurrentOperations = function(){
			$this->setProjectSetting('some value');
			$this->removeProjectSetting();
		};

		$parentAction = function ($isChildRunning) use ($concurrentOperations, $iterations, $maxIterations) {
			while($isChildRunning()){
				$concurrentOperations();
				$iterations++;
			}

			// The parent will generally run more iterations than the child, but apparently not always.
			// Consider the text successful if $iterations is at least 90% of $maxIterations.
			$this->assertGreaterThan($maxIterations * 0.9, $iterations);
		};

		$childAction = function () use ($iterations, $maxIterations, $concurrentOperations) {
			while ($iterations < $maxIterations) {
				$concurrentOperations();
				$iterations++;
			}

			$this->assertSame($iterations, $maxIterations);
		};

		$this->runConcurrentTestProcesses(__FUNCTION__, $parentAction, $childAction);
	}

	function testSetSetting_projectDesignRights()
	{
		ExternalModules::setSuperUser(false);

		$m = $this->getInstance();
		$fieldName = 'project';
		$pid = $_GET['pid'] = TEST_SETTING_PID;
		$pidWithRights = TEST_SETTING_PID_2;

		$this->setConfig([
			'system-settings' => [
				[
					'key' => $fieldName,
					'type' => 'project-id'
				]
			]
		]);

		$username = $this->getUsernameNotOnProject($pid);

		// Make the username lowercase because some usernames are stored with a capitalized first character (ex: 'Crenshd'),
		// even though REDCap functions like UserRights::getPrivileges() expect them to be all lowercase.
		$username = strtolower($username);
		
		ExternalModules::setUsername($username);

		$addToProject = function($pid, $design) use ($m, $username){
			$m->framework->getProject($pid)->addUser($username, [
				'design' => $design,
			]);
		};

		$addToProject($pid, 0);
		$addToProject($pidWithRights, 1);
		
		$assert = function($exceptionExpected, $oldValue, $newValue) use ($m, $fieldName, $username){
			$action = function() use ($m, $fieldName, $oldValue, $newValue){
				ExternalModules::setSuperUser(true);
				$m->setProjectSetting($fieldName, $oldValue);

				ExternalModules::setSuperUser(false);
				$m->setProjectSetting($fieldName, $newValue);
			};

			// change to assert?
			// The try/catch is only to print the username used on failure.
			try{
				if($exceptionExpected){
					$this->assertThrowsException($action, 'do not have design rights');
				}
				else{
					$action();
				}
			}
			catch(Exception $e){
				throw new Exception("Error running test for username: $username", 0, $e);
			}
		};

		// Test a few different sub-setting structures for good measure.
		$values = [
			$pid,
			[$pid, $pidWithRights],
			[$pidWithRights, $pid],
			[[$pid, $pidWithRights], [$pidWithRights, $pidWithRights]],
			[[$pidWithRights, $pidWithRights], [$pidWithRights, $pid]],
		];

		try{
			foreach($values as $value){
				$valueWithRights = json_decode(str_replace($pid, $pidWithRights, json_encode($value)), true);

				$assert(false, null, $valueWithRights);
				$assert(true, null, $value);
				$assert(false, $value, $valueWithRights);
				$assert(true, $valueWithRights, $value);
				
				if(is_array($value)){
					// Adding instances should work if you have access to the ones you're adding (regardless of existing instances).
					$assert(false, $value, array_merge($value, $valueWithRights));
					$assert(true, $value, array_merge($value, $value));
					
					// Removing instances should always work.
					$assert(false, array_merge($value, $value), $value);
				}
			}
		}
		finally{
			ExternalModules::removeUserFromProject($pid, $username);
			ExternalModules::removeUserFromProject($pidWithRights, $username);
		}
	}

	private function getUsernameNotOnProject($pid){
		$rights = \UserRights::getPrivileges($pid)[$pid];
		$usernames = array_keys($rights);
		
		$count = 0;
		while(true){
			$username = $this->getRandomUsername();
			if(!in_array($username, $usernames)){
				break;
			}

			$count++;
			if($count > 10){
				throw new Exception("An error occurred while trying to find a user that wasn't on project $pid.");
			}
		}

		return $username;
	}

	function testGetPublicSurveyUrl(){
		$m = $this->getInstance();

		$result = $m->query("
			select *
			from (
				select s.project_id, h.hash, count(*)
				from redcap_surveys s
				join redcap_surveys_participants h
					on s.survey_id = h.survey_id
				join redcap_metadata m
					on m.project_id = s.project_id
					and m.form_name = s.form_name
					and field_order = 1 -- getting the first field is the easiest way to get the first form
				where participant_email is null
				group by s.form_name -- test a form name that exists on multiple projects
				order by count(*) desc
				limit 100
			) a
			order by rand() -- select a random row to make sure we often end up with a different project ID than getPublicSurveyUrl() would by default if it didn't specific a project ID in it's query
			limit 1
		", []);

		$row = $result->fetch_assoc();
		$projectId = $row['project_id'];
		$hash = $row['hash'];

		global $Proj;
		$Proj = new \Project($projectId);
		$_GET['pid'] = $projectId;
		
		$expected = APP_PATH_SURVEY_FULL . "?s=$hash";
		$actual = $m->getPublicSurveyUrl();

		$this->assertSame($expected, $actual);
	}

	function testMultipleDAGMethods(){
		$_GET['pid'] = TEST_SETTING_PID;

		$getName = function($id){
			$result = ExternalModules::query('select group_name from redcap_data_access_groups s where project_id = ? and group_id = ?', [TEST_SETTING_PID, $id]);
			return $result->fetch_assoc()['group_name'];
		};

		$m = $this->getInstance();
		$name = 'test dag ' . rand();
		$id = $m->createDag($name);
		
		$this->assertSame($name, $getName($id));

		$name .= '-2';
		$m->renameDAG($id, $name);
		$this->assertSame($name, $getName($id));

		$m->deleteDAG($id);
		$this->assertNull($getName($id));
	}

	function testGetProjectAndRecordFromHashes(){
		$m = $this->getInstance();

		$result = $m->query("
			SELECT s.project_id as projectId, r.record as recordId, s.form_name as surveyForm, p.event_id as eventId,
				p.hash, r.return_code
			FROM redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s
			WHERE p.survey_id = s.survey_id
				AND p.participant_id = r.participant_id
				and return_code is not null
			ORDER BY p.participant_id DESC
			LIMIT 1
		", []);

		$expected = $result->fetch_assoc();
		$actual = $m->getProjectAndRecordFromHashes($expected['hash'], $expected['return_code']);

		$fieldNames = [
			'projectId',
			'recordId',
			'surveyForm',
			'eventId'
		];

		foreach($fieldNames as $fieldName){
			$this->assertSame($expected[$fieldName], $actual[$fieldName]);
		}
	}

	function testGetProjectDetails(){
		$m = $this->getInstance();
		$details = $m->getProjectDetails(TEST_SETTING_PID);

		$this->assertSame(TEST_SETTING_PID, $details['project_id']);
		$this->assertGreaterThan(100, count($details));
	}

	function testSetData(){
		$_GET['pid'] = TEST_SETTING_PID;
		$_GET['event_id'] = $this->getInstance()->framework->getEventId();
		$_GET['instance'] = 1;
		$recordId = 1;

		$result = $this->query("
			select field_name
			from redcap_metadata
			where
				project_id = ?
				and field_order = ?
				and field_name not like '%_complete'
		", [TEST_SETTING_PID, 2]);

		$fieldName = $result->fetch_row()[0];
		if(empty($fieldName)){
			throw new Exception("You must add a field to the External Module test project with ID " . TEST_SETTING_PID);
		}

		$value = (string) rand();

		$this->ensureRecordExists($recordId);
		
		$this->setData($recordId, $fieldName, $value);

		$data = json_decode(REDCap::getData(TEST_SETTING_PID, 'json', $recordId), true)[0];

		$this->assertSame($value, $data[$fieldName]);
	}

	function __get($varName){
		return $this->getInstance()->$varName;
	}

	function testAddAutoNumberedRecord(){
		$_GET['pid'] = TEST_SETTING_PID;

		$recordId1 = $this->addAutoNumberedRecord();
		$recordId2 = $this->addAutoNumberedRecord();

		$this->assertSame($recordId1+1, $recordId2);

		$this->deleteRecords(TEST_SETTING_PID, [$recordId1, $recordId2]);
	}

	function deleteRecord($pid, $recordId){
		$this->deleteRecords($pid, [$recordId]);
	}

	function deleteRecords($pid, $recordIds){
		$q = $this->framework->createQuery();
		$q->add('delete from redcap_data where project_id = ? and', [$pid]);
		$q->addInClause('record', $recordIds);
		$q->execute();

		$this->assertSame(count($recordIds), $q->affected_rows);
	}

	function testResetSurveyAndGetCodes_partial(){
		// Just make sure it runs without exception for now.  We can expand this test in the future.
		$this->resetSurveyAndGetCodes(TEST_SETTING_PID, 1);
		$this->expectNotToPerformAssertions();
	}

	function testGenerateUniqueRandomSurveyHash(){
		$hash = $this->generateUniqueRandomSurveyHash();
		$this->assertSame(10, strlen($hash));
	}

	function testGetValidFormEventId(){
		$pid = TEST_SETTING_PID;
		$formName = ExternalModules::getFormNames($pid)[0];
		$expected = $this->getValidFormEventId($formName, $pid);
		$actual = (string) $this->getFramework()->getEventId($pid);

		$this->assertSame($expected, $actual);
	}

	function testGetSurveyId(){
		list($surveyId, $formName) = $this->getSurveyId(TEST_SETTING_PID);
		$this->assertTrue(ctype_digit($surveyId));
		$this->assertTrue($surveyId > 0);
		$this->assertSame(ExternalModules::getFormNames(TEST_SETTING_PID)[0], $formName);
	}

	function testGetParticipantAndResponseId(){
		list($surveyId, $formName) = $this->getSurveyId(TEST_SETTING_PID);
		$ids = $this->getParticipantAndResponseId($surveyId, 1);

		foreach($ids as $id){
			$this->assertTrue(ctype_digit($id));
			$this->assertTrue($id > 0);
		}
	}

	function testGetChoiceLabel_sql(){
		$result = $this->query("
			select * from redcap_metadata
			where
				project_id = ?
				and field_name = ?
		", [TEST_SETTING_PID, TEST_SQL_FIELD]);

		$field = $result->fetch_assoc();
		$result = $this->query($field['element_enum']);
		$choices = $result->fetch_all(MYSQLI_BOTH);

		$choice = $choices[0];
	
		$actualLabel = $this->getChoiceLabel($field['field_name'], $choice[0], $field['project_id']);
		
		$this->assertSame($choice[1], $actualLabel, "Failed on field: " . json_encode($field));
	}

	function testGetFirstEventId(){
		$_GET['pid'] = TEST_SETTING_PID;
		$eventId = $this->framework->getEventId(TEST_SETTING_PID);
		$this->assertSame($eventId, $this->getFirstEventId());
	}

	function testDelayModuleExecution()
	{
        $exceptionThrown = false;
        $throwException = function($message) use (&$exceptionThrown){
            $exceptionThrown = true;
            throw new Exception($message);
        };

        $hookExecutionsExpected = 3;
        $executionNumber = 0;
        $delayTestFunction = function($module) use (&$executionNumber, $hookExecutionsExpected, $throwException){
            $hookRunner = self::callPrivateMethodForClass(ExternalModules::class, 'getCurrentHookRunner');

            // The delay queue should be empty at the beginning of each call.
            $this->assertEmpty($hookRunner->getDelayed());

			$delaySuccessful = $module->delayModuleExecution();
            $executionNumber++;

            if($executionNumber < $hookExecutionsExpected){
                if(!$delaySuccessful){
                    $throwException("The first hook run and the first attempt at re-running after delaying should both successfully delay.");
                }
            }
            else if($executionNumber == $hookExecutionsExpected){
                if($delaySuccessful){
                    $throwException("The final run that gives modules a last chance to run if they have been delaying should NOT successfully delay.");
                }
            }
        };

		ExternalModules::callHook('redcap_test_call_function', [$delayTestFunction]);
        $this->assertFalse($exceptionThrown);
		$this->assertEquals($hookExecutionsExpected, $executionNumber);
	}

	function testSetDAG(){
		$_GET['pid'] = TEST_SETTING_PID;
		$recordId = rand();
		$dagName = (string) rand();

		$this->assertSame(null, $this->getFramework()->getDAG($recordId));
		$this->setDAG($recordId, $dagName);
		$this->assertSame($dagName, $this->getFramework()->getDAG($recordId));
	}

	/**
	 * New AbstractExternalModule methods can potentially conflict with module code, sometimes even crashing the REDCap server.
	 * This test ensures that new methods are only added to the Framework class going forward,
	 * and that old methods are left in place for backward compatibility (including method_exists() calls).
	 */
	function testNoNewMethodsAdded(){
		$expected = [
			"__call",
			"__construct",
			"__get",
			"addAutoNumberedRecord",
			"areSettingPermissionsUserBased",
			"createDAG",
			"createPassthruForm",
			"delayModuleExecution",
			"deleteDAG",
			"disableUserBasedSettingPermissions",
			"exitAfterHook",
			"generateUniqueRandomSurveyHash",
			"getChoiceLabel",
			"getChoiceLabels",
			"getConfig",
			"getData",
			"getFieldLabel",
			"getFirstEventId",
			"getMetadata",
			"getModuleDirectoryName",
			"getModuleName",
			"getModulePath",
			"getParticipantAndResponseId",
			"getProjectAndRecordFromHashes",
			"getProjectDetails",
			"getProjectId",
			"getProjectSetting",
			"getProjectSettings",
			"getPublicSurveyHash",
			"getPublicSurveyUrl",
			"getQueryLogsSql",
			"getRecordId",
			"getRecordIdOrTemporaryRecordId",
			"getSettingConfig",
			"getSubSettings",
			"getSurveyId",
			"getSystemSetting",
			"getSystemSettings",
			"getUrl",
			"getUserSetting",
			"getValidFormEventId",
			"hasPermission",
			"init",
			"initializeJavascriptModuleObject",
			"isSurveyPage",
			"logAjax",
			"prefixSettingKey",
			"query",
			"queryLogs",
			"redcap_every_page_test",
			"redcap_module_configure_button_display",
			"redcap_module_link_check_display",
			"redcap_save_record",
			"redcap_test",
			"redcap_test_call_function",
			"removeLogs",
			"removeProjectSetting",
			"removeSystemSetting",
			"removeUserSetting",
			"renameDAG",
			"requireProjectId",
			"resetSurveyAndGetCodes",
			"saveData",
			"saveFile",
			"saveInstanceData",
			"sendAdminEmail",
			"setDAG",
			"setData",
			"setLinkCheckDisplayReturnValue",
			"setProjectSetting",
			"setProjectSettings",
			"setRecordId",
			"setSettingKeyPrefix",
			"setSystemSetting",
			"setUserSetting",
			"validateSettings"
		];

		$actual = get_class_methods($this->getReflectionClass());
		sort($actual);
		$this->assertSame($expected, $actual);
	}
}
