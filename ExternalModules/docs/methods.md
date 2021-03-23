## Methods Provided by the External Module Framework

**Some method behavior differs between framework versions.  [Click here](framework/intro.md) for more information on framework versioning in general.**

The following methods are provided by the framework.  Modules that have been updated to framework version `5` or greater can access methods directly on the module object (e.g. `$module->getModuleName()`).  Modules on older framework versions can access methods via the framework object (e.g. `$module->framework->getModuleName()`).  Older methods may also be accessible directly on the module class even in on framework versions (for backward compatibility).  Unless otherwise stated, module methods throw standard PHP exceptions if any errors occur.  Any uncaught exception triggers an email to the REDCap admin address, avoiding the need for any error checking boilerplate in many cases.

Method<br><br>&nbsp; | Minimum<br>REDCap<br>Version | Description<br><br>&nbsp;
--- | --- | --- 
addAutoNumberedRecord([$pid]) | 8.0.0 | Creates the next auto numbered record and returns the record id.  If the optional PID parameter is not specified, the current PID will be automatically detected.
convertIntsToStrings($row) | 9.7.6 | Returns a copy of the specified array with any integer values cast to strings.  This method is intended to aid in converting queries to use parameters with minimal refactoring.
countLogs($whereClause, $parameters) | 9.9.1 | Returns the count of log statements matching the specified where clause.  Example: `countLogs("message = ? and timestamp > ?", [$message, $dateTimeObject])`
createDAG($name) | 8.0.0 | Creates a DAG with the specified name, and returns it's ID.
createPassthruForm(<br>&emsp;$projectId,<br>&emsp;$recordId,<br>&emsp;<br>&emsp;// optional<br>&emsp;$surveyFormName,<br>&emsp;$eventId<br>) | 8.11.8 | Outputs the HTML for opening/continuing the survey submission for the specified record.  If a return code is required, a link is returned.  Otherwise, a self submitting form is returned.
createProject($title, $purpose, [, $projectNote]) | 9.7.6 | Creates a new redcap project and returns the project id.
createQuery() | 9.7.8 | Creates a `Query` object to aid in building complex queries using parameters.  See the [Query Documentation](querying.md) page for more details.
dataDictionaryCSVToMetadataArray($csvFilePath) | 10.3.1 | Given a CSV data dictionary file path, returns that file into a metadata array.
delayModuleExecution() | 8.0.0 | When called within a hook, this method causes the current hook to be "delayed", which means it will be called again after all other enabled modules (that define that hook) have executed.  This allows modules to interact with each other to control their run order.  For example, one module may wait for a second module to set a field value before it finishes executing.  A boolean value of `true` is returned if the hook was successfully delayed, or `false` if the hook cannot be delayed any longer and this is the module's last chance to perform any required actions.  If the delay was successful, hooks normally `return;` immediately after calling this method to stop the current execution of hook.
deleteDAG($dagId) | 8.2.1 | Given a DAG ID, deletes the DAG and all Users and Records assigned to it.
enableModule($pid, $prefix = null) | 9.9.1 | Calling this method enables a certain module on a particular project. If the optional prefix parameter is not given it will activate that module in the project specified.
exitAfterHook() | 8.2.0 | Calling this method inside of a hook will schedule PHP's exit() function to be called after ALL modules finish executing for the current hook.  Do NOT call die() or exit() manually afterward (the framework will call it for you).
getChoiceLabel($params) | 8.0.0 | Given an associative array, get the label associated with the specified choice value for a particular field. See the following example:<br> $params = array('field_name'=>'my_field', 'value'=>3, 'project_id'=>1, 'record_id'=>3, 'event_id'=>18, 'survey_form'=>'my_form', 'instance'=>2);
getChoiceLabels($fieldName[, $pid]) | 8.0.0 | Returns an array mapping all choice values to labels for the specified field.
getConfig() | 8.0.0 | Get the config for the current External Module; consists of config.json and filled-in values
getDAG($recordId) | 10.3.1 | Return the DAG for the given record ID on the current project.
getEnabledModules([$pid]) | 9.9.1 | Returns an array with the modules enabled on the system or for the project with the given project id. The array is of the form "prefix" => "version".
getEventId() | 9.7.6 | Returns the current event ID.  If an 'event_id' GET parameter is specified, it will be returned.  If not, and the project only has a single event, that event's ID will be returned.  If no 'event_id' GET parameter is specified and the project has multiple events, an exception will be thrown.
getFieldLabel($fieldName) | 8.2.3 | Returns the label for the specified field name.
getFieldNames($formName[, $pid]) | 9.10.0 | Returns an array of field names for the specified form.
getJavascriptModuleObjectName() | 8.10.12 | Returns the name of the javascript object for this module.
getModuleDirectoryName() | 8.0.0 | get the directory name of the current external module
getModuleName() | 8.0.0 | get the name of the current external module
getModulePath() | 8.0.0 | Get the path of the current module directory (e.g., /var/html/redcap/modules/votecap_v1.1/)
getProject([$projectId]) | 8.11.10 | Returns a `Project` object for the given project ID, or the current project if no ID is specified.  This `Project` object is documented below.
getProjectId() | 8.7.2 | A convenience method for returning the current project id.
getProjectsWithModuleEnabled() | 8.11.6 | Returns an array of project ids for which the  current module is enabled (especially useful in cron jobs). 
getProjectSetting($key&nbsp;[,&nbsp;$pid]) | 8.0.0 | Returns the value stored for the specified key for the current project if it exists.  For non-repeatable settings, `null` is returned if no value is set.  For repeatable settings, an array with a single `null` value is returned if no value is set.  In most cases the project id can be detected automatically, but it can optionally be specified as a parameter instead.
getProjectSettings([$pid]) | 9.10.0 | Gets all project settings as an array.  Useful for cases when you may be creating a custom config page for the external module in a project. **Breaking change in framework v5.**
getProjectStatus([$pid]) | 9.9.1 | Returns the status of the specified project (project id is inferred if not given). Status can be: "DEV" (development mode), "PROD" (production mode), "AC" (analysis/cleanup mode), "DONE" (completed). In case the project does not exist, NULL is returned.
getPublicSurveyHash($pid=null) | 9.9.1 | Returns the survey hash code for the current project. If a project_id is specified it will return the hash for that specific project. If the hash does not exist it will return null.
getPublicSurveyUrl($pid=null) | 8.2.3 | Returns the public survey url for the current project. If a project_id is specified it will return the link for that specific project. If the link does not exist it will return null.
getQueryLogsSql($sql) | 8.7.3 | Returns the raw SQL that would run if the supplied parameter was passed into **queryLogs()**. 
getRecordId() | 8.7.2 | Returns the current record id if called from within a hook that includes the record id.
getRecordIdField([$pid]) | 9.3.5 | Returns the name of the record ID field. Unlike the same method on the `REDCap` class, this method accepts a `$pid`, and also works outside a project context when a `pid` GET parameter is set.
getRepeatingForms([$eventId, $pid]) | 9.7.6 | Returns an array of repeating form names for the current or specified event & pid.
getSafePath($path[, $root]) | 9.7.6   | Checks a file path to make sure a [path traversal attack](https://www.owasp.org/index.php/Path_Traversal) is not in progress and returns a normalized path similar to PHP's `realpath()` function.  If a path traversal attack is detected, an exception is thrown.  This is very import when generating paths using strings created from user input.  The `$path` can be relative to the `$root`, or include it.  If `$root` is not specified, the module directory is assumed.  The `$root` can be either absolute or relative to the module directory.  A path traversal attack is considered to be in progress if the the `$root` does not contain the `$path`.
getSettingConfig($key) | 8.0.0 | Returns the configuration for the specified setting.
getSettingKeyPrefix() | 8.0.0 | This method can be overridden to prefix all setting keys.  This allows for multiple versions of settings depending on contexts defined by the module.
getSubSettings($key&nbsp;[,&nbsp;$pid]) | 8.0.0 | Returns the sub-settings under the specified key in a user friendly array format.  In most cases the project id can be detected automatically, but it can optionally be specified as a parameter instead.
getUrl($path [, $noAuth=false [, $useApiEndpoint=false]]) | 8.0.0 | Get the url to a resource (php page, js/css file, image etc.) at the specified path relative to the module directory. A `$module` variable representing an instance of your module class will automatically be available in PHP files.  If the $noAuth parameter is set to true, then "&NOAUTH" will be appended to the URL, which disables REDCap's authentication for that PHP page (assuming the link's URL in config.json contains "?NOAUTH"). Also, if you wish to obtain an alternative form of the URL that does not contain the REDCap version directory (e.g., https://example.com/redcap/redcap_vX.X.X/ExternalModules/?prefix=your_module&page=index&pid=33), then set $useApiEndpoint=true, which will return a version-less URL using the API end-point (e.g., https://example.com/redcap/api/?prefix=your_module&page=index&pid=33). Both links will work identically.
getUser([$username]) | 8.11.9 | Returns a `User` object for the given username, or the current user if no username is specified.  This `User` object is documented below.
getUserSetting($key) | 8.3.0 | Returns the value stored for the specified key for the current user and project.  Null is always returned on surveys and NOAUTH pages.
importDataDictionary($projectId,$path) | 9.7.6 | Given a project id and a path, imports a data dictionary CSV file.
initializeJavascriptModuleObject() | 8.7.2 | Returns a JavaScript block that initializes the JavaScript version of the module object (documented below).
isModuleEnabled($prefix [, $pid]) | 9.9.1 | Returns true if the module with the given prefix is enabled on the system (when no project id is supplied) or the given project; or false otherwise.
isPage($path) | 9.7.6 | Returns true if the current page matches the supplied file/dir path.  The path can be any file/dir under the versioned REDCap directory (ex: `Design/online_designer.php`).
isRoute($routeName) | 8.11.10 | Returns true if the 'route' GET/URL parameter matches the specified string.
isSurveyPage() | 8.4.3 | Returns true if the current page is a survey.  This is primarily useful in the **redcap_every_page_before_render** and **redcap_save_record** hooks.
isValidProjectId($pid [, $condition]) | 9.9.1 | Checks whether a project id is valid (under the given conditions) and returns true or false. Condition can be true = the project must exist or any of "DEV", "PROD", "AC" (Analysis/Cleanup), "DONE" (completed) or a combination (given as array, e.g. ["AC", "DONE"]) = the project must be in (any of) the given state(s).
log($message[, $parameters]) | 8.7.2 | Inserts a log entry including a message and optional array of key-value pairs for later retrieval using the **queryLogs()** method, and returns the inserted **log_id**.  Some parameters/columns are stored automatically, even if the **$parameters** argument is omitted (see **queryLogs()** for more details).  Log parameter names are only allowed to contain alphanumeric, space, dash, underscore, or dollar sign characters.
query($sql)<br>query($sql, $parameters) | 8.0.0<br>9.7.8 | Executes a SQL query against the database with automatic error detection and reporting, and returns a [mysqli_result](https://www.php.net/manual/en/class.mysqli-result.php) or compatible result object.  The `$parameters` argument is supported as of REDCap version 9.7.8 and required in framework version 4 to encourage the use of parameterized queries.  There are subtle differences in the way queries behave when using vs. not using parameters (see the [v4 page](framework/v4.md) for details).  If no parameters are required (not common), an empty array can be specified to show that use of parameters was seriously considered.  See the [Query Documentation](querying.md) page for more details on querying the database.
queryLogs($sql [, &nbsp;$parameters]) | 8.7.2 | Queries log entries added via the **log()** method using SQL-like syntax with the "from" portion omitted, and returns a MySQL result resource (just like **mysql_query()**).  The `$parameters` argument behaves the same way as described in the `query()` method documentation.  Queries can include standard "select", "where", "order by", and "group by" clauses.  Available columns include **log_id**, **timestamp**, **user**, **ip**, **project_id**, **record**, **message**, and any parameter name passed to the **log()** method.  All columns must be specified explicitly ("select \*" syntax is not supported).  The raw SQL being executed by this method can be retrieved by calling **getQueryLogsSql()**.  Here are some query examples:*<br><br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;select timestamp, user where message = 'some message'<br><br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;select message, ip<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;where<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;timestamp > '2017-07-07'<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;and user in ('joe', 'tom')<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;or some_parameter like '%abc%'<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;order by timestamp desc*<br><br>If the `external_module_id` or `project_id` columns are not specified in the where clause, queries are limited to the current module and project (if detected) by default.  For complex queries, the log table can be manually queried (this method does not have to be used). 
records->lock($recordIds) | 8.11.6 | Locks all forms/instances for the given record ids.
removeLogs($sql [, &nbsp;$parameters]) | 8.7.2 | Removes log entries matching the current module, current project (if detected), and the specified sql "where" clause.  See the `queryLogs()` method for an example where clause.  The `$parameters` argument behaves the same way as described in the `query()` method documentation.  
removeProjectSetting($key&nbsp;[,&nbsp;$pid]) | 8.0.0 | Remove the value stored for this project and the specified key.  In most cases the project id can be detected automatically, but it can optionaly be specified as a parameter instead. 
removeSystemSetting($key) | 8.0.0 | Removes the value stored systemwide for the specified key.
removeUserSetting($key) | 8.3.0 | Removes the value stored for the specified key for the current user and project.  This method does nothing on surveys and NOAUTH pages.
renameDAG($dagId, $name) | 8.0.0 | Renames the DAG with the given ID to the specified name.
requireInteger($mixed) | 8.11.10 | Throws an exception if the supplied value is not an integer or a string representation of an integer.  Returns the integer equivalent of the given value regardless.
resetSurveyAndGetCodes(<br>&emsp;$projectId, $recordId<br>&emsp;[, $surveyFormName, $eventId]<br>) | 8.11.8 | Resets the survey status so that REDCap will allow the survey to be accessed again (completed surveys can't be edited again without changing the survey settings).  A survey participant and respondent are also created if they doesn't exist.
saveMetadata($pid, $metadata[, $preventLogging=false]) | 10.3.1 | Given a project id and a metadata array, expected format returned by dataDictionaryCSVToMetadataArray(), saves the metadata. The `$preventLogging` argument prevents logging the metadata changes, and by default is false.
setDAG($record, $dagId) | 8.0.0 | Sets the DAG for the given record ID to given DAG ID.
setData($record, $fieldName, $values) | 8.0.0 | Sets the data for the given record and field name to the specified value or array of values.
setProjectSetting($key,&nbsp;$value&nbsp;[,&nbsp;$pid]) | 8.0.0 | Sets the setting specified by the key to the specified value for this project.  In most cases the project id can be detected automatically, but it can optionally be specified as a parameter instead.
setProjectSettings($settings[, $pid]) | 9.10.0 | Saves all project settings (to be used with getProjectSettings).  Useful for cases when you may create a custom config page or need to overwrite all project settings for an external module. Note: Due to a bug, this method was broken (did nothing) in framework versions <5.
setSystemSetting($key,&nbsp;$value) | 8.0.0 | Set the setting specified by the key to the specified value systemwide (shared by all projects).
setUserSetting($key, $value) | 8.3.0 |  Sets the setting specified by the key to the given value for the current user and project.  This method does nothing on surveys and NOAUTH pages.  
tt($key[, $value, ...]) | 9.5.0 | Returns the language string identified by `$key`, optionally interpolated using the values supplied as further arguments (if the first value argument is an array, its elements will be used for interpolation and any further arguments ignored). Refer to the [internationalization guide](i18n-guide.md) for more details.
tt_addToJavascriptModuleObject(<br>&emsp;$key, $item<br>) | 9.5.0 | Adds an item (such as a string, number, or array), identified by the given key, to the _JavaScript Module Object_'s language string store, where it then can be retrieved using the `tt()` function of the _JavaScript Module Object_.
tt_transferToJavascriptModuleObject(<br>&emsp;[$key[, $value[, ...]]]<br>) | 9.5.0 | Transfers one (interpolated) or many language strings (without interpolation) to the _JavaScript Module Object_. When no arguments are passed, or `null` for `$key`, all strings defined in the module's language file are transferred. An array of keys can be passed to transfer multiple language strings. When `$key` is a string, further arguments can be passed which will be used for interpolation (if the first such argument is an array, its elements will be used for interpolation and any further arguments ignored).
validateSettings($settings) | 8.0.2 | Override this method in order to validate settings at save time.  If a non-empty error message string is returned, it will be displayed to the user, and settings will NOT be saved.

#### Deprecated Methods

The following methods still exist for backward compatibility, but should NOT be used going forward as they could be removed or modified in the future:


Method<br><br>&nbsp; | Minimum<br>REDCap<br>Version | Description<br><br>&nbsp;
--- | --- | ---
~~hasPermission($permissionName)~~ | 8.0.0 | Checks whether the current External Module has permission for $permissionName.  This method was deprecated because it is not helpful to module authors (only the framework internally).
~~saveFile($filePath[, $pid])~~ | 8.0.0 | Saves a file and returns the new edoc ID.  This method was deprecated because:<br> - It deletes the file passed into into it.<br> - It does not check the existence of the file passed into it, but instead returns an edoc ID of zero when a file doesn't exist (an exception would likely be more appropriate).<br> - It does not provide a way to save the new edoc ID to a field.<br><br>[Issue #356](https://github.com/vanderbilt/redcap-external-modules/issues/356) was created to track ideas for more complete solutions.


#### Project Object
The following methods are avaiable on the `Project` object returned by `$module->getProject()`.

Method<br><br>&nbsp; | Minimum<br>REDCap<br>Version | Description<br><br>&nbsp;
--- | --- | ---
addRole($roleName, $rights) | 10.4.1 | Add a role with the given name to a project.  The `$rights` argument is expected in the format returned by `getRights()`.
addUser($username[, $rights]) | 10.4.1 | Adds a user to a project, optionally with the specified rights in the format returned by `getRights()`.
addOrUpdateInstances($instances, $keyFieldNames) | 10.3.1 | Allows adding or updating of repeating form instances by matching the values of one or more "key" fields on each instance.  This method is useful for keeping a repeating instrument in sync with an external data source that uses IDs that aren't compatible with `redcap_repeat_instance`, or uses a combination of multiple fields as a unique identifier.  The `$instances` specified should be in the format returned by `REDCap::getData()` when using the `json` type and using `json_decoding($data, true)` to convert the data into a PHP array.  This method does not remove existing fields or instances that are not specified (though it could be modified to do so if anyone wants to work on it).  The result of the underlying `REDCap::saveData()` call will be returned, and should be checked for any errors or warnings.  For convenience, this method may also be called directly on the module object for the current project.
getFormForField($fieldName) | 10.3.1 | Returns the form name for the given field.
getProjectId() | 9.8.1 | Returns the project id.
getRights($username) | 10.4.1 | Returns the rights for the given user on the current project.
getTitle() | 10.2.2 | Returns the project title.
getUsers() | 8.11.10 | Returns an array of `User` objects for each user with rights on the project.
removeRole() | 10.4.1 | Removes a role from a project.
removeUser($username) | 10.4.1 | Removes a user from a project.
setRights($username, $rights) | 10.4.1 | Sets the rights for a specified user.  The `$rights` argument is expected in the format returned by `getRights()`.
setRoleForUser($roleName, $username) | 10.4.1 | Sets the role for the given user.

#### User Object
The following fields and methods are avaiable on the `User` object returned by `$module->getUser()`.

Method<br><br>&nbsp; | Minimum<br>REDCap<br>Version | Description<br><br>&nbsp;
--- | --- | ---
getUsername() | 9.8.1 | Returns the username.
getEmail() | 8.11.10 | Returns the user's primary email address.
getRights([$projectIds]) | 8.11.10 | Returns this user's rights on the specified project id(s).  If a single project id is specified, the rights for that project are returned.  If multiple project ids are specified, an array is returned with project id indexes pointing to rights arrays.  If no project ids are specified, rights for the current project are returned.
hasDesignRights([$projectId]) | 8.11.10 | Returns true if the user has design rights on the specified project.  The current project is used if no project id is specified.
isSuperUser() | 8.11.10 | Returns true if the user is a super user.

#### JavaScript Module Object
A JavaScript version of any module object can be initialized by including the JavaScript code block returned by the PHP module object's `initializeJavascriptModuleObject()` method at any point in any hook. The name of the _JavaScript Module Object_ is returned by the framework method `getJavascriptModuleObjectName()`. Here is a basic example of how to initialize and use the _JavaScript Module Object_ from any PHP hook:

```php
<?=$this->initializeJavascriptModuleObject()?>

<script>
	$(function(){
		var module = <?=$this->getJavascriptModuleObjectName()?>;
		module.log('Hello from JavaScript!');
	})
</script>
```

The _JavaScript Module Object_ provides the following methods framework version 2 and up:

Method<br><br>&nbsp; | Minimum<br>REDCap<br>Version | Description<br><br>&nbsp;
--- | --- | ---
getUrlParameter(name) | 8.11.10 | Returns the value for the specified GET/URL parameter.
getUrlParameters() | 8.11.10 | Returns an object containing all GET parameters for the current URL.
isImportPage() | 8.11.10 | Returns true if the current page is a **Data Import Tool** page.
isImportReviewPage() | 8.11.10 | Returns true if the current page is the **Data Import Tool** review page.
isImportSuccessPage() | 8.11.10 | Returns true if the current page is the **Data Import Tool** success page.
isRoute(routeName) | 8.11.10 | See the description for the PHP version of this method (above). 
log(message[, parameters]) | 8.11.10 | See the description for the PHP version of this method (above).
tt(key[, value[, ...]]) | 9.5.0 | Returns the string identified by `key` from the language store, optionally interpolated with the values passed as additional arguments (if the first such value is an array or object, its elements/members are used for interpolation and any further arguments are ignored). Refer to the [internationalization guide](i18n-guide.md) for more details.
tt_add(key, item) | 9.5.0 | Adds a (new) item (typically a string), identified by `key`, to the language store of the _JavaScript Module Object_. If an entry with the same name already exists in the store, it will be overwritten.
