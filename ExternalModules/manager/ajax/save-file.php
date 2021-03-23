<?php
namespace ExternalModules;

require_once __DIR__ . '/../../redcap_connect.php';
require_once APP_PATH_DOCROOT.'Classes/Files.php';

$pid = @$_GET['pid'];
$moduleDirectoryPrefix = $_GET['moduleDirectoryPrefix'];
$version = $_GET['moduleDirectoryVersion'];

$returnError = function($message){
	header('Content-type: application/json');
	echo json_encode(array(
		'status' => $message
	));
	exit();
};

if(empty($pid) && !ExternalModules::hasSystemSettingsSavePermission($moduleDirectoryPrefix)){
	//= You do not have permission to save system settings!
	$returnError(ExternalModules::tt("em_errors_80"));
}

$config = ExternalModules::getConfig($moduleDirectoryPrefix, $version, $pid);
$files = array();
$repeatingFiles = array();
foreach(['system-settings', 'project-settings'] as $settingsKey){
	$files = array_merge(ExternalModules::getAllFileSettings($config[$settingsKey]),$files);
	foreach($config[$settingsKey] as $row) {
		if($row['type'] && $row['type'] == 'sub_settings' && $row['repeatable']) {
			$repeatingFiles = array_merge(ExternalModules::getAllFileSettings($row['sub_settings']),$repeatingFiles);
		}
		else if ($row['type'] && ($row['type'] == "file") && $row['repeatable']) {
			$repeatingFiles[] = $row['key'];
		}
	}
}

# returns boolean
function isExternalModuleFile($key, $fileKeys) {
	 if (in_array($key, $fileKeys)) {
		  return true;
	 }
	 foreach ($fileKeys as $fileKey) {
		  if (preg_match('/^'.$fileKey.'____\d+$/', $key)) {
			   return true;
		  }
         if (preg_match("/____/", $key)) {
             $parts = preg_split("/____/", $key);
             $shortKey = array_shift($parts);
             if($shortKey == $fileKey){
                 return true;
             }
         }
	 }
	 return false;
}

if(empty($pid)) {
	$pidPossiblyWithNullValue = null;
} else {
	$pidPossiblyWithNullValue = $pid;
}

$edoc = null;
$myfiles = array();
foreach($_FILES as $key=>$value){
	$myfiles[] = $key;
	if (isExternalModuleFile($key, $files) && $value) {
		if(!empty($pid) && !ExternalModules::hasProjectSettingSavePermission($moduleDirectoryPrefix, $key)) {
			//= You do not have permission to save the following project setting: {0}.
			$returnError(ExternalModules::tt("em_errors_87", $key));
		}

		# use REDCap's uploadFile
		$edoc = \Files::uploadFile($_FILES[$key]);
		if ($edoc) {
            //For repeatable elements we change the key
            if (preg_match("/____/", $key)) {
                $settings = array();
                $parts = preg_split("/____/", $key);
                $shortKey = array_shift($parts);
                $aux =& $settings;

                foreach ($parts as $index) {
                    $aux[$index] = array();
                    $aux =& $aux[$index];
                }
                $aux = (string)$edoc;

                $isThisSystem = ExternalModules::isSystemSetting($moduleDirectoryPrefix,$shortKey);

                if ($isThisSystem) {
                    $data = ExternalModules::getSystemSetting($moduleDirectoryPrefix,$shortKey);
                }
                else {
                    $data = ExternalModules::getProjectSetting($moduleDirectoryPrefix, $pidPossiblyWithNullValue, $shortKey);
                }
                if(!isset($data) || !is_array($data) || $data == null){
                    //do nothing
                }else{
                    $settings = array_replace_recursive($data,$settings);
                }
				\REDCap::logEvent("Save file $edoc on $moduleDirectoryPrefix module to $shortKey for ".(!empty($pid) ? "project ".$pid : "system"),"",var_export($settings,true));

                if ($isThisSystem) {
                    ExternalModules::setSystemSetting($moduleDirectoryPrefix,$shortKey,$settings);
                }
                else {
                    ExternalModules::setProjectSetting($moduleDirectoryPrefix, $pidPossiblyWithNullValue, $shortKey, $settings);
                }
            }else{
                ExternalModules::setFileSetting($moduleDirectoryPrefix, $pidPossiblyWithNullValue, $key, $edoc);

				\REDCap::logEvent("Save file $edoc on $moduleDirectoryPrefix module to $key for ".(!empty($pid) ? "project ".$pid : "system"),$edoc);
			}

		} else {
			header('Content-type: application/json');
			echo json_encode(array(
				//= You could not save a file properly.
				'status' => ExternalModules::tt("em_errors_88")
			));
		}
	 }
}

if ($edoc) {
	header('Content-type: application/json');
	echo json_encode(array(
		'status' => 'success',
        'myfiles' => json_encode($myfiles),
        'shortkey' => $shortKey,
		'data' => json_encode($data),
		'setting' => json_encode($settings),
		'parts' => $parts
	));
} else {
	### Check if trying to convert string file field to json-array file field
	foreach($repeatingFiles as $key) {
		if(array_key_exists($key."____0",$_POST)) {
			$edoc = $_POST[$key."____0"];
            $isThisSystem = ExternalModules::isSystemSetting($moduleDirectoryPrefix,$key);
            if ($isThisSystem) {
                $data = ExternalModules::getSystemSetting($moduleDirectoryPrefix,$key);
            }
            else {
                $data = ExternalModules::getProjectSetting($moduleDirectoryPrefix, $pidPossiblyWithNullValue, $key);
            }
			if(is_array($data)){
				//do nothing since it's already an array
			}else if($data == $edoc) {
				$settings = [$data];
				\REDCap::logEvent("Re-save file $edoc as array on $moduleDirectoryPrefix module to $key for ".(!empty($pid) ? "project ".$pid : "system"),"",var_export($settings,true));

				if ($isThisSystem) {
				    ExternalModules::setSystemSetting($moduleDirectoryPrefix,$key,$settings);
                }
				else {
                    ExternalModules::setProjectSetting($moduleDirectoryPrefix, $pidPossiblyWithNullValue, $key, $settings);
                }
			}
		}
	}

	if($edoc) {
		header('Content-type: application/json');
		echo json_encode(array(
				'status' => 'success',
				'myfiles' => json_encode($myfiles),
				'shortkey' => $key,
				'data' => json_encode($data),
				'setting' => json_encode($settings),
				'parts' => $parts
		));
	}
	else {
		header('Content-type: application/json');
		echo json_encode(array(
			'myfiles' => json_encode($myfiles),
			'_POST' => json_encode($_POST),
			'message' => $message,
			//= You could not find a file.
			'status' => ExternalModules::tt("em_errors_89") 
		));
	}
}
