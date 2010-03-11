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
<?php shopp('catalog','views','label=Views: '); ?>

<?php shopp('catalog','featured-products','load=true&show=3'); ?>
<h3><?php _e('Featured Products','Shopp'); ?></h3>
<?php shopp('category','slideshow'); ?>

<?php shopp('catalog','onsale-products','show=16&controls=false&load=true'); ?>
<h3><?php _e('On Sale','Shopp'); ?></h3>
<?php shopp('category','carousel'); ?>

<?php shopp('catalog','bestseller-products','show=3&controls=false'); ?>
<?php shopp('catalog','new-products','show=3&controls=false'); ?>