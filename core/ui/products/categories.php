<?php
	$defaults = array('taxonomy' => 'shopp_category');
	if ( !isset($options['args']) || !is_array($options['args']) ) $options = array();
	else $options = $options['args'];
	extract( wp_parse_args($options, $defaults), EXTR_SKIP );
	$tax = get_taxonomy($taxonomy);
?>
<div id="taxonomy-<?php echo $taxonomy; ?>" class="category-metabox">
	<div id="<?php echo $taxonomy; ?>-pop" class="multiple-select category-menu tabs-panel hide-if-no-js hidden">
		<ul id="<?php echo $taxonomy; ?>-checklist-pop" class="form-no-clear">
			<?php $popular_ids = ShoppAdminProductCategoriesBox::popular_terms_checklist($Product->id,$taxonomy); ?>
		</ul>
	</div>

	<div id="<?php echo $taxonomy; ?>-all" class="multiple-select category-menu tabs-panel">
		<ul id="<?php echo $taxonomy; ?>-checklist" data-wp-lists="list:<?php echo $taxonomy; ?>" class="list:<?php echo $taxonomy; ?> form-no-clear">
		<?php wp_terms_checklist($Product->id, array( 'taxonomy' => $taxonomy, 'popular_cats' => $popular_ids) ) ?>
		</ul>
	</div>

	<div id="<?php echo $taxonomy; ?>-add" class="new-category hide-if-no-js">
	<input type="text" name="new<?php echo $taxonomy; ?>" value="" id="new-<?php echo $taxonomy; ?>-name" /><br />
	<?php wp_dropdown_categories( array( 'taxonomy' => $taxonomy, 'hide_empty' => 0, 'name' => 'new'.$taxonomy.'_parent', 'orderby' => 'name', 'hierarchical' => 1, 'show_option_none' => $tax->labels->parent_item.'&hellip;', 'tab_index' => 3 ) ); ?>

	<input type="button" id="<?php echo $taxonomy; ?>-add-submit" value="<?php _e('Add'); ?>" data-wp-lists="add:<?php echo $taxonomy ?>-checklist:<?php echo $taxonomy ?>-add" class="add:<?php echo $taxonomy ?>-checklist:taxonomy-<?php echo $taxonomy ?> button <?php echo $taxonomy ?>-add-submit" tabindex="3" />
	<?php wp_nonce_field( 'add-'.$taxonomy, '_ajax_nonce-add-'.$taxonomy, false ); ?>
	<span id="<?php echo $taxonomy; ?>-ajax-response"></span>
	</div>

	<ul id="<?php echo $taxonomy; ?>-tabs" class="category-tabs">
		<li class="tabs"><a href="#<?php echo $taxonomy; ?>-all" tabindex="3"><?php _e('Show All'); ?></a></li>
		<li class="hide-if-no-js"><a href="#<?php echo $taxonomy; ?>-pop" tabindex="3"><?php _e( 'Popular','Shopp' ); ?></a></li>
		<li class="hide-if-no-js new-category"><a href="#<?php echo $taxonomy; ?>-all" tabindex="3"  class="new-category-tab"><?php _e( 'New Category' ); ?></a></li>
	</ul>
</div>