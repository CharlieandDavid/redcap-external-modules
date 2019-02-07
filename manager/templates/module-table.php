<?php namespace ExternalModules; ?>

<input type='hidden' name='version' value='<?= $version ?>'>

<div class='external-modules-title'><?= $config['name'] . ' - ' . $version ?>
	<?php if ($system_enabled) print "<span class='label label-warning badge badge-warning'>Enabled for All Projects</span>" ?>
	<?php if ($isDiscoverable) print "<span class='label label-info badge badge-info'>Discoverable</span>" ?>
</div>
<div class='external-modules-description'>
	<?php echo $config['description'] ? $config['description'] : '';?>
</div>
<div class='external-modules-byline'>
	<?php
	if (SUPER_USER && !isset($_GET['pid'])) {
		$renderContact = function($name, $email, $institution) use ($config, $version){
			if ($name) {
				if ($email) {
					return "<a href='mailto:".$email."?subject=".rawurlencode($config['name']." - ".$version)."'>".$name."</a>$institution";
				} else {
					return $name . $institution;
				}
			}
		};

		$authorRole = 'Created';
		if($supportInfo['overridden']){
			echo '<p>Supported by ' . $renderContact($supportInfo['entity'], $supportInfo['email'], null) . '</p>';
		}
		else if ($supportInfo['support_end_date']){
			$authorRole .= ' & Supported';
		}

		$names = array();
		foreach ($config['authors'] as $author) {
			$name = @$author['name'];
			$email = @$author['email'];
			$institution = empty(@$author['institution']) ? "" : " <span class='author-institution'>({$author['institution']})</span>";

			$names[] = $renderContact($name, $email, $institution);
		}

		echo "<p>$authorRole by " . implode($names, ", ") . "</p>";
	}

	$documentationUrl = ExternalModules::getDocumentationUrl($prefix);
	if(!empty($documentationUrl)){
		?><a href='<?=$documentationUrl?>' style="display: block; margin-top: 7px" target="_blank"><i class='fas fa-file' style="margin-right: 5px"></i>View Documentation</a><?php
	}
	?>
</div>