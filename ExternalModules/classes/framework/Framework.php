<?php
namespace ExternalModules;

require_once __DIR__ . '/Project.php';
require_once __DIR__ . '/Records.php';
require_once __DIR__ . '/User.php';

use Exception;

class Framework
{
	/**
	 * The framework version
	 */
	private $VERSION;

	/**
	 * The module for which the framework is initialized
	 */
	private $module;

	/**
	 * Constructor
	 * @param AbstractExternalModule $module The module for which the framework is initialized.
	 */
	function __construct($module){
        if(!($module instanceof AbstractExternalModule)){
			throw new Exception(ExternalModules::tt("em_errors_70")); //= Initializing the framework requires a module instance.
        }

        $this->module = $module;
        $this->initialize();

		// Initialize language support (parse language files etc.).
        ExternalModules::initializeLocalizationSupport($module->PREFIX, $module->VERSION);
        
		$this->records = new Records($module);
    }
    
    // This method is used by unit testing.
    private function initialize(){
        $frameworkVersion = ExternalModules::getFrameworkVersion($this->module);
        if($frameworkVersion > ExternalModules::getMaxSupportedFrameworkVersion()){
            throw new Exception(ExternalModules::tt('em_errors_130', $frameworkVersion));
        }
        else if($frameworkVersion > 1){
            $this->module->framework = $this;
        }

		$this->VERSION = $frameworkVersion;
    }

	//region Language features

	/**
	 * Returns the translation for the given language file key.
	 * 
	 * @param string $key The language file key.
	 * 
	 * Note: Any further arguments are used for interpolation. When the first additional parameter is an array, it's members will be used and any further parameters ignored. 
	 * 
	 * @return string The translation (with interpolations).
	 */
	public function tt($key) {
		// Get all arguments and send off for processing.
		return ExternalModules::tt_process(func_get_args(), $this->module->PREFIX, false);
	}

	/**
	 * Transfers one (interpolated) or many strings (without interpolation) to the module's JavaScript object.
	 * 
	 * @param mixed $key (optional) The language key or an array of language keys.
	 * 
	 * Note: When a single language key is given, any number of arguments can be supplied and these will be used for interpolation. When an array of keys is passed, then any further arguments will be ignored and the language strings will be transfered without interpolation. If no key or null is passed, all language strings will be transferred.
	 */
	public function tt_transferToJavascriptModuleObject($key = null) {
		// Get all arguments and send off for processing.
		ExternalModules::tt_prepareTransfer(func_get_args(), $this->module->PREFIX);
	}

	/**
	 * Adds a key/value pair directly to the language store for use in the JavaScript module object. 
	 * Value can be anything (string, boolean, array).
	 * 
	 * @param string $key The language key.
	 * @param mixed $value The corresponding value.
	 */
	public function tt_addToJavascriptModuleObject($key, $value) {
		ExternalModules::tt_addToJSLanguageStore($key, $value, $this->module->PREFIX, $key);
	}

	//endregion

	/**
	 * Gets all project settings as an array. Useful for cases when you may
	 * be creating a custom config page for the external module in a project. 
	 * Each setting is formatted as: [ 'yourkey' => 'value' ]
	 * (in case of repeating settings, value will be an array).
	 * This return value can be used as input for setProjectSettings().
	 * 
	 * @param int|null $pid
	 * @return array containing settings
	 */
    function getProjectSettings($pid = null)
	{
        $pid = self::requireProjectId($pid);
        $prefix = $this->module->PREFIX;

		if ($this->VERSION < 5) {
		    return ExternalModules::getProjectSettingsAsArray($prefix, $pid);
		}
		
		$vSettings = ExternalModules::getProjectSettingsAsArray($prefix, $pid, false);
		// Transform settings to match the output from ExternalModules::formatRawSettings,
		// i.e. remove 'value' keys, preserving the project values "one level up"
		$settings = array();
		foreach ($vSettings as $key => $values) {
			$settings[$key] = $values["value"];
		}
		return $settings;
	}

	/**
	 * Saves all project settings (to be used with getProjectSettings). Useful
	 * for cases when you may create a custom config page or need to overwrite all
	 * project settings for an external module.
	 * @param array $settings Array of project-specific settings
	 * @param int|null $pid
	 */
	function setProjectSettings($settings, $pid = null)
	{
		$pid = self::requireProjectId($pid);
		if ($this->VERSION >= 5) {
			ExternalModules::saveProjectSettings($this->module->PREFIX, $pid, $settings);
		}
		else{
			// In older framework versions, this method existed but did nothing (besides require the $pid).
		}
	}

