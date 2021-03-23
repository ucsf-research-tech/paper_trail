<?php

namespace ExternalModules;
require_once __DIR__ . '/../redcap_connect.php';
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';

$numDays = 60;
$enabledModules = ExternalModules::getEnabledModules();
$labelsAndTimedAttributes = array(
					"Hour" => 'cron_hour',
					"Minute" => 'cron_minute',
					"<span title='Sunday is 0, Monday is 1, etc.' style='text-decoration: underline #888 solid;'>Weekday</span>" => 'cron_weekday',
					"Day-of-Month" => 'cron_monthday'
					);


$sep = "___";
$different = array();
if (count($_POST) > 0) {
	$timedAttrs = array_values($labelsAndTimedAttributes);
	$changes = array();
	foreach ($_POST as $key => $value) {
		$nodes = preg_split("/".$sep."/", $key);
		if (count($nodes) == 4) {
			$prefix = $nodes[0];
			$version = $nodes[1];
			$name = $nodes[2];
			$attr = $nodes[3];

			if (!isset($changes[$prefix])) {
				$changes[$prefix] = array();
				$different[$prefix] = array();
			}
			if (!isset($changes[$prefix][$version])) {
				$changes[$prefix][$version] = array();
				$different[$prefix][$version] = array();
			}
			if (!isset($changes[$prefix][$version][$name])) {
				$changes[$prefix][$version][$name] = getBlankTimedCron();
				$different[$prefix][$version][$name] = array();
			}

			# copy over modifications
			if (in_array($attr, $timedAttrs)) {
				# always change the requested value if specified
				if ($value !== "") {
					$changes[$prefix][$version][$name][$attr] = $value;
				}

				# now, mark if different from before; also, copy over remaining items in the cron from before
				$origCronAttrs = ExternalModules::getCronSchedules($prefix);
				foreach ($origCronAttrs as $origCronAttrAry) {
					if ($origCronAttrAry['cron_name'] == $name) {
						# save whether the new value is different from the original
						$different[$prefix][$version][$name][$attr] = (strval($value) !== strval($origCronAttrAry[$attr]));

						# save in changes all values that aren't specifically for timed crons
						# need entire cron array in order to save in setModifiedCrons if applicable
						foreach ($origCronAttrAry as $key => $keyValue) {
							# only set if not previously set; if previously set, it's been changed manually
							if (!in_array($key, $timedAttrs) && !isset($changes[$prefix][$version][$name][$key])) {
								$changes[$prefix][$version][$name][$key] = $keyValue;
							}
						}
					}
				}
			}

			# copy remaining crons from module that aren't already set
			$config = ExternalModules::getConfig($prefix);
			if (isset($config['crons']) && is_array($config['crons'])) {
				foreach ($config['crons'] as $cronAttr) {
					if (($name != $cronAttr['cron_name']) && !isset($changes[$prefix][$version][$cronAttr['cron_name']]))  {
						# copy cron into hash
						$changes[$prefix][$version][$cronAttr['cron_name']] = array();
						foreach ($cronAttr as $key => $keyValue) {
							$changes[$prefix][$version][$cronAttr['cron_name']][$key] = $keyValue;
						}
					}
				}
			}
		}
	}

	# now for those that are different, copy changes into modifications; else, remove modifications
	foreach ($changes as $prefix => $versions) {
		foreach ($versions as $version => $crons) {
			$shouldSet = FALSE;
			$cronAry = array();
			foreach ($crons as $name => $attrs) {
				if (ExternalModules::isValidTimedCron($attrs) || ExternalModules::isValidTabledCron($attrs)) {
					foreach ($attrs as $attr => $value) {
						if ($different[$prefix][$version][$name][$attr]) {
							$shouldSet = TRUE;
						}
					}
					array_push($cronAry, $attrs);
				} else {
					throw new \Exception("The following cron is not valid ".json_encode($attrs));
				}
			}
			if ($shouldSet) {
				ExternalModules::setModifiedCrons($prefix, $cronAry);
			} else {
				ExternalModules::removeModifiedCrons($prefix);
			}
		}
	}
}

# expensive; lower $numDays to speed up; calculates if can run for every minute in timespan
$conflicts = ExternalModules::getCronConflictTimestamps($numDays * 24 * 3600);
$numConflicts = count($conflicts);

?>

<h1>Manager for Timed Crons</h1>

<table style='margin: 0 auto; max-width: 700px;'>
<tr>
	<td style='width: 175px; vertical-align: middle;'><h4 style='margin: 0px;'><?= $numConflicts ?> conflicts<br>in next <?= $numDays ?> days</h4></td>
	<td style='vertical-align: middle;'>A <b>conflict</b> occurs when two crons are run at the same time. If one cron runs long, this could result in delays. Generally, this number should be as low as possible.</td>
</tr>
</table>

<br><br>

<form method='POST'>
<?php

$numTimedCrons = 0;
$spacing = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
foreach ($enabledModules as $moduleDirectoryPrefix=>$version) {
	$cronAttrs = ExternalModules::getCronSchedules($moduleDirectoryPrefix);
	if (!empty($cronAttrs)) {
		$config = ExternalModules::getConfig($moduleDirectoryPrefix, $version, null, true);
		echo "<h3>Module ".$config['name']." ".$version."</h3>\n";
	}
	foreach ($cronAttrs as $cronAttr) {
		$cronId = $moduleDirectoryPrefix.$sep.$version.$sep.$cronAttr['cron_name'];
		if ($cronAttr['method'] && ExternalModules::isValidTimedCron($cronAttr)) {
			$numTimedCrons++;

			$attrs = $labelsAndTimedAttributes;
			$descript = $cronAttr['cron_name'];
			if ($cronAttr['cron_description']) {
				$descript = $cronAttr['cron_description']." (".$cronAttr['cron_name'].")";
			}

			echo "<div style='margin-left: 50px; background-color: #eee; padding: 8px; width: 600px; border: 1px solid #888;'>\n";
			echo "<h4>$descript Attributes</h4>\n";
			$lines = array();
			echo "<p>\n";
			foreach ($attrs as $label => $attr) {
				$value = $cronAttr[$attr];
				if (!isset($value)) {
					$value = "";
				}
				array_push($lines, $label." <input type='text' style='width: 50px;' name='".$cronId.$sep.$attr."' value='$value'>");
			}
			echo implode($spacing, $lines);
			echo "</p>\n";
			echo "</div>\n";
		}
	}
	if (!empty($cronAttrs)) {
		echo "<hr>\n";
	} 
}

if ($numTimedCrons > 0) {
	echo "<p><input type='submit' value='Submit Changes'></p>\n";
}
?>
</form>
<?php

function getBlankTimedCron() {
	return array("cron_minute" => "", "cron_hour" => "",);
}
