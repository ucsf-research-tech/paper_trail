<?php
namespace ExternalModules;
set_include_path('.' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../../redcap_connect.php';
$extModLinks = ExternalModules::getLinks();
if (!empty($extModLinks)) {
?>
<script type="text/javascript">
	$(function () {
		var items = '';
		<?php
        foreach($extModLinks as $name=>$link){
            $prefix = $link['prefix'];
            $module_instance = ExternalModules::getModuleInstance($prefix);
            try {
                $new_link = $module_instance->redcap_module_link_check_display(null, $link);
                if ($new_link) {
                    if (is_array($new_link)) {
                        $link = $new_link;
                    }
					?>
					items += <?=json_encode(ExternalModules::getLinkIconHtml($module_instance, $link))?>;
					<?php
                }
            } catch(\Throwable $e) {
                ExternalModules::sendAdminEmail(
					//= An exception was thrown when generating control center links
					ExternalModules::tt("em_errors_78"),
					$e->__toString(), $prefix);
			} catch(\Exception $e) {
				ExternalModules::sendAdminEmail(
					//= An exception was thrown when generating control center links
					ExternalModules::tt("em_errors_78"),
					$e->__toString(), $prefix);
			}
		}
		?>
		if (items != '') {
			var menu = $('#control_center_menu');
			menu.append('<div class="cc_menu_divider"></div>');
			menu.append('<div class="cc_menu_section">');
			menu.append('<div class="cc_menu_header"><?=strip_tags(ExternalModules::tt("em_manage_57"))?></div>'); //= External Modules
			menu.append(items);
			menu.append('</div>');
		}
	})
</script>
<?php
}
