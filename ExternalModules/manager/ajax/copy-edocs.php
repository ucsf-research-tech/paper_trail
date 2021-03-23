<?php
namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

$pid = $_POST['pid'];

// The following method checks for design rights before making any changes.
ExternalModules::recreateAllEDocs($pid);

echo 'success';