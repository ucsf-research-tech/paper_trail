<?php
namespace ExternalModules;

use DateTime;
use REDCap;

class FrameworkV1Test extends BaseTest
{
	function __construct(){
		parent::__construct();

		preg_match('/[0-9]+/', get_class($this), $matches);
		$this->frameworkVersion = (int) $matches[0];
	}

	protected function getReflectionClass()
	{
		return $this->getFramework();
	}

	function getFrameworkVersion(){
		return $this->frameworkVersion;
	}

	function testImportDataDictionary(){
		// BaseTest::setUp() calls importDataDictionary() once when the first test runs
		// (so it will already have been called at this point).
		// So far this test only contains assertions for things that have changed since the initial implementation

		$actual = $this->query(
			'select element_enum from redcap_metadata where project_id = ? and field_name = ?',
			[TEST_SETTING_PID, TEST_FORM . '_complete']
		)->fetch_assoc()['element_enum'];
		
		$this->assertSame('0, Incomplete \n 1, Unverified \n 2, Complete', $actual);
	}

	function testQuery_noParameters(){
		$value = (string)rand();
		$result = $this->query("select $value", []);
		$row = $result->fetch_row();
		$this->assertSame($value, $row[0]);

		$frameworkVersion = $this->getFrameworkVersion();
		if($frameworkVersion < 4){
			$value = (string)rand();
			$result = $this->query("select $value");
			$row = $result->fetch_row();
			$this->assertSame($value, $row[0]);	
		}
		else{
			$this->assertThrowsException((function(){
				$this->query("select 1");
			}), ExternalModules::tt('em_errors_117'));
		}
	}

	function testQuery_trueReturnForDatalessQueries(){
		$r = $this->query('update redcap_ip_banned set time_of_ban=now() where ?=?', [1,2]);
        $this->assertTrue($r);
	}

	function testQuery_invalidQuery(){
		$this->assertThrowsException(function(){
			ob_start();
			$this->query("select * from some_table_that_does_not_exist", []);
		}, ExternalModules::tt("em_errors_29"));

		ob_end_clean();
	}

	function testQuery_paramTypes(){
		$dateTimeString = '2001-02-03 04:05:06';

		$values = [
			true,
			2,
			3.3,
			'four',
			null,
			new DateTime($dateTimeString)
		];

		$row = $this->query('select ?, ?, ?, ?, ?, ?', $values)->fetch_row();

		$values[0] = 1; // The boolean 'true' will get converted to the integer '1'.  This is excepted.
		$values[5] = $dateTimeString;

		$this->assertSame($values, $row);
	}

	function testQuery_invalidParamType(){
		$this->assertThrowsException(function(){
			ob_start();
			$invalidParam = new \stdClass();
			$this->query("select ?", [$invalidParam]);
		}, ExternalModules::tt('em_errors_109'));

		ob_end_clean();
	}
	
	function testQuery_singleParams(){
		$values = [
			rand(),
			
			// Check falsy values
			0,
			'0',
			''
		];

		foreach($values as $value){
			$row = $this->query('select ?', $value)->fetch_row();
			$this->assertSame($value, $row[0]);
		}
	}

	function testGetSubSettings_complexNesting()
	{
		if($this->getFrameworkVersion() === 1){
			// This test is intended for newer framework versions only.
			$this->expectNotToPerformAssertions();
			return;
		}

		$m = $this->getInstance();
		$_GET['pid'] = TEST_SETTING_PID;

		// This json file can be copied into a module for hands on testing/modification via the settings dialog.
		$this->setConfig(json_decode(file_get_contents(__DIR__ . '/complex-nested-settings.json'), true));

		// These values were copied directly from the database after saving them through the settings dialog (as configured by the json file above).
		$m->setProjectSetting('countries', ["true","true"]);
		$m->setProjectSetting('country-name', ["USA","Canada"]);
		$m->setProjectSetting('states', [["true","true"],["true"]]);
		$m->setProjectSetting('state-name', [["Tennessee","Alabama"],["Ontario"]]);
		$m->setProjectSetting('cities', [[["true","true"],["true"]],[["true"]]]);
		$m->setProjectSetting('city-name', [[["Nashville","Franklin"],["Huntsville"]],[["Toronto"]]]);
		$m->setProjectSetting('city-size', [[["large","small"],["medium"]],[[null]]]); // The null is an important scenario to test here, as it can change output behavior.

		$expectedCountries = [
			[
				"states" => [
					[
						"state-name" => "Tennessee",
						"cities" => [
							[
								"city-name" => "Nashville",
								"city-size" => "large"
							],
							[
								"city-name" => "Franklin",
								"city-size" => "small"
							]
						]
					],
					[
						"state-name" => "Alabama",
						"cities" => [
							[
								"city-name" => "Huntsville",
								"city-size" => "medium"
							]
						]
					]
				],
				"country-name" => "USA"
			],
			[
				"states" => [
					[
						"state-name" => "Ontario",
						"cities" => [
							[
								"city-name" => "Toronto",
								"city-size" => null
							]
						]
					]
				],
				"country-name" => "Canada"
			]
		];

		// Call the new implementation on the framework object directly.
		// The old implementation was available via the module instance directly until v5,
		// and it does NOT support complex nesting correctly.
		$this->assertEquals($expectedCountries, $this->getFramework()->getSubSettings('countries'));
	}

