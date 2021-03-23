$(function(){
	$('#update-all-modules, .update-single-module').click(function(){		
		updateEnableModule($(this).data('module-info').split(';'), 0, ($(this).prop('id') == 'update-all-modules'));
	});
});
function updateEnableModule(moduleUpdatesInfo, moduleUpdatesKey, updateAll) 
{
	if (moduleUpdatesKey == 0) showProgress(1);
	else if (moduleUpdatesKey >= moduleUpdatesInfo.length) {
		// The process has finished, so give confirmation
		showProgress(0,0);
		var modulesFailedUpdate = updateAll ? $('#repo-updates-count').html()*1 : 0;
		if (modulesFailedUpdate == 0) {
			var title = ExternalModules.$lang.tt('em_manage_27'); //= SUCCESS
			var msg = (moduleUpdatesKey == 1) ? 
				//= The module was successfully updated and enabled.
				ExternalModules.$lang.tt('em_manage_79') :
				//= All {0} modules were successfully updated and enabled.
				ExternalModules.$lang.tt('em_manage_80', moduleUpdatesKey);
		} else {
			var title = ExternalModules.$lang.tt('em_manage_81'); //= SUCCESS + ERRORS
			var msg = ExternalModules.$lang.tt('em_manage_82', moduleUpdatesKey, modulesFailedUpdate); //= {0} modules were successfully updated and enabled, but {1} were not able to be updated for unknown reasons.
		}
		simpleDialog('<div style="color:green;"><i class="fas fa-check"></i> '+msg+'</div>',title,null,null,'window.location.reload();',$lang.tt('em_manage_68')); //= Close
		return;
	}
	var attr = moduleUpdatesInfo[moduleUpdatesKey].split(',');
	// Download this module
	$.get(ext_mod_base_url+'manager/ajax/download-module.php?module_id='+attr[0],{},function(data){
		if (!isNumeric(data) && data != '') {			
			// Append module name to form
			$('#download-new-mod-form').append('<input type="hidden" name="downloaded_modules[]" value="'+attr[1]+'_'+attr[2]+'">');
			// Remove the downloaded module from the module updates alert
			if ($('.repo-updates').length) {
				var updatesCount = $('#repo-updates-count').html()*1 - 1;
				$('#repo-updates-count').html(updatesCount);
				$('#repo-updates-modid-'+attr[0]).hide();
				if (updatesCount < 1) $('.repo-updates').hide();
			}
			// Enable this module
			$.post(ext_mod_base_url+'manager/ajax/enable-module.php',{prefix: attr[1], version: attr[2]},function(data){
				// Process next module
				updateEnableModule(moduleUpdatesInfo, ++moduleUpdatesKey);				
			});
		} else {
			// Process next module
			updateEnableModule(moduleUpdatesInfo, ++moduleUpdatesKey);				
		}
	});
}