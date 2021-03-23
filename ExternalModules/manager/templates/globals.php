<?php

namespace ExternalModules;
set_include_path('.' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../redcap_connect.php';

if(empty($versionsByPrefixJSON)) {
    $versionsByPrefixJSON = "''";
}

if(empty($configsByPrefixJSON)) {
    $configsByPrefixJSON = "''";
}

ExternalModules::tt_initializeJSLanguageStore();
ExternalModules::tt_transferToJSLanguageStore(array(
	"em_errors_91",
	"em_errors_92",
	"em_errors_93",
	"em_errors_94",
	"em_errors_95",
	"em_errors_96",
	"em_errors_97",
	"em_manage_13",
	"em_manage_72",
	"em_manage_73",
	"em_manage_74",
	"em_manage_75",
	"em_manage_76",
	"em_manage_77",
	"em_manage_78",
	"em_tinymce_language",
));
ExternalModules::addResource(ExternalModules::getManagerJSDirectory().'globals.js');
ExternalModules::addResource(ExternalModules::getManagerJSDirectory().'spin.js');
ExternalModules::addResource(ExternalModules::getManagerJSDirectory().'async.min.js');

if (version_compare(REDCAP_VERSION, '9.8.0', '>=')) {
	?>
    <link rel='stylesheet' href='<?php echo APP_PATH_CSS ?>spectrum.css'>
    <script type='text/javascript' src='<?php echo APP_PATH_JS ?>Libraries/spectrum.js'></script>
	<?php
} else {
	?>
    <script src="<?php echo APP_PATH_JS ?>tinymce/tinymce.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo APP_PATH_CSS ?>select2.css">
    <script type="text/javascript" src="<?php echo APP_PATH_JS ?>select2.js"></script>
    <link rel='stylesheet' href='<?php echo APP_PATH_CSS ?>spectrum.css'>
    <script type='text/javascript' src='<?php echo APP_PATH_JS ?>spectrum.js'></script>
	<?php
}

?>
<script type="text/javascript">
    ExternalModules.PID = <?=json_encode(@$_GET['pid'])?>;
    ExternalModules.SUPER_USER = <?=SUPER_USER?>;
    ExternalModules.KEY_ENABLED = <?=json_encode(ExternalModules::KEY_ENABLED)?>;
    ExternalModules.OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS = <?=json_encode(ExternalModules::OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS)?>;
    ExternalModules.OVERRIDE_PERMISSION_LEVEL_SUFFIX = <?=json_encode(ExternalModules::OVERRIDE_PERMISSION_LEVEL_SUFFIX)?>;
    ExternalModules.BASE_URL = <?=json_encode(ExternalModules::$BASE_URL)?>;
    ExternalModules.configsByPrefixJSON = <?=$configsByPrefixJSON?>;
    ExternalModules.versionsByPrefixJSON = <?=$versionsByPrefixJSON?>;
	ExternalModules.LIB_URL = '<?=APP_URL_EXTMOD_LIB?>login.php?referer=<?=urlencode(APP_URL_EXTMOD)."manager/control_center.php"?>'
		+ '&php_version=<?=urlencode(PHP_VERSION)?>&redcap_version=<?=urlencode(REDCAP_VERSION)?>';
    
    $(function () {
		// Inform IE 8-9 users that this page won't work for them
		if (isIE && IEv <= 9) {
			// Our apologies, but your web browser is not compatible with the External Modules Manager page. We recommend using another browser (e.g., Chrome, Firefox) or else upgrade your current browser to a more recent version. Thanks!
			// ERROR: Web browser not compatible
			simpleDialog(<?=ExternalModules::tt_js("em_errors_73")?>, <?=ExternalModules::tt_js("em_errors_74")?>);
		}
		
        var disabledModal = $('#external-modules-disabled-modal');
        $('#external-modules-enable-modules-button').click(function(){
            var form = disabledModal.find('.modal-body form');
            var loadingIndicator = $('<div class="loading-indicator"></div>');

            var pid = ExternalModules.PID;
            if (!pid) {
                new Spinner().spin(loadingIndicator[0]);
            }
            form.html('');
            form.append(loadingIndicator);

            // This ajax call was originally written thinking the list of available modules would come from a central repo.
            // It may not be necessary any more.
            var url = "ajax/get-disabled-modules.php";
            if (pid) {
                url += "?pid="+pid;
            }
            $.post(url, { }, function (html) {
                form.html(html);
				// Enable module search
				$('input#disabled-modules-search').quicksearch('table#external-modules-disabled-table tbody tr', {
					selector: 'td:eq(0)'
				});
            });

            disabledModal.modal('show');
        });
	$('#external-modules-configure-crons').click(function() {
			window.location.href='<?=APP_URL_EXTMOD?>manager/crons.php';
		});
        $('#external-modules-download-modules-button').click(function(){
			$('#download-new-mod-form').submit();
		});
        $('#external-modules-add-custom-text-button').click(function(){
			$('#external-modules-custom-text-dialog').dialog({ 
				//= Set custom text for Project Module Manager (optional)
				title: <?=ExternalModules::tt_js("em_manage_25")?>, 
				bgiframe: true, modal: true, width: 550, 
				buttons: {
					//= Cancel
					<?=ExternalModules::tt_js("em_manage_12")?>: function() {
						$(this).dialog('close'); 
					},
					//= Save
					<?=ExternalModules::tt_js("em_manage_13")?>: function() { 
						showProgress(1,0);
						$.post(app_path_webroot+'ControlCenter/set_config_val.php',{ settingName: 'external_modules_project_custom_text', value: $('#external_modules_project_custom_text').val() },function(data){
							showProgress(0,0);
							if (data == '1') {
								// The custom text was successfully saved!
								// SUCCESS
								simpleDialog(<?=ExternalModules::tt_js("em_manage_26")?>,
									<?=ExternalModules::tt_js("em_manage_27")?>);
							} else {
								alert(woops);
							}
						});
						$(this).dialog('close'); 
					}
				} 
			});
		});
		var download_module_id = getParameterByName('download_module_id');
		if (isNumeric(download_module_id) && getParameterByName('download_module_name') != '') {
			$('#external-modules-download').dialog({ 
				//= Download external module?
				title: <?=ExternalModules::tt_js("em_manage_28")?>, 
				bgiframe: true, modal: true, width: 550, 
				buttons: {
					//= Cancel
					<?=ExternalModules::tt_js("em_manage_12")?>: function() { 
						modifyURL('<?=PAGE_FULL?>');
						$(this).dialog('close'); 
					},
					//= Download
					<?=ExternalModules::tt_js("em_manage_29")?>: function() { 
						showProgress(1);
						$.get('<?=APP_URL_EXTMOD?>manager/ajax/download-module.php?module_id='+download_module_id,{},function(data){
							showProgress(0,0);
							if (data == '0') {
								simpleDialog(
									//= ERROR
									<?=ExternalModules::tt_js("em_manage_31")?>, 
									//= An error occurred because the External Module could not be found.
									<?=ExternalModules::tt_js("em_manage_30")?>);
							} else if (data == '1') {
								simpleDialog(
									//= ERROR
									<?=ExternalModules::tt_js("em_manage_32")?>, 
									//= An error occurred because the External Module zip file could not be written to the REDCap temp directory before extracting it.
									<?=ExternalModules::tt_js("em_manage_30")?>);
							} else if (data == '2' || data == '3') {
								simpleDialog(
									//= ERROR
									<?=ExternalModules::tt_js("em_manage_33")?>, 
									//= An error occurred because the External Module zip file could not be extracted or could not create a new modules directory on the REDCap web server.
									<?=ExternalModules::tt_js("em_manage_30")?>);
							} else if (data == '4') {
								alert(
									//= ERROR
									<?=ExternalModules::tt_js("em_manage_34")?>, 
								//= PLEASE TRY AGAIN:\nAn unknown error occurred, so the page will now reload to allow you to TRY AGAIN.
									<?=ExternalModules::tt_js("em_manage_30")?>);
								showProgress(1);
								window.location.reload();
								return;
							} else {
								// Append module name to form
								$('#download-new-mod-form').append('<input type="hidden" name="downloaded_modules[]" value="'+getParameterByName('download_module_name')+'">');
								// Remove the downloaded module from the module updates alert
								if ($('.repo-updates').length) {
									var updatesCount = $('#repo-updates-count').html()*1 - 1;
									$('#repo-updates-count').html(updatesCount);
									$('#repo-updates-modid-'+download_module_id).hide();
									if (updatesCount < 1) $('.repo-updates').hide();
								}
								// Success msg
								simpleDialog(data,
									//= SUCCESS
									<?=ExternalModules::tt_js("em_manage_27")?>,
									null,null,function(){
									$('#external-modules-enable-modules-button').trigger('click');
								},"Close");
							}
							modifyURL('<?=PAGE_FULL?>');
						});
						$(this).dialog('close'); 
					}
				} 
			});
		}
    });
</script>
