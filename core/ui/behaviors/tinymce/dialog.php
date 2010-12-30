<?php

function shopp_tinymce_find_root ($filename, $directory, $root, &$found) {
	if (is_dir($directory)) {
		$Directory = @dir($directory);
		if ($Directory) {
			while (( $file = $Directory->read() ) !== false) {
				if (substr($file,0,1) == "." || substr($file,0,1) == "_") continue;			// Ignore .dot files and _directories
				if (is_dir($directory.'/'.$file) && $directory == $root)					// Scan one deep more than root
					shopp_tinymce_find_root($filename,$directory.'/'.$file,$root, $found);	// but avoid recursive scans
				elseif ($file == $filename)
					$found[] = substr($directory,strlen($root)).'/'.$file;					// Add the file to the found list
			}
			return true;
		}
	}
	return false;
}

$root = realpath($_SERVER['DOCUMENT_ROOT']);
if (!$root) $root = realpath(substr(	// Attempt to trace document root by script pathing
				$_SERVER['SCRIPT_FILENAME'],0,
				strpos($_SERVER['SCRIPT_FILENAME'],$_SERVER['SCRIPT_NAME'])
			));

$found = array();
shopp_tinymce_find_root('wp-load.php',$root,$root,$found);
if (empty($found[0])) exit();
require_once($root.$found[0]);
require_once(ABSPATH.'/wp-admin/admin.php');
if(!current_user_can('edit_posts')) die;
do_action('admin_init');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>{#Shopp.title}</title>
	<script language="javascript" type="text/javascript" src="<?php echo $Shopp->siteurl; ?>/wp-includes/js/tinymce/tiny_mce_popup.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo $Shopp->siteurl; ?>/wp-includes/js/tinymce/utils/mctabs.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo $Shopp->siteurl; ?>/wp-includes/js/tinymce/utils/form_utils.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo $Shopp->siteurl; ?>/wp-includes/js/tinymce/utils/form_utils.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo $Shopp->siteurl; ?>/wp-includes/js/jquery/jquery.js"></script>
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
