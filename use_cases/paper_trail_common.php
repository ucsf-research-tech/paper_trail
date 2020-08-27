<?php
/**
 * Some commonly-used functions in the Paper Trail EM
 */

namespace Partners\autoPdfUpload;

use ExternalModules\ExternalModules as EM;
use \REDCap as REDCap;
use \Files as Files;
use \Logging as Logging;
use \System as System;
use Sabre;
use League;

/**
 * Return TRUE or FALSE if the triggering condition, as set by the EM settings, has been met
 *
 * @param $Proj
 * @param $record
 * @param $event_id
 * @param $repeat_instance
 * @param $not_null_fields
 * @param $upload_type
 *
 * @return bool
 */
function check_triggering_condition ( $Proj, $record, $event_id, $repeat_instance, $fields_to_check, $upload_type ) {
  try {
    $ok_to_generate = false;
    switch ( $upload_type ) {
      case 'C1':
        $fields_with_data_count = 0;
        // Add in the "form_complete"
        foreach ($fields_to_check as $f_c => $field) {
          $fields_to_check[] = $Proj->metadata[$field]['form_name'].'_complete';
        }
        $data = REDCap::getData(
          $Proj->project_id,
          'array',
          array($record),
          array_values($fields_to_check),
          array($event_id)
        );
        if ( count($data) >0 ) {
          // Check the data
          foreach ($fields_to_check as $f_c => $field) {
            if ($Proj->hasRepeatingFormsEvents()) {
              $field_instrument = $Proj->metadata[$field]['form_name'];

              $isRepeatEvent = ($Proj->hasRepeatingFormsEvents() && $Proj->isRepeatingEvent($event_id));
              $isRepeatForm  = $isRepeatEvent ? false : ($Proj->hasRepeatingFormsEvents() && $Proj->isRepeatingForm($event_id, $field_instrument));
              $isRepeatEventOrForm = ($isRepeatEvent || $isRepeatForm);

              $field_data_value = "";
              if ($isRepeatEventOrForm) {
                $field_data_value = $data[$record]['repeat_instances'][$event_id][$field_instrument][$repeat_instance][$field];
              }
              else {
                $field_data_value = $data[$record][$event_id][$field];
              }
              if ( isset($field_data_value) && !is_null($field_data_value) && strlen(trim($field_data_value)) > 0 ) {
                $fields_with_data_count++; // increment the count of the fields with data
              }
            }
            else {
              if ( $data[$record] ) {
                if ( $data[$record][$event_id] ) {
                  if ( isset($data[$record][$event_id][$field]) && strlen(trim($data[$record][$event_id][$field])) > 0 ) {
                    $fields_with_data_count++; // increment the count of the fields with data
                  }
                }
              }
            }
          }

          if ( $fields_with_data_count == count($fields_to_check) )
            $ok_to_generate = true;
          else
            $ok_to_generate = false;
        }
        else {
          return false;
        }

        break;
      case 'C2':
        $data = REDCap::getData(
          $Proj->project_id,
          'array',
          array($record),
          array($fields_to_check, $Proj->metadata[$fields_to_check]['form_name'].'_complete'),
          array($event_id)
        );
        if ( count($data) > 0 ) {
          if ( $Proj->hasRepeatingFormsEvents()) {
            $field_instrument = $Proj->metadata[$fields_to_check]['form_name'];

            $isRepeatEvent = ($Proj->hasRepeatingFormsEvents() && $Proj->isRepeatingEvent($event_id));
            $isRepeatForm  = $isRepeatEvent ? false : ($Proj->hasRepeatingFormsEvents() && $Proj->isRepeatingForm($event_id, $field_instrument));
            $isRepeatEventOrForm = ($isRepeatEvent || $isRepeatForm);

            $field_data_value = "";
            if ($isRepeatEventOrForm) {
              $field_data_value = $data[$record]['repeat_instances'][$event_id][$field_instrument][$repeat_instance][$fields_to_check];
            }
            else {
              $field_data_value = $data[$record][$event_id][$fields_to_check];
            }
            if ( isset($field_data_value) && !is_null($field_data_value) && strlen(trim($field_data_value)) > 0 ) {
              $ok_to_generate = true;
            }
          }
          else {
            if ( $data[$record] ) {
              if ( $data[$record][$event_id] ) {
                if ( $data[$record][$event_id][$fields_to_check] == 1 || $data[$record][$event_id][$fields_to_check] == '1' )
                  $ok_to_generate = true;
              }
            }
          }
        }
        else {
          return false; // no data found for this - no service!
        }

          break;

        default:
          return false; // we should not be here
          break;
    }

    return $ok_to_generate;
  }
  catch ( Exception $ee ) {
    return false; // Something went wrong - assume condition is not met
  }
}

/**
 * Trigger the PDF generation depending on where the generation needs to take place
 *
 * @param $project_id
 * @param $record
 * @param $pdf_these_forms
 * @param $target_field
 * @param $event_id
 * @param $target_form
 * @param $pk
 * @param $file_prefix
 */
