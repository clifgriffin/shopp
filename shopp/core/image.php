<?php
$db =& DB::get();
require("model/Asset.php");
$settings = $db->query("SELECT name,value WHERE name='image_storage' OR name='image_path'",AS_ARRAY);
foreach ($settings as $setting) {
	if ($setting->name == "image_storage") $storage = $setting->value;
	if ($setting->name == "image_path") $path = $setting->value;
}

if (isset($_GET['shopp_image'])) $image = $_GET['shopp_image'];
elseif (preg_match('/\/images\/(\d+).*$/',$_SERVER['REQUEST_URI'],$matches)) 
	$image = $matches[1];

if (empty($image)) die();
$Asset = new Asset($image);
header ("Content-type: ".$Asset->properties['mimetype']);
header ("Content-Disposition: inline; filename='".$Asset->name."'"); 
header ("Content-Description: Delivered by WordPress/Shopp ".SHOPP_VERSION);
if ($storage == "fs") {
	header ("Content-length: ".@filesize($path.$Asset->name)); 
	readfile($path.$Asset->name);
} else {
	header ("Content-length: ".strlen($Asset->data)); 
	echo $Asset->data;
} 
exit();

?>