	function getProjectsWithModuleEnabled(){
		$results = $this->query("SELECT CAST(s.project_id AS CHAR) AS project_id
								FROM redcap_external_modules m
								JOIN redcap_external_module_settings s
									ON m.external_module_id = s.external_module_id
								JOIN redcap_projects p
									ON s.project_id = p.project_id
								WHERE
									m.directory_prefix = ?
									AND s.value = 'true'
									AND s.key = ?
									AND p.date_deleted IS NULL
									AND p.status IN (0,1) 
									AND p.completed_time IS NULL
		", [$this->module->PREFIX, ExternalModules::KEY_ENABLED]);

		$pids = [];
		while($row = $results->fetch_assoc()) {
			$pids[] = $row['project_id'];
		}

		return $pids;
	}

	function __call($name, $arguments){
        // TODO - Consider refactoring the framework call magic method calls back to the module aren't needed.
		return call_user_func_array([$this->module, $name], $arguments);
	}
	
	function callFromModuleInstance($name, $arguments){
        if($this->isSafeToForwardMethodToFramework($name)) {
            if($name === 'getSubSettings' && $this->VERSION < 5){
                // Calling this method from the module instance still resulted in the
                // implementation from v1 all the way through v4.
                $name = 'getSubSettings_v1';
            }          

			return call_user_func_array([$this, $name], $arguments);
		}

		//= The following method does not exist: {0}
		throw new Exception(ExternalModules::tt("em_errors_69", $name)); 
	}

	function getSubSettings($key, $pid = null)
	{
		$settingsAsArray = ExternalModules::getProjectSettingsAsArray($this->module->PREFIX, $this->requireProjectId($pid));

		$settingConfig = $this->getSettingConfig($key);

		return $this->getSubSettings_internal($settingsAsArray, $settingConfig);
	}

	private function getSubSettings_internal($settingsAsArray, $settingConfig)
	{
		$subSettings = [];
		foreach($settingConfig['sub_settings'] as $subSettingConfig){
			$subSettingKey = $subSettingConfig['key'];

			if($subSettingConfig['type'] === 'sub_settings'){
				// Handle nested sub_settings recursively
				$values = $this->getSubSettings_internal($settingsAsArray, $subSettingConfig);

				$recursionCheck = function($value){
					// We already know the value must be an array.
					// Recurse until we're two levels away from the leaves, then wrap in $subSettingKey.
					// If index '0' is not defined, we know it's a leaf since only setting key names will be used as array keys (not numeric indexes).
					return isset($value[0][0]);
				};
			}
			else{
				$values = $settingsAsArray[$this->prefixSettingKey($subSettingKey)]['value'];
				if($values === null){
					continue;
				}

				$recursionCheck = function($value) use ($subSettingConfig){
					// Only recurse if this is an array, and not a leaf.
					// If index '0' is not defined, we know it's a leaf since only setting key names will be used as array keys (not numeric indexes).
					// Using array_key_exists() instead of isset() is important since there could be a null value set.
					return !@$subSettingConfig['repeatable'] && is_array($value) && array_key_exists(0, $value);
				};
			}

			$formatValues = function($values) use ($subSettingConfig, $subSettingKey, $recursionCheck, &$formatValues){
				for($i=0; $i<count($values); $i++){
					$value = $values[$i];
					
					if($recursionCheck($value)){
						$values[$i] = $formatValues($value);
					}
					else{
						$values[$i] = [
							$subSettingKey => $value
						];
					}
				}

				return $values;
			};

			$values = $formatValues($values);

			$subSettings = ExternalModules::array_merge_recursive_distinct($subSettings, $values);
		}

		return $subSettings;
    }
    
    // This method is not in the main method documentation, but does exist in the docs for v5,
    // and should remain supported long term for backward compatibility.
    function getSubSettings_v1($key, $pid = null)
	{
		$keys = [];
		$config = $this->getSettingConfig($key);
		foreach($config['sub_settings'] as $subSetting){
			$keys[] = $this->prefixSettingKey($subSetting['key']);
		}

		$rawSettings = ExternalModules::getProjectSettingsAsArray($this->module->PREFIX, self::requireProjectId($pid));

		$subSettings = [];
		foreach($keys as $key){
            $values = $rawSettings[$key]['value'];
            if($values === null){
                continue;
            }

			for($i=0; $i<count($values); $i++){
				$value = $values[$i];
				$subSettings[$i][$key] = $value;
			}
		}

		return $subSettings;
	}

	function getSQLInClause($columnName, $values){
        if($this->VERSION >= 4){
            throw new Exception(ExternalModules::tt('em_errors_122'));
        }
        
        return ExternalModules::getSQLInClause($columnName, $values);
	}

	function getUser($username = null){
		if(empty($username)){
			if(!defined('USERID')){
				//= A username was not specified and could not be automatically detected.
				throw new Exception(ExternalModules::tt("em_errors_71")); 
			}

			$username = USERID;
		}

		return new User($this, $username);
	}

	function getProject($project_id = null){
        $project_id = $this->requireProjectId($project_id);
		return new Project($this, $project_id);
	}

	function requireInteger($mixed){
		return ExternalModules::requireInteger($mixed);
	}

	function getJavascriptModuleObjectName(){
		return ExternalModules::getJavascriptModuleObjectName($this->module);
	}

	function isRoute($routeName){
		return ExternalModules::isRoute($routeName);
	}

	function getRecordIdField($pid = null){
        return $this->getProject($pid)->getRecordIdField();
	}

	function getRepeatingForms($eventId = null, $projectId = null){
		if($eventId === null){
			$eventId = $this->getEventId($projectId);
		}

        $result = $this->query('select * from redcap_events_repeat where event_id = ?', $eventId);

        $forms = [];
        while($row = $result->fetch_assoc()){
            $forms[] = $row['form_name'];
        }

        return $forms;
    }

	function createQuery(){
		return ExternalModules::createQuery();
	}

	function getEventId($projectId = null){
        if(!$projectId){
            $eventId = @$_GET['event_id'];
            if($eventId){
                return $eventId;
            }
        }
        
        return $this->getProject($projectId)->getEventId();
    }

	function getSafePath($path, $root=null){
		$moduleDirectory = $this->module->getModulePath();
		if(!$root){
			$root = $moduleDirectory;
		}
		else if(!file_exists($root)){
			$root = "$moduleDirectory/$root";
		}

		return ExternalModules::getSafePath($path, $root);
	}

	function convertIntsToStrings($row){
		return ExternalModules::convertIntsToStrings($row);
	}

	function isPage($path){
        $path = APP_PATH_WEBROOT . $path;
        return strpos($_SERVER['REQUEST_URI'], $path) === 0;
    }

    function createProject($title, $purpose, $project_note=null){
	    global $auth_meth_global;
        $userInfo = \User::getUserInfo(USERID);
        if (!$userInfo['allow_create_db']) throw new Exception("ERROR: You do not have Create Project privileges!");

        if ($title == "" || $title == null) throw new Exception("ERROR: Title can't be null or blank!");
        $title = \Project::cleanTitle($title);
        $new_app_name = \Project::getValidProjectName($title);

        $userid = USERID;

        if(!is_numeric($purpose) || $purpose < 0 || $purpose > 4) throw new Exception("ERROR: The purpose has to be numeric and it's value between 0 and 4.");

        $auto_inc_set = 1;

        $GLOBALS['__SALT__'] = substr(sha1(rand()), 0, 10);

        $user_id_result = ExternalModules::query("select ui_id from redcap_user_information where username = ? limit 1",[$userid]);
        $ui_id = $user_id_result->fetch_assoc()['ui_id'];

        ExternalModules::query("insert into redcap_projects (project_name, purpose, app_title, creation_time, created_by, auto_inc_set, project_note,auth_meth,__SALT__) values(?,?,?,?,?,?,?,?,?)",
            [$new_app_name,$purpose,$title,NOW,$ui_id,$auto_inc_set,trim($project_note),$auth_meth_global,$GLOBALS['__SALT__']]);

        // Get this new project's project_id
        $pid = db_insert_id();

        // Insert project defaults into redcap_projects
        \Project::setDefaults($pid);

        $logDescrip = "Create project";
        \Logging::logEvent("","redcap_projects","MANAGE",$pid,"project_id = $pid",$logDescrip);

        // Give this new project an arm and an event (default)
        \Project::insertDefaultArmAndEvent($pid);
        // Now add the new project's metadata
        $form_names = createMetadata($pid, 0);
        ## USER RIGHTS
        // Insert user rights for this new project for user REQUESTING the project
        \Project::insertUserRightsProjectCreator($pid, $userid, 0, 0, $form_names);

        return $pid;
    }

    function importDataDictionary($project_id,$path){
        $dictionary_array = $this->dataDictionaryCSVToMetadataArray($path, 'array');

        //Return warnings and errors from file (and fix any correctable errors)
        list ($errors_array, $warnings_array, $dictionary_array) = \MetaData::error_checking($dictionary_array);
        // Save data dictionary in metadata table
        $sql_errors = $this->saveMetadataCSV($dictionary_array,$project_id);

        // Display any failed queries to Super Users, but only give minimal info of error to regular users
        if (count($sql_errors) > 0) {
            throw new Exception("There was an error importing ".$path." Data Dictionary");
        }
    }

    function dataDictionaryCSVToMetadataArray($csvFilePath, $returnType = null)
    {
        $dd_column_var = array("0" => "field_name", "1" => "form_name","2" => "section_header", "3" => "field_type",
            "4" => "field_label", "5" => "select_choices_or_calculations","6" => "field_note", "7" => "text_validation_type_or_show_slider_number",
            "8" => "text_validation_min", "9" => "text_validation_max","10" => "identifier", "11" => "branching_logic",
            "12" => "required_field", "13" => "custom_alignment","14" => "question_number", "15" => "matrix_group_name",
            "16" => "matrix_ranking", "17" => "field_annotation"
        );

        // Set up array to switch out Excel column letters
        $cols = \MetaData::getCsvColNames();

        // Extract data from CSV file and rearrange it in a temp array
        $newdata_temp = array();
        $i = 1;

        // Set commas as default delimiter (if can't find comma, it will revert to tab delimited)
        $delimiter 	  = ",";
        $removeQuotes = false;

        if (($handle = fopen($csvFilePath, "rb")) !== false)
        {
            // Loop through each row
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false)
            {
                // Skip row 1
                if ($i == 1)
                {
                    ## CHECK DELIMITER
                    // Determine if comma- or tab-delimited (if can't find comma, it will revert to tab delimited)
                    $firstLine = implode(",", $row);
                    // If we find X number of tab characters, then we can safely assume the file is tab delimited
                    $numTabs = 6;
                    if (substr_count($firstLine, "\t") > $numTabs)
                    {
                        // Set new delimiter
                        $delimiter = "\t";
                        // Fix the $row array with new delimiter
                        $row = explode($delimiter, $firstLine);
                        // Check if quotes need to be replaced (added via CSV convention) by checking for quotes in the first line
                        // If quotes exist in the first line, then remove surrounding quotes and convert double double quotes with just a double quote
                        $removeQuotes = (substr_count($firstLine, '"') > 0);
                    }
                    // Increment counter
                    $i++;
                    // Check if legacy column Field Units exists. If so, tell user to remove it (by returning false).
                    // It is no longer supported but old values defined prior to 4.0 will be preserved.
                    if (strpos(strtolower($row[2]), "units") !== false)
                    {
                        return false;
                    }
                    continue;
                }
                if($returnType == null){
                    // Loop through each row and create array
                    $json_aux = array();
                    foreach ($row as $key => $value){
                        $json_aux[$dd_column_var[$key]] = $value;
                    }
                    $newdata_temp[$json_aux['field_name']] = $json_aux;
                }else if($returnType == 'array'){
                    // Loop through each column in this row
                    for ($j = 0; $j < count($row); $j++) {
                        // If tab delimited, compensate sightly
                        if ($delimiter == "\t") {
                            // Replace characters
                            $row[$j] = str_replace("\0", "", $row[$j]);
                            // If first column, remove new line character from beginning
                            if ($j == 0) {
                                $row[$j] = str_replace("\n", "", ($row[$j]));
                            }
                            // If the string is UTF-8, force convert it to UTF-8 anyway, which will fix some of the characters
                            if (function_exists('mb_detect_encoding') && mb_detect_encoding($row[$j]) == "UTF-8") {
                                $row[$j] = utf8_encode($row[$j]);
                            }
                            // Check if any double quotes need to be removed due to CSV convention
                            if ($removeQuotes) {
                                // Remove surrounding quotes, if exist
                                if (substr($row[$j], 0, 1) == '"' && substr($row[$j], -1) == '"') {
                                    $row[$j] = substr($row[$j], 1, -1);
                                }
                                // Remove any double double quotes
                                $row[$j] = str_replace("\"\"", "\"", $row[$j]);
                            }
                        }
                        // Add to array
                        $newdata_temp[$cols[$j + 1]][$i] = $row[$j];
                    }
                }
                $i++;
            }
            fclose($handle);
        } else {
            // ERROR: File is missing
            throw new Exception("ERROR. File is missing!");
        }

        // If file was tab delimited, then check if it left an empty row on the end (typically happens)
        if ($delimiter == "\t" && $newdata_temp['A'][$i-1] == "")
        {
            // Remove the last row from each column
            foreach (array_keys($newdata_temp) as $this_col)
            {
                unset($newdata_temp[$this_col][$i-1]);
            }
        }

        // Return array with data dictionary values
        return $newdata_temp;

    }

