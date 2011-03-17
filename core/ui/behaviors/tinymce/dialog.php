<?php

function shopp_find_wpload () {
	global $table_prefix;

	$loadfile = 'wp-load.php';
	$wp_abspath = false;

	$syspath = explode('/',$_SERVER['SCRIPT_FILENAME']);
	$uripath = explode('/',$_SERVER['SCRIPT_NAME']);
	$rootpath = array_diff($syspath,$uripath);
	$root = '/'.join('/',$rootpath);

	$filepath = dirname(!empty($_SERVER['SCRIPT_FILENAME'])?$_SERVER['SCRIPT_FILENAME']:__FILE__);

	if ( file_exists(sanitize_path($root).'/'.$loadfile))
		$wp_abspath = $root;

	if ( isset($_SERVER['SHOPP_WP_ABSPATH'])
		&& file_exists(sanitize_path($_SERVER['SHOPP_WP_ABSPATH']).'/'.$configfile) ) {
		// SetEnv SHOPP_WPCONFIG_PATH /path/to/wpconfig
		// and SHOPP_ABSPATH used on webserver site config
		$wp_abspath = $_SERVER['SHOPP_WP_ABSPATH'];

	} elseif ( strpos($filepath, $root) !== false ) {
		// Shopp directory has DOCUMENT_ROOT ancenstor, find wp-config.php
		$fullpath = explode ('/', sanitize_path($filepath) );
		while (!$wp_abspath && ($dir = array_pop($fullpath)) !== null) {
			if (file_exists( sanitize_path(join('/',$fullpath)).'/'.$loadfile ))
				$wp_abspath = join('/',$fullpath);
		}

	} elseif ( file_exists(sanitize_path($root).'/'.$loadfile) ) {
		$wp_abspath = $root; // WordPress install in DOCUMENT_ROOT
	} elseif ( file_exists(sanitize_path(dirname($root)).'/'.$loadfile) ) {
		$wp_abspath = dirname($root); // wp-config up one directory from DOCUMENT_ROOT
	}

	$wp_load_file = sanitize_path($wp_abspath).'/'.$loadfile;

	if ( $wp_load_file !== false ) return $wp_load_file;
	return false;

}
if(!function_exists('sanitize_path')){
	/**
	 * Normalizes path separators to always use forward-slashes
	 *
	 * PHP path functions on Windows-based systems will return paths with
	 * backslashes as the directory separator.  This function is used to
	 * ensure we are always working with forward-slash paths
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param string $path The path to clean up
	 * @return string $path The forward-slash path
	 **/
	function sanitize_path ($path) {
		return str_replace('\\', '/', $path);
	}
}

$loader = shopp_find_wpload();
if (!file_exists($loader)) return false;
$adminpath = dirname($loader).'/wp-admin';
require_once($adminpath.'/admin.php');
if(!current_user_can('edit_posts')) die;
do_action('admin_init');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>{#Shopp.title}</title>
<script language="javascript" type="text/javascript" src="<?php echo get_bloginfo('wpurl').'/'.WPINC; ?>/js/tinymce/tiny_mce_popup.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo get_bloginfo('wpurl').'/'.WPINC; ?>/js/tinymce/utils/mctabs.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo get_bloginfo('wpurl').'/'.WPINC; ?>/js/tinymce/utils/form_utils.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo get_bloginfo('wpurl').'/'.WPINC; ?>/js/tinymce/utils/form_utils.js"></script>
<script language="javascript" type="text/javascript" src="<?php echo get_bloginfo('wpurl').'/'.WPINC; ?>/js/jquery/jquery.js"></script>
	<script language="javascript" type="text/javascript">

	var _self = tinyMCEPopup;
	function init () {
		updateCategories();
		changeCategory();
	}

	function insertTag () {
		var tag = '';
		if (parseInt(jQuery('#category-menu').val()) > 0)
			tag = '[category id="'+jQuery('#category-menu').val()+'"]';
		else if (jQuery('#category-menu').val() != '') tag = '[category slug="catalog"]';

		var productid = jQuery('#product-menu').val();
		if (productid != 0) tag = '[product id="'+productid+'"]';

		if(window.tinyMCE) {
			window.tinyMCE.execInstanceCommand('content', 'mceInsertContent', false, tag);
			tinyMCEPopup.editor.execCommand('mceRepaint');
			tinyMCEPopup.close();
		}

	}

	function closePopup () {
		tinyMCEPopup.close();
	}

	function updateCategories () {
		var menu = jQuery('#category-menu');
		jQuery.get("<?php echo wp_nonce_url(admin_url('admin-ajax.php'),'wp_ajax_shopp_category_menu'); ?>&action=shopp_category_menu",{},function (results) {
			menu.empty().html(results);
		},'string');
	}

	function changeCategory () {
		var menu = jQuery('#category-menu');
		var products = jQuery('#product-menu');
		jQuery.get("<?php echo wp_nonce_url(admin_url('admin-ajax.php'),'wp_ajax_shopp_category_products'); ?>&action=shopp_category_products",{category:menu.val()},function (results) {
			products.empty().html(results);
		},'string');
	}

	</script>

	<style type="text/css">
		table th { vertical-align: top; }
		.panel_wrapper { border-top: 1px solid #909B9C; }
		.panel_wrapper div.current { height:auto !important; }
		#product-menu { width: 180px; }
	</style>

</head>
<body onload="init()">

<div id="wpwrap">
<form onsubmit="insertTag();return false;" action="#">
	<div class="panel_wrapper">
		<table border="0" cellpadding="4" cellspacing="0">
		<tr>
		<th nowrap="nowrap"><label for="category-menu"><?php _e("Category", 'Shopp'); ?></label></th>
		<td><select id="category-menu" name="category" onchange="changeCategory()"></select></td>
		</tr>
		<tr id="product-selector">
		<th nowrap="nowrap"><label for="product-menu"><?php _e("Product", 'Shopp'); ?></label></th>
		<td><select id="product-menu" name="product" size="7"></select></td>
		</tr>
		</table>
	</div>

	<div class="mceActionPanel">
		<div style="float: left">
			<input type="button" id="cancel" name="cancel" value="{#cancel}" onclick="closePopup()"/>
		</div>

		<div style="float: right">
			<input type="button" id="insert" name="insert" value="{#insert}" onclick="insertTag()" />
		</div>
	</div>
</form>
</div>

</body>
</html>
