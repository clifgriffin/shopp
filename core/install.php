<?php
/**
 * install.php
 * Performs the initial database setup
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 4 April, 2008
 * @package Shopp
 **/

global $wpdb,$wp_rewrite,$wp_version,$table_prefix;
$db = DB::get();

// Install tables
if (!file_exists(SHOPP_DBSCHEMA)) {
 	trigger_error("Could not install the shopp database tables because the table definitions file is missing: ".SHOPP_DBSCHEMA);
	exit();
}

ob_start();
include(SHOPP_DBSCHEMA);
$schema = ob_get_contents();
ob_end_clean();

// Check if development version tables exist without the WP $table_prefix
// Remove this transitionary code in official release
$setting = substr(DatabaseObject::tablename('setting'),strlen($table_prefix));
$devtable = $db->query("SHOW CREATE TABLE `$setting`");
if ($devtable->Table == $setting) {
	$devtables = array('shopp_asset', 'shopp_billing', 'shopp_cart', 'shopp_catalog', 'shopp_category', 'shopp_customer', 'shopp_discount', 'shopp_price', 'shopp_product', 'shopp_promo', 'shopp_purchase', 'shopp_purchased', 'shopp_setting', 'shopp_shipping', 'shopp_spec', 'shopp_tag');
	
	$renaming = "";
	foreach ($devtables as $oldtable) $renaming .= ((empty($renaming))?"":", ")."$oldtable TO $table_prefix$oldtable";
	$db->query("RENAME TABLE $renaming");
} else {
	$db->loaddata($schema);
	unset($schema);
}

$parent = 0;
foreach ($this->Flow->Pages as $key => &$page) {
	if (!empty($this->Flow->Pages['catalog']['id'])) $parent = $this->Flow->Pages['catalog']['id'];
	$query = "INSERT $wpdb->posts SET post_title='{$page['title']}',
										post_name='{$page['name']}',
										post_content='{$page['content']}',
										post_parent='$parent',
										post_author='1',
										post_status='publish',
										post_type='page',
										post_date=now(),
										post_date_gmt=utc_timestamp(),
										post_modified=now(),
										post_modified_gmt=utc_timestamp(),
										comment_status='closed',
										ping_status='closed',
										menu_order='$i'";
										
	$wpdb->query($query);
	$page['id'] = $wpdb->insert_id;
	$page['permalink'] = get_permalink($page['id']);
	if ($key == "checkout") $page['permalink'] = str_replace("http://","https://",$page['permalink']);
	$wpdb->query("UPDATE $wpdb->posts SET guid='{$page['permalink']}' WHERE ID={$page['id']}");
	$page['permalink'] = preg_replace('|https?://[^/]+/|i','',$page['permalink']);
}

$wp_rewrite->flush_rules();
$this->Settings->save("pages",$this->Flow->Pages);

?>