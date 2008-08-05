<?php
global $wpdb,$wp_rewrite,$wp_version;
$db =& DB::get();


// Check Pre-Requisites
if ($wp_version < 2.5) trigger_error("Sorry! Shopp is not designed to work with WordPress $wp_version.  You'll need to upgrade to version 2.5 or higher.");

// Install/Upgrade tables

if (!file_exists(SHOPP_DBSCHEMA)) {
 	trigger_error("Could not install the shopp database tables because the table definitions file is missing: ".SHOPP_DBSCHEMA);
	exit();
}

$db->loaddata(file_get_contents(SHOPP_DBSCHEMA));

// Auto-Generate pages
$pages = array();
$pages[] = array('name'=>'shop','title'=>'Shop','content'=>'[catalog]');
$pages[] = array('name'=>'cart','title'=>'Cart','content'=>'[cart]');
$pages[] = array('name'=>'checkout','title'=>'Checkout','content'=>'[checkout]');
$pages[] = array('name'=>'account','title'=>'Your Account','content'=>'[account]');

$parent = 0;
foreach ($pages as $i => &$page) {	
	if (!empty($pages[0]['id'])) $parent = $pages[0]['id'];
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
	$wpdb->query("UPDATE $wpdb->posts SET guid='{$page['permalink']}' WHERE ID={$page['id']}");		
}

$this->Settings->add("pages",$pages);

?>