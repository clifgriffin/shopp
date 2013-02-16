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
<ul class="shopp account">
<?php while (shopp('storefront','account-menu')): ?>
	<li>
		<a href="<?php shopp('storefront','account-menuitem','url'); ?>"><?php shopp('storefront','account-menuitem'); ?></a>
	</li>
<?php endwhile; ?>
</ul>
