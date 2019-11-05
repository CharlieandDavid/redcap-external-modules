<?php
namespace ExternalModules\FrameworkVersion3;

require_once __DIR__ . '/../v2/Framework.php';

use Exception;
use SplFileInfo;

class Framework extends \ExternalModules\FrameworkVersion2\Framework
{
	function getRecordIdField($pid = null){
		$pid = db_escape($this->requireProjectId($pid));

		$result = $this->query("
			select field_name
			from redcap_metadata
			where project_id = $pid
			order by field_order
			limit 1
		");

		$row = $result->fetch_assoc();

		return $row['field_name'];
	}

	function getSafePath($path, $root=null){
		$moduleDirectory = $this->module->getModulePath();
		if(!$root){
			$root = $moduleDirectory;
		}
		else if(!file_exists($root)){
			$root = "$moduleDirectory/$root";
		}

		if(!file_exists($root)){
			throw new Exception("The specified root ($root) does not exist as either an absolute path or a relative path to the module directory.");
		}

		$root = realpath($root);

		$fullPath = "$root/$path";

		if(file_exists($fullPath)){
			$fullPath = realpath($fullPath);
		}
		else{
			// Also support the case where this is a path to a new file that doesn't exist yet and check it's parents.
			$dirname = dirname($fullPath);
				
			if(!file_exists($dirname)){
				throw new Exception("The parent directory ($dirname) does not exist.  Please create it before calling getSafePath() since the realpath() function only works on directories that exist.");
			}

			$fullPath = realpath($dirname) . DIRECTORY_SEPARATOR . basename($fullPath);
		}

		if(strpos($fullPath, $root) !== 0){
			throw new Exception("You referenced a path ($fullPath) that is outside of your allowed parent directory ($root).");
		}

		return $fullPath;
	}
}