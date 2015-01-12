<?php
	$tax = get_taxonomy($Category->taxonomy);
?>
	<p><?php wp_dropdown_categories( array( 'taxonomy' => $Category->taxonomy, 'selected' => $Category->parent, 'hide_empty' => 0, 'name' => 'parent', 'orderby' => 'name', 'hierarchical' => 1, 'show_option_none' => $tax->labels->parent_item.'&hellip;', 'tab_index' => 3 ) );?>
<label><span><?php _e('Categories, unlike tags, can be or have nested sub-categories.','Shopp'); ?></span></label></p>

	<p class="toggle"><input type="hidden" name="spectemplate" value="off" /><input type="checkbox" name="spectemplate" value="on" id="spectemplates-setting" tabindex="11" <?php if (isset($Category->spectemplate) && $Category->spectemplate == "on") echo ' checked="checked"'?> /><label for="spectemplates-setting"> <?php _e('Product Details Template','Shopp'); ?><br /><span><?php _e('Predefined details for products created in this category','Shopp'); ?></span></label></p>
	<p id="facetedmenus-setting" class="toggle"><input type="hidden" name="facetedmenus" value="off" /><input type="checkbox" name="facetedmenus" value="on" id="faceted-setting" tabindex="12" <?php if (isset($Category->facetedmenus) && $Category->facetedmenus == "on") echo ' checked="checked"'?> /><label for="faceted-setting"><?php _e('Faceted Menus','Shopp'); ?><br /><span><?php _e('Build drill-down filter menus based on the details template of this category','Shopp'); ?></span></label></p>
	<p class="toggle"><input type="hidden" name="variations" value="off" /><input type="checkbox" name="variations" value="on" id="variations-setting" tabindex="13"<?php if (isset($Category->variations) && $Category->variations == "on") echo ' checked="checked"'?> /><label for="variations-setting"> <?php _e('Variations','Shopp'); ?><br /><span><?php _e('Predefined selectable product options for products created in this category','Shopp'); ?></span></label></p>
	<?php if (isset($Category->count) && $Category->count > 1): ?>
	<p class="toggle"><a href="<?php echo add_query_arg(array('page'=>'shopp-categories','id'=>$Category->id,'a'=>'products'),admin_url('admin.php')); ?>" class="button-secondary"><?php _e('Arrange Products','Shopp'); ?></a></p>
	<?php endif; ?>