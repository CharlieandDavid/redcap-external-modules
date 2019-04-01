<?php
namespace ExternalModules;
set_include_path('.' . PATH_SEPARATOR . get_include_path());
require_once dirname(__FILE__) . '/../../../classes/ExternalModules.php';

$project_id = $arguments[0];

$links = ExternalModules::getLinks();

$getIcon = function ($icon){
	if (file_exists(ExternalModules::$BASE_PATH . 'images' . DS . $icon . '.png')) {
		$image = ExternalModules::$BASE_URL . 'images/' . $icon . ".png";
	} else {
		$image = APP_PATH_WEBROOT . 'Resources/images/' . $icon . ".png";
	}
	return $image;
}
?>
<script type="text/javascript">
	$(function () {
		if ($('#project-menu-logo').length > 0 && <?=json_encode(!empty($links))?>) {
			var newPanel = $('#app_panel').clone()
			newPanel.attr('id', 'external_modules_panel')
			newPanel.find('.x-panel-header div:first-child').html("External Modules")

			// The following chained blocks were adapted from base.js lines 7147 to 7170 in v6.16.8
			newPanel.find('.projMenuToggle').mouseenter(function() {
				$(this).removeClass('opacity50');
				if (isIE) $(this).find("img").removeClass('opacity50');
			}).mouseleave(function() {
				$(this).addClass('opacity50');
				if (isIE) $(this).find("img").addClass('opacity50');
			}).click(function(){ // Copied from base.js line 7155 in v6.16.8
				var divBox = $(this).parent().parent().find('.x-panel-bwrap:first');
				// Toggle the box
				divBox.toggle('blind','fast');
				// Toggle the image
				var toggleImg = $(this).find('img:first');
				if (toggleImg.prop('src').indexOf('toggle-collapse.png') > 0) {
					toggleImg.prop('src', app_path_images+'toggle-expand.png');
					var collapse = 1;
				} else {
					toggleImg.prop('src', app_path_images+'toggle-collapse.png');
					var collapse = 0;
				}
				// Send ajax request to save cookie
				$.post(app_path_webroot+'ProjectGeneral/project_menu_collapse.php?pid='+ExternalModules.PID, { menu_id: $(this).prop('id'), collapse: collapse });
			});

			function getLink(icon, name,url, target){
				newLink = exampleLink.clone()
				newLink.find('img').attr('src', icon)
				newLink.find('a').attr('href', url+'&pid=<?= $project_id ?>')
				newLink.find('a').attr('target', target)
				newLink.find('a').html(name);
				return(newLink);
			}

			var menubox = newPanel.find('.x-panel-body .menubox .menubox')
			var exampleLink = menubox.find('.hang:first-child').clone()
			menubox.html('')

			var newLink;
			<?php
			foreach($links as $name=>$link){
				$prefix = $link['prefix'];
				$module_instance = ExternalModules::getModuleInstance($prefix);

				try{
					$new_link = $module_instance->redcap_module_link_check_display($project_id, $link);
					if($new_link){
						if(is_array($new_link)){
							$link = $new_link;
						}

						?>
						newLink = getLink('<?=$getIcon($link['icon'])?>', '<?= $name ?>','<?=$link['url']?>', '<?= $link['target'] ?>');
						menubox.append(newLink);
						<?php
					}
				}
				catch(\Exception $e){
					ExternalModules::sendAdminEmail("An exception was thrown when generating links", $e->__toString(), $prefix);
				}
			}
			?>
            // Only render the newPanel if the menubox contains links
			if (menubox.children().length) newPanel.insertBefore('#help_panel')
		}
	})
</script>

<?php

if(ExternalModules::isRoute('DataImportController:index')){
	ExternalModules::callHook('redcap_module_import_page_top', [$project_id]);
}
