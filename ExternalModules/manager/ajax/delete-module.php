<?php
namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

// There is a super user check inside the following function.
print ExternalModules::deleteModuleDirectory($_POST['module_dir']);