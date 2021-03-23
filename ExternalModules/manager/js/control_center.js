$(function () {
	// Make Control Center the active tab
	$('#sub-nav li.active').removeClass('active');
	$('#sub-nav a[href*="ControlCenter"]').closest('li').addClass('active');

	var configureModal = $('#external-modules-configure-modal');
	configureModal.on('show.bs.modal', function (event) {
		var button = $(event.target);
		var moduleName = $(button.closest('tr').find('td')[0]).html();
		configureModal.find('.module-name').html(moduleName);
	});

	$('.external-modules-disable-button').click(function (event) {	
		var button = $(event.target);
		var row = button.closest('tr');
		var module = row.data('module');
		var version = row.data('version');
		$('#external-modules-disable-confirm-modal').modal('show');
		$('#external-modules-disable-confirm-module-name').html(module);
		$('#external-modules-disable-confirm-module-version').html(version);
	});
		
	$('#external-modules-disable-button-confirmed').click(function (event) {
		var button = $(event.target);
		button.attr('disabled', true);
		var module = $('#external-modules-disable-confirm-module-name').text();
		$.post('ajax/disable-module.php', {module: module}, function (data) {
			button.attr('disabled', false);
			$('#external-modules-disable-confirm-modal').modal('hide');
			if (data == 'success') {
				$('#external-modules-enabled tr[data-module="'+module+'"]').remove();    
				if($('#external-modules-enabled tr').length == 0){
					$('#external-modules-enabled').html('None');
				}
			}
			else {
				//= An error occurred while disabling the {0} module:
				alert(ExternalModules.$lang.tt('em_errors_5', module) + ' ' + data);
			}
		});
	});
});
