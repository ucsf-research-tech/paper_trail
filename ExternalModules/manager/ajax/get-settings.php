<?php
namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

$pid = @$_POST['pid'];
$prefix = $_POST['moduleDirectoryPrefix'];
$module_instance = ExternalModules::getModuleInstance($prefix);

header('Content-type: application/json');
if (!empty($pid)) {
	ExternalModules::requireDesignRights($pid);
	$settings = ExternalModules::getProjectSettingsAsArray($prefix, $pid, false);
	$settingType = 'project-settings';
} else if (ExternalModules::isAdminWithModuleInstallPrivileges()){
	$settings = ExternalModules::getSystemSettingsAsArray($prefix);
	$settingType = 'system-settings';
}

$config = ExternalModules::getConfig($prefix, null, $pid, true);

foreach($config[$settingType] as $configKey => $configRow) {
	$config[$settingType][$configKey] = ExternalModules::getAdditionalFieldChoices($configRow, $pid);
}

if(method_exists($module_instance,'redcap_module_configuration_settings')){
    $config[$settingType] = $module_instance->redcap_module_configuration_settings($pid,$config[$settingType]);
}


echo json_encode(array(
	'status' => 'success',
	'config' => $config,
	'settings' => $settings
));
