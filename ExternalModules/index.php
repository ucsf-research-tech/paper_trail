<?php
namespace ExternalModules;

$page = rawurldecode(urldecode($_GET['page']));

// We set NOAUTH for get-file.php requests to support old Inline Popup module settings
// that include old URLs that point to this file directly instead of going through the API.
// We shouldn't remove this without some plan to support or update those old URLs.
$isGetFilePage = $page === '/manager/rich-text/get-file.php';
$noAuth = isset($_GET['NOAUTH']) || $isGetFilePage;
if($noAuth){
	// This must be defined at the top before redcap_connect.php is required.
	define('NOAUTH', true);
}

// We call redcap_connect.php before loading any classes to make sure redirections from previous REDCap
// version URLs happen first.  We don't want to try to load old and new versions of the same class.
require_once __DIR__ . '/redcap_connect.php';

if($isGetFilePage){
	require_once __DIR__ . $page;
	return;
}

use Exception;

$pid = @$_GET['pid'];

$prefix = $_GET['prefix'];
if(empty($prefix)){
	$prefix = ExternalModules::getPrefixForID($_GET['id']);
	if(empty($prefix)){
		throw new Exception(ExternalModules::tt('em_errors_123'));
	}
}

if($prefix === ExternalModules::TEST_MODULE_PREFIX){
	$version = TEST_MODULE_VERSION;
}
else{
	// We don't call getEnabledVersion() here because there's no reason to cache data for all modules on single page loads.
	$version = ExternalModules::getSystemSetting($prefix, ExternalModules::KEY_VERSION);
}

if(empty($version)){
	throw new Exception(ExternalModules::tt('em_errors_124', $prefix));
}

$config = ExternalModules::getConfig($prefix, $version);
if($noAuth && !@in_array($page, $config['no-auth-pages'])){
	throw new Exception(ExternalModules::tt('em_errors_125'));
}

$getLink = function () use ($prefix, $version, $page) {
	$links = ExternalModules::getLinks($prefix, $version);
	foreach ($links as $link) {
		if ($link['url'] == ExternalModules::getPageUrl($prefix, $page)) {
			return $link;
		}
	}

	return null;
};

$link = $getLink();
$showHeaderAndFooter = @$link['show-header-and-footer'] === true;
if($pid != null){
	$enabledGlobal = ExternalModules::getSystemSetting($prefix,ExternalModules::KEY_ENABLED);
	$enabled = ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::KEY_ENABLED);
	if(!$enabled && !$enabledGlobal){
		throw new Exception(ExternalModules::tt('em_errors_126', $prefix, $pid));
	}

	$headerPath = 'ProjectGeneral/header.php';
	$footerPath = 'ProjectGeneral/footer.php';
}
else{
	$headerPath = 'ControlCenter/header.php';
	$footerPath = 'ControlCenter/footer.php';
}

$pageExtension = strtolower(pathinfo($page, PATHINFO_EXTENSION));
$pagePath = $page . ($pageExtension == '' ? ".php" : "");

$modulePath = ExternalModules::getModuleDirectoryPath($prefix, $version);
$pagePath = ExternalModules::getSafePath($pagePath, $modulePath);

if(!file_exists($pagePath)){
	throw new Exception(ExternalModules::tt('em_errors_127', $pagePath));
}

$checkLinkPermission = function ($module) use ($link) {
	if (!$link) {
		// This url is not defined in config.json.  Allow it to work for backward compatibility.
		return true;
	}

	$link = $module->redcap_module_link_check_display($_GET['pid'], $link);
	if (!$link) {
		throw new Exception(ExternalModules::tt('em_errors_128'));
	}
};

switch ($pageExtension) {
    case "php":
    case "":
        // PHP content
		$module = ExternalModules::getModuleInstance($prefix, $version);

        // Leave setting permissions up to module authors.
		// The redcap_module_link_check_display() hook already limits access to design rights users by default.
		// No additional security should be required.
		$module->disableUserBasedSettingPermissions();

		$checkLinkPermission($module);

		if($showHeaderAndFooter){
			require_once APP_PATH_DOCROOT . $headerPath;
		}

        require_once $pagePath;

		if($showHeaderAndFooter){
			require_once APP_PATH_DOCROOT . $footerPath;
		}

        break;
    case "md":
        // Markdown Syntax
        $Parsedown = new \Parsedown();
        $html = $Parsedown->text(file_get_contents($pagePath));

        $search = '<img src="';
        $replace = $search . ExternalModules::getModuleDirectoryUrl($prefix, $version);
        $html = str_replace($search, $replace, $html);

		ExternalModules::addResource('css/markdown.css');
        echo $html;
        break;
    default:
        // OTHER content (css/js/etc...):
        $contentType = ExternalModules::getContentType($pageExtension);
        if($contentType){
            // In most cases index.php is not used to access non-php files (and a content type is not needed).
            // However, Andy Martin has a use case where users are behind Shibboleth and it makes sense to serve all
            // files through index.php.  This content type was added specifically for that case.
            $mime_type = $contentType;
        } else {
            // Make a best guess
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $pagePath);
        }

        // send the headers
        // header("Content-Disposition: attachment; filename=$public_name;");
        header("Content-Type: $mime_type");
        header('Content-Length: ' . filesize($pagePath));

        // stream the file
        $fp = fopen($pagePath, 'rb');
        fpassthru($fp);
        exit();
}
