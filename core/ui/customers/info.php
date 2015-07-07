<?php foreach($Customer->info->meta as $id => $meta): ?>
<p class="clearfix">
	<label for="info-<?php echo $meta->id; ?>"><?php echo esc_html($meta->name); ?></label>
	<?php echo apply_filters('shopp_customer_info_input', '<input type="text" name="info[' . $meta->id . ']" id="info-' . $meta->id . '" value="' . esc_attr($meta->value) . '" />', $meta); ?>
</p>
<?php endforeach; ?>