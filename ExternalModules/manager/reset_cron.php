<?php

namespace ExternalModules;

require_once __DIR__ . '/../redcap_connect.php';

if (ExternalModules::isSuperUser()) {
	$moduleDirectoryPrefix = @$_GET['prefix'];
	ExternalModules::resetCron($moduleDirectoryPrefix);
	echo ExternalModules::tt("em_manage_92");
} else {
	throw new \Exception(ExternalModules::tt("em_errors_120"));
}
