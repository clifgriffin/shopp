<?php

$root = __FILE__;
for ($i = 0; $i < 8; $i++) $root = dirname($root);
require_once($root.'/wp-load.php');
require_once(ABSPATH.'/wp-admin/admin.php');
if(!current_user_can('edit_posts')) die;
do_action('admin_init');

?>


<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>{#Shopp.title}</title>
	<script language="javascript" type="text/javascript" src="<?php echo get_option('siteurl') ?>/wp-includes/js/tinymce/tiny_mce_popup.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo get_option('siteurl') ?>/wp-includes/js/tinymce/utils/mctabs.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo get_option('siteurl') ?>/wp-includes/js/tinymce/utils/form_utils.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo get_option('siteurl') ?>/wp-includes/js/tinymce/utils/form_utils.js"></script>
	<script language="javascript" type="text/javascript" src="<?php echo get_option('siteurl') ?>/wp-includes/js/jquery/jquery.js"></script>
	<script language="javascript" type="text/javascript">
	
	var _self = tinyMCEPopup;
	function init () {
		changeCategory();
	}
	
	function insertTag () {
		
		var tag = '[category id="'+jQuery('#category-menu').val()+'"]';

		var productid = jQuery('#product-menu').val();
		if (productid) tag = '[product id="'+productid+'"]';
		
		if(window.tinyMCE) {
			window.tinyMCE.execInstanceCommand('content', 'mceInsertContent', false, tag);
			tinyMCEPopup.editor.execCommand('mceRepaint');
			tinyMCEPopup.close();
		}
				
	}
	
	function closePopup () {
		tinyMCEPopup.close();
	}

	function changeCategory () {
		var menu = jQuery('#category-menu');
		var products = jQuery('#product-menu');
		jQuery.get("<?php echo get_option('siteurl') ?>?shopp_lookup=category-products-menu",{category:menu.val()},function (results) {
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
		<td><select id="category-menu" name="category" onchange="changeCategory()"><?php echo $Shopp->Flow->category_menu(); ?></select></td>
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

<script type="text/javascript">

</script>
</body>
</html>
