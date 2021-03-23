<?php
namespace ExternalModules;

require_once __DIR__ . '/../redcap_connect.php';

// Only administrators can enable/disable modules
if (!SUPER_USER || !is_numeric($_GET['request_id']) || !is_numeric($_GET['pid'])) exit("ERROR");

// Get current version of module
$prefix = $_GET['prefix'];
$version = ExternalModules::getModuleVersionByPrefix($prefix);
$config = ExternalModules::getConfig($prefix, $version, null, true);
$module_name = strip_tags($config["name"]); // Strip tags for display in errors.
// Is module enabled already?
$enabledModules = ExternalModules::getEnabledModules($project_id);
if (isset($enabledModules[$prefix])) exit("ERROR: External module \"{$module_name}\" is already enabled for this project!");

require_once ExternalModules::getProjectHeaderPath();

?>
<h4 style="margin-top: 0;">
	<i class="fas fa-cube"></i>
    Activate External Module for Project (User Request)
</h4>

<div class="my-4 external-module-activation-request" style="max-width:600px;">
    <input type="hidden" id="external-module-version" value="<?=$version?>">
    <table class="table table-no-top-row-border">
        <tr data-module="<?=$prefix?>" data-version="<?=$version?>">
            <td class="align-middle">
                <div class="external-modules-title font-weight-bold"><?=\RCView::escape($config['name']." - ".$version)?></div>
                <div class="external-modules-description">
                    <?=\RCView::escape($config['description'])?>
                </div>
            </td>
            <td class="external-modules-action-buttons align-middle">
                <button class="enable-button"><?=ExternalModules::tt("em_manage_59")?></button>
            </td>
        </tr>
    </table>
</div>

<div id="external-module-activation-request-dialog" class="simpleDialog" title="Enable module '<?=\RCView::escape($config['name']." - ".$version)?>'?">
    <div class="external-modules-title font-weight-bold"><?=\RCView::escape($config['name']." - ".$version)?></div>
    <div class="external-modules-description">
		<?=\RCView::escape($config['description'])?>
    </div>
</div>
<?php

ExternalModules::addResource(ExternalModules::getManagerJSDirectory().'project.js');

require_once ExternalModules::getProjectFooterPath();
