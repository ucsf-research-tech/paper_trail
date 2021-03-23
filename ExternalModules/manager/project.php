<?php
namespace ExternalModules;

use REDCap;

require_once __DIR__ . '/../redcap_connect.php';
require_once ExternalModules::getProjectHeaderPath();

if(!ExternalModules::hasDesignRights() && !ExternalModules::hasDiscoverableModules()){
	//= You don't have permission to manage external modules on this project.
	echo ExternalModules::tt("em_errors_72"); 
	return;
}

?>

<h4 style="margin-top: 0;">
	<i class="fas fa-cube"></i>
	<!--= External Modules - Project Module Manager -->
	<?=ExternalModules::tt("em_manage_8")?>
</h4>

<?php
ExternalModules::safeRequireOnce('manager/templates/enabled-modules.php');
?>

<style>
	#external-modules-configure-modal th:nth-child(2),
	#external-modules-configure-modal td:nth-child(3) {
		text-align: center;
	}
</style>

<div id="external-modules-configure-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">
					<!--= Conifure Module: -->
					<?=ExternalModules::tt("em_manage_9")?> 
					<span class="module-name"></span>
				</h4>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<div style='font-size: 14px; color: #212529; margin-left: 12px;'>
					<!--= <b>Project:</b> {0} -->
					<?=ExternalModules::tt("em_manage_88", REDCap::getProjectTitle())?>
				</div>
				<table class="table table-no-top-row-border">
					<thead>
						<tr>
							<th>
								<!--= Settings -->
								<?=ExternalModules::tt("em_manage_10")?>
							</th>
							<th style='text-align: center;'>
								<!--= Values -->
								<?=ExternalModules::tt("em_manage_11")?>
							</th>
							<th style='min-width: 75px; text-align: center;'></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
			<div class="modal-footer">
				<button data-dismiss="modal">
					<!--= Cancel -->
					<?=ExternalModules::tt("em_manage_12")?>
				</button>
				<button class="save">
					<!--= Save -->
					<?=ExternalModules::tt("em_manage_13")?>
				</button>
			</div>
		</div>
	</div>
</div>

<?php

ExternalModules::tt_initializeJSLanguageStore();
ExternalModules::tt_transferToJSLanguageStore("em_manage_69");
ExternalModules::addResource(ExternalModules::getManagerJSDirectory().'project.js');

require_once ExternalModules::getProjectFooterPath();
