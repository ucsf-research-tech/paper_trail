<?php
//© 2020 Partners HealthCare System, Inc. / Mass General Brigham
//All Rights Reserved.
namespace Partners\autoPdfUpload;

use ExternalModules\ExternalModules as EM;
use \REDCap as REDCap;
use \Files as Files;
use \Survey as Survey;
use \Logging as Logging;
require_once 'paper_trail_common.php';

global $Proj;

/**
 * Get the settings of this Multi-Use-case
 */
//$target_field = $this->getProjectSetting('target_field'); // Input variable
$target_field = $multi_target_field;
$pdf_inst_list = $multi_pdf_form;

/* */
$pdf_these_forms = array(); //$this->getProjectSetting('pdf_form');
//$target_field = $this->getProjectSetting('target_field'); // Input variable

$forms_in_project = array_keys($Proj->forms);
foreach ( $pdf_inst_list as $f_c => $f ) {
    if ( in_array($f, $forms_in_project) ) {
        $pdf_these_forms[$f] = $f; // form is valid in this project - put it in an associative array so we de-duplicate
    }
}

if ( count($pdf_these_forms)<=0 ) return; // nothing to do

/**
 * Check to see if the instrument that is triggering this is on the PDF form. If it's not - return.
 * We only want to have this work from one of the forms that are being PDFed
 */
if ( !in_array($instrument, $pdf_these_forms) ) {
    return; // Don't do anything
}



// service URL
$url = $this->getUrl('auto_pdf_service.php')."&NOAUTH&pid=". $project_id;

/**
 * C1 -> Automatic -
 * C2 -> Controlled - Depends on a Yes/No field
 * 99 -> Disabled
 */
//$upload_type = $this->getProjectSetting('upload_type');
$upload_type = $multi_upload_type;
//$file_prefix = $this->getProjectSetting('file_prefix');
$file_prefix = $multi_file_prefix;
//$server_side_processing = $this->getProjectSetting('enable_cron');
$server_side_processing = $multi_enable_cron;
//$form_status = $this->getProjectSetting('complete_stat');
$form_status = $multi_complete_stat;
$k_index = $k;

$allowed_form_status = array ("0","1","2");
if ( !in_array($form_status, $allowed_form_status) )
    $form_status = "0";

$pk = $Proj->table_pk;

// Adding Event selection for target field
$target_form_id = $Proj->longitudinal == TRUE ? $multi_event_name : $Proj->metadata[$target_field]['form_name'];
$target_form = $Proj->metadata[$target_field]['form_name'];

$enable_survey_archive = $multi_enable_survey_archive;

$survey_id = -1;
if ( $enable_survey_archive ) {
    $surveys = $Proj->surveys;
    foreach ( $surveys as $sid => $s_details ) {
        if ( $instrument == $s_details['form_name'] ) {
            $survey_id = $sid;
        }
    }

    // IF the survey ID was not found, then use the first survey ID if we have surveys at all
    if ( $survey_id < 0 && count($surveys)>0) {
        $survey_id = $Proj->firstFormSurveyId;
    }
}

switch ($upload_type) {
    case 'C1':

        $not_null_fields = $multi_not_null_fields;
        // Check to see if the PDF generating condition is true
        if ( PAGE == 'surveys/index.php' ){
            // Check to see if the form is complete
            $survey_status = Survey::getResponseStatus ($project_id, $record, $event_id);
            if ( isset($survey_status) && count($survey_status)>0 ) {
                if ( $survey_status[$record][$event_id][$instrument][$repeat_instance] != 2 ){
                    return; // we only trigger on Complete when it's coming from a survey!!!!
                }
            }
        }

        $ok_to_generate = check_triggering_condition( $Proj, $record, $event_id, $repeat_instance, $not_null_fields, $upload_type );

        if ( $ok_to_generate ) {
            trigger_pdf_generation($server_side_processing, $project_id, $record, $pdf_these_forms, $target_field, $event_id, $target_form, $pk, $repeat_instance, $file_prefix, $url, $form_status, $survey_id, $enable_survey_archive,$k_index,$target_form_id);

        }

        break;

    case 'C2':

        $trigger_field = $multi_trigger_field;
        // Check to see if the PDF generating condition is true
        if ( PAGE == 'surveys/index.php' ){
            // Check to see if the form is complete
            $survey_status = Survey::getResponseStatus ($project_id, $record, $event_id);
            if ( isset($survey_status) && count($survey_status)>0 ) {
                if ( $survey_status[$record][$event_id][$instrument][$repeat_instance] != 2 ){
                    return; // we only trigger on Complete when it's coming from a survey!!!
                }
            }
        }
        // Check to see if the PDF generating condition is true
        $ok_to_generate = check_triggering_condition( $Proj, $record, $event_id, $repeat_instance, $trigger_field, $upload_type );
        if ( $ok_to_generate ) {
            trigger_pdf_generation($server_side_processing, $project_id, $record, $pdf_these_forms, $target_field, $event_id, $target_form, $pk, $repeat_instance, $file_prefix, $url, $form_status, $survey_id, $enable_survey_archive,$k_index,$target_form_id);
        }
        break;

    default: break; // Captures case 99 - Disabled
}

?>