    // Save metadata when in DD array format
    private function saveMetadataCSV($dictionary_array, $project_id, $appendFields=false, $preventLogging=false)
    {
        $status = 0;
        $Proj = new \Project($project_id);

        // If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
        $metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

        // DEV ONLY: Only run the following actions (change rights level, events designation) if in Development
        if ($status < 1)
        {
            // If new forms are being added, give all users "read-write" access to this new form
            $existing_form_names = array();
            if (!$appendFields) {
                $results = $this->query("select distinct form_name from ".$metadata_table." where project_id = ?",[$project_id]);
                while ($row = $results->fetch_assoc()) {
                    $existing_form_names[] = $row['form_name'];
                }
            }
            $newforms = array();
            foreach (array_unique($dictionary_array['B']) as $new_form) {
                if (!in_array($new_form, $existing_form_names)) {
                    //Add rights for EVERY user for this new form
                    $newforms[] = $new_form;
                    //Add all new forms to redcap_events_forms table
                    $this->query("insert into redcap_events_forms (event_id, form_name) select m.event_id, ?
                                                              from redcap_events_arms a, redcap_events_metadata m
                                                              where a.project_id = ? and a.arm_id = m.arm_id",[$new_form,$project_id]);

                }
            }
            if(!empty($newforms)){
                //Add new forms to rights table
                $data_entry = "[".implode(",1][",$newforms).",1]";
                $this->query("update redcap_user_rights set data_entry = concat(data_entry,?) where project_id = ? ",[$data_entry,$project_id]);
            }

            //Also delete form-level user rights for any forms deleted (as clean-up)
            if (!$appendFields) {
                foreach (array_diff($existing_form_names, array_unique($dictionary_array['B'])) as $deleted_form) {
                    //Loop through all 3 data_entry rights level states to catch all instances
                    for ($i = 0; $i <= 2; $i++) {
                        $deleted_form_sql = '['.$deleted_form.','.$i.']';
                        $this->query("update redcap_user_rights set data_entry = replace(data_entry,?,'') where project_id = ? ",[$deleted_form_sql,$project_id]);
                    }
                    //Delete all instances in redcap_events_forms
                    $this->query("delete from redcap_events_forms where event_id in
							(select m.event_id from redcap_events_arms a, redcap_events_metadata m, redcap_projects p where a.arm_id = m.arm_id
							and p.project_id = a.project_id and p.project_id = ?) and form_name = ?",[$project_id,$deleted_form]);
                }
            }

            ## CHANGE FOR MULTIPLE SURVEYS????? (Should we ALWAYS assume that if first form is a survey that we should preserve first form as survey?)
            // If using first form as survey and form is renamed in DD, then change form_name in redcap_surveys table to the new form name
            if (!$appendFields && isset($Proj->forms[$Proj->firstForm]['survey_id']))
            {
                $columnB = $dictionary_array['B'];
                $newFirstForm = array_shift(array_unique($columnB));
                unset($columnB);
                // Do not rename in table if the new first form is ALSO a survey (assuming it even exists)
                if ($newFirstForm != '' && $Proj->firstForm != $newFirstForm && !isset($Proj->forms[$newFirstForm]['survey_id']))
                {
                    // Change form_name of survey to the new first form name
                    $this->query("update redcap_surveys set form_name = ? where survey_id = ?",[$newFirstForm,$Proj->forms[$Proj->firstForm]['survey_id']]);
                }
            }
        }

        // Build array of existing form names and their menu names to try and preserve any existing menu names
        $q = $this->query("select form_name, form_menu_description from $metadata_table where project_id = ? and form_menu_description is not null",[$project_id]);
        $existing_form_menus = array();
        while ($row = $q->fetch_assoc()) {
            $existing_form_menus[$row['form_name']] = $row['form_menu_description'];
        }

        // Before wiping out current metadata, obtain values in table not contained in data dictionary to preserve during carryover (e.g., edoc_id)
        $q = $this->query("select field_name, edoc_id, edoc_display_img, stop_actions, field_units, video_url, video_display_inline
				from $metadata_table where project_id = ?
				and (edoc_id is not null or stop_actions is not null or field_units is not null or video_url is not null)",[$project_id]);
        $extra_values = array();
        while ($row = $q->fetch_assoc())
        {
            if (!empty($row['edoc_id'])) {
                // Preserve edoc values
                $extra_values[$row['field_name']]['edoc_id'] = $row['edoc_id'];
                $extra_values[$row['field_name']]['edoc_display_img'] = $row['edoc_display_img'];
            }
            if ($row['stop_actions'] != "") {
                // Preserve stop_actions value
                $extra_values[$row['field_name']]['stop_actions'] = $row['stop_actions'];
            }
            if ($row['field_units'] != "") {
                // Preserve field_units value (no longer included in data dictionary but will be preserved if defined before 4.0)
                $extra_values[$row['field_name']]['field_units'] = $row['field_units'];
            }
            if ($row['video_url'] != "") {
                // Preserve video_url value
                $extra_values[$row['field_name']]['video_url'] = $row['video_url'];
                $extra_values[$row['field_name']]['video_display_inline'] = $row['video_display_inline'];
            }
        }

        // Determine if we need to replace ALL fields or append to existing fields
        if ($appendFields) {
            // Only append new fields to existing metadata (as opposed to replacing them all)
            $q = $this->query("select max(field_order)+1 from $metadata_table where project_id = ?",[$project_id]);
            $field_order = $q;
        } else {
            // Default field order value
            $field_order = 1;
            // Delete all instances of metadata for this project to clean out before adding new
            $this->query("delete from $metadata_table where project_id = ?", [$project_id]);
        }

        // Capture any SQL errors
        $sql_errors = array();
        // Create array to keep track of form names for building form_menu_description logic
        $form_names = array();
        // Set up exchange values for replacing legacy back-end values
        $convertValType = array("integer"=>"int", "number"=>"float");
        $convertFldType = array("notes"=>"textarea", "dropdown"=>"select", "drop-down"=>"select");

        // Loop through data dictionary array and save into metadata table
        foreach (array_keys($dictionary_array['A']) as $i)
        {
            // If this is the first field of a form, generate form menu description for upcoming form
            // If form menu description already exists, it may have been customized, so keep old value
            $form_menu = "";
            if (!in_array($dictionary_array['B'][$i], $form_names)) {
                if (isset($existing_form_menus[$dictionary_array['B'][$i]])) {
                    // Use existing value if form existed previously
                    $form_menu = $existing_form_menus[$dictionary_array['B'][$i]];
                } else {
                    // Create menu name on the fly
                    $form_menu = ucwords(str_replace("_", " ", $dictionary_array['B'][$i]));
                }
            }
            // Deal with hard/soft validation checktype for text fields
            $valchecktype = ($dictionary_array['D'][$i] == "text") ? "'soft_typed'" : "NULL";
            // Swap out Identifier "y" with "1"
            $dictionary_array['K'][$i] = (strtolower(trim($dictionary_array['K'][$i])) == "y") ? "'1'" : "NULL";
            // Swap out Required Field "y" with "1"	(else "0")
            $dictionary_array['M'][$i] = (strtolower(trim($dictionary_array['M'][$i])) == "y") ? "'1'" : "'0'";
            // Format multiple choices
            if ($dictionary_array['F'][$i] != "" && $dictionary_array['D'][$i] != "calc" && $dictionary_array['D'][$i] != "slider" && $dictionary_array['D'][$i] != "sql") {
                $dictionary_array['F'][$i] = str_replace(array("|","\n"), array("\\n"," \\n "), $dictionary_array['F'][$i]);
            }
            // Do replacement of front-end values with back-end equivalents
            if (isset($convertFldType[$dictionary_array['D'][$i]])) {
                $dictionary_array['D'][$i] = $convertFldType[$dictionary_array['D'][$i]];
            }
            if ($dictionary_array['H'][$i] != "" && $dictionary_array['D'][$i] != "slider") {
                // Replace with legacy/back-end values
                if (isset($convertValType[$dictionary_array['H'][$i]])) {
                    $dictionary_array['H'][$i] = $convertValType[$dictionary_array['H'][$i]];
                }
            } elseif ($dictionary_array['D'][$i] == "slider" && $dictionary_array['H'][$i] != "" && $dictionary_array['H'][$i] != "number") {
                // Ensure sliders only have validation type of "" or "number" (to display number value or not)
                $dictionary_array['H'][$i] = "";
            }
            // Make sure question_num is 10 characters or less
            if (strlen($dictionary_array['O'][$i]) > 10) $dictionary_array['O'][$i] = substr($dictionary_array['O'][$i], 0, 10);
            // Swap out Matrix Rank "y" with "1" (else "0")
            $dictionary_array['Q'][$i] = (strtolower(trim($dictionary_array['Q'][$i])) == "y") ? "'1'" : "'0'";
            // Remove any hex'ed double-CR characters in field labels, etc.
            $dictionary_array['E'][$i] = str_replace("\x0d\x0d", "\n\n", $dictionary_array['E'][$i]);
            $dictionary_array['C'][$i] = str_replace("\x0d\x0d", "\n\n", $dictionary_array['C'][$i]);
            $dictionary_array['F'][$i] = str_replace("\x0d\x0d", "\n\n", $dictionary_array['F'][$i]);
            // Insert edoc_id and slider display values that should be preserved
            $edoc_id 		  = isset($extra_values[$dictionary_array['A'][$i]]['edoc_id']) ? $extra_values[$dictionary_array['A'][$i]]['edoc_id'] : NULL;
            $edoc_display_img = isset($extra_values[$dictionary_array['A'][$i]]['edoc_display_img']) ? $extra_values[$dictionary_array['A'][$i]]['edoc_display_img'] : "0";
            $stop_actions 	  = isset($extra_values[$dictionary_array['A'][$i]]['stop_actions']) ? $extra_values[$dictionary_array['A'][$i]]['stop_actions'] : "";
            $field_units	  = isset($extra_values[$dictionary_array['A'][$i]]['field_units']) ? $extra_values[$dictionary_array['A'][$i]]['field_units'] : "";
            $video_url	  	  = isset($extra_values[$dictionary_array['A'][$i]]['video_url']) ? $extra_values[$dictionary_array['A'][$i]]['video_url'] : "";
            $video_display_inline = isset($extra_values[$dictionary_array['A'][$i]]['video_display_inline']) ? $extra_values[$dictionary_array['A'][$i]]['video_display_inline'] : "0";

            $sql = "insert into $metadata_table (project_id, field_name, form_name, field_units, element_preceding_header, "
                . "element_type, element_label, element_enum, element_note, element_validation_type, element_validation_min, "
                . "element_validation_max, field_phi, branching_logic, element_validation_checktype, form_menu_description, "
                . "field_order, field_req, edoc_id, edoc_display_img, custom_alignment, stop_actions, question_num, "
                . "grid_name, grid_rank, misc, video_url, video_display_inline) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

            $q = $this->query($sql,
                [
                    $project_id,
                    $this->checkNull($dictionary_array['A'][$i]),
                    $this->checkNull($dictionary_array['B'][$i]),
                    $this->checkNull($field_units),
                    $this->checkNull($dictionary_array['C'][$i]),
                    $this->checkNull($dictionary_array['D'][$i]),
                    $this->checkNull($dictionary_array['E'][$i]),
                    $this->checkNull($dictionary_array['F'][$i]),
                    $this->checkNull($dictionary_array['G'][$i]),
                    $this->checkNull($dictionary_array['H'][$i]),
                    $this->checkNull($dictionary_array['I'][$i]),
                    $this->checkNull($dictionary_array['J'][$i]),
                    $dictionary_array['K'][$i],
                    $this->checkNull($dictionary_array['L'][$i]),
                    $valchecktype,
                    $this->checkNull($form_menu),
                    $field_order,
                    $dictionary_array['M'][$i],
                    $edoc_id,
                    $edoc_display_img,
                    $this->checkNull($dictionary_array['N'][$i]),
                    $this->checkNull($stop_actions),
                    $this->checkNull($dictionary_array['O'][$i]),
                    $this->checkNull($dictionary_array['P'][$i]),
                    $dictionary_array['Q'][$i],
                    $this->checkNull(isset($dictionary_array['R']) ? $dictionary_array['R'][$i] : null),
                    $this->checkNull($video_url),
                    "'".$video_display_inline."'"
                ]
            );
            //Insert into table
            if ($q) {
                // Increment field order
                $field_order++;
            } else {
                //Log this error
                $sql_errors[] = $sql;
            }


            //Add Form Status field if we're on the last field of a form
            if (isset($dictionary_array['B'][$i]) && $dictionary_array['B'][$i] != $dictionary_array['B'][$i+1]) {
                $form_name = $dictionary_array['B'][$i];
                $q = $this->insertFormStatusField($metadata_table, $project_id, $form_name, $field_order);
                //Insert into table
                if ($q) {
                    // Increment field order
                    $field_order++;
                } else {
                    //Log this error
                    // $sql_errors[] = $sql;
                }
            }

            //Add form name to array for later checking for form_menu_description
            $form_names[] = $dictionary_array['B'][$i];

        }

        // Logging
        if (!$appendFields && !$preventLogging) {
            \Logging::logEvent("",$metadata_table,"MANAGE",$project_id,"project_id = ".$project_id,"Upload data dictionary");
        }
        // Return any SQL errors
        return $sql_errors;
    }

    private function isDataDictionaryFormatCorrect($metadata){
        if(empty($metadata)){
            throw new Exception("The Metadata is empty");
        }else{
            $dd_column_var = array("0" => "field_name", "1" => "form_name","2" => "section_header", "3" => "field_type",
                "4" => "field_label", "5" => "select_choices_or_calculations","6" => "field_note", "7" => "text_validation_type_or_show_slider_number",
                "8" => "text_validation_min", "9" => "text_validation_max","10" => "identifier", "11" => "branching_logic",
                "12" => "required_field", "13" => "custom_alignment","14" => "question_number", "15" => "matrix_group_name",
                "16" => "matrix_ranking", "17" => "field_annotation"
            );
            foreach ($metadata as $dd){
                foreach ($dd as $dd_field => $value){
                    $found = false;
                    foreach ($dd_column_var as $field){
                        if($field == $dd_field){
                            $found = true;
                        }
                    }
                    if(!$found){
                        throw new Exception("Some fields are missing in the Metadata.");
                    }
                }
            }
        }
        return true;
    }

    function saveMetadata($project_id, $metadata, $preventLogging=false) {
	    if($this->isDataDictionaryFormatCorrect($metadata)){
            $status = 0;
            $Proj = new \Project($project_id);

            // If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
            $metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

            // DEV ONLY: Only run the following actions (change rights level, events designation) if in Development
            if ($status < 1)
            {
                // If new forms are being added, give all users "read-write" access to this new form
                $existing_form_names = array();
                $results = $this->query("select distinct form_name from " . $metadata_table . " where project_id = ?", [$project_id]);
                while ($row = $results->fetch_assoc()) {
                    $existing_form_names[] = $row['form_name'];
                }

                $newforms = array();
                $allforms = array();
                foreach ($metadata as $dd) {
                    array_push($allforms,$dd['form_name']);
                    if (!in_array($dd['form_name'], $existing_form_names)) {
                        //Add rights for EVERY user for this new form
                        $newforms[] = $dd['form_name'];
                        //Add all new forms to redcap_events_forms table
                        $this->query("insert into redcap_events_forms (event_id, form_name) select m.event_id, ?
                                                                  from redcap_events_arms a, redcap_events_metadata m
                                                                  where a.project_id = ? and a.arm_id = m.arm_id",[$dd['form_name'],$project_id]);

                    }
                }
                if(!empty($newforms)){
                    //Add new forms to rights table
                    $data_entry = "[".implode(",1][",$newforms).",1]";
                    $this->query("update redcap_user_rights set data_entry = concat(data_entry,?) where project_id = ? ",[$data_entry,$project_id]);
                }

                //Also delete form-level user rights for any forms deleted (as clean-up)
                foreach (array_diff($existing_form_names, array_unique($allforms)) as $deleted_form) {
                    //Loop through all 3 data_entry rights level states to catch all instances
                    for ($i = 0; $i <= 2; $i++) {
                        $deleted_form_sql = '['.$deleted_form.','.$i.']';
                        $this->query("update redcap_user_rights set data_entry = replace(data_entry,?,'') where project_id = ? ",[$deleted_form_sql,$project_id]);
                    }
                    //Delete all instances in redcap_events_forms
                    $this->query("delete from redcap_events_forms where event_id in
                            (select m.event_id from redcap_events_arms a, redcap_events_metadata m, redcap_projects p where a.arm_id = m.arm_id
                            and p.project_id = a.project_id and p.project_id = ?) and form_name = ?",[$project_id,$deleted_form]);
                }

                ## CHANGE FOR MULTIPLE SURVEYS????? (Should we ALWAYS assume that if first form is a survey that we should preserve first form as survey?)
                // If using first form as survey and form is renamed in DD, then change form_name in redcap_surveys table to the new form name
                if (isset($Proj->forms[$Proj->firstForm]['survey_id']))
                {
                    $columnB = $allforms;
                    $newFirstForm = array_shift(array_unique($columnB));
                    unset($columnB);
                    // Do not rename in table if the new first form is ALSO a survey (assuming it even exists)
                    if ($newFirstForm != '' && $Proj->firstForm != $newFirstForm && !isset($Proj->forms[$newFirstForm]['survey_id']))
                    {
                        // Change form_name of survey to the new first form name
                        $this->query("update redcap_surveys set form_name = ? where survey_id = ?",[$newFirstForm,$Proj->forms[$Proj->firstForm]['survey_id']]);
                    }
                }
            }

            // Build array of existing form names and their menu names to try and preserve any existing menu names
            $q = $this->query("select form_name, form_menu_description from $metadata_table where project_id = ? and form_menu_description is not null",[$project_id]);
            $existing_form_menus = array();
            while ($row = $q->fetch_assoc()) {
                $existing_form_menus[$row['form_name']] = $row['form_menu_description'];
            }

            // Before wiping out current metadata, obtain values in table not contained in data dictionary to preserve during carryover (e.g., edoc_id)
            $q = $this->query("select field_name, edoc_id, edoc_display_img, stop_actions, field_units, video_url, video_display_inline
                    from $metadata_table where project_id = ?
                    and (edoc_id is not null or stop_actions is not null or field_units is not null or video_url is not null)",[$project_id]);
            $extra_values = array();
            while ($row = $q->fetch_assoc())
            {
                if (!empty($row['edoc_id'])) {
                    // Preserve edoc values
                    $extra_values[$row['field_name']]['edoc_id'] = $row['edoc_id'];
                    $extra_values[$row['field_name']]['edoc_display_img'] = $row['edoc_display_img'];
                }
                if ($row['stop_actions'] != "") {
                    // Preserve stop_actions value
                    $extra_values[$row['field_name']]['stop_actions'] = $row['stop_actions'];
                }
                if ($row['field_units'] != "") {
                    // Preserve field_units value (no longer included in data dictionary but will be preserved if defined before 4.0)
                    $extra_values[$row['field_name']]['field_units'] = $row['field_units'];
                }
                if ($row['video_url'] != "") {
                    // Preserve video_url value
                    $extra_values[$row['field_name']]['video_url'] = $row['video_url'];
                    $extra_values[$row['field_name']]['video_display_inline'] = $row['video_display_inline'];
                }
            }

            // Replace ALL fields
            // Default field order value
            $field_order = 1;
            // Delete all instances of metadata for this project to clean out before adding new
            $this->query("delete from $metadata_table where project_id = ?", [$project_id]);

            // Capture any SQL errors
            $sql_errors = array();
            // Create array to keep track of form names for building form_menu_description logic
            $form_names = array();
            // Set up exchange values for replacing legacy back-end values
            $convertValType = array("integer"=>"int", "number"=>"float");
            $convertFldType = array("notes"=>"textarea", "dropdown"=>"select", "drop-down"=>"select");

            // Loop through data dictionary array and save into metadata table
            foreach ($metadata as $i => $metadata){
                // If this is the first field of a form, generate form menu description for upcoming form
                // If form menu description already exists, it may have been customized, so keep old value
                $form_menu = "";
                if (!in_array($metadata['form_name'], $form_names)) {
                    if (isset($existing_form_menus[$metadata['form_name']])) {
                        // Use existing value if form existed previously
                        $form_menu = $existing_form_menus[$metadata['form_name']];
                    } else {
                        // Create menu name on the fly
                        $form_menu = ucwords(str_replace("_", " ", $metadata['form_name']));
                    }
                }
                // Deal with hard/soft validation checktype for text fields
                $valchecktype = ($metadata[$i]['field_type'] == "text") ? "'soft_typed'" : "NULL";
                // Swap out Identifier "y" with "1"
                $metadata[$i]['identifier'] = (strtolower(trim($metadata[$i]['identifier'])) == "y") ? "'1'" : "NULL";
                // Swap out Required Field "y" with "1"	(else "0")
                $metadata[$i]['required_field'] = (strtolower(trim($metadata[$i]['required_field'])) == "y") ? "'1'" : "'0'";
                // Format multiple choices
                if ($metadata[$i]['select_choices_or_calculations'] != "" && $metadata[$i]['field_type'] != "calc" && $metadata[$i]['field_type'] != "slider" && $metadata[$i]['field_type'] != "sql") {
                    $metadata[$i]['select_choices_or_calculations'] = str_replace(array("|","\n"), array("\\n"," \\n "), $metadata[$i]['select_choices_or_calculations']);
                }
                // Do replacement of front-end values with back-end equivalents
                if (isset($convertFldType[$metadata[$i]['field_type']])) {
                    $metadata[$i]['field_type'] = $convertFldType[$metadata[$i]['field_type']];
                }
                if ($metadata[$i]['text_validation_type_or_show_slider_number'] != "" && $metadata[$i]['field_type'] != "slider") {
                    // Replace with legacy/back-end values
                    if (isset($convertValType[$metadata[$i]['text_validation_type_or_show_slider_number']])) {
                        $metadata[$i]['text_validation_type_or_show_slider_number'] = $convertValType[$metadata[$i]['text_validation_type_or_show_slider_number']];
                    }
                } elseif ($metadata[$i]['field_type'] == "slider" && $metadata[$i]['text_validation_type_or_show_slider_number'] != "" && $metadata[$i]['text_validation_type_or_show_slider_number'] != "number") {
                    // Ensure sliders only have validation type of "" or "number" (to display number value or not)
                    $metadata[$i]['text_validation_type_or_show_slider_number'] = "";
                }
                // Make sure question_num is 10 characters or less
                if (strlen($metadata[$i]['question_number']) > 10) $metadata[$i]['question_number'] = substr($metadata[$i]['question_number'], 0, 10);
                // Swap out Matrix Rank "y" with "1" (else "0")
                $metadata[$i]['matrix_ranking'] = (strtolower(trim($metadata[$i]['matrix_ranking'])) == "y") ? "'1'" : "'0'";
                // Remove any hex'ed double-CR characters in field labels, etc.
                $metadata[$i]['field_label'] = str_replace("\x0d\x0d", "\n\n", $metadata[$i]['field_label']);
                $metadata[$i]['section_header'] = str_replace("\x0d\x0d", "\n\n", $metadata[$i]['section_header']);
                $metadata[$i]['select_choices_or_calculations'] = str_replace("\x0d\x0d", "\n\n", $metadata[$i]['select_choices_or_calculations']);
                // Insert edoc_id and slider display values that should be preserved
                $edoc_id 		  = isset($extra_values[$metadata[$i]['field_name']]['edoc_id']) ? $extra_values[$metadata[$i]['field_name']]['edoc_id'] : NULL;
                $edoc_display_img = isset($extra_values[$metadata[$i]['field_name']]['edoc_display_img']) ? $extra_values[$metadata[$i]['field_name']]['edoc_display_img'] : "0";
                $stop_actions 	  = isset($extra_values[$metadata[$i]['field_name']]['stop_actions']) ? $extra_values[$metadata[$i]['field_name']]['stop_actions'] : "";
                $field_units	  = isset($extra_values[$metadata[$i]['field_name']]['field_units']) ? $extra_values[$metadata[$i]['field_name']]['field_units'] : "";
                $video_url	  	  = isset($extra_values[$metadata[$i]['field_name']]['video_url']) ? $extra_values[$metadata[$i]['field_name']]['video_url'] : "";
                $video_display_inline = isset($extra_values[$metadata[$i]['field_name']]['video_display_inline']) ? $extra_values[$metadata[$i]['field_name']]['video_display_inline'] : "0";

                $sql = "insert into $metadata_table (project_id, field_name, form_name, field_units, element_preceding_header, "
                    . "element_type, element_label, element_enum, element_note, element_validation_type, element_validation_min, "
                    . "element_validation_max, field_phi, branching_logic, element_validation_checktype, form_menu_description, "
                    . "field_order, field_req, edoc_id, edoc_display_img, custom_alignment, stop_actions, question_num, "
                    . "grid_name, grid_rank, misc, video_url, video_display_inline) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

                $q = $this->query($sql,
                    [
                        $project_id,
                        $this->checkNull($metadata[$i]['field_name']),
                        $this->checkNull($metadata[$i]['form_name']),
                        $this->checkNull($field_units),
                        $this->checkNull($metadata[$i]['section_header']),
                        $this->checkNull($metadata[$i]['field_type']),
                        $this->checkNull($metadata[$i]['field_label']),
                        $this->checkNull($metadata[$i]['select_choices_or_calculations']),
                        $this->checkNull($metadata[$i]['field_note']),
                        $this->checkNull($metadata[$i]['text_validation_type_or_show_slider_number']),
                        $this->checkNull($metadata[$i]['text_validation_min']),
                        $this->checkNull($metadata[$i]['text_validation_max']),
                        $metadata[$i]['identifier'],
                        $this->checkNull($metadata[$i]['branching_logic']),
                        $valchecktype,
                        $this->checkNull($form_menu),
                        $field_order,
                        $metadata[$i]['required_field'],
                        $edoc_id,
                        $edoc_display_img,
                        $this->checkNull($metadata[$i]['custom_alignment']),
                        $this->checkNull($stop_actions),
                        $this->checkNull($metadata[$i]['question_number']),
                        $this->checkNull($metadata[$i]['matrix_group_name']),
                        $metadata[$i]['matrix_ranking'],
                        $this->checkNull(isset($metadata[$i]['field_annotation']) ? $metadata[$i]['field_annotation'] : null),
                        $this->checkNull($video_url),
                        "'".$video_display_inline."'"
                    ]
                );
                //Insert into table
                if ($q) {
                    // Increment field order
                    $field_order++;
                } else {
                    //Log this error
                    $sql_errors[] = $sql;
                }


                //Add Form Status field if we're on the last field of a form
                if (isset($metadata[$i]['form_name']) && $metadata[$i]['form_name'] != $metadata[$i+1]['form_name']) {
                    $form_name = $metadata[$i]['form_name'];
                    $q = $this->insertFormStatusField($metadata_table, $project_id, $form_name, $field_order);
                    //Insert into table
                    if ($q) {
                        // Increment field order
                        $field_order++;
                    } else {
                        //Log this error
                        // $sql_errors[] = $sql;
                    }
                }

                //Add form name to array for later checking for form_menu_description
                $form_names[] = $metadata[$i]['form_name'];

            }

            // Logging
            if (!$preventLogging) {
                \Logging::logEvent("",$metadata_table,"MANAGE",$project_id,"project_id = ".$project_id,"Upload data dictionary");
            }
            // Return any SQL errors
            return $sql_errors;
	    }
    }

    private function insertFormStatusField($metadata_table, $project_id, $form_name, $field_order){
        return $this->query("insert into $metadata_table (project_id, field_name, form_name, field_order, element_type, "
        . "element_label, element_enum, element_preceding_header) values (?,?,?,?,?,?,?,?)"
        ,[$project_id,$form_name . "_complete",$form_name,$field_order,'select', 'Complete?', '0, Incomplete \\n 1, Unverified \\n 2, Complete', 'Form Status']);
    }

    /*
	** Give null value if equals "" (used inside queries)
	*/
    private function checkNull($value) {
        if ($value === "" || $value === null || $value === false) {
            return NULL;
        }
        return $value;
    }

    public function countLogs($whereClause, $parameters){
        $result = $this->queryLogs("select count(*) where $whereClause", $parameters);
        $row = $result->fetch_row();
        return $row[0];
    }

    // Pass through for any method was added in framework version 5 or greater.
    // We initially allowed pass through for any method without a framework version, but this caused problems when deploying modules using
    // the new/shorter syntax to older REDCap systems that didn't support it.
    // Requiring framework version 5 ensures that module authors write code compatible with older REDCap versions if their module is on an
    // older framework version (even if their REDCap instance supports the new syntax).
    public function isSafeToForwardMethodToFramework($name){
        if(!method_exists($this, $name)){
            return false;
        }

        if(
            $this->VERSION >= 5
            ||
            method_exists($this->getModuleInstance(), $name) // For methods that have always been accessible from the module object prior to v5.
        ){
            return true;
        }

        /**
         * These method have always been callable from AbstractExternalModule::__call(),
         * even though they never existed on that class as explicit methods.
         * They exist here rather than as method stubs in AbstractExternalModule
         * to prevent potential conflicts with existing modules.
         */
        return in_array($name, [
            'tt',
            'tt_transferToJavascriptModuleObject',
            'tt_addToJavascriptModuleObject',
        ]);
    }

    function enableModule($pid, $prefix = null){
        if(empty($pid)){
            // TODO - tt
            throw new Exception("A project ID must be specified on which to enable the module.");
        }
        if($prefix === null){
            $prefix = $this->module->PREFIX;
        }
        $version = ExternalModules::getEnabledVersion($prefix);
        ExternalModules::enableForProject($prefix, $version, $pid);
    }


    /**
     * Checks whether a project id is valid.
     * 
     * Additional conditions can be via a second argument:
     *  - TRUE: The project must actually exist (with any status).
     *  - "DEV": The project must be in development mode.
     *  - "PROD": The project must be in production mode.
     *  - "AC": The project must be in analysis/cleanup mode.
     *  - "DONE": The project must be completed.
     *  - An array containing any of the states listed, e.g. ["DEV","PROD"]
     * 
     * @param string|int $pid The project id to check.
     * @param bool|string|array $condition Performs additional checks depending on the value (default: false).
     * @return bool True/False depending on whether the project id is valid or not.
     */
    public function isValidProjectId($pid, $condition = false) {
        return ExternalModules::isValidProjectId($pid, $condition);
    }

	/**
    * Checks whether a module is enabled for a project or on the system.
    *
    * @param string $prefix A unique module prefix.
    * @param string $projectId A project id (optional).
    * @return mixed False if the module is not enabled, otherwise the enabled version of the module (string).
    * @throws InvalidArgumentException
    **/
    public function isModuleEnabled($prefix, $pid = null) {
        if (empty($prefix)) {
            throw new InvalidArgumentException(ExternalModules::tt("em_errors_50")); //= You must specify a prefix!
        }
        if ($pid !== null && !ExternalModules::isValidProjectId($pid)) {
            throw new InvalidArgumentException(ExternalModules::tt("em_errors_131")); //= Invalid value for project id!
        }
        $enabled = ExternalModules::getEnabledModules($pid);
        return array_key_exists($prefix, $enabled) ? $enabled[$prefix] : false;
    }

    /**
    * Gets a list of enabled modules for a project or on the system.
    *
    * @param string $pid A project id (optional).
    * @return array An associative array listing the enabled modules, with module prefix as key and version as value.
    * @throws InvalidArgumentException
    **/
    public function getEnabledModules($pid = null) {
        if ($pid !== null && !ExternalModules::isValidProjectId($pid)) {
            throw new InvalidArgumentException(ExternalModules::tt("em_errors_131")); //= Invalid value for project id!
        }
        return ExternalModules::getEnabledModules($pid);
    }

    /**
     * Gets the status of the current or given project.
     * 
     * Status can be one of the following:
     * - DEV: Development mode
     * - PROD: Production mode
     * - AC: Analysis/Cleanup mode
     * - DONE: Completed
     * 
     * @param int|string|null $pid The project id (when omitted, the project id is determined from context).
     * @return string|null The status of the project (or NULL in case the project does not exist).
     */
    public function getProjectStatus($pid = null) {
        $pid = $this->requireProjectId($pid);
        return ExternalModules::getProjectStatus($pid);
    }

    public function getModuleInstance(){
        return $this->module;
    }
    
    public function getFieldNames($formName, $pid = null){
        if(!$pid){
            $pid = $this->requireProjectId();
        }

        return array_keys(\REDCap::getDataDictionary($pid, 'array', false, null, $formName));
    }

    function addOrUpdateInstances($newInstances, $uniqueInstanceField){
        return $this->getProject()->addOrUpdateInstances($newInstances, $uniqueInstanceField);
    }

    public function setData($record, $fieldName, $values){
		$pid = self::requireProjectId();
		$eventId = $this->getEventId();

		if(!is_array($values)){
			$values = [$values];
		}

		$beginTransactionVersion = '5.5';
		if($this->isPHPGreaterThan($beginTransactionVersion)){
			$this->query("SET AUTOCOMMIT=0", []);
			$this->query("BEGIN", []);
		}

		$this->query(
			"DELETE FROM redcap_data where project_id = ? and event_id = ? and record = ? and field_name = ?",
			[$pid, $eventId, $record, $fieldName]
		);

		foreach($values as $value){
			$this->query(
				"INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES (?, ?, ?, ?, ?)",
				[$pid, $eventId, $record, $fieldName, $value]
			);
		}

		if($this->isPHPGreaterThan($beginTransactionVersion)) {
			$this->query("COMMIT", []);
			$this->query("SET AUTOCOMMIT=1", []);
		}
    }
    
    private function isPHPGreaterThan($requiredVersion){
		return version_compare(PHP_VERSION, $requiredVersion, '>=');
    }
    
    public function getDAG($recordId){
        $pid = self::requireProjectId();
        $eventId = $this->getEventId();

        // The limit is added simply for faster querying.  There should never be more than one matching result.
        $result = $this->query('select value from redcap_data where project_id = ? and event_id = ? and record = ? and field_name = ? limit 1', [$pid, $eventId, $recordId, '__GROUPID__']);
        $row = $result->fetch_assoc();
        return @$row['value'];
    }

	public function queryLogs($sql, $parameters = null){
        if($parameters === null && $this->VERSION < 6){
            // Allow the parameters argument to be omitted.
            $parameters = [];
        }

		return $this->query($this->getQueryLogsSql($sql), $parameters);
    }
    
    public function removeLogs($sql, $parameters = null){
        if($parameters === null && $this->VERSION < 6){
            // Allow the parameters argument to be omitted.
            $parameters = [];
        }
        
        if(empty($sql)){
			throw new Exception('You must specify a where clause.');
		}

		$select = "select 1";
		$sql = $this->getQueryLogsSql("$select where $sql");
		$sql = substr_replace($sql, 'delete redcap_external_modules_log', 0, strlen($select));

		if(strpos($sql, AbstractExternalModule::EXTERNAL_MODULE_ID_STANDARD_WHERE_CLAUSE_PREFIX) === false) {
			// An external_module_id must have been specified in the where clause, preventing the standard clause from being included.
			// This check also make sure that a bug in the framework doesn't remove logs for all modules (especially important when developing changes to log methods).
			throw new Exception("Specifying an 'external_module_id' in the where clause for removeLogs() is not allowed to prevent modules from accidentally removing logs for other modules.");
		}

		return $this->query($sql, $parameters);
	}
}