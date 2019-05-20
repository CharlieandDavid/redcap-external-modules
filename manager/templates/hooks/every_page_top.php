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
};

$menu_id = 'projMenuExternalModules';
?>
<script type="text/javascript">
	$(function () {
		if ($('#project-menu-logo').length > 0 && <?=json_encode(!empty($links))?>) {
			var newPanel = $('#app_panel').clone()
			newPanel.attr('id', 'external_modules_panel')
			newPanel.find('.x-panel-header div:first-child').html("External Modules")
			var menuToggle = newPanel.find('.projMenuToggle')
			var menuId = <?=json_encode($menu_id)?>;
			menuToggle.attr('id', menuId)

			function getLink(icon, name,url, target){
				newLink = exampleLink.clone()
				newLink.find('a').attr('href', url+'&pid=<?= $project_id ?>')
				newLink.find('a').attr('target', target)
				newLink.find('a').html(name);

				var img = $('<img />')
				img.css('vertical-align', '-3px')
				img.css('height', '14px')
				img.css('width', '14px')
				img.attr('src', icon)
				newLink.find('i').replaceWith(img)

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
			if (menubox.children().length) {
				newPanel.insertBefore('#help_panel')
				
				projectMenuToggle('#projMenuExternalModules')

				var shouldBeCollapsed = <?=json_encode(\UIState::getMenuCollapseState($project_id, $menu_id))?>;
				var isCollapsed = menuToggle.find('img')[0].src.indexOf('collapse') === -1
				if(
					(shouldBeCollapsed && !isCollapsed)
					||
					(!shouldBeCollapsed && isCollapsed)
				){
					menuToggle.click()
				}
			}
		}
	})
</script>

<?php

if(ExternalModules::isRoute('DataImportController:index')){
	ExternalModules::callHook('redcap_module_import_page_top', [$project_id]);
}
