<?php

namespace Partners\autoPdfUpload;

// respond to POST ONLY
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  return exit('[]');
}

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules as EM;

use \REDCap as REDCap;
use \Records as Records;
use \Project as Project;
use \Survey as Survey;
use \System as System;
use \Files as Files;
use \Logging as Logging;

require_once 'use_cases/paper_trail_common.php';

// Get the PID
if ( !isset($_GET['pid']) || !is_numeric($_GET['pid']) ) exit('[]');
$pid = trim(strip_tags(html_entity_decode($_GET['pid'], ENT_QUOTES)));

$projects_with_em_enabled = $module->framework->getProjectsWithModuleEnabled();
if ( !in_array($pid, $projects_with_em_enabled) )
  return exit('[]'); // Module is not enabled on this project

/**
 * GET the rest of the parameters you need
 */

global $Proj;

/**
 * We need the following parameters in order to process the request:
 * trigger_pdf_generation(
    $server_side_processing - get from EM,
    $project_id - get from URL,
    $record - get from URL,
    $pdf_these_forms - get from EM,
    $target_field - get from EM,
    $event_id - get from URL,
    $target_form - get from EM,
    $pk - get from EM,
    $repeat_instance - get from URL,
    $file_prefix - get from EM
 * );
 *
 * BUT we can get a lot of them out of the module!
 */

//// If project is not-server-side - return
//$server_side_processing = $module->getProjectSetting('enable_cron');
//if ( $server_side_processing != 1 && $server_side_processing != '1' ) {
//  return exit('[]'); // Server-side processing is not enabled on this module - nothing to do
//}

// Get the Event ID
if ( !isset($_POST['ss_event_id']) || !is_numeric($_POST['ss_event_id']) ) exit('[2]');
$event_id = trim(strip_tags(html_entity_decode($_POST['ss_event_id'], ENT_QUOTES)));
// repeat instance
if ( !isset($_POST['ss_instance']) ) exit('[3]');
$repeat_instance = trim(strip_tags(html_entity_decode($_POST['ss_instance'], ENT_QUOTES)));
// record
if ( !isset($_POST['ss_record']) || strlen($_POST['ss_record']) <= 0 ) exit('[4]');
$record = trim(strip_tags(html_entity_decode($_POST['ss_record'], ENT_QUOTES)));
// instrument
if ( !isset($_POST['ss_survey_id']) || strlen($_POST['ss_survey_id']) <= 0 ) exit('[5]');
$survey_id = trim(strip_tags(html_entity_decode($_POST['ss_survey_id'], ENT_QUOTES)));

$pk = $Proj->table_pk;

$paper_trail_type = $module->getProjectSetting('paper_trail_type');

if ($paper_trail_type == 'ppt_1') {
  // SINGLE Use Case Scenario
    // If project is not-server-side - return
    $server_side_processing = $module->getProjectSetting('enable_cron');
    if ( $server_side_processing != 1 && $server_side_processing != '1' ) {
        return exit('[]'); // Server-side processing is not enabled on this module - nothing to do
    }

  $pdf_these_forms = array(); //$module->getProjectSetting('pdf_form');
  $target_field = $module->getProjectSetting('target_field');
  $forms_in_project = array_keys($Proj->forms);
  foreach ( $module->getProjectSetting('pdf_form') as $f_c => $f ) {
    if ( in_array($f, $forms_in_project) ) {
      $pdf_these_forms[$f] = $f; // form is valid in this project - put it in an associative array so we de-duplicate
    }
  }
  $form_status = $module->getProjectSetting('complete_stat');
  $allowed_form_status = array ("0","1","2");
  if ( !in_array($form_status, $allowed_form_status) )
    $form_status = "0";

  if ( count($pdf_these_forms) <=0 )
    return exit('[]'); // nothing to do
  if ( strlen($target_field) <=0 )
    return exit('[]'); // nothing to do
  if ( !isset($Proj->metadata[$target_field]) )
    return exit('[]'); // nothing to do

  $file_prefix = $module->getProjectSetting('file_prefix');
  $target_form = $Proj->metadata[$target_field]['form_name'];
  $target_event = $module->getProjectSetting('target_event');

  $pdf_archival = $module->getProjectSetting('enable_survey_archive');

  // We should have everything we need at this point
  generate_and_upload_pdf ( $Proj->project_id, $record, $pdf_these_forms, $target_field, $target_event, $target_form, $pk, $repeat_instance, $file_prefix, $form_status, "SYSTEM", $pdf_archival, $survey_id );

  return; // We're done
}

if ($paper_trail_type == 'ppt_2') {
    // Multi Use Case Scenario
    // Multi k-index
    if ( !isset($_POST['ss_multi_k']) || !is_numeric($_POST['ss_multi_k']) ) exit('[6]');
    $k = trim(strip_tags(html_entity_decode($_POST['ss_multi_k'], ENT_QUOTES)));

    $pdf_these_forms = array(); //$module->getProjectSetting('pdf_form');
    $target_field = $module->getProjectSetting('multi_target_field')[$k];
    $forms_in_project = array_keys($Proj->forms);

      //TODO: Add $multi_target_event lookup  
      foreach ( $module->getProjectSetting('multi_pdf_form')[$k] as $f_c => $f ) {
        if ( in_array($f, $forms_in_project) ) {
            $pdf_these_forms[$f] = $f; // form is valid in this project - put it in an associative array so we de-duplicate
        }
    }

    $form_status = $module->getProjectSetting('multi_complete_stat')[$k];
    $allowed_form_status = array ("0","1","2");
    if ( !in_array($form_status, $allowed_form_status) )
        $form_status = "0";

    if ( count($pdf_these_forms) <=0 )
        return exit('[]'); // nothing to do
    if ( strlen($target_field) <=0 )
        return exit('[]'); // nothing to do
    if ( !isset($Proj->metadata[$target_field]) )
        return exit('[]'); // nothing to do

    $file_prefix = $module->getProjectSetting('multi_file_prefix')[$k];
    $target_event = $module->getProjectSetting('multi_target_event')[$k];

    $target_form = $Proj->metadata[$target_field]['form_name'];

    $pdf_archival = $module->getProjectSetting('multi_enable_survey_archive')[$k];

    // We should have everything we need at this point
    generate_and_upload_pdf ( $Proj->project_id, $record, $pdf_these_forms, $target_field, $target_event, $target_form, $pk, $repeat_instance, $file_prefix, $form_status, "SYSTEM", $pdf_archival, $survey_id );

    return; // We're done
}

