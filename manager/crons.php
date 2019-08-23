<?php

require_once __DIR__ . '/../classes/ExternalModules.php';

$numDays = 365;   // set to check for year
$enabledModules = ExternalModules::getEnabledModules();
$labelsAndTimedAttributes = array(
					"Hour" => 'cron_hour',
					"Minute" => 'cron_minute',
					"Weekday (Sunday is 0)" => 'cron_weekday',
					"Day-of-Month" => 'cron_monthday'
					);


$sep = "___";
$different = array();
if (count($_POST) > 0) {
	$timedAttrs = array_values($labelsAndTimedAttributes);
	$changes = array();
	foreach ($_POST as $key => $value) {
		$nodes = preg_split("/".$sep."/", $key);
		if (count($nodes) == 3) {
			$prefix = $nodes[0];
			$name = $nodes[1];
			$attr = $nodes[2];

			if (!isset($changes[$prefix])) {
				$changes[$prefix] = array();
				$different[$prefix] = array();
			}
			if (!isset($changes[$prefix][$name])) {
				$changes[$prefix][$name] = getBlankTimedCron();
				$different[$prefix][$name] = array();
			}

			$moduleInstance = self::getModuleInstance($prefix);
			if ($moduleInstance) {
				# copy over modifications
				if (in_array($attr, $timedAttrs)) {
					# always change the requested value
					$changes[$prefix][$name][$attr] = $value;

					# now, mark if different from before; also, copy over remaining items in the cron from before
					$origCronAttrs = $moduleInstance->getCronSchedules();
					foreach ($origCronAttrs as $origCronAttrAry) {
						if ($origCronAttrAry['cron_name'] == $name) {
							# save whether the new value is different from the original
							$different[$prefix][$name][$attr] = (strval($value) === strval($origCronAttrAry[$attr]));
	
							# save in changes all values that aren't specifically for timed crons
							# need entire cron array in order to save in setModifiedCrons if applicable
							foreach ($origCronAttrAry as $key => $keyValue) {
								# only set if not previously set; if previously set, it's been changed manually
								if (!in_array($key, $timedAttrs) && !isset($changes[$prefix][$name][$key])) {
									$changes[$prefix][$name][$key] = $keyValue;
								}
							}
						}
					}
				}

				# copy remaining crons from module that aren't already set
				$config = $moduleInstance->getConfig();
				if (isset($config['crons']) && is_array($config['crons'])) {
					foreach ($config['crons'] as $cronAttr) {
						if (($name != $cronAttr['cron_name']) && !isset($changes[$prefix][$cronAttr['cron_name']]))  {
							# copy cron into hash
							$changes[$prefix][$cronAttr['cron_name']] = array();
							foreach ($cronAttr as $key => $keyValue) {
								$changes[$prefix][$cronAttr['cron_name']][$key] = $keyValue;
							}
						}
					}
				}
			}
		}
	}

	# now for those that are different, copy changes into modifications; else, remove modifications
	foreach ($changes as $prefix => $crons) {
		if (!empty($crons)) {
			$moduleInstance = self::getModuleInstance($prefix);
		} else {
			throw new \Exception("Could not instantiate module '$prefix'!"); 
		}
		$shouldSet = FALSE;
		foreach ($crons as $name => $attrs) {
			if (ExternalModules::isValidCron($attrs)) {
				foreach ($attrs as $attr => $value) {
					if ($different[$prefix][$name][$attr]) {
						$shouldSet = TRUE;
						break;
					}
				}
			} else {
				throw new \Exception("The following cron is not valid ".json_encode($attrs));
			}
		}
		if ($shouldSet) {
			$moduleInstance->setModifiedCrons($crons);
		} else {
			$moduleInstance->removeModifiedCrons();
		}
	}
}

# expensive; lower $numDays to speed up; calculates if can run for every minute in timespan
$numConflicts = count(ExternalModules::getCronConflictTimetamps($numDays * 24 * 3600));

?>

<h1>Manager of Timed Crons</h1>

<table style='margin: auto 0;'>
<tr>
	<td><h2><?= $numConflicts ?> conflicts<br>in next <?= $numDays ?> days</h2></td>
	<td>A <b>conflict</b> occurs when two crons are run at the same time. If one cron runs long, this could result in delays. Generally, this number should be as low as possible.</td>
</tr>
</table>

<form method='POST'>
<?php

$numTimedCrons = 0;
foreach ($enabledModules as $moduleDirectoryPrefix=>$version) {
	$moduleInstance = self::getModuleInstance($moduleDirectoryPrefix);
	$cronAttrs = $moduleInstance->getCronSchedules();
	if (!empty($cronAttrs)) {
		echo "<h3>Attributes for $moduleDirectoryPrefix<h3>\n";
	} 
	foreach ($cronAttrs as $cronAttr) {
		$cronId = $moduleDirectoryPrefix.$sep.$cronAttr['cron_name'];
		if ($cronAttr['method'] && ExternalModules::isValidTimedCron($cronAttr)) {
			$numTimedCrons++;

			$attrs = $labelsAndTimedAttributes;
			echo "<h4>".$cronAttr['cron_name']." Attributes</h4>\n";

			echo "<p>\n";
			$lines = array();
			foreach ($attrs as $label => $attr) {
				$value = $cronAttr[$attr];
				if (!isset($value)) {
					$value = "";
				}
				array_push($lines, $label." <input type='text' style='width: 50px;' name='".$cronId.$sep.$attr."' value='$value'>");
			}
			echo implode("<br>\n", $lines);
			echo "</p>\n";
		}
	}
	if (!empty($cronAttrs)) {
		echo "<hr>\n";
	} 
}

if ($numTimedCrons > 0) {
	echo "<p style='text-align: center;'><input type='submit' value='Submit Changes'></p>\n";
}
?>
</form>
<?php

function getBlankTimedCron() {
	return array("cron_minute" => "", "cron_hour" => "", "cron_weekday" => "", "cron_monthday" => "",);
}
