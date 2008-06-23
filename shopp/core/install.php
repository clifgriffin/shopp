<?php
$db =& DB::get();

// Install/Upgrade tables

if (!file_exists(SHOPP_DBSCHEMA)) {
 	trigger_error("Could not install the shopp database tables because the table definitions file is missing: ".SHOPP_DBSCHEMA);
	exit();
}

$db->loaddata(file_get_contents(SHOPP_DBSCHEMA));

// Auto-Generate pages

// Setup default settings


?>