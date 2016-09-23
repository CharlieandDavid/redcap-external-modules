<?php
/**
 * Created by PhpStorm.
 * User: mceverm
 * Date: 9/22/2016
 * Time: 10:30 AM
 */

namespace ExternalModules;

require_once 'header.php';
require_once __DIR__ . '/../../' . APP_PATH_WEBROOT . 'ProjectGeneral/header.php';

Modules::addResource('css/style.css');

require_once 'templates/installed-modules.php';

?>

<style>
	#modules-configure-modal th:nth-child(2),
	#modules-configure-modal td:nth-child(3) {
		text-align: center;
	}
</style>

<div id="modules-configure-modal" class="modal fade" role="dialog" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title">Configure Module: <span class="module-name"></span></h4>
			</div>
			<div class="modal-body">
				<table class="table table-no-top-row-border">
					<tr>
						<th colspan="2">Project Settings</th>
						<th>Override Default</th>
					</tr>
					<tr>
						<td>
							<label>Enable on this project: </label>
						</td>
						<td>
							<input type="checkbox" name="enabled">
						</td>
						<?= Modules::getProjectSettingOverrideCheckbox() ?>
					</tr>
					<tr>
						<td>
							<label>Module Defined Setting 1:</label>
						</td>
						<td>
							<input name="module-setting-1">
						</td>
						<?= Modules::getProjectSettingOverrideCheckbox() ?>
					</tr>
					<tr>
						<td>
							<label>Module Defined Setting 2:</label>
						</td>
						<td>
							<input type="checkbox" name="module-setting-2">
						</td>
						<?= Modules::getProjectSettingOverrideCheckbox() ?>
					</tr>
					<tr>
						<td>
							<label>Module Defined Project Setting 1:</label>
						</td>
						<td>
							<input name="module-setting-1">
						</td>
						<td></td>
					</tr>
					<tr>
						<td>
							<label>Module Defined Project Setting 2:</label>
						</td>
						<td>
							<input type="checkbox" name="module-setting-2">
						</td>
						<td></td>
					</tr>
				</table>
			</div>
			<div class="modal-footer">
				<button class="btn" data-dismiss="modal">Cancel</button>
				<button class="btn">Save</button>
			</div>
		</div>
	</div>
</div>