function trigger_pdf_generation ( $server_side_processing, $project_id, $record, $pdf_these_forms, $target_field, $event_id, $target_form, $pk, $repeat_instance, $file_prefix, $url, $form_status, $survey_id, $enable_survey_archive,$k_index ) {
  if ( $server_side_processing == 1 || $server_side_processing == '1' ) {
    // Send this to the service
    // Form the params
    $params = array(
      'ss_record' => $record,
      'ss_event_id' => $event_id,
      'ss_instance' => $repeat_instance,
      'ss_survey_id' => $survey_id,
        'ss_multi_k' => $k_index // I added this!
    );

    // POST to the service
    post_to_auto_pdf_service ( $url, $params );
  }
  else {
    // Process now
    generate_and_upload_pdf ( $project_id, $record, $pdf_these_forms, $target_field, $event_id, $target_form, $pk, $repeat_instance, $file_prefix, $form_status, "", $enable_survey_archive, $survey_id );
  }
}

/**
 * Post to the auto_pdf_service
 * @param $params
 * @param $project_id
 */
function post_to_auto_pdf_service ( $url, $params ) {
  try {
    $param_string = http_build_query($params, '', '&');

    $curlpost = curl_init();
    curl_setopt($curlpost, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curlpost, CURLOPT_VERBOSE, 0);
    curl_setopt($curlpost, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curlpost, CURLOPT_AUTOREFERER, true);
    curl_setopt($curlpost, CURLOPT_MAXREDIRS, 10);
    curl_setopt($curlpost, CURLOPT_URL, $url);
    curl_setopt($curlpost, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($curlpost, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curlpost, CURLOPT_POSTFIELDS, $param_string);
    curl_setopt($curlpost, CURLOPT_FRESH_CONNECT, 1); // Don't use a cached version of the url
    $timeout = 1;
    if (is_numeric($timeout)) {
      curl_setopt($curlpost, CURLOPT_CONNECTTIMEOUT, $timeout); // Set timeout time in seconds
      curl_setopt($curlpost, CURLOPT_TIMEOUT, $timeout);
    }

    if (!empty($headers) && is_array($headers)) {
      curl_setopt($curlpost, CURLOPT_HTTPHEADER, $headers);
    }
    // Make the call
    $response = curl_exec($curlpost);
    $info = curl_getinfo($curlpost);
    curl_close($curlpost);

    return; // we don't care about the response at this time
  }
  catch ( Exception $ee ) {
    return;
  }
}

/**
 * Generate the PDF and upload to where it needs to go
 *
 * @param $project_id
 * @param $record
 * @param $pdf_this_form
 * @param $target_field
 * @param $event_id
 * @param $target_form
 * @param $pk
 * @param $file_prefix
 */
function generate_and_upload_pdf ( $project_id, $record, $pdf_these_forms, $target_field, $event_id, $target_form, $pk, $repeat_instance, $file_prefix, $form_status, $user_override = "", $uplaod_to_pdf_archive = false, $survey_id = -1 ) {
  try {
    // get the first form to process form the list of forms
    $first_form = "";
    foreach ( $pdf_these_forms as $pdf_form ) {
      $first_form = $pdf_form;
      break;
    }

    /**
     * This works in conjunction with the redcap_pdf hook. getPDF calls the redcap_pdf hook, which is where we are going to inject
     * the rest of the forms
     * Set a session variable to control the metadata - i.e. the forms that need to be injected
     */
    $_SESSION['PAPER_TRAIL_PDF'] = true;
    $_SESSION['PAPER_TRAIL_PDF_FORMS'] = $pdf_these_forms;
    $pdf_content = REDCap::getPDF($record, $first_form, $event_id, false, $repeat_instance, false, "", "", false);
    // full path and filename of the file to upload
    $filename = $file_prefix . "_" . $project_id . "_" . $record . "_" . date("_Y-m-d_Hi") . ".pdf";
    //    $filename_with_path = "/tmp/" . $filename;
    $filename_with_path = APP_PATH_TEMP . $filename; // Consider creating a ternary operation and allow user to determine their temp folder location

    // Save the PDF to a local web server directory
    $pdf_file = file_put_contents($filename_with_path, $pdf_content);

    $pdf_file_details = array(
      'name' => $filename,
      'size' => filesize($filename_with_path),
      'tmp_name' => $filename_with_path,
    );

    // Now we have to upload the file to the desired instrument
    $docId = Files::uploadFile($pdf_file_details);

    if ($docId != 0) {
      $data_to_save = array(
        $record => array(
          $target_form => array(
            $pk => $record,
            $target_field => $docId,
            $target_form . "_complete" => $form_status)
        ));

      // Import the data with REDCap::saveData
      if ( !is_null($user_override) && strlen($user_override) > 0 )
        define("USERID", "SYSTEM");
      $response = REDCap::saveData(
        $project_id,
        'array', // The format of the data
        $data_to_save, // The Data
        'overwrite', // Overwrite behavior
        'YMD', // date format
        'flat', // type of the data
        null, // Group ID
        null, // data logging
        true, // perform auto calculations
        true, // commit data
        false, // log as auto calc
        true, // skip calc fields
        array(), // change reasons
        false, // return data comparison array
        false, // skip file upload fields - this is what we are actually updating
        false // remove locked fields
      );

      // Check to see if we need to archive this thing too
      if ( $uplaod_to_pdf_archive && $survey_id > 0) {
        /**
         * For this we need the survey ID. Because of the way the Paper Trail functionality is designed, this "Instrument" may or may not be a survey
         * IF this instrument that we're currently working wiht is a survey - then use that. If NOT then use the first survey form
         * IF there are no surveys at all then we can't use this functionality
         */
        $pdf_vault_generation = upload_file_to_pdf_archive (
          $pdf_file_details,
          $docId,
          $pdf_content,
          $record,
          $event_id,
          $survey_id,
          $repeat_instance);

        if ( !$pdf_vault_generation ) {
          Logging::logEvent(NULL, "", "OTHER", $record,
            "PaperTrail - Could not upload file to PDF Survey Archive",
            "PaperTrail","", "",
            "", true, null, null, false);
        }
        else {
          Logging::logEvent(NULL, "", "OTHER", $record,
            "PaperTrail - Successfully added \"".$filename."\" to the PDF Survey Archive",
            "PaperTrail","", "",
            "", true, null, null, false);
        }
      }

      unset ($_SESSION['PAPER_TRAIL_PDF']);
      unset ($_SESSION['PAPER_TRAIL_PDF_FORMS']);
    }
  }
  catch ( Exception $ee ) {
    unset ($_SESSION['PAPER_TRAIL_PDF']);
    unset ($_SESSION['PAPER_TRAIL_PDF_FORMS']);
    return false;
  }
}

/**
 * @param $pdf_file_details
 * @param $pdf_edoc_id
 * @param $pdf_contents
 * @param $record
 * @param $event_id
 * @param $survey_id
 * @param $repeat_instance
 * @param null $nameDobText
 * @param null $versionText
 * @param null $typeText
 *
 * @return bool
 */
function upload_file_to_pdf_archive ( $pdf_file_details, $pdf_edoc_id, $pdf_contents, $record, $event_id, $survey_id, $repeat_instance, $nameDobText = null, $versionText = null, $typeText = null) {
  global $pdf_econsent_system_enabled, $pdf_auto_archive, $pdf_econsent_system_ip, $lang, $pdf_custom_header_text;
  $ip = $pdf_econsent_system_ip ? System::clientIpAddress() : "";

  // Check to see if we have another one here
  $lookup_sql = "SELECT doc_id FROM redcap_surveys_pdf_archive WHERE ".
                  "record = '".db_escape($record)."' AND " .
                  "event_id = '".db_escape($event_id)."' AND " .
                   "survey_id = '".db_escape($survey_id)."' AND " .
                  "instance = '".db_escape($repeat_instance)."'";
  $lookup_q = db_query($lookup_sql);
  while ($row = db_fetch_assoc($lookup_q)) {
    // This means that there is a record in there that we have to remove
    $delete_sql = "DELETE FROM redcap_surveys_pdf_archive WHERE ".
                    "doc_id = '".db_escape($row['doc_id'])."' AND ".
                    "record = '".db_escape($record)."' AND " .
                    "event_id = '".db_escape($event_id)."' AND " .
                    "survey_id = '".db_escape($survey_id)."' AND " .
                    "instance = '".db_escape($repeat_instance)."'";
    $del_q = db_query($delete_sql);
    // LOG this action
    $old_file_name = Files::getEdocName(db_escape($row['doc_id']));
    if ( !$old_file_name )
      $old_file_name = "N/A";

    Logging::logEvent(NULL, "", "OTHER", $record,
      "PaperTrail - Updating \"".$old_file_name."\" with new version \"".$pdf_file_details['name']."\"",
      "PaperTrail","", "",
      "", true, null, null, false);
  }

  $sql = "insert into redcap_surveys_pdf_archive (doc_id, record, event_id, survey_id, instance, identifier, version, type, ip) values
				($pdf_edoc_id, '".db_escape($record)."', '".db_escape($event_id)."', '".db_escape($survey_id)."', '".db_escape($repeat_instance)."', 
				".checkNull($nameDobText).", ".checkNull($versionText).", ".checkNull($typeText).", ".checkNull($ip).")";
  $q = db_query($sql);

  if ( !q ) {
    return false;
  }

  try {


    $response=\Files::writeFilePdfAutoArchiverToExternalServer( $pdf_file_details['name'], $pdf_contents);
    // Return boolean regarding success
    Logging::logEvent(NULL, "", "OTHER", $record,
      "PaperTrail - wrote  \"".$pdf_file_details['name']."\" to external storage \"",
      "PaperTrail","", "",
      "", true, null, null, false);
    return $response;
  }
  catch (Exception $e)
  {
    Logging::logEvent(NULL, "", "OTHER", $record,
      "PaperTrail - FAILED TO WRITE  \"".$pdf_file_details['name']."\" to external storage \"",
      "PaperTrail","", "",
      "", true, null, null, false);
    return false;
  }
}