	function testGetSubSettings_plainOldRepeatableInsideSubSettings(){
		$m = $this->getInstance();
		$_GET['pid'] = TEST_SETTING_PID;

		$this->setConfig('
			{
				"project-settings": [
					{
						"key": "one",
						"name": "one",
						"type": "sub_settings",
						"repeatable": true,
						"sub_settings": [
							{
								"key": "two",
								"name": "two",
								"type": "text",
								"repeatable": true
							}
						]
					}
				]
			}
		');

		$m->setProjectSetting('one', ["true"]);
		$m->setProjectSetting('two', [["value"]]);

		$this->assertEquals(
			[
				[
					'two' => [
						'value'
					]
				]
			],
			$this->getSubSettings('one')
		);
	}

	function testGetProjectsWithModuleEnabled(){
		$assert = function($enableValue, $expectedPids){
			$m = $this->getInstance();
			$m->setProjectSetting(ExternalModules::KEY_ENABLED, $enableValue, TEST_SETTING_PID);
			$pids = $this->getProjectsWithModuleEnabled();
			$this->assertSame($expectedPids, $pids);
		};

		$assert(true, [TEST_SETTING_PID]);
		$assert(false, []);
	}

	function testProject_getUsers(){
		$username = $this->getRandomUsername();
		$project = $this->getProject(TEST_SETTING_PID);

		$project->removeUser($username);
		$project->addUser($username);

		$result = $this->getFramework()->query("
			select user_email
			from redcap_user_rights r
			join redcap_user_information i
				on r.username = i.username
			where project_id = ?
			order by r.username
		", TEST_SETTING_PID);

		$actualUsers = $project->getUsers();

		$i = 0;
		while($row = $result->fetch_assoc()){
			$this->assertSame($row['user_email'], $actualUsers[$i]->getEmail());
			$i++;
		}
	}

	function testProject_getProjectId(){
		$this->assertSame((int)TEST_SETTING_PID, $this->getProject(TEST_SETTING_PID)->getProjectId());
	}

	private function assertAddOrUpdateInstances($instanceData, $expected, $keyFields, $message = null){
		$_GET['pid'] = TEST_SETTING_PID;
		
		// Run the assertion twice, to make sure subsequent calls with the same data have no effect.
		for($i=0; $i<2; $i++){
			$addOrUpdateResult = $this->addOrUpdateInstances($instanceData, $keyFields);
			$this->assertTrue(isset($addOrUpdateResult['item_count']), 'Make sure the underlying saveData() result is returned');

			$fields = [$this->getRecordIdField(), TEST_REPEATING_FIELD_1, TEST_REPEATING_FIELD_2, TEST_REPEATING_FIELD_3];
			$results = json_decode(\REDCap::getData($this->getFramework()->getProjectId(), 'json', null, $fields), true);

			$actual = [];
			foreach($results as $result){
				if($result['redcap_repeat_instance'] === ''){
					continue;
				}

				$actual[] = $result;
			}
			
			$this->assertSame($expected, $actual, $message);
		}
	}

	function testProject_addOrUpdateInstances(){
		$nextRecordId = rand();
		$uniqueFieldValue = rand();
		$expected = [];
		
		$createInstanceData = function($recordId, $instanceNumber) use(&$uniqueFieldValue, &$expected){
			$instanceExpected = [
				$this->getRecordIdField(TEST_SETTING_PID) => (string) $recordId,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => $instanceNumber,
				TEST_REPEATING_FIELD_1 => (string) ($uniqueFieldValue++),
				TEST_REPEATING_FIELD_2 => (string) rand(),
				TEST_REPEATING_FIELD_3 => ''
			];

			$expected[] = $instanceExpected;
			$instanceData = $instanceExpected;
			
			// Unset these so that the test verifies that they gets added appropriately.
			unset($instanceData['redcap_repeat_instrument']);
			unset($instanceData['redcap_repeat_instance']);

			return $instanceData;
		};

		$assert = function($instanceData, $message) use (&$expected){	
			$this->assertAddOrUpdateInstances($instanceData, $expected, TEST_REPEATING_FIELD_1, $message);
		};

		$recordId1 = $nextRecordId++;
		$instanceData1 = $createInstanceData($recordId1, 1);
		$assert([$instanceData1], 'Add one instance');

		$this->assertThrowsException(function() use ($assert, $instanceData1){
			$assert([$instanceData1, $instanceData1], 'An exception should be thrown before this assertion message is ever reached');
		}, ExternalModules::tt('em_errors_138'));

		$instanceData2 = $createInstanceData($recordId1, 2);
		$instanceData3 = $createInstanceData($recordId1, 3);
		$instanceData3['redcap_repeat_instrument'] = TEST_REPEATING_FORM; // Also ensure that manually specifying the form makes no difference
		$assert([$instanceData2, $instanceData3], 'Add two more instances for the same record');
		
		$updatedValue1 = (string) rand();
		$instanceData1[TEST_REPEATING_FIELD_2] = $updatedValue1;
		$expected[count($expected)-3][TEST_REPEATING_FIELD_2] = $updatedValue1;
		$updatedValue2 = (string) rand();
		$instanceData2[TEST_REPEATING_FIELD_2] = $updatedValue2;
		$expected[count($expected)-2][TEST_REPEATING_FIELD_2] = $updatedValue2;
		$assert([$instanceData1, $instanceData2], 'Updating a couple of instances');

		$instanceData4 = $createInstanceData($recordId1, 4);
		$recordId2 = $nextRecordId++;
		$record2InstanceData1 = $createInstanceData($recordId2, 1);
		$assert([$instanceData4, $record2InstanceData1], 'Adding instances for multiple records');

		$record2UpdatedValue = (string) rand();
		$record2InstanceData1[TEST_REPEATING_FIELD_2] = $record2UpdatedValue;
		$expected[count($expected)-1][TEST_REPEATING_FIELD_2] = $record2UpdatedValue;
		$assert([$record2InstanceData1], 'Updating an instance for another record');

		$duplicateInstance = $expected[count($expected)-1];
		$duplicateInstance['redcap_repeat_instance']++;
		REDCap::saveData($this->getFramework()->getProjectId(), 'json', json_encode([$duplicateInstance]));
		$this->assertThrowsException(function() use($assert, $duplicateInstance){
			$assert([$duplicateInstance], 'An exception should be thrown before this assertion message is ever reached');
		}, ExternalModules::tt('em_errors_135', TEST_REPEATING_FORM));
	}

	function testProject_addOrUpdateInstances_multipleKeys(){
		$firstInstance = [
			TEST_RECORD_ID => (string) rand(),
			'redcap_repeat_instrument' => TEST_REPEATING_FORM,
			'redcap_repeat_instance' => 1,
			TEST_REPEATING_FIELD_1 => (string) rand(),
			TEST_REPEATING_FIELD_2 => (string) rand(),
			TEST_REPEATING_FIELD_3 => (string) rand(),
		];

		$expectedResult = [
			$firstInstance
		];

		$assert = function($instances, $message) use (&$expectedResult){
			$this->assertAddOrUpdateInstances($instances, $expectedResult, [
				TEST_REPEATING_FIELD_1,
				TEST_REPEATING_FIELD_2
			], $message);
		};

		$assert($expectedResult, 'initial save');

		$firstInstance[TEST_REPEATING_FIELD_3] = (string) rand();
		$expectedResult[0] = $firstInstance;
		$assert([$firstInstance], 'update non-key value on existing instance');

		$secondInstance = $firstInstance;
		$secondInstance['redcap_repeat_instance'] = 2;
		$secondInstance[TEST_REPEATING_FIELD_1] = (string) rand();
		$secondInstance[TEST_REPEATING_FIELD_3] = (string) rand();
		$expectedResult[] = $secondInstance;
		$assert([$secondInstance], 'updating the first of two keys causes a new instance');

		$thirdInstance = $secondInstance;
		$thirdInstance['redcap_repeat_instance'] = 3;
		$thirdInstance[TEST_REPEATING_FIELD_2] = (string) rand();
		$thirdInstance[TEST_REPEATING_FIELD_3] = (string) rand();
		$expectedResult[] = $thirdInstance;
		$assert([$thirdInstance], 'updating the second of two keys causes a new instance');

		$record2Instance1 = $firstInstance;
		$record2Instance1[TEST_RECORD_ID] = (string) ($record2Instance1[TEST_RECORD_ID] + 1); // Add one so it appears next in the result list.
		$record2Instance1[TEST_REPEATING_FIELD_3] = (string) rand();
		$expectedResult[] = $record2Instance1;
		$assert([$firstInstance, $record2Instance1], 'using the same key fields on a different records results in separate instances for each record');
	}

	function testProject_addOrUpdateInstances_falsyValues(){
		$recordId = (string) rand();

		$expected = [
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => 1,
				TEST_REPEATING_FIELD_1 => '0',
				TEST_REPEATING_FIELD_2 => (string) rand(),
				TEST_REPEATING_FIELD_3 => (string) rand(),
			],
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => 2,
				TEST_REPEATING_FIELD_1 => '',
				TEST_REPEATING_FIELD_2 => (string) rand(),
				TEST_REPEATING_FIELD_3 => (string) rand(),
			]
		];

		$this->assertAddOrUpdateInstances($expected, $expected, [TEST_REPEATING_FIELD_1], "Make sure zero and empty string are considered separate values");
	}

