<?php

/**
 * Merges the External Module language file (English.ini) into REDCap's language file (English.ini).
 * This should be executed as part of the deployment/packaging pipeline, after the external module 
 * framework files have been copied to redcap_vX.Y.Z/ExternalModules.
 * 
 * Execute:
 * php redcap/redcap_vX.Y.Z/ExternalModules/merge_language_file.php
 * 
 * After this file has been executed and was successfull, it can be deleted.
 * 
 */

error_reporting(E_ALL);

$redcap_lang_file = dirname(__DIR__) . "/LanguageUpdater/English.ini";
$em_lang_file = __DIR__ . "/classes/English.ini";

if (file_exists($em_lang_file)) {
    // Read REDCap and External Module Framework language files.
    $redcap = parse_ini_file($redcap_lang_file);
    $em = parse_ini_file(EM);
    // Combine both.
    $combined = array_merge($redcap, $em);
    // Prepare a string containing the full merged content.
    $ini = "";
    foreach ($combined as $key => $value) {
        $ini .= $key . " = \"" . $value . "\"\n";
    }
    // Write the merged content back to REDCap's language file.
    file_put_contents($redcap_lang_file, $ini);
    // Remove the now redundant English.ini from the framework.
    unlink($em_lang_file);
}