<?php
global $wpdb,$wp_rewrite,$wp_version;
$db = DB::get();

// Install tables

if (!file_exists(SHOPP_DBSCHEMA)) {
 	trigger_error("Could not install the shopp database tables because the table definitions file is missing: ".SHOPP_DBSCHEMA);
	exit();
}

$db->loaddata(file_get_contents(SHOPP_DBSCHEMA));

// Auto-Generate pages
$pages = array();
$pages['catalog'] = array('name'=>'shop','title'=>'Shop','content'=>'[catalog]');
$pages['cart'] = array('name'=>'cart','title'=>'Cart','content'=>'[cart]');
$pages['checkout'] = array('name'=>'checkout','title'=>'Checkout','content'=>'[checkout]');
$pages['account'] = array('name'=>'account','title'=>'Your Orders','content'=>'[account]');

$parent = 0;
foreach ($pages as $key => &$page) {	
	if (!empty($pages['catalog']['id'])) $parent = $pages['catalog']['id'];
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
}

$this->Settings->add("pages",$pages);

?>