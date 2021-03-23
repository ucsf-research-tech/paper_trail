<?php namespace ExternalModules;
require_once __DIR__ . '/../redcap_connect.php';
if(PHP_SAPI !== 'cli'){
    die('This file is only executable on the command line.');
}

echo ExternalModules::getPHPUnitPath();