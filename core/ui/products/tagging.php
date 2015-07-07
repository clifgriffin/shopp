<?php
	$defaults = array('taxonomy' => 'shopp_tag');
	if ( !isset($options['args']) || !is_array($options['args']) ) $options = array();
	else $options = $options['args'];
	extract( wp_parse_args($options, $defaults), EXTR_SKIP );
	$tax = get_taxonomy($taxonomy);
	$disabled = !current_user_can($tax->cap->assign_terms) ? 'disabled="disabled"' : '';

?>
<div id="taxonomy-<?php echo $taxonomy; ?>" class="tags-metabox">
<div class="hide-if-no-js">
<p><?php echo sprintf(__('Type a tag name and press %s tab to add it.','Shopp'),'<abbr title="'.__('tab key','Shopp').'">&#8677;</abbr>'); ?></p>
</div>
<div class="nojs-tags hide-if-js">
<p><?php echo $tax->labels->add_or_remove_items; ?></p>
<textarea name="<?php echo "tax_input[$taxonomy]"; ?>" rows="3" cols="20" class="tags" id="tax-input-<?php echo $taxonomy; ?>" <?php echo $disabled; ?>><?php echo esc_attr(get_terms_to_edit( $Product->id, $taxonomy )); ?></textarea></div>
</div>
