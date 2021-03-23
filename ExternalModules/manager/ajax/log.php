<?php
namespace ExternalModules;

$data = json_decode(file_get_contents('php://input'), true);

if($data['noAuth']){
	define('NOAUTH', true);
}

require_once __DIR__ . '/../../redcap_connect.php';

$module = ExternalModules::getModuleInstance($_GET['prefix']);
$module->logAjax($data);

echo 'success';
