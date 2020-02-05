<?php
namespace ExternalModules\FrameworkVersion3;

require_once __DIR__ . '/../v2/Framework.php';

use Exception;
use ExternalModules\ExternalModules;
use SplFileInfo;

class Framework extends \ExternalModules\FrameworkVersion2\Framework
{
	// TODO - Remove this class?  In hindsight we may not really need a class for each framework versions (most could likley be shared).
}