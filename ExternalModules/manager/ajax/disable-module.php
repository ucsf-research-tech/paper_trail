<?php
namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

// Can the current user enable/disable modules?
if (!ExternalModules::userCanEnableDisableModule($_POST['module'])) exit;

$module = $_POST['module'];

if (empty($module)) {
	//= You must specify a module to disable
	echo ExternalModules::tt("em_errors_81"); 
	return;
}

$version = ExternalModules::getModuleVersionByPrefix($module);

if (isset($_GET["pid"])) {
	ExternalModules::setProjectSetting($module, $_GET['pid'], ExternalModules::KEY_ENABLED, false);
} else {
	ExternalModules::disable($module, false);
}

// Log this event
$logText = "Disable external module \"{$module}_{$version}\" for " . (!empty($_GET['pid']) ? "project" : "system");
\REDCap::logEvent($logText);

echo 'success';
