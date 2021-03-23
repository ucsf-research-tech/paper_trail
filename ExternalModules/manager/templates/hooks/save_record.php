<?php

use ExternalModules\ExternalModules;

$recordId = $arguments[1];

$temporaryRecordId = @$_POST[ExternalModules::EXTERNAL_MODULES_TEMPORARY_RECORD_ID];
if(ExternalModules::isTemporaryRecordId($temporaryRecordId)){
	$sql = "update redcap_external_modules_log set record = ? where record = ?";
	ExternalModules::query($sql, [$recordId, $temporaryRecordId]);
}