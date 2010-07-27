<?php
/** 
 ** WARNING! DO NOT EDIT!
 **
 ** These templates are part of the core Shopp files 
 ** and will be overwritten when upgrading Shopp.
 **
 ** For editable templates, setup Shopp theme templates:
 ** http://docs.shopplugin.net/Setting_Up_Theme_Templates
 **
 **/
?>
<div id="errors" class="shopp">
	<h3><?php _e('Errors','Shopp'); ?></h3>
	<ul>
		<?php shopp('checkout','errors'); ?>
	</ul>
</div>