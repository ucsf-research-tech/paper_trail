<?php namespace ExternalModules; ?>

<input type='hidden' name='version' value='<?= $version ?>'>

<div class='external-modules-title'><?= $config['name'] . ' - ' . $version ?>
	<?php if ($system_enabled) print "<span class='label label-warning badge badge-warning'><!--= Enabled for All Projects -->" . ExternalModules::tt("em_manage_22") . "</span>" ?>
	<?php if ($isDiscoverable) print "<span class='label label-info badge badge-info'><!--= Discoverable -->" . ExternalModules::tt("em_manage_23") . "</span>" ?>
</div>
<div class='external-modules-description'>
	<?php echo $config['description'] ? $config['description'] : '';?>
</div>
<div class='external-modules-byline'>
	<?php
		if (SUPER_USER && !isset($_GET['pid'])) {
			if ($config['authors']) {
				$names = array();
				foreach ($config['authors'] as $author) {
					$name = $author['name'];
					$institution = empty($author['institution']) ? "" : " <span class='author-institution'>({$author['institution']})</span>";
					if ($name) {
						if ($author['email']) {
							$names[] = "<a href='mailto:".$author['email']."?subject=".rawurlencode(strip_tags($config['name'])." - ".$version)."'>".$name."</a>$institution";
						} else {
							$names[] = $name . $institution;
						}
					}
				}
				if (count($names) > 0) {
					echo "by ".implode(", ", $names);
				}
			}
		}

		$documentationUrl = ExternalModules::getDocumentationUrl($prefix);
		if(!empty($documentationUrl)){
			?><a href='<?=$documentationUrl?>' style="display: block; margin-top: 7px" target="_blank">
				<i class='fas fa-file' style="margin-right: 5px"></i>
				<!--= View Documentation -->
				<?=ExternalModules::tt("em_manage_24")?>
			</a><?php
		}
	?>
</div>