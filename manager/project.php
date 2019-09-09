<?php
namespace ExternalModules;

use REDCap;

require_once __DIR__ . '/../classes/ExternalModules.php';
require_once ExternalModules::getProjectHeaderPath();

if(!ExternalModules::hasDesignRights() && !ExternalModules::hasDiscoverableModules()){
	echo "You don't have permission to manage external modules on this project.";
	return;
}

?>

<h4 style="margin-top: 0;">
	<i class="fas fa-cube"></i>
	External Modules - Project Module Manager
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
				<h4 class="modal-title">Configure Module: <span class="module-name"></span></h4>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<div style='font-size: 14px; color: #212529; margin-left: 12px;'>
					<b>Project:</b> <?=REDCap::getProjectTitle()?>
				</div>
				<table class="table table-no-top-row-border">
					<thead>
						<tr>
							<th>Settings</th>
							<th style='text-align: center;'>Values</th>
							<th style='min-width: 75px; text-align: center;'></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
			<div class="modal-footer">
				<button data-dismiss="modal">Cancel</button>
				<button class="save">Save</button>
			</div>
		</div>
	</div>
</div>

<?php

ExternalModules::addResource(ExternalModules::getManagerJSDirectory().'project.js');

require_once ExternalModules::getProjectFooterPath();
