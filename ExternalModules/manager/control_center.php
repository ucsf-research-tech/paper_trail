<?php
namespace ExternalModules;
require_once __DIR__ . '/../redcap_connect.php';
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
ExternalModules::storeREDCapRepoUpdatesInConfig(@$_GET['module_updates'], !isset($_GET['download_module_id']));

?>

<h4 style="margin-top:0;" class="clearfix">
	<div class="pull-left float-left">
		<i class="fas fa-cube"></i>
		<!--= External Modules - Module Manager -->
		<?=ExternalModules::tt("em_manage_14")?>
	</div>
    <?php if (ExternalModules::isAdminWithModuleInstallPrivileges()) { ?>
        <div class="pull-right float-right" style="margin-top:5px;">
            <button id="external-modules-add-custom-text-button" class="btn btn-defaultrc btn-xs">
                <i class="fas fa-pencil-alt"></i>
                <!--= Set custom text for Project Module Manager page -->
                <?=ExternalModules::tt("em_manage_15")?>
            </button>
        </div>
    <?php } ?>
</h4>

<div id="external-modules-custom-text-dialog" class="simpleDialog" role="dialog">
	<!--= 
		You may optionally provide custom text in the text box below that will appear to users on the External Modules "Project Module Manager" page in each project.
		It may be useful to provide some custom text to users for any of the following reasons: 
		1) To make users aware of institutional policies or procedures required before an administrator can enable a module, 
		2) To display guidelines (or a link to an external page with guidelines) regarding the usage of particular modules at your institution, 
		or 3) To bring to the user's attention anything that might be helpful regarding particular modules or External Modules in general.
	-->
	<?=ExternalModules::tt("em_manage_16")?>
	<br><br>
	<!--= Custom text displayed on Project Module Manager page: -->
	<?=ExternalModules::tt("em_manage_17")?>
	<br>
	<textarea id="external_modules_project_custom_text" class="x-form-field notesbox"><?=htmlspecialchars($external_modules_project_custom_text, ENT_QUOTES)?></textarea>
	<div class="cc_info">
		<!--= NOTE: HTML may be used in order to adjust the style of the text or to display links, images, etc. -->
		<?=ExternalModules::tt("em_manage_18")?>
	</div>
</div>

<?php
if (version_compare(REDCAP_VERSION, '9.8.0', '<')) {
	?><script type="text/javascript" src="<?php echo APP_PATH_JS ?>tinymce/tinymce.min.js"></script><?php
}

ExternalModules::safeRequireOnce('manager/templates/enabled-modules.php');
?>

<div id="external-modules-enable-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">
					<!--= Enable Module: -->
					<?=ExternalModules::tt("em_manage_19")?> 
					<span class="module-name"></span>
				</h4>
				<button type="button" class="close close-button" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<div id="external-modules-enable-modal-error"></div>
				<p>
					<!--= This module requests the following permissions:-->
					<?=ExternalModules::tt("em_manage_20")?>
				</p>
				<ul></ul>
			</div>
			<div class="modal-footer">
				<button class="close-button" data-dismiss="modal">
					<!--= Cancel -->
					<?=ExternalModules::tt("em_manage_12")?>
				</button>
				<button class="enable-button"></button>
			</div>
		</div>
	</div>
</div>

<div id="external-modules-configure-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">
					<!--= Configure Module: -->
					<?=ExternalModules::tt("em_manage_9")?>
					<span class="module-name"></span>
				</h4>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<table class="table table-no-top-row-border">
					<thead>
						<tr>
							<th colspan="3">
								<!--= System Settings for All Projects -->
								<?=ExternalModules::tt("em_manage_21")?>
							</th>
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
//= An error occurred while disabling the {0} module:
ExternalModules::tt_transferToJSLanguageStore("em_errors_5"); 
ExternalModules::addResource(ExternalModules::getManagerJSDirectory().'control_center.js'); 
?>

<?php

require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';