	function testProject_addOrUpdateInstances_numericTypeComparison(){
		$instance = [
			TEST_RECORD_ID => rand(),
			'redcap_repeat_instrument' => TEST_REPEATING_FORM,
			'redcap_repeat_instance' => 1,
			TEST_REPEATING_FIELD_1 => 0,
			TEST_REPEATING_FIELD_2 => '',
			TEST_REPEATING_FIELD_3 => '',
		];

		$expected = $instance;
		$expected[TEST_RECORD_ID] = (string) $expected[TEST_RECORD_ID];
		$expected[TEST_REPEATING_FIELD_1] = (string) $expected[TEST_REPEATING_FIELD_1];
		
		unset($instance['redcap_repeat_instance']);

		$this->assertAddOrUpdateInstances(
			[$instance],
			[$expected], 
			[TEST_REPEATING_FIELD_1], 
			'Ensure that passing in integers instead of strings does not result in duplicate instances (relies on the duplicate call loop in assertAddOrUpdateInstances())'
		);

		$this->assertThrowsException(function(){
			$this->addOrUpdateInstances(
				[
					[
						TEST_RECORD_ID => TEST_RECORD_ID,
						'redcap_repeat_instrument' => TEST_REPEATING_FORM,
						TEST_REPEATING_FIELD_1 => '0',
						TEST_REPEATING_FIELD_2 => '',
						TEST_REPEATING_FIELD_3 => '',
					],
					[
						TEST_RECORD_ID => TEST_RECORD_ID,
						'redcap_repeat_instrument' => TEST_REPEATING_FORM,
						TEST_REPEATING_FIELD_1 => 0,
						TEST_REPEATING_FIELD_2 => '',
						TEST_REPEATING_FIELD_3 => '',
					],
				],
				[TEST_REPEATING_FIELD_1]
			);
		}, ExternalModules::tt('em_errors_138'), 'Make sure duplicate keys that vary in type are caught when passed in at the same time');
	}

