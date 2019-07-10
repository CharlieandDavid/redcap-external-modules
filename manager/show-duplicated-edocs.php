<?php
namespace ExternalModules;
require_once __DIR__ . '/../classes/ExternalModules.php';
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';

?>
<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

<style>
	#pagecontainer {
		max-width: 1200px;
	}

	#control_center_window{
		max-width: 100%;
		flex: 1;
	}
</style>

<h5 style='margin:10px'>Unsafe Module File References</h5>
<p>
	In previous versions of REDCap, eDoc ID's were copied as-is along with other module settings when projects were copied.
	If an eDoc referenced by multiple projects was deleted in any of them, it would then unexpectedly no longer exist for the other projects referencing it.
	Newer reversions of REDCap automatically make new copies of eDocs referenced in module settings when projects are copied.
	Below is a list of eDocs unsafely referenced in module settings outside the project for which it was uploaded.
	To remove unsafe references, go through and re-upload the file for each module setting listed below.
	If you have a PHP Developer at your institution, it is possible to do this programmatically as follows:
	<ol>
		<li>Only consider proceeding at your own risk.  The following method has not been heavily tested.</li>
		<li>Backup ALL module settings for affected projects (ex: a dump or query of the <b>redcap_external_module_settings</b> table).</li>
		<li>Be prepared to restore module settings from your backup in case the following call corrupts them.</li>
		<li>Create a plugin or module and call the following method for each "Referencing Project" ID listed: <b>\ExternalModules\ExternalModules::recreateAllEDocs($pid);</b></li>
	</ol>
</p>
<?php

$references = ExternalModules::getUnsafeEDocReferences();
if(empty($references)){
	echo "<br><h6>Congratulations, no unsafe references exist!</h6>";
}
else{
	echo "<table class='table'>";

	foreach(['EDoc ID', 'Source Project', 'Referencing Project', 'Module', 'Setting Key'] as $value){
		echo "<th>$value</th>";
	}

	foreach($references as $reference){
		echo "<tr>";
		foreach([$reference['edocId'], $reference['sourcePid'], $reference['pid'], $reference['prefix'], $reference['key']] as $value){
			echo "<td>$value</td>";
		}
		echo "</tr>";
	}

	echo "</table>";
}

require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';