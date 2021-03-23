<?php
namespace ExternalModules;

$filename = $_GET['file'];
$prefix = $_GET['prefix'];
$pid = @$_GET['pid'];

$parts = explode('.', $filename);
$edocId = $parts[0];
$extension = $parts[1];


$files = ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST);
$found = false;
foreach($files as $file){
	if($file['edocId'] == $edocId){
		$found = true;
		break;
	}
}

if(!$found){
	// Only allow rich text edocs to be accessed publicly.
	//= EDoc {0} was not found on project {1}!
	throw new \Exception(ExternalModules::tt("em_errors_79", $edocId, $pid)); 
}

$edocPath = ExternalModules::getEdocPath($edocId);
$fp = fopen($edocPath, 'rb');
$mimeType = \Files::get_mime_types()[$extension];

header("Content-Type: $mimeType");
header("Content-Length: " . filesize($edocPath));
header('Pragma: public');
header('Cache-Control: max-age=86400');
header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));

fpassthru($fp);
