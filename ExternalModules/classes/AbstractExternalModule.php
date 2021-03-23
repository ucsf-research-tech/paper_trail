<?php
namespace ExternalModules;

AbstractExternalModule::init();

if (class_exists('ExternalModules\AbstractExternalModule')) {
	return;
}

use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;

use Exception;
use UIState;

class AbstractExternalModule
{
	const UI_STATE_OBJECT_PREFIX = 'external-modules.';
	const EXTERNAL_MODULE_ID_STANDARD_WHERE_CLAUSE_PREFIX = "redcap_external_modules_log.external_module_id = (SELECT external_module_id FROM redcap_external_modules WHERE directory_prefix";

	// check references to this to make sure moving vars was safe
	// rename the following var?
	public static $RESERVED_LOG_PARAMETER_NAMES = ['log_id', 'external_module_id', 'ui_id'];
	private static $RESERVED_LOG_PARAMETER_NAMES_FLIPPED;
	public static $OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE = ['timestamp', 'username', 'ip', 'project_id', 'record', 'message'];
	private static $LOG_PARAMETERS_ON_MAIN_TABLE;

	public $PREFIX;
	public $VERSION;

	private $recordId;
	private $userBasedSettingPermissions = true;

	# constructor
	function __construct()
	{
		list($prefix, $version) = ExternalModules::getParseModuleDirectoryPrefixAndVersion($this->getModuleDirectoryName());

		$this->PREFIX = $prefix;
		$this->VERSION = $version;

		// Disallow illegal configuration options at module instantiation (and enable) time.
		self::checkSettings();

		// The framework instance must be cached in this constructor so that it is available
		// for any calls to framework provided methods in the constructor of any module subclasses.
		// We used to cache framework instances in ExternalModules::getFrameworkInstance() after the module instance was created,
		// but the above scenario caused infinite loops: https://github.com/vanderbilt/redcap-external-modules/issues/329
		ExternalModules::cacheFrameworkInstance($this);
	}

	# checks the config.json settings for validity of syntax
	protected function checkSettings()
	{
		$config = $this->getConfig();
		$systemSettings = $config['system-settings'];
		$projectSettings = $config['project-settings'];

		$settingKeys = [];
		$checkSettings = function($settings) use (&$settingKeys, &$checkSettings){
			if($settings === null){
				return;
			}

			foreach($settings as $details) {
				$key = $details['key'];
				self::checkSettingKey($key);

				if (isset($settingKeys[$key])) {
					//= The '{0}' module defines the '{1}' setting multiple times!
					throw new Exception(ExternalModules::tt("em_errors_61", $this->PREFIX, $key)); 
				} else {
					$settingKeys[$key] = true;
				}

				if($details['type'] === 'sub_settings'){
					$checkSettings($details['sub_settings']);
				}
			}
		};

		$checkSettings($systemSettings);
		$checkSettings($projectSettings);
	}

	# checks a config.json setting key $key for validity
	# throws an exception if invalid
	private function checkSettingKey($key)
	{
		if(!self::isSettingKeyValid($key)){
			//= The '{0}' module has a setting named '{1}' that contains invalid characters. Only lowercase characters, numbers, and dashes are allowed.
			throw new Exception(ExternalModules::tt("em_errors_62", $this->PREFIX, $key)); 
		}
	}

	# validity check for a setting key $key
	# returns boolean
	protected function isSettingKeyValid($key)
	{
		// Only allow lowercase characters, numbers, dashes, and underscores to ensure consistency between modules (and so we don't have to worry about escaping).
		return !preg_match("/[^a-z0-9-_]/", $key);
	}

