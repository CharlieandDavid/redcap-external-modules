<?php
namespace ExternalModules;
require_once dirname(__FILE__) . '/../../classes/ExternalModules.php';
?>

<h3>Enabled Modules</h3>

<table id='external-modules-enabled' class="table">
	<?php

	$configsByName = ExternalModules::getConfigs(ExternalModules::getEnabledModuleNames());

	if (empty($configsByName)) {
		echo 'None';
	} else {
		foreach ($configsByName as $module => $config) {
			?>
			<tr data-module='<?= $module ?>'>
				<td><?= $config['name'] ?></td>
				<td class="external-modules-action-buttons">
					<button class='external-modules-configure-button' data-toggle="modal" data-target="#external-modules-configure-modal">Configure</button>
					<?php if (!isset($_GET['pid'])) { ?>
						<button class='external-modules-disable-button'>Disable</button>
					<?php } ?>
				</td>
			</tr>
			<?php
		}
	}

	?>
</table>