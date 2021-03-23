<?php
//© 2020 Partners HealthCare System, Inc. / Mass General Brigham
//All Rights Reserved.
namespace Partners\autoPdfUpload;
//use function PartnersMGB\DataRollup\logThis;
use \REDCap as REDCap;
use \Files as Files;
use ExternalModules\ExternalModules as EM;

//include "debugLog/logFunction.php";

global $Proj;

$paper_trail_type = $this->getProjectSetting('paper_trail_type');
//logThis2($paper_trail_type,$project_id);

if ($paper_trail_type == 'ppt_1') {
    $main_ulr = __DIR__ . '/use_cases/single_uc.php';
    if (!@include($main_ulr)) ;
}

if ($paper_trail_type == 'ppt_2') {
    foreach ($this->getProjectSetting('multi_use_case_name') as $k =>$v){
        // Get settings per each use-case
        $multi_use_case_name = $this->getProjectSetting('multi_use_case_name')[$k];
        $multi_enable_cron = $this->getProjectSetting('multi_enable_cron')[$k];                         // Done
        $multi_pdf_form = $this->getProjectSetting('multi_pdf_form')[$k];                               // Done
        $multi_target_field = $this->getProjectSetting('multi_target_field')[$k];                       // Done
        $multi_event_name = $this::getProjectSetting('multi_event_name')[$k];                           // Done
        $multi_complete_stat = $this->getProjectSetting('multi_complete_stat')[$k];                     // Done
        $multi_file_prefix = $this->getProjectSetting('multi_file_prefix')[$k];                         // Done
        $multi_enable_survey_archive = $this->getProjectSetting('multi_enable_survey_archive')[$k];     // Done
        $multi_upload_type = $this->getProjectSetting('multi_upload_type')[$k];                         // Done
        $multi_not_null_fields = $this->getProjectSetting('multi_not_null_fields')[$k];                 // Done
        $multi_trigger_field = $this->getProjectSetting('multi_trigger_field')[$k];                     // Done
        $multi_hide_css = $this->getProjectSetting('multi_hide_css')[$k];
        $main_ulr = __DIR__ . '/use_cases/multi_uc_v01.php';
        if (!@include($main_ulr)) ;
    }
}

?>