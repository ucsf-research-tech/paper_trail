$(function(){
	// first show disabledModal and then show enableModal
	var disabledModal = $('#external-modules-disabled-modal');
	var enableModal = $('#external-modules-enable-modal');

	var reloadThisPage = function(){
		$('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
		window.location.reload();
	}

	disabledModal.find('.disable-button').click(function(event){
		var row = $(event.target).closest('tr');
		var title = row.find('td:eq(0)')[0].childNodes[0].textContent.trim();
		var prefix = row.data('module');
		var version = row.find('[name="version"]').val();	
		
		simpleDialog(
			//= Do you wish to delete the module <b>{0}</b> (<b>{1}_{2}</b>)? Doing so will permanently remove the module's directory from the REDCap server.	
			ExternalModules.$lang.tt('em_manage_64', title, prefix, version), 
			//= DELETE MODULE?
			ExternalModules.$lang.tt('em_manage_65'),
			null, null, null, 
			//= Cancel
			ExternalModules.$lang.tt('em_manage_12'), 
			function(){
				showProgress(1);
				$.post('ajax/delete-module.php', { module_dir: prefix+'_'+version },function(data){
					showProgress(0,0);
					if (data == '1') {
						simpleDialog(
							//= An error occurred because the External Module directory could not be found on the REDCap web server.
							ExternalModules.$lang.tt('em_manage_66'),
							//= ERROR
							ExternalModules.$lang.tt('em_manage_30'));
					} else if (data == '0') {
						simpleDialog(
							//= An error occurred because the External Module directory could not be deleted from the REDCap web server.
							ExternalModules.$lang.tt('em_manage_67'),
							//= ERROR
							ExternalModules.$lang.tt('em_manage_30'));
					} else {
						$('#external-modules-disabled-modal').hide();
						simpleDialog(data, 
							//= SUCCESS
							ExternalModules.$lang.tt('em_manage_27'), 
							null,null,function(){
								window.location.reload();
						}, 
						//= Close
						ExternalModules.$lang.tt('em_manage_68'));
					}
				});
			}, 
			//= Delete module
			ExternalModules.$lang.tt('em_manage_63'));
		return false;
	});

	disabledModal.find('.enable-button').click(function(event){
		// Prevent form submission
		event.preventDefault();
		var myClass = $(this).attr('class');
		var classArray = myClass.split(" ");
		disabledModal.hide();

		var row = $(event.target).closest('tr');
		var prefix = row.data('module');
		var version = row.find('[name="version"]').val();

		var enableErrorDiv = $('#external-modules-enable-modal-error');
		enableErrorDiv.html(''); // Clear out any previous errors

		var enableButton = enableModal.find('.enable-button');

		var enableModule = function(){
			var url = 'ajax/enable-module.php'
			if (classArray.includes('module-request')) {
				url = 'ajax/send-enable-module-request.php';
			}
			if (pid) {
				url += '?pid=' + pid
			}

			var showErrorAlert = function(message){
				//= An error occurred while enabling the module:
				var errorPrefix = '';
				if (classArray.includes('module-request')) {
					errorPrefix = ExternalModules.$lang.tt('em_manage_89')+' ';
				}
				else {
					errorPrefix = ExternalModules.$lang.tt('em_manage_69')+' ';
				}
				var message = errorPrefix+' '+message;
				console.log('AJAX Request Error:', message);
				alert(message);
				disabledModal.modal('hide');
				enableModal.modal('hide');
			}

			$.post(url, {prefix: prefix, version: version}, function(data){
				var jsonAjax
				try{
					jsonAjax = jQuery.parseJSON(data);
				}
				catch(e){
					showErrorAlert(data)
					return
				}

				if (typeof jsonAjax != 'object') {
					showErrorAlert(data)
					return
				}

				var errorMessage = jsonAjax['error_message']
				if (errorMessage) {
					if(pid){
						showErrorAlert(errorMessage)
					}
					else{
						enableErrorDiv.show();
						enableErrorDiv.html(errorMessage);
						$('.close-button').attr('disabled', false);
						enableButton.hide();
					}
				}else if (jsonAjax['message'] == 'success') {
					disabledModal.modal('hide');
					enableModal.modal('hide');
					if (classArray.includes('module-request')) {
						simpleDialog(ExternalModules.$lang.tt('em_errors_112'),ExternalModules.$lang.tt('em_manage_27'));
					} else {
						reloadThisPage();
					}
				}
			});
		}

		if (!pid) {
			enableButton.html('Enable');
			enableModal.find('button').attr('disabled', false);

			var list = enableModal.find('.modal-body ul');
			list.html('');
			
			var permissionCount = 0;
			disabledModules[prefix][version].permissions.forEach(function(permission){
				if (permission != "") {
					list.append("<li>" + permission + "</li>");
					permissionCount++;
				}
			});
			if (permissionCount == 0) {
				list.append('<li><i>' + 
					//= None (no permissions requested)
					ExternalModules.$lang.tt('em_manage_70') + 
					'</i></li>');
			}

			enableButton.off('click'); // disable any events attached from other modules
			enableButton.click(function(){
				//= Enabling... 
				enableButton.html(ExternalModules.$lang.tt('em_manage_71')); 
				enableModal.find('button').attr('disabled', true);
				enableModule();
			});
			enableButton.show();
			enableModal.modal('show');
		} else {   // pid
			enableModule();
		}
	});

	if (enableModal) {
		enableModal.on('hide.bs.modal', function(){
			// We used to try to display the previous dialog again here, but it caused some odd edge cases related to multiple dialogs.
			// Simply reloading is cleaner.
			reloadThisPage();
		});
	}
});
