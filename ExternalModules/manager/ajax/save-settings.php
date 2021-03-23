<?php
namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

$pid = @$_GET['pid'];
if ($pid === null) { $pid = ""; } // LS https://github.com/vanderbilt/redcap-external-modules/issues/311 
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];

$rawSettings = json_decode($_POST['settings'], true);
if($rawSettings === null){
	//= Unable to parse module settings!
	throw new \Exception(ExternalModules::tt("em_errors_90")); 
}

$module = ExternalModules::getModuleInstance($moduleDirectoryPrefix);
$validationErrorMessage = $module->validateSettings(ExternalModules::formatRawSettings($moduleDirectoryPrefix, $pid, $rawSettings));
if(!empty($validationErrorMessage)){
	die($validationErrorMessage);
}

// The saveSettings() method eventually calles setSystemSetting() & setProjectSetting() under the hood.
// Both of those methods verify that the current user has appropriate permissions to save settings.
$saveSqlByField = ExternalModules::saveSettings($moduleDirectoryPrefix, $pid, $rawSettings);

if(!empty($saveSqlByField)){
	// At least one setting changed.  Log this event.
	$logText = "Modify configuration for external module \"{$moduleDirectoryPrefix}_{$module->VERSION}\" for " . (!empty($_GET['pid']) ? "project" : "system");
	
	// We do NOT include the values of changed settings here, since they could contain sensitive data that some users shouldn't see (API keys, etc.).
	$changeText = join(', ', array_keys($saveSqlByField));

	$queryDetails = '';
	foreach($saveSqlByField as $query){
		$queryDetails .= "SQL: " . $query->getSQL() . "\nParameters: " . json_encode($query->getParameters()) . "\n\n";
	}

	\REDCap::logEvent($logText, $changeText, $queryDetails);
}

ExternalModules::callHook('redcap_module_save_configuration', array($pid), $moduleDirectoryPrefix);

header('Content-type: application/json');
echo json_encode(array(
    'status' => 'success',
    'test' => 'success'
));