	# check whether the current External Module has permission to call the requested method $methodName
	private function checkPermissions($methodName)
	{
		# Convert from camel to snake case.
		# Taken from the second solution here: http://stackoverflow.com/questions/1993721/how-to-convert-camelcase-to-camel-case
		$permissionName = ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $methodName)), '_');

		if (!$this->hasPermission($permissionName)) {
			//= This module must request the '{0}' permission in order to call the '{1}' method.
			throw new Exception(ExternalModules::tt("em_errors_64", $permissionName, $methodName())); 
		}
	}

	# checks whether the current External Module has permission for $permissionName
	function hasPermission($permissionName)
	{
		return ExternalModules::hasPermission($this->PREFIX, $this->VERSION, $permissionName);
	}

	# get the config for the current External Module
	# consists of config.json and filled-in values
	function getConfig()
	{
		return ExternalModules::getConfig($this->PREFIX, $this->VERSION, null, true);
	}

	# get the directory name of the current external module
	function getModuleDirectoryName()
	{
		$reflector = new \ReflectionClass(get_class($this));
		return basename(dirname($reflector->getFileName()));
	}

	protected function getSettingKeyPrefix(){
		return '';
	}

	public function prefixSettingKey($key){
		return $this->getSettingKeyPrefix() . $key;
	}

	# a SYSTEM setting is a value to be used on all projects. It can be overridden by a particular project
	# a PROJECT setting is a value set by each project. It may be a value that overrides a system setting
	#      or it may be a value set for that project alone with no suggested System-level value.
	#      the project_id corresponds to the value in REDCap
	#      if a project_id (pid) is null, then it becomes a system value

	# Set the setting specified by the key to the specified value
	# systemwide (shared by all projects).
	function setSystemSetting($key, $value)
	{
		$key = $this->prefixSettingKey($key);
		ExternalModules::setSystemSetting($this->PREFIX, $key, $value);
	}

	# Get the value stored systemwide for the specified key.
	function getSystemSetting($key)
	{
		$key = $this->prefixSettingKey($key);
		return ExternalModules::getSystemSetting($this->PREFIX, $key);
	}

	/**
	 * Gets all system settings as an array. Does not include project settings. Each setting
	 * is formatted as: [ 'yourkey' => ['system_value' => 'foo', 'value' => 'bar'] ]
	 *
	 * @return array
	 */
	function getSystemSettings()
	{
	    return ExternalModules::getSystemSettingsAsArray($this->PREFIX);
	}

	# Remove the value stored systemwide for the specified key.
	function removeSystemSetting($key)
	{
		$key = $this->prefixSettingKey($key);
		ExternalModules::removeSystemSetting($this->PREFIX, $key);
	}

	# Set the setting specified by the key to the specified value for
	# this project (override the system setting).  In most cases
	# the project id can be detected automatically, but it can
	# optionaly be specified as the third parameter instead.
	function setProjectSetting($key, $value, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		$key = $this->prefixSettingKey($key);
		ExternalModules::setProjectSetting($this->PREFIX, $pid, $key, $value);
	}

	# Returns the value stored for the specified key for the current
	# project if it exists.  If this setting key is not set (overriden)
	# for the current project, the system value for this key is
	# returned.  In most cases the project id can be detected
	# automatically, but it can optionally be specified as the third
	# parameter instead.
	function getProjectSetting($key, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		$key = $this->prefixSettingKey($key);
		return ExternalModules::getProjectSetting($this->PREFIX, $pid, $key);
	}

	# Remove the value stored for this project and the specified key.
	# In most cases the project id can be detected automatically, but
	# it can optionally be specified as the third parameter instead.
	function removeProjectSetting($key, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		$key = $this->prefixSettingKey($key);
		ExternalModules::removeProjectSetting($this->PREFIX, $pid, $key);
	}

	function getSettingConfig($key)
	{
		$config = $this->getConfig();
		foreach(['project-settings', 'system-settings'] as $type) {
			foreach ($config[$type] as $setting) {
				if ($key == $setting['key']) {
					return $setting;
				}
			}
		}

		return null;
	}

	function getUrl($path, $noAuth = false, $useApiEndpoint = false)
	{
		$pid = self::detectProjectId();
		return ExternalModules::getUrl($this->PREFIX, $path, $pid, $noAuth, $useApiEndpoint);
	}

	public function getModulePath()
	{
		return ExternalModules::getModuleDirectoryPath($this->PREFIX, $this->VERSION) . DS;
	}

	public function getModuleName()
	{
		return $this->getConfig()['name'];
	}

	public function resetSurveyAndGetCodes($projectId,$recordId,$surveyFormName = "", $eventId = "") {
		list($surveyId,$surveyFormName) = $this->getSurveyId($projectId,$surveyFormName);

		## Validate surveyId and surveyFormName were found
		if($surveyId == "" || $surveyFormName == "") return false;

		## Find valid event ID for form if it wasn't passed in
		if($eventId == "") {
			$eventId = $this->getValidFormEventId($surveyFormName,$projectId);

			if(!$eventId) return false;
		}

		## Search for a participant and response id for the given survey and record
		list($participantId,$responseId) = $this->getParticipantAndResponseId($surveyId,$recordId,$eventId);

		## Create participant and return code if doesn't exist yet
		if($participantId == "" || $responseId == "") {
			$hash = self::generateUniqueRandomSurveyHash();

			$participantId = ExternalModules::addSurveyParticipant($surveyId, $eventId, $hash);
			
			## Insert a response row for this survey and record
			$returnCode = generateRandomHash();
			$responseId = ExternalModules::addSurveyResponse($participantId, $recordId, $returnCode);
		}
		## Reset response status if it already exists
		else {
			$sql = "SELECT CAST(p.participant_id as CHAR) as participant_id, p.hash, r.return_code, CAST(r.response_id as CHAR) as response_id, COALESCE(p.participant_email,'NULL') as participant_email
					FROM redcap_surveys_participants p, redcap_surveys_response r
					WHERE p.survey_id = ?
						AND p.participant_id = r.participant_id
						AND r.record = ?
						AND p.event_id = ?";

			$q = self::query($sql, [$surveyId, $recordId, $eventId]);
			$rows = [];
			while($row = $q->fetch_assoc()) {
				$rows[] = $row;
			}

			## If more than one exists, delete any that are responses to public survey links
			if($q->num_rows > 1) {
				foreach($rows as $thisRow) {
					if($thisRow["participant_email"] == "NULL" && $thisRow["response_id"] != "") {
						self::query("DELETE FROM redcap_surveys_response
								WHERE response_id = ?", $thisRow["response_id"]);
					}
					else {
						$row = $thisRow;
					}
				}
			}
			else {
				$row = $rows[0];
			}
			$returnCode = $row['return_code'];
			$hash = $row['hash'];
			$participantId = "";

			if($returnCode == "") {
				$returnCode = generateRandomHash();
			}

			## If this is only as a public survey link, generate new participant row
			if($row["participant_email"] == "NULL") {
				$hash = self::generateUniqueRandomSurveyHash();
				$participantId = ExternalModules::addSurveyParticipant($surveyId, $eventId, $hash);
			}

			// Set the response as incomplete in the response table, update participantId if on public survey link
			$q = ExternalModules::createQuery();
			$q->add("UPDATE redcap_surveys_participants p, redcap_surveys_response r
					SET r.completion_time = null,
						r.first_submit_time = '".date('Y-m-d H:i:s')."',
						r.return_code = ?", $returnCode);

			if($participantId != ""){
				$q->add(", r.participant_id = ?", $participantId);
			}

			$q->add("WHERE p.survey_id = ?
						AND p.event_id = ?
						AND r.participant_id = p.participant_id
						AND r.record = ?", [$surveyId, $eventId, $recordId]);
			
			$q->execute();
		}

		list($q, $r) = ExternalModules::setRecordCompleteStatus($projectId, $recordId, $eventId, $surveyFormName, 0);

		// Log the event (if value changed)
		if ($r && $q->affected_rows > 0) {
			if(function_exists("log_event")) {
				\log_event($sql,"redcap_data","UPDATE",$recordId,"{$surveyFormName}_complete = '0'","Update record");
			}
			else {
				\Logging::logEvent($sql,"redcap_data","UPDATE",$recordId,"{$surveyFormName}_complete = '0'","Update record");
			}
		}

		return array("hash" => $hash, "return_code" => $returnCode);
	}

	public function generateUniqueRandomSurveyHash() {
		## Generate a random hash and verify it's unique
		do {
			$hash = generateRandomHash(10);

			$sql = "SELECT p.hash
						FROM redcap_surveys_participants p
						WHERE p.hash = ?";

			$result = self::query($sql, $hash);
			$hashExists = ($result->num_rows > 0);
		} while($hashExists);

		return $hash;
	}

	public function getProjectAndRecordFromHashes($surveyHash, $returnCode) {
		$sql = "SELECT
					CAST(s.project_id as CHAR) as projectId,
					r.record as recordId,
					s.form_name as surveyForm,
					CAST(p.event_id as CHAR) as eventId
				FROM redcap_surveys_participants p, redcap_surveys_response r, redcap_surveys s
				WHERE p.hash = ?
					AND p.survey_id = s.survey_id
					AND p.participant_id = r.participant_id
					AND r.return_code = ?";

		$q = self::query($sql, [$surveyHash, $returnCode]);

		$row = $q->fetch_assoc();

		if($row) {
			return $row;
		}

		return false;
	}

	public function createPassthruForm($projectId,$recordId,$surveyFormName = "", $eventId = "") {
		$codeDetails = $this->resetSurveyAndGetCodes($projectId,$recordId,$surveyFormName,$eventId);

		$hash = $codeDetails["hash"];
		$returnCode = $codeDetails["return_code"];

		$surveyLink = APP_PATH_SURVEY_FULL . "?s=$hash";

		## Build invisible self-submitting HTML form to get the user to the survey
		echo "<html><body>
				<form name='passthruform' action='$surveyLink' method='post' enctype='multipart/form-data'>
				".($returnCode == "NULL" ? "" : "<input type='hidden' value='".$returnCode."' name='__code'/>")."
				<input type='hidden' value='1' name='__prefill' />
				</form>
				<script type='text/javascript'>
					document.passthruform.submit();
				</script>
				</body>
				</html>";
		return false;
	}

	public function getValidFormEventId($formName,$projectId) {
		if(!is_numeric($projectId) || $projectId == "") return false;

		$projectDetails = $this->getProjectDetails($projectId);

		if($projectDetails["repeatforms"] == 0) {
			$sql = "SELECT CAST(e.event_id as CHAR) as event_id
					FROM redcap_events_metadata e, redcap_events_arms a
					WHERE a.project_id = ?
						AND a.arm_id = e.arm_id
					ORDER BY e.event_id ASC
					LIMIT 1";

			$q = ExternalModules::query($sql, [$projectId]);

			if($row = $q->fetch_assoc()) {
				return $row['event_id'];
			}
		}
		else {
			$sql = "SELECT CAST(f.event_id as CHAR) as event_id
					FROM redcap_events_forms f, redcap_events_metadata m, redcap_events_arms a
					WHERE a.project_id = ?
						AND a.arm_id = m.arm_id
						AND m.event_id = f.event_id
						AND f.form_name = ?
					ORDER BY f.event_id ASC
					LIMIT 1";

			$q = ExternalModules::query($sql, [$projectId, $formName]);

			if($row = $q->fetch_assoc()) {
				return $row['event_id'];
			}
		}

		return false;
	}

	public function getSurveyId($projectId,$surveyFormName = "") {
		// Get survey_id, form status field, and save and return setting
		$query = ExternalModules::createQuery();
		$query->add("
			SELECT CAST(s.survey_id as CHAR) as survey_id, s.form_name, CAST(s.save_and_return as CHAR) as save_and_return
			FROM redcap_projects p, redcap_surveys s, redcap_metadata m
			WHERE p.project_id = ?
				AND p.project_id = s.project_id
				AND m.project_id = p.project_id
				AND s.form_name = m.form_name
		", [$projectId]);

		if($surveyFormName != ""){
			if(is_numeric($surveyFormName)){
				$query->add("AND s.survey_id = ?", $surveyFormName);
			}
			else{
				$query->add("AND s.form_name = ?", $surveyFormName);
			}
		}
		
		$query->add("
			ORDER BY s.survey_id ASC
			LIMIT 1
		");

		$r = $query->execute();
		$row = $r->fetch_assoc();

		$surveyId = $row['survey_id'];
		$surveyFormName = $row['form_name'];

		return [$surveyId,$surveyFormName];
	}

	public function getParticipantAndResponseId($surveyId,$recordId,$eventId = "") {
		$query = ExternalModules::createQuery();
		$query->add("
			SELECT
				CAST(p.participant_id as CHAR) as participant_id,
				CAST(r.response_id as CHAR) as response_id
			FROM redcap_surveys_participants p, redcap_surveys_response r
			WHERE p.survey_id = ?
				AND p.participant_id = r.participant_id
				AND r.record = ?
		", [$surveyId, $recordId]);

		if($eventId != ""){
			$query->add(" AND p.event_id = ?", $eventId);
		}

		$result = $query->execute();
		$row = $result->fetch_assoc();

		$participantId = $row['participant_id'];
		$responseId = $row['response_id'];

		return [$participantId,$responseId];
	}

	public function getProjectDetails($projectId) {
		$sql = "SELECT *
				FROM redcap_projects
				WHERE project_id = ?";

		$q = ExternalModules::query($sql, $projectId);

		$row = ExternalModules::convertIntsToStrings($q->fetch_assoc());
		
		return $row;
	}

	public function getMetadata($projectId,$forms = NULL) {
		return ExternalModules::getMetadata($projectId, $forms);
	}

	public function getData($projectId,$recordId,$eventId="",$format="array") {
		$data = \REDCap::getData($projectId,$format,$recordId);

		if($eventId != "") {
			return $data[$recordId][$eventId];
		}
		return $data;
	}

	public function saveData($projectId,$recordId,$eventId,$data) {
		return \REDCap::saveData($projectId,"array",[$recordId => [$eventId =>$data]]);
	}

	/**
	 * @param $projectId
	 * @param $recordId
	 * @param $eventId
	 * @param $formName
	 * @param $data array This must be in [instance => [field => value]] format
	 * @return array
	 */
	public function saveInstanceData($projectId,$recordId,$eventId,$formName,$data) {
		return \REDCap::saveData($projectId,"array",[$recordId => [$eventId => [$formName => $data]]]);
	}

	# function to enforce that a pid is required for a particular function
	public function requireProjectId($pid = null)
	{
		return ExternalModules::requireProjectId($pid);
	}

	private function requireEventId($eventId = null)
	{
		return $this->requireParameter('event_id', $eventId);
	}

	// Not currently used, but left in place because it's unit tested.
	private function requireInstanceId($instanceId = null)
	{
		return $this->requireParameter('instance', $instanceId);
	}

	private function requireParameter($parameterName, $value)
	{
		return ExternalModules::requireParameter($parameterName, $value);
	}

	private function detectParameter($parameterName, $value = null)
	{
		return ExternalModules::detectParameter($parameterName, $value);
	}

	# if $pid is empty/null, can get the pid from $_GET if it exists
	private function detectProjectId($projectId=null)
	{
		return $this->detectParameter('pid', $projectId);
	}

	private function detectEventId($eventId=null)
	{
		return $this->detectParameter('event_id', $eventId);
	}

	private function detectInstanceId($instanceId=null)
	{
		return $this->detectParameter('instance', $instanceId);
	}

	# pushes the execution of the module to the end of the queue
	# helpful to wait for data to be processed by other modules
	# execution of the module will be restarted from the beginning
	# For example:
	# 	if ($data['field'] === "") {
	#		delayModuleExecution();
	#		return;       // the module will be restarted from the beginning
	#	}
	public function delayModuleExecution() {
		return ExternalModules::delayModuleExecution($this->PREFIX, $this->VERSION);
	}

    public function sendAdminEmail($subject, $message){
        ExternalModules::sendAdminEmail($subject, $message, $this->PREFIX);
    }

    /**
     * Function that returns the label name from checkboxes, radio buttons, etc instead of the value
     * @param $params, associative array
     * @param null $value, (to support the old version)
     * @param null $pid, (to support the old version)
     * @return mixed|string, label
     */
    public function getChoiceLabel ($params, $value=null, $pid=null)
    {

        if(!is_array($params)) {
            $params = array('field_name'=>$params, 'value'=>$value, 'project_id'=>$pid);
        }

        //In case it's for a different project
        if ($params['project_id'] != "")
        {
            $pid = $params['project_id'];
        }else{
            $pid = self::detectProjectId();
        }

        $data = \REDCap::getData($pid, "array", $params['record_id']);
        $fieldName = str_replace('[', '', $params['field_name']);
        $fieldName = str_replace(']', '', $fieldName);

        $dateFormats = [
            "date_dmy" => "d-m-Y",
            "date_mdy" => "m-d-Y",
            "date_ymd" => "Y-m-d",
            "datetime_dmy" => "d-m-Y h:i",
            "datetime_mdy" => "m-d-Y h:i",
            "datetime_ymd" => "Y-m-d h:i",
            "datetime_seconds_dmy" => "d-m-Y h:i:s",
            "datetime_seconds_mdy" => "m-d-Y h:i:s",
            "datetime_seconds_ymd" => "Y-m-d  h:i:s"
        ];

        if (@array_key_exists('repeat_instances', $data[$params['record_id']])) {
            if ($data[$params['record_id']]['repeat_instances'][$params['event_id']][$params['survey_form']][$params['instance']][$fieldName] != "") {
                //Repeat instruments
                $data_event = $data[$params['record_id']]['repeat_instances'][$params['event_id']][$params['survey_form']][$params['instance']];
            } else if ($data[$params['record_id']]['repeat_instances'][$params['event_id']][''][$params['instance']][$fieldName] != "") {
                //Repeat events
                $data_event = $data[$params['record_id']]['repeat_instances'][$params['event_id']][''][$params['instance']];
            } else {
                $data_event = $data[$params['record_id']][$params['event_id']];
            }
        } else {
            $data_event = $data[$params['record_id']][$params['event_id']];
        }

        $metadata = \REDCap::getDataDictionary($pid, 'array', false, $fieldName);

        //event arm is defined
        if (empty($metadata)) {
            preg_match_all("/\[[^\]]*\]/", $fieldName, $matches);
            $event_name = str_replace('[', '', $matches[0][0]);
            $event_name = str_replace(']', '', $event_name);

            $fieldName = str_replace('[', '', $matches[0][1]);
            $fieldName = str_replace(']', '', $fieldName);
            $metadata = \REDCap::getDataDictionary($pid, 'array', false, $fieldName);
        }
        $label = "";
        if ($metadata[$fieldName]['field_type'] == 'checkbox' || $metadata[$fieldName]['field_type'] == 'dropdown' || $metadata[$fieldName]['field_type'] == 'radio') {
            $project = new \Project($pid);
            $other_event_id = $project->getEventIdUsingUniqueEventName($event_name);
            $choices = preg_split("/\s*\|\s*/", $metadata[$fieldName]['select_choices_or_calculations']);
            foreach ($choices as $choice) {
                $option_value = preg_split("/,/", $choice)[0];
                if ($params['value'] != "") {
                    if (is_array($data_event[$fieldName])) {
                        foreach ($data_event[$fieldName] as $choiceValue => $multipleChoice) {
                            if ($multipleChoice === "1" && $choiceValue == $option_value) {
                                $label .= trim(preg_split("/^(.+?),/", $choice)[1]) . ", ";
                            }
                        }
                    } else if ($params['value'] === $option_value) {
                        $label = trim(preg_split("/^(.+?),/", $choice)[1]);
                    }
                } else if ($params['value'] === $option_value) {
                    $label = trim(preg_split("/^(.+?),/", $choice)[1]);
                    break;
                } else if ($params['value'] == "" && $metadata[$fieldName]['field_type'] == 'checkbox') {
                    //Checkboxes for event_arms
                    if ($other_event_id == "") {
                        $other_event_id = $params['event_id'];
                    }
                    if ($data[$params['record_id']][$other_event_id][$fieldName][$option_value] == "1") {
                        $label .= trim(preg_split("/^(.+?),/", $choice)[1]) . ", ";
                    }
                }
            }
            //we delete the last comma and space
            $label = rtrim($label, ", ");
        } else if ($metadata[$fieldName]['field_type'] == 'truefalse') {
            if ($params['value'] == '1') {
                $label = "True";
            } else  if ($params['value'] == '0'){
                $label = "False";
            }
        } else if ($metadata[$fieldName]['field_type'] == 'yesno') {
            if ($params['value'] == '1') {
                $label = "Yes";
            } else  if ($params['value'] == '0'){
                $label = "No";
            }
        } else if ($metadata[$fieldName]['field_type'] == 'sql') {
            if (!empty($params['value'])) {
                $q = ExternalModules::query($metadata[$fieldName]['select_choices_or_calculations'], []);

                if ($error = db_error()) {
                    die($metadata[$fieldName]['select_choices_or_calculations'] . ': ' . $error);
                }

                while ($row = $q->fetch_assoc()) {
                    if ($row['record'] == $params['value']) {
                        $label = $row['value'];
                        break;
                    }
                }
            }
        } else if (in_array($metadata[$fieldName]['text_validation_type_or_show_slider_number'], array_keys($dateFormats)) && $params['value'] != "") {
            $label = date($dateFormats[$metadata[$fieldName]['text_validation_type_or_show_slider_number']], strtotime($params['value']));
        }
        return $label;
    }

	public function getChoiceLabels($fieldName, $pid = null){
		// Caching could be easily added to this method to improve performance on repeat calls.

		$pid = $this->requireProjectId($pid);

		$dictionary = \REDCap::getDataDictionary($pid, 'array', false, [$fieldName]);
		$choices = explode('|', $dictionary[$fieldName]['select_choices_or_calculations']);
		$choicesById = [];
		foreach($choices as $choice){
			$parts = explode(', ', $choice);
			$id = trim($parts[0]);
			$label = trim($parts[1]);
			$choicesById[$id] = $label;
		}

		return $choicesById;
	}

	public function getFieldLabel($fieldName){
		$pid = self::requireProjectId();
		$dictionary = \REDCap::getDataDictionary($pid, 'array', false, [$fieldName]);
		return $dictionary[$fieldName]['field_label'];
	}

	public function query($sql, $parameters = null){
		$frameworkVersion = ExternalModules::getFrameworkVersion($this);
		if($parameters === null && $frameworkVersion < 4){
			// Allow queries without parameters.
			$parameters = [];
		}

		return ExternalModules::query($sql, $parameters);
	}

	public function createDAG($dagName){
		$this->query(
			"insert into redcap_data_access_groups (project_id, group_name) values (?, ?)",
			[self::requireProjectId(), $dagName]
		);
		return db_insert_id();
	}

    public function deleteDAG($dagId){
        $this->deleteAllDAGRecords($dagId);
		$this->deleteAllDAGUsers($dagId);
		
        $this->query(
			"DELETE FROM redcap_data_access_groups where project_id = ? and group_id = ?",
			[self::requireProjectId(), $dagId]
		);
    }

    private function deleteAllDAGRecords($dagId){
		$pid = self::requireProjectId();

        $records = $this->query(
			"SELECT record FROM redcap_data where project_id = ? and field_name = '__GROUPID__' and value = ?",
			[$pid, $dagId]
		);

        while ($row = $records->fetch_assoc()){
            $record = $row['record'];
            $this->query("DELETE FROM redcap_data where project_id = ? and record = ?", [$pid, $record]);
		}
		
        $this->query("DELETE FROM redcap_data where project_id = ? and field_name = '__GROUPID__' and value = ?", [$pid, $dagId]);
    }

    private function deleteAllDAGUsers($dagId){
        $this->query("DELETE FROM redcap_user_rights where project_id = ? and group_id = ?", [self::requireProjectId(), $dagId]);
    }

	public function renameDAG($dagId, $dagName){
		$this->query(
			"update redcap_data_access_groups set group_name = ? where project_id = ? and group_id = ?",
			[$dagName, self::requireProjectId(), $dagId]
		);
	}

	public function setDAG($record, $dagId){
		// $this->setData() is used instead of REDCap::saveData(), since REDCap::saveData() has some (perhaps erroneous) limitations for super users around setting DAGs on records that are already in DAGs  .
		// It also doesn't seem to be aware of DAGs that were just added in the same hook call (likely because DAGs are cached before the hook is called).
		// Specifying a "redcap_data_access_group" parameter for REDCap::saveData() doesn't work either, since that parameter only accepts the auto generated names (not ids or full names).

		$this->setData($record, '__GROUPID__', $dagId);
		
		// Update the record list cache table too
		if (method_exists('Records', 'updateRecordDagInRecordListCache')) {
			\Records::updateRecordDagInRecordListCache(self::requireProjectId(), $record, $dagId);
		}
	}

	public function areSettingPermissionsUserBased(){
		return $this->userBasedSettingPermissions;
	}

	public function disableUserBasedSettingPermissions(){
		$this->userBasedSettingPermissions = false;
	}

	public function addAutoNumberedRecord($pid = null){
		$pid = $this->requireProjectId($pid);

		// The actual id passed to saveData() doesn't matter, since autonumbering will overwrite it.
		$importRecordId = 1;

		$data = [
			[
				ExternalModules::getRecordIdField($pid) => $importRecordId,
			]
		];

		$results = \REDCap::saveData(
			$pid,
			'json',
			json_encode($data),
			'normal',
			'YMD',
			'flat',
			null,
			true,
			true,
			true,
			false,
			true,
			[],
			false,
			true,
			false,
			true // Use auto numbering
		);

		if(!empty($results['errors'])){
			throw new Exception("Error calling " . __METHOD__ . "(): " . json_encode($results, JSON_PRETTY_PRINT));
		}

		if(!empty($results['warnings'])){
			ExternalModules::errorLog("Warnings occurred while calling " . __METHOD__ . "().  These should likely be ignored.  In fact, this error message could potentially be removed:" . json_encode($results, JSON_PRETTY_PRINT));
		}

		return (int) $results['ids'][$importRecordId];
	}

	public function getFirstEventId($pid = null){
		$pid = $this->requireProjectId($pid);
		$results = $this->query("
			select event_id
			from redcap_events_arms a
			join redcap_events_metadata m
				on a.arm_id = m.arm_id
			where a.project_id = ?
			order by event_id
		", [$pid]);

		$row = $results->fetch_assoc();
		return $row['event_id'];
	}

	public function saveFile($path, $pid = null){
		$pid = $this->requireProjectId($pid);

		$file = [];
		$file['name'] = basename($path);
		$file['tmp_name'] = $path;
		$file['size'] = filesize($path);

		return \Files::uploadFile($file, $pid);
	}

	public function validateSettings($settings){
		return null;
	}

	/**
	 * Return a value from the UI state config. Return null if key doesn't exist.
	 * @param int/string $key key
	 * @return mixed - value if exists, else return null
	 */
	public function getUserSetting($key)
	{
		return UIState::getUIStateValue($this->detectProjectId(), self::UI_STATE_OBJECT_PREFIX . $this->PREFIX, $key);
	}
	
	/**
	 * Save a value in the UI state config
	 * @param int/string $key key
	 * @param mixed $value value for key
	 */
	public function setUserSetting($key, $value)
	{
		UIState::saveUIStateValue($this->detectProjectId(), self::UI_STATE_OBJECT_PREFIX . $this->PREFIX, $key, $value);
	}
	
	/**
	 * Remove key-value from the UI state config
	 * @param int/string $key key
	 */
	public function removeUserSetting($key)
	{
		UIState::removeUIStateValue($this->detectProjectId(), self::UI_STATE_OBJECT_PREFIX . $this->PREFIX, $key);
	}

	public function exitAfterHook(){
		ExternalModules::exitAfterHook();
	}

	public function redcap_module_link_check_display($project_id, $link)
	{
		// On NOAUTH pages, is this constant resolving to the string "SUPER_USER"
		// and allowing anyone access to the page in a correct but very strange way?
		if (SUPER_USER) {
			return $link;
		}

		if (!empty($project_id) && \REDCap::getUserRights(USERID)[USERID]['design']) {
			return $link;
		}

		return null;
    }

    public function redcap_module_configure_button_display(){
        return true;
    }

    public function getPublicSurveyUrl($pid=null){

        if(empty($pid)){
            $pid = $this->getProjectId();
        }

        $hash = $this->getPublicSurveyHash($pid);

        $link = APP_PATH_SURVEY_FULL . "?s=$hash";
        if($hash == null){
            $link = null;
        }
        return $link;
    }

    function getPublicSurveyHash($pid=null){
        $sql ="
			select p.hash 
            from redcap_surveys s
            join redcap_surveys_participants p
            on s.survey_id = p.survey_id
            join redcap_metadata  m
            on m.project_id = s.project_id and m.form_name = s.form_name
            where p.participant_email is null and m.field_order = 1 and s.project_id = ?
		";

        $result = $this->query($sql, [$pid]);
        if($result->num_rows > 0){
            $row = $result->fetch_assoc();
            $hash = @$row['hash'];
        }else{
            $hash = null;
        }

        return $hash;
    }

	public function isSurveyPage()
	{
		return ExternalModules::isSurveyPage();
	}

	public function initializeJavascriptModuleObject()
	{
		global $lang;

		$jsObject = ExternalModules::getJavascriptModuleObjectName($this);

		$pid = $this->getProjectId();
		$logUrl = APP_URL_EXTMOD . "manager/ajax/log.php?prefix=" . $this->PREFIX . "&pid=$pid";
		$noAuth = defined('NOAUTH');

		$recordId = $this->getRecordIdOrTemporaryRecordId();
		if($noAuth && !ExternalModules::isTemporaryRecordId($recordId)){
			// Don't sent the actual record id, since it shouldn't be trusted on non-authenticated requests anyway.
			$recordId = null;
		}

		ExternalModules::tt_initializeJSLanguageStore();

		?>
		<script>
			(function(){
				// Create the module object, and any missing parent objects.
				var parent = window
				;<?=json_encode($jsObject)?>.split('.').forEach(function(part){
					if(parent[part] === undefined){
						parent[part] = {}
					}

					parent = parent[part]
				})

				// Shorthand for the external module object.
				var module = <?=$jsObject?>

				// Add methods.
				module.log = function(message, parameters){
					if(parameters === undefined){
						parameters = {}
					}
					<?php
					if(!empty($recordId)){
						?>
						if(parameters.record === undefined){
							parameters.record = <?=json_encode($recordId)?>
						}
						<?php
					}
					?>
					$.ajax({
						'type': 'POST',
						'url': "<?=$logUrl?>",
						'data': JSON.stringify({
							message: message
							,parameters: parameters
							,noAuth: <?=json_encode($noAuth)?>
							<?php if($this->isSurveyPage()) { ?>
								,surveyHash: <?=json_encode($_GET['s'])?>
								,responseHash: $('#form input[name=__response_hash__]').val()
							<?php } ?>
						}),
						'success': function(data){
							if(data !== 'success'){
								//= An error occurred while calling the log API:
								console.error(<?=json_encode(ExternalModules::tt("em_errors_68"))?>, data)
							}
						}
					})
				}

				module.getUrlParameters = function(){
					var search = location.search
					if(location.search[0] !== '?'){
						// There aren't any URL parameters
						return null
					}

					// Remove the leading question mark
					search = search.substring(1)

					var params = []
					var parts = search.split('&')
					$.each(parts, function(index, part){
						var innerParts = part.split('=')
						var name = innerParts[0]
						var value = null

						if(innerParts.length === 2){
							value = innerParts[1]
						}

						params[name] = value
					})

					return params
				}

				module.getUrlParameter = function(name){
					var params = this.getUrlParameters()
					return params[name]
				}

				module.isRoute = function(routeName){
					return this.getUrlParameter('route') === routeName
				}

				module.isImportPage = function(){
					return this.isRoute('DataImportController:index')
				}

				module.isImportReviewPage = function(){
					if(!this.isImportPage()){
						return false
					}

					return $('table#comptable').length === 1
				}

				module.isImportSuccessPage = function(){
					if(!this.isImportPage()){
						return false
					}
					var successMessage = $('#center > .green > b').text()
					return successMessage === <?=json_encode($lang["data_import_tool_133"])?> // 'Import Successful!'
				}

				/**
				 * Constructs the full language key for an EM-scoped key.
				 * @private
				 * @param {string} key The EM-scoped key.
				 * @returns {string} The full key for use in $lang.
				 */
				module._constructLanguageKey = function(key) {
					return <?=json_encode(ExternalModules::EM_LANG_PREFIX . $this->PREFIX)?> + '_' + key
				}
				
				/**
				 * Gets and interpolate a translation.
				 * @param {string} key The key for the string.
				 * Note: Any further arguments after key will be used for interpolation. If the first such argument is an array, it will be used as the interpolation source.
				 * @returns {string} The interpolated string.
				 */
				module.tt = function (key) {
					var argArray = Array.prototype.slice.call(arguments)
					argArray[0] = this._constructLanguageKey(key)
					var lang = window.ExternalModules.$lang
					return lang.tt.apply(lang, argArray)
				}
				/**
				 * Adds a key/value pair to the language store.
				 * @param {string} key The key.
				 * @param {string} value The string value to add.
				 */
				module.tt_add = function(key, value) {
					key = this._constructLanguageKey(key)
					window.ExternalModules.$lang.add(key, value)
				}
			})()
		</script>
		<?php
	}

	public function __call($name, $arguments){
		if(!isset($this->PREFIX)){
			// The module's parent constructor has not finished yet.
			// Simulate the standard error.
			throw new Exception("Call to undefined method: $name");
		}

		// Mark is working on a PR to move log functionality to the ExternalModules class so it can be used by the framework internally,
		// without a module instance.  After that, this weird 'log_internal' pass through can be replaced by a log() method in the Framework class.
		if($name === 'log'){
			return call_user_func_array([$this, 'log_internal'], $arguments);
		}
		
		// The version argument is required here for the case where a module is in the process of being enabled
		// (so the current version has not yet been set in the database)
		// and the constructor references a method on the framework
		// or a method that doesn't exist.
		return ExternalModules::getFrameworkInstance($this->PREFIX, $this->VERSION)->callFromModuleInstance($name, $arguments);
	}

	// Allow framework object references like `records` to be returned directly.
	public function __get($name){
		if(!isset($this->PREFIX)){
			// The module's parent constructor has not finished yet.  Just return null.
			return null;
		}

		// The version argument is required here for the case where a module is in the process of being enabled
		// (so the current version has not yet been set in the database)
		// and the constructor references a property on the framework
		// or a property that doesn't exist.
		return ExternalModules::getFrameworkInstance($this->PREFIX, $this->VERSION)->{$name};
	}

	private function log_internal($message, $parameters = [])
	{
		if (empty($message)) {
			throw new Exception("A message is required for log entries.");
		}

		if(!is_array($parameters)){
			throw new Exception("The second argument to the log() method must be an array of parameters. A '" . gettype($parameters) . "' was given instead.");
		}

		foreach ($parameters as $name => $value) {
			if (isset(self::$RESERVED_LOG_PARAMETER_NAMES_FLIPPED[$name])) {
				throw new Exception("The '$name' parameter name is set automatically and cannot be overridden.");
			}
			else if($value === null){
				// There's no point in storing null values in the database.
				// If a parameter is missing, queries will return null for it anyway.
				unset($parameters[$name]);
			}
			else if(strpos($name, "'") !== false){
				throw new Exception("Single quotes are not allowed in parameter names.");
			}

			$type = gettype($value);
			if(!in_array($type, ['boolean', 'integer', 'double', 'string', 'NULL'])){
				throw new Exception("The type '$type' for the '$name' parameter is not supported.");
			}
		}

		$projectId = @$parameters['project_id'];
		if (empty($projectId)) {
			$projectId = $this->getProjectId();

			if (empty($projectId)) {
				$projectId = null;
			}
		}

		$username = @$parameters['username'];
		if(empty($username)){
			$username = ExternalModules::getUsername();;
		}

		if(isset($parameters['record'])){
			$recordId = $parameters['record'];

			// Unset it so it doesn't get added to the parameters table.
			unset($parameters['record']);
		}
		else{
			$recordId = $this->getRecordIdOrTemporaryRecordId();
		}

		if (empty($recordId)) {
			$recordId = null;
		}

		$timestamp = @$parameters['timestamp'];
		$ip = $this->getIP(@$parameters['ip']);

		// Remove parameter values that will be stored on the main log table,
		// so they are not also stored in the parameter table
		foreach(AbstractExternalModule::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE as $paramName){
			unset($parameters[$paramName]);
		}

		$query = ExternalModules::createQuery();
		$query->add("
			insert into redcap_external_modules_log
				(
					timestamp,
					ui_id,
					ip,
					external_module_id,
					project_id,
					record,
					message
				)
			values
		");

		$query->add('(');

		if(empty($timestamp)){
			$query->add('now()');
		}
		else{
			$query->add('?', $timestamp);
		}


		$query->add("
			,
			(select ui_id from redcap_user_information where username = ?),
			?,
			(select external_module_id from redcap_external_modules where directory_prefix = ?),
			?,
			?,
			?
		", [$username, $ip, $this->PREFIX, $projectId, $recordId, $message]);

		$query->add(')');

		$query->execute();

		$logId = db_insert_id();
		if (!empty($parameters)) {
			$this->insertLogParameters($logId, $parameters);
		}

		return $logId;
	}

	private function getIP($ip)
	{
		$username = ExternalModules::getUsername();
		
		if(
			empty($ip)
			&& !empty($username) // Only log the ip if a user is currently logged in
			&& !$this->isSurveyPage() // Don't log IPs for surveys
		){
			// The IP could contain multiple comma separated addresses (if proxies are used).
			// To accommodated at least three IPv4 addresses, the DB field is 100 chars long like the redcap_log_event table.
			$ip = \System::clientIpAddress();
		}

		if (empty($ip)) {
			$ip = null;
		}

		return $ip;
	}

	private function insertLogParameters($logId, $parameters)
	{
		$query = ExternalModules::createQuery();

		$query->add('insert into redcap_external_modules_log_parameters (log_id, name, value) VALUES');

		$addComma = false;
		foreach ($parameters as $name => $value) {
			if (!$addComma) {
				$addComma = true;
			}
			else{
				$query->add(',');
			}

			if(empty($name)){
				throw new Exception(ExternalModules::tt('em_errors_116'));
			}

			// Limit allowed characters to prevent SQL injection when logs are queried later.
			ExternalModules::checkForInvalidLogParameterNameCharacters($name);

			$query->add('(?, ?, ?)', [$logId, $name, $value]);
		}

		$query->execute();
	}

	public function logAjax($data)
	{
		$parameters = @$data['parameters'];
		if(!$parameters){
			$parameters = [];
		}

		foreach($parameters as $name=>$value){
			if($name === 'record' && ExternalModules::isTemporaryRecordId($value)){
				// Allow the temporary record id to get passed through as a parameter.
				continue;
			}

			if(in_array($name, self::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE)){
				throw new Exception("For security reasons, the '$name' parameter cannot be overridden via AJAX log requests.  It can be overridden only be overridden by PHP log requests.  You can add your own PHP page to this module to perform the logging, and call it via AJAX.");
			}
		}

		$surveyHash = @$data['surveyHash'];
		$responseHash = @$data['responseHash'];
		if(!empty($responseHash)){
			// We're on a survey submission that already has a record id.
			// We shouldn't pass the record id directly because it would be easy to spoof.
			// Instead, we determine the record id from the response hash.

			// This method is called to set the $participant_id global;
			global $participant_id;
			\Survey::setSurveyVals($surveyHash);

			$responseId = \Survey::decryptResponseHash($responseHash, $participant_id);
			$result = $this->query("select record from redcap_surveys_response where response_id = ?", [$responseId]);
			$row = $result->fetch_assoc();
			$recordId = $row['record'];
		}

		if(!empty($recordId)){
			$this->setRecordId($recordId);
		}

		return $this->log($data['message'], $parameters);
	}

	public function getQueryLogsSql($sql)
	{
		$parser = new PHPSQLParser();
		$parsed = $parser->parse($sql);

		if($parsed['SELECT'] === null){
			throw new Exception("Queries must start with a 'select' statement.");
		}

		$selectFields = [];
		$whereFields = [];
		$orderByFields = [];
		$groupByFields = [];
		$this->processPseudoQuery($parsed['SELECT'], $selectFields, true);
		$this->processPseudoQuery($parsed['WHERE'], $whereFields, false);
		$this->processPseudoQuery($parsed['ORDER'], $orderByFields, false);
		$this->processPseudoQuery($parsed['GROUP'], $groupByFields, false);
		$fields = array_merge($selectFields, $whereFields, $orderByFields, $groupByFields);

		$standardWhereClauses = [];

		if(!in_array('external_module_id', $whereFields)){
			$standardWhereClauses[] = AbstractExternalModule::EXTERNAL_MODULE_ID_STANDARD_WHERE_CLAUSE_PREFIX . " = '{$this->PREFIX}')";
		}

		if(!in_array('project_id', $whereFields)){
			$projectId = $this->getProjectId();
			if (!empty($projectId)) {
				$standardWhereClauses[] = "redcap_external_modules_log.project_id = $projectId";
			}
		}

		if(!empty($standardWhereClauses)){
			$standardWhereClausesSql = 'where ' . implode(' and ', $standardWhereClauses);

			if($parsed['WHERE'] === null){
				// Set it to an empty array, since array_merge() won't work on null.
				$parsed['WHERE'] = [];
			}
			else{
				$standardWhereClausesSql .= ' and ';
			}

			$parsedStandardWhereClauses = $parser->parse($standardWhereClausesSql);
			$parsed['WHERE'] = array_merge($parsedStandardWhereClauses['WHERE'], $parsed['WHERE']);
		}

		$creator = new PHPSQLCreator();
		$select = $creator->create(['SELECT' => $parsed['SELECT']]);
		$otherClauses = substr($creator->create($parsed), strlen($select));

		$fields = array_unique($fields);
		$joinUsername = false;
		$parameterFields = [];
		foreach ($fields as $field) {
			if ($field == 'username') {
				$joinUsername = true;
			} else if (isset(self::$LOG_PARAMETERS_ON_MAIN_TABLE[$field])) {
				// do nothing
			} else {
				$parameterFields[] = $field;
			}
		}

		$from = ' from redcap_external_modules_log';
		foreach ($parameterFields as $field) {
			// The invalid character check below should be enough, but lets escape too just to be safe.
			$field = db_escape($field);

			// Needed for field names with spaces.
			$fieldString = str_replace("`", "", $field);
			
			// Prevent SQL injection.
			ExternalModules::checkForInvalidLogParameterNameCharacters($fieldString);

			$from .= "
						left join redcap_external_modules_log_parameters $field on $field.name = '$fieldString'
						and $field.log_id = redcap_external_modules_log.log_id
					";
		}
		
		if ($joinUsername) {
			$from .= "
						left join redcap_user_information on redcap_user_information.ui_id = redcap_external_modules_log.ui_id
					";
		}

		$sql = implode(' ', [$select, $from, $otherClauses]);

		return $sql;
	}

	private function processPseudoQuery(&$parsed, &$fields, $addAs, $parentItem = null)
	{
		if($parsed === null){
			return;
		}

		for ($i = 0; $i < count($parsed); $i++) {
			$item =& $parsed[$i];
			$subtree =& $item['sub_tree'];

			if (is_array($subtree)) {
				$this->processPseudoQuery($subtree, $fields, $addAs, $item);
			} else if ($item['expr_type'] == 'colref'){
				if($item['base_expr'] === '*'){
					if(strtolower(@$parentItem['base_expr']) !== 'count'){
						throw new Exception("Log queries do not currently '*' for selecting column names.  Columns must be explicitly defined in all log queries.");
					}
				}
				else{
					$field = $item['base_expr'];
					if($field === '?'){
						continue;
					}

					$fields[] = $field;

					if ($field === 'username') {
						$newField = 'redcap_user_information.username';
					} else if(isset(self::$LOG_PARAMETERS_ON_MAIN_TABLE[$field])) {
						$newField = "redcap_external_modules_log.$field";
					} else {
						$newField = "$field.value";

						if ($addAs && $item['alias'] == false) {
							$newField .= " as $field";
						}
					}

					$item['base_expr'] = $newField;
				}
			}
		}
	}

	public function getProjectId()
	{
		$pid = @$_GET['pid'];

		// Require only digits to prevent sql injection.
		if (ctype_digit($pid)) {
			return $pid;
		} else {
			return null;
		}
	}

	public function getRecordId()
	{
		return $this->recordId;
    }
    
    public function setRecordId($recordId)
	{
		$this->recordId = $recordId;
    }

	public function getRecordIdOrTemporaryRecordId()
	{
		$recordId = $this->getRecordId();
		if(empty($recordId)){
			// Use the temporary record id if it exists.
			$recordId = ExternalModules::getTemporaryRecordId();
		}

		return $recordId;
	}

	public static function init()
	{
		self::$RESERVED_LOG_PARAMETER_NAMES_FLIPPED = array_flip(self::$RESERVED_LOG_PARAMETER_NAMES);
		self::$LOG_PARAMETERS_ON_MAIN_TABLE = array_flip(array_merge(self::$RESERVED_LOG_PARAMETER_NAMES, self::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE));
	}

	/**
	 * The following methods have been defined directly on the module instance ever since they were added.
	 * New methods should NOT be added here, but should be added to the Framework class instead.
	 * These method stubs exist to allow forwarding to the Framework class pre-v5,
	 * and to ensure that method_exists() calls continue working indefinitely for existing modules.
	 * We know some modules at Vanderbilt require that, and it's safer for backward compatibility not to change any behavior.
	 */
	function getProjectSettings(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function setProjectSettings(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function getSubSettings(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function setData(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function queryLogs(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
	function removeLogs(){
		return $this->__call(__FUNCTION__, func_get_args());
	}
}
