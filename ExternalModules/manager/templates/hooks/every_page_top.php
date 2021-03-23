<?php
namespace ExternalModules;

if(!defined('USERID')){
	// Only execute this file for authenticated users.
	// This was added because the redcap_module_import_page_top hook at the bottom of this file was causing error emails,
	// but really nothing should be executing in this file in that case.
	return;
}

set_include_path('.' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../../redcap_connect.php';

$project_id = $arguments[0];

$links = ExternalModules::getLinks();

$menu_id = 'projMenuExternalModules';
?>
<script type="text/javascript">
	$(function () {
		if ($('#project-menu-logo').length > 0 && <?=json_encode(!empty($links))?>) {
			var newPanel = $('#app_panel').clone()
			newPanel.attr('id', 'external_modules_panel')
			newPanel.find('.x-panel-header div:first-child').html("External Modules")
			var menuToggle = newPanel.find('.projMenuToggle')
			var menuId = <?=json_encode($menu_id)?>;
			menuToggle.attr('id', menuId)

			var menubox = newPanel.find('.x-panel-body .menubox .menubox')
			var exampleLink = menubox.find('.hang:first-child').clone()
			menubox.html('')

			<?php
			foreach($links as $_=>$link){

				$prefix = $link['prefix'];
				$module_instance = ExternalModules::getModuleInstance($prefix);

				try{
					$new_link = $module_instance->redcap_module_link_check_display($project_id, $link);
					if($new_link){
						if(is_array($new_link)){
							$link = $new_link;
						}
						// Moved this check here, as it makes no sense to append a link that has no display name
						// (which could be the case after returning from the hook).
						if(empty($link["name"])){ 
							continue;
						}
						?>
						menubox.append(<?=json_encode(ExternalModules::getLinkIconHtml($module_instance, $link))?>);
						<?php
					}
				}
				catch(\Throwable $e){
					ExternalModules::sendAdminEmail(
						//= An exception was thrown when generating links
						ExternalModules::tt("em_errors_77"), 
						$e->__toString(), $prefix);
				}
				catch(\Exception $e){
					ExternalModules::sendAdminEmail(
						//= An exception was thrown when generating links
						ExternalModules::tt("em_errors_77"), 
						$e->__toString(), $prefix);
				}
			}
			?>
            // Only render the newPanel if the menubox contains links
			if (menubox.children().length) {
				newPanel.insertBefore('#help_panel')
				
				projectMenuToggle('#projMenuExternalModules')

				var shouldBeCollapsed = <?=json_encode(\UIState::getMenuCollapseState($project_id, $menu_id))?>;
				var isCollapsed = menuToggle.find('img')[0].src.indexOf('collapse') === -1
				if(
					(shouldBeCollapsed && !isCollapsed)
					||
					(!shouldBeCollapsed && isCollapsed)
				){
					menuToggle.click()
				}
			}
		}
	})
</script>

<?php

if(ExternalModules::isRoute('DataImportController:index')){
	ExternalModules::callHook('redcap_module_import_page_top', [$project_id]);
}