	function testProject_addOrUpdateInstances_exceptions(){
		$_GET['pid'] = TEST_SETTING_PID;
		$recordIdFieldName = $this->getRecordIdField();

		$assertException = function($instances, $message){
			$this->assertThrowsException(function() use ($instances){
				$this->addOrUpdateInstances($instances, TEST_REPEATING_FIELD_1);
			}, $message);
		};

		$assertException([
			[
				TEST_REPEATING_FIELD_1 => 1
			],
		], ExternalModules::tt('em_errors_134', TEST_RECORD_ID));

		$assertException([
			[
				$recordIdFieldName => 1
			],
		], ExternalModules::tt('em_errors_134', TEST_REPEATING_FIELD_1));

		$assertException([
			[
				'redcap_repeat_instrument' => 'one',
			]
		], ExternalModules::tt('em_errors_137', TEST_REPEATING_FORM, 'one'));

		$fakeFieldName = 'some_nonexistent_field';
		$results = $this->addOrUpdateInstances([
			[
				$recordIdFieldName => 'one',
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				TEST_REPEATING_FIELD_1 => 1,
				$fakeFieldName => 1
			],
		], TEST_REPEATING_FIELD_1);
		$this->assertStringContainsString("not found in the project as real data fields: $fakeFieldName", $results['errors']);

		$assertException([1,2,3], ExternalModules::tt('em_errors_136'));

		$this->assertThrowsException(function(){
			$this->addOrUpdateInstances([[]], []);
		}, ExternalModules::tt('em_errors_132'));

		$this->assertThrowsException(function(){
			$this->addOrUpdateInstances([[]], [TEST_REPEATING_FIELD_1, TEST_TEXT_FIELD]);
		}, ExternalModules::tt('em_errors_133'));

		$this->assertThrowsException(function() use ($fakeFieldName){
			$this->addOrUpdateInstances([[]], [$fakeFieldName]);
		}, ExternalModules::tt('em_errors_139', $fakeFieldName));

		$setValidation = function($project, $field, $validation){
			$this->query("
				update redcap_metadata
				set element_validation_type = ?
				where project_id = ?
				and field_name = ?
			", [$validation, $project, $field]);
		};

		$setValidation(TEST_SETTING_PID, TEST_REPEATING_FIELD_1, 'float');
		$result = $this->addOrUpdateInstances([[
			$recordIdFieldName => 'one',
			'redcap_repeat_instrument' => TEST_REPEATING_FORM,
			TEST_REPEATING_FIELD_1 => 'some non-numeric value',
		]], [TEST_REPEATING_FIELD_1]);
		
		$this->assertSame(80, strpos($result['errors'][0], 'could not be validated'));
		$setValidation(TEST_SETTING_PID, TEST_REPEATING_FIELD_1, null);
	}

	function testProject_addUser(){
		$username = $this->getRandomUsername();
		$project = $this->getProject(TEST_SETTING_PID);

		$project->removeUser($username);
		$project->addUser($username);
		$this->assertSame('0', $project->getRights($username)['design']);

		$project->removeUser($username);		
		$project->addUser($username, ['design' => 1]);
		$this->assertSame('1', $project->getRights($username)['design']);

		$project->removeUser($username);
	}

	function testProject_removeUser(){
		$username = $this->getRandomUsername();
		$project = $this->getProject(TEST_SETTING_PID);

		$project->addUser($username);
		$project->removeUser($username);
		$this->assertNull($project->getRights($username));
	}

	function testProject_getRights(){
		$username = $this->getRandomUsername();
		$project = $this->getProject(TEST_SETTING_PID);

		$project->removeUser($username);

		$value = (string) rand(0, 1);
		$project->addUser($username, ['design' => $value]);

		$this->assertSame($value, $project->getRights($username)['design']);

		$project->removeUser($username);
	}

	function testProject_setRights(){
		$username = $this->getRandomUsername();
		$project = $this->getProject(TEST_SETTING_PID);

		$project->removeUser($username);
		$project->addUser($username);
		$this->assertSame('0', $project->getRights($username)['design']);

		$project->setRights($username, ['design' => 1]);
		$this->assertSame('1', $project->getRights($username)['design']);

		$project->removeUser($username);		
	}

	function testRecords_lock(){
		$_GET['pid'] = TEST_SETTING_PID;
		$recordIds = [1, 2];
		$records = $this->getFramework()->records;
		
		foreach($recordIds as $recordId){
			$this->ensureRecordExists($recordId);
		}

		$records->lock($recordIds);
		foreach($recordIds as $recordId){
			$this->assertTrue($records->isLocked($recordId));
		}

		$records->unlock($recordIds);
		foreach($recordIds as $recordId){
			$this->assertFalse($records->isLocked($recordId));
		}
	}

	function testUser_isSuperUser(){
		$result = ExternalModules::query('select username from redcap_user_information where super_user = 1 limit 1', []);
		$row = $result->fetch_assoc();
		$username = $row['username'];
		
		$user = $this->getUser($username);
		$this->assertTrue($user->isSuperUser());
	}

	function testUser_getRights(){
		$result = ExternalModules::query("
			select * from redcap_user_rights
			where username != ''
			order by rand() limit 1
		", []);

		$row = $result->fetch_assoc();
		$projectId = $row['project_id'];
		$username = $row['username'];
		$expectedRights = \UserRights::getPrivileges($projectId, $username)[$projectId][$username];

		$user = $this->getUser($username);
		
		$actualRights = $user->getRights($projectId, $username);
		$this->assertSame($expectedRights, $actualRights);

		$_GET['pid'] = $projectId;
		$actualRights = $user->getRights(null, $username);
		$this->assertSame($expectedRights, $actualRights);
	}
	
	function testGetEventId(){
		$this->assertThrowsException(function(){
			$this->getEventId();
		}, ExternalModules::tt('em_errors_65', 'pid'));

		$_GET['pid'] = (string) TEST_SETTING_PID;
		$project1EventId = $this->getEventId();
		$this->assertIsInt($project1EventId);

		$urlEventId = rand();
		$_GET['event_id'] = $urlEventId;
		$this->assertEquals($urlEventId,  $this->getEventId());

		$project2EventId =  $this->getEventId(TEST_SETTING_PID_2);
		$this->assertIsInt($project2EventId);
		$this->assertNotSame($project1EventId, $project2EventId);
		$this->assertNotSame($urlEventId, $project2EventId);
	}

    function testGetSafePath(){
        $test = function($path, $root=null){
            // Get the actual value before manipulating the root for testing.
            $actual = call_user_func_array([$this, 'getSafePath'], func_get_args());

			$moduleDirectory = ExternalModules::getModuleDirectoryPath(TEST_MODULE_PREFIX);
            if(!$root){
                $root = $moduleDirectory;
            }
            else if(!file_exists($root)){
                $root = "$moduleDirectory/$root";
            }

            $root = realpath($root);
            $expected = $root . DS . $path;
            if(file_exists($expected)){
                $expected = realpath($expected);
            }

            $this->assertEquals($expected, $actual);
        };

        $test(basename(__FILE__));
        $test('.');
        $test('non-existant-file.php');
        $test('test-subdirectory');
        $test('test-file.php', 'test-subdirectory'); // relative path
        $test('test-file.php', ExternalModules::getTestModuleDirectoryPath() . '/test-subdirectory'); // absolute path

        $expectedExceptions = [
            'outside of your allowed parent directory' => [
                '../index.php',
                '..',
                '../non-existant-file',
                '../../../passwd'
            ],
            'only works on directories that exist' => [
                'non-existant-directory/non-existant-file.php',
                'non-existant-directory/../../../passwd'
            ],
            'does not exist as either an absolute path or a relative path' => [
                ['foo', 'non-existent-root']
            ]
        ];

        foreach($expectedExceptions as $excerpt=>$calls){
            foreach($calls as $args){
                if(!is_array($args)){
                    $args = [$args];
                }    

                $this->assertThrowsException(function() use ($test, $args){
                    call_user_func_array($test, $args);
                }, $excerpt);
            }
        }
    }

    function testConvertIntsToStrings(){
        $assert = function($expected, $data){
            $actual = $this->convertIntsToStrings($data);
            $this->assertSame($expected, $actual);
        };

        $assert(['1', 'b', null], [1, 'b', null]);
        $assert(['a' => '1', 'b'=>'b', 'c' => null], ['a' => 1, 'b'=>'b', 'c' => null]);
    }

    function testIsPage(){
        $originalRequestURI = $_SERVER['REQUEST_URI'];
        
        $path = 'foo/goo.php';

        $this->assertFalse($this->isPage($path));
        
        $_SERVER['REQUEST_URI'] = APP_PATH_WEBROOT . $path;
        $this->assertTrue($this->isPage($path));

        $_SERVER['REQUEST_URI'] = $originalRequestURI;
    }
	
	function testGetLinkIconHtml(){
		$iconName = 'fas fa-whatever';
		$link = ['icon' => $iconName];
		$html = ExternalModules::getLinkIconHtml($this->getInstance(), $link);

		if($this->getFrameworkVersion() < 3){
			$expected = "<img src='" . APP_PATH_IMAGES . "$iconName.png'";
		}
		else{
			$expected = "<i class='$iconName'";
		}

		$this->assertTrue(strpos($html, $expected) > 0, "Could not find '$expected' in '$html'");
	}
	
	function testGetSQLInClause(){
		// This method is tested more thoroughly in ExternalModulesTest.

		$getSQLInClause = function(){
			$clause = $this->getSQLInClause('a', [1]);
			$this->assertSame("(a IN ('1'))", $clause);
		};

		if($this->getFrameworkVersion() < 4){
			$getSQLInClause();
		}
		else{
			$this->assertThrowsException(function() use ($getSQLInClause){
				$getSQLInClause();
			}, ExternalModules::tt('em_errors_122'));
		}
	}

	function testCountLogs(){
		$whereClause = "message = ?";
		$message = rand();

		$assert = function($expected) use ($whereClause, $message){
			$actual = $this->countLogs($whereClause, $message);
			$this->assertSame($expected, $actual);
		};
		
		$assert(0);

		$this->log($message);
		$assert(1);

		$this->log($message);
		$assert(2);

		$this->getInstance()->removeLogs($whereClause, $message);
		$assert(0);
	}

	function testIsSafeToForwardMethodToFramework(){
		// The 'tt' methods are grandfathered in.
		$this->assertTrue($this->isSafeToForwardMethodToFramework('tt'));

		// This assertion specifically checks the method_exists() call in isSafeToForwardMethodToFramework()
		// to ensure infinite loops cannot occur.
		$this->assertThrowsException(function(){
			$this->getInstance()->someNonExistentMethod();
		}, 'method does not exist');
		
		$passThroughAllowed = $this->getFrameworkVersion() >= 5;
		$this->assertSame($passThroughAllowed, $this->isSafeToForwardMethodToFramework('getRecordIdField'));

		$methodName = 'getRecordIdField';
		$passThroughCall = function() use ($methodName){
			$this->getInstance()->{$methodName}(TEST_SETTING_PID);
		};
		
		if($passThroughAllowed){
			// Make sure no exception is thrown.
			$passThroughCall();
		}
		else{
			$this->assertThrowsException(function() use ($passThroughCall){
				$passThroughCall();
			}, ExternalModules::tt("em_errors_69", $methodName));
		}
	}

	function testGetRecordIdField(){
		$metadata = ExternalModules::getMetadata(TEST_SETTING_PID);
		$expected = array_keys($metadata)[0];
		
		$this->assertThrowsException(function(){
			$this->getRecordIdField();
		}, ExternalModules::tt('em_errors_65', 'pid'));

		$this->assertSame($expected, $this->getRecordIdField(TEST_SETTING_PID));

		$_GET['pid'] = TEST_SETTING_PID;
		$this->assertSame($expected, $this->getRecordIdField());
	}

	function testGetProjectSettings(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$_GET['pid'] = TEST_SETTING_PID;

		$value = rand();
		$this->setProjectSetting($value);
		$array = $m->getProjectSettings();

		$actual = $array[TEST_SETTING_KEY];

		if($this->getFrameworkVersion() < 5){
			$this->assertSame(null, @$actual['system_value']);
			$actual = $actual['value'];
		}

		$this->assertSame($value, $actual);
	}

	function testSetProjectSettings(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$_GET['pid'] = TEST_SETTING_PID;

		$value = rand();
		$m->setProjectSettings([
			TEST_SETTING_KEY => $value
		]);

		if($this->getFrameworkVersion() >= 5){
			$expected = $value;
		}
		else{
			$expected = null;
		}

		$this->assertSame($expected, $m->getProjectSetting(TEST_SETTING_KEY));
	}

	function testObjectReferencePassThrough(){
		$name = 'records';
		$expected = $this->getFramework()->{$name};
		$this->assertNotNull($expected);
		$this->assertSame($expected, $this->getInstance()->{$name});
	}

	function testGetProjectStatus(){
		$this->assertThrowsException(function(){
			$this->getProjectStatus(-1);
		}, ExternalModules::tt("em_errors_131"));

		// Test behavior for a PID that doesn't exist.
		$this->assertSame(null, $this->getProjectStatus(PHP_INT_MAX));

		$assert = function($expected, $status, $completedTime = null){
			// Clear the Project cache in REDCap core.
			$this->setPrivateVariable('project_cache', null, 'Project');
			
			$this->query('update redcap_projects set status = ?, completed_time = ? where project_id = ?', [$status, $completedTime, TEST_SETTING_PID]);
			$this->assertSame($expected, $this->getProjectStatus(TEST_SETTING_PID));
		};

		$assert(null, 3); // some status that isn't checked in this method
		$assert('DONE', 2, ExternalModules::makeTimestamp());
		$assert('AC', 2);
		$assert('PROD', 1);
		$assert('DEV', 0);
	}

	function testIsPHPGreaterThan()
	{
		$isPHPGreaterThan = function($requiredVersion){
			return $this->callPrivateMethodForClass($this->getFramework(), 'isPHPGreaterThan', $requiredVersion);
		};

		$versionParts = explode('.', PHP_VERSION);
		$lastNumber = $versionParts[2];

		$versionParts[2] = $lastNumber-1;
		$lowerVersion = implode('.', $versionParts);

		$versionParts[2] = $lastNumber+1;
		$higherVersion = implode('.', $versionParts);

		$this->assertTrue($isPHPGreaterThan(PHP_VERSION));
		$this->assertFalse($isPHPGreaterThan($higherVersion));
		$this->assertTrue($isPHPGreaterThan($lowerVersion));
	}	

	function testQueryLogs_parameters()
	{
		$m = $this->getInstance();
		$value = rand();
		$m->log('test', [
			'value' => $value
		]);

		$result = $m->queryLogs("select count(*) as count where value = ?", $value);
		$row = $result->fetch_assoc();

		$this->assertSame(1, $row['count']);
	}

	function testQueryLogs_parametersArgumentRequirement()
	{
		// On older framework versions, parameters are not required.
		$this->queryLogs("select 1");
		$this->expectNotToPerformAssertions();
	}

	function testQueryLogs_complexStatements()
	{
		$m = $this->getInstance();

		// Just make sure this query is parsable, and runs without an exception.
		$m->queryLogs("select 1 where a = 1 and (b = 2 or c = 3)", []);

		$this->assertTrue(true); // Each test requires an assertion
	}

	function testQueryLogs_complexSelectClauses()
	{
		$m = $this->getInstance();

		$logId = $m->log('test');
		$whereClause = 'log_id = ?';

		// Make sure a function and an "as" clause work.
		$result = $m->queryLogs("select unix_timestamp(timestamp) as abc where $whereClause", $logId);
		
		$row = $result->fetch_assoc();
		$aDayAgo = time() - ExternalModules::DAY_IN_SECONDS;
		$this->assertTrue($row['abc'] > $aDayAgo);

		$m->removeLogs($whereClause, $logId);
	}

	function testQueryLogs_multipleReferencesToSameColumn()
	{
		$m = $this->getInstance();

		// Just make sure this query is parsable, and runs without an exception.
		$m->queryLogs("select 1 where a > 1 and a < 5", []);

		$this->assertTrue(true); // Each test requires an assertion
	}

	function testQueryLogs_groupBy()
	{
		$paramName = 'some_param';
		for($i=0; $i<2; $i++){
			$this->log('some_message', [
				$paramName => 'some_value'
			]);
		}

		$assert = function($sql, $expectedCount){
			$result = $this->queryLogs($sql, []);
			$this->assertSame($expectedCount, $result->num_rows);
		};

		$sql = 'select log_id, message';
		$assert($sql, 2);
		$assert($sql . " group by $paramName", 1);
	}

	function testQueryLogs_orderBy()
	{
		$expected = [];
		$paramName = 'some_param';
		for($i=0; $i<3; $i++){
			$logId = $this->log('some message', [
				$paramName => $i
			]);

			$expected[] = [
				'log_id' => (string) $logId,
				$paramName => (string) $i
			];
		}

		$assert = function($order, $expected) use ($paramName){
			foreach(['log_id', $paramName] as $orderColumn){
				$result = $this->queryLogs("select log_id, $paramName order by $orderColumn $order", []);
	
				$actual = [];
				while($row = $result->fetch_assoc()){
					$actual[] = $row;
				}
	
				$this->assertSame($expected, $actual);
			}
		};

		$assert('asc', $expected);
		$assert('desc', array_reverse($expected));
	}

	function testQueryLogs_stars()
	{
		$m = $this->getInstance();

		// "select count(*)" should be allowed
		$result = $m->queryLogs("select count(*) as count where some_fake_parameter = 1", []);
		$row = $result->fetch_assoc();
		$this->assertSame('0', $row['count']);

		// "select *" should not be allowed
		$this->assertThrowsException(function() use ($m){
			$m->queryLogs('select * where some_fake_parameter = 1');
		}, "Columns must be explicitly defined in all log queries");
	}

	function testRemoveLogs()
	{
		$m = $this->getInstance();
		$message = rand();
		$logId1 = $m->log($message);
		$logId2 = $m->log($message);

		$m->removeLogs("log_id = ?", $logId1);

		$result = $m->queryLogs('select log_id where message = ?', $message);
		$this->assertSame($logId2, $result->fetch_assoc()['log_id']);
		
		// Make sure only one row exists
		$this->assertNull($result->fetch_assoc());

		$this->assertThrowsException(function() use ($m){
			$m->removeLogs('');
		}, 'must specify a where clause');

		$this->assertThrowsException(function() use ($m){
			$m->removeLogs('external_module_id = 1');
		}, 'not allowed to prevent modules from accidentally removing logs for other modules');
	}

	function testRemoveLogs_parametersArgumentRequirement()
	{
		// On older framework versions, parameters are not required.
		$this->removeLogs("1 = 2");
		$this->expectNotToPerformAssertions();
	}

	function testTt(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$key = 'some_key';
		$value = rand();
		$this->spoofTranslation(TEST_MODULE_PREFIX, $key, $value);

		$key = 'some_key';
		$this->assertSame($value, $m->tt($key));
	}

	function testTt_transferToJavascriptModuleObject(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$key = 'some_key';
		$value = rand();
		$this->spoofTranslation(TEST_MODULE_PREFIX, $key, $value);
		
		ob_start();
		$m->tt_transferToJavascriptModuleObject($key, $value);
		$actual = ob_get_clean();

		$this->assertJSLanguageKeyAdded($key, $value, $actual);
	}

	function testTt_addToJavascriptModuleObject(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$key = 'some_key';
		$value = rand();

		ob_start();
		$m->tt_addToJavascriptModuleObject($key, $value);
		$actual = ob_get_clean();
		
		$this->assertJSLanguageKeyAdded($key, $value, $actual);
	}

	function assertJSLanguageKeyAdded($key, $value, $actual){
		$this->assertSame("<script>ExternalModules.\$lang.add(\"emlang_" . TEST_MODULE_PREFIX . "_$key\", $value)</script>", $actual);
	}
}