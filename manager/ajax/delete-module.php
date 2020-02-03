<?php
namespace ExternalModules;
require_once dirname(dirname(dirname(__FILE__))) . '/classes/ExternalModules.php';

// There is a super user check inside the following function.
print ExternalModules::deleteModuleDirectory($_POST['module_dir']);