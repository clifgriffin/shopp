<div id="misc-publishing-actions">
<?php if ($Customer->id > 0): ?>
<p><strong><a href="<?php echo esc_url(add_query_arg(array('page'=>'shopp-orders','customer'=>$Customer->id),admin_url('admin.php'))); ?>"><?php _e('Orders','Shopp'); ?></a>: </strong><?php echo $Customer->orders; ?> &mdash; <strong><?php echo Shopp::money($Customer->total); ?></strong></p>
<p><strong><a href="<?php echo esc_url( add_query_arg(array('page'=>'shopp-customers','range'=>'custom','start'=>date('n/j/Y',$Customer->created),'end'=>date('n/j/Y',$Customer->created)),admin_url('admin.php'))); ?>"><?php _e('Joined','Shopp'); ?></a>: </strong><?php echo date(get_option('date_format'),$Customer->created); ?></p>
<?php endif; ?>
<?php do_action('shopp_customer_editor_info',$Customer); ?>
</div>
<div id="major-publishing-actions">
	<input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" />
</div>