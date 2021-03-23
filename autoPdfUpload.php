<?php
//© 2020 Partners HealthCare System, Inc. / Mass General Brigham
//All Rights Reserved.
namespace Partners\autoPdfUpload;
use \REDCap as REDCap;
use \Files as Files;
include_once dirname(__FILE__)."/classes/common.php";

class autoPdfUpload extends \ExternalModules\AbstractExternalModule
{
    function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        $main_ulr = __DIR__ . '/auto_pdf_upload_main.php';
        if (!@include($main_ulr)) ;
    }

//    CSS changes to upload field
    function redcap_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        $css_ulr = __DIR__ . '/ui/hide_upload_labels.php';
        if (!@include($css_ulr)) ;
    }

    function redcap_every_page_top($project_id)
    {
        if (self::getProjectSetting('hide_simple_use_case',$project_id) == true and
            strstr(PAGE,"ExternalModules/manager/project.php?pid=") and
            strlen(strstr(PAGE,"ExternalModules/manager/project.php?pid=")) > 0)
        {
            $script_longitudinal = <<<SCRIPT
<script type="text/javascript">

 document.addEventListener("DOMNodeInserted", function(event){
    var element = event.target;
    if (element.tagName == 'TR') {
        if (typeof element.attributes.field.nodeValue !== 'undefined'){
            if (element.attributes.field.nodeValue == "description_cron") {
                $(".external-modules-input-td input[type='radio'][name='paper_trail_type'][value='ppt_1']").hide();
                $(".external-modules-input-td input[type='radio'][name='paper_trail_type'][value='ppt_1'] ").next().hide();
            }
        }
    }
});
            </script>
SCRIPT;

            $script_non_longitudinal = <<<SCRIPT
<script type="text/javascript">

 document.addEventListener("DOMNodeInserted", function(event){
    var element = event.target;
    if (element.tagName == 'TR') {       
         if (typeof element.attributes.field !== 'undefined'){             
          if(element.getAttribute("field") == "multi_uc_instance"){                
                $(".external-modules-input-td input[type='radio'][name='paper_trail_type'][value='ppt_1']").hide();
                $(".external-modules-input-td input[type='radio'][name='paper_trail_type'][value='ppt_1'] ").next().hide();
                $('table').find('tr[field="multi_event_name"]').dialog({ autoOpen: false });
                }
         }
    }
    
});
            </script>
SCRIPT;

            global $Proj;
            $script = $Proj->longitudinal == TRUE ? $script_longitudinal : $script_non_longitudinal;

            print $script;
        }
    }

  /**
   * Hoook to alter the metadata for the final PDF generation
   * This will, in theory, be executed ONLY by the paper trail hook
   *
   * ATTRIBUTION: A lot of this code and the concept of calling a hook FROM A HOOK!!! was borrowed from Andy Martin's code:
   * https://github.com/susom/multi-signature-consent/blob/master/MultiSignatureConsent.php
   * 99.8% of the code in this function is Andy's code
   *
   * @param $project_id
   * @param $metadata
   * @param $data
   * @param null $instrument
   * @param null $record
   * @param null $event_id
   * @param int $instance
   *
   * @return array
   */
    function redcap_pdf ( $project_id, $metadata, $data, $instrument = NULL, $record = NULL, $event_id = NULL, $instance = 1 ) {
      if (isset ($_SESSION['PAPER_TRAIL_PDF']) && $_SESSION['PAPER_TRAIL_PDF'] == true ) {
        if (isset ($_SESSION['PAPER_TRAIL_PDF_FORMS']) && is_array($_SESSION['PAPER_TRAIL_PDF_FORMS']) && count($_SESSION['PAPER_TRAIL_PDF_FORMS']) > 0) {
          // Build metadata from all forms
          global $Proj;

          // GET the forms that need to be generated and all of their fields
          $new_meta = [];
          $fields = [];
          foreach ($Proj->metadata as $field_name => $field_meta) {
            if (in_array($field_meta['form_name'], $_SESSION['PAPER_TRAIL_PDF_FORMS'])) {
              // Skip form_complete fields
              if ($field_meta['form_name'] . "_complete" == $field_meta['field_name']) {
                continue;
              }
              // Skip @HIDDEN-PDF fields
              if (strpos($field_meta['misc'], '@HIDDEN-PDF') !== FALSE) {
                continue;
              }

              $new_meta[] = $field_meta;
              $fields[] = $field_name;
            }
          }

          // Get the updated data
          $new_data = \REDCap::getData('array', $record, $fields, $event_id);

          return ['metadata' => $new_meta, 'data' => $new_data];
        }
      }
    }
}
