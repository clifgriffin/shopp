<div class="wrap shopp">
	<div class="icon32"></div>
	<h2><?php printf(__('Arrange Products for "%s"','Shopp'),$CategoryProducts->name); ?></h2>

	<?php do_action('shopp_admin_notice'); ?>

	<form action="" id="products" method="get">
	<div>
		<input type="hidden" name="page" value="<?php echo $this->Admin->pagename('categories'); ?>" />
		<input type="hidden" name="category" id="category-id" value="<?php echo $CategoryProducts->term_taxonomy_id; ?>" />
	</div>

	<div class="tablenav">
		<div class="alignleft actions">
			<a href="<?php echo esc_url(add_query_arg(array_merge(stripslashes_deep($_GET),array('page'=>$this->Admin->pagename('categories'),'a'=>null)),admin_url('admin.php'))); ?>" class="button add-new">&larr; <?php printf(__('Return to %s','Shopp'),$CategoryProducts->name); ?></a>
		</div>
		<div class="clear"></div>
	</div>
	<div class="clear"></div>

	<table id="arrange-products" class="widefat" cellspacing="0">
		<thead>
		<tr><?php print_column_headers('shopp_page_shopp-categories'); ?></tr>
		</thead>
		<tfoot>
		<tr><?php print_column_headers('shopp_page_shopp-categories',false); ?></tr>
		</tfoot>
	<?php if (sizeof($CategoryProducts) > 0): ?>
		<tbody id="categories-table" class="list categories">
		<?php
		$hidden = array();
		$hidden = get_hidden_columns('shopp_page_shopp-categories');

		$even = false;
		foreach ($CategoryProducts as $Product):


		$editurl = esc_url(esc_attr(add_query_arg(array_merge(stripslashes_deep($_GET),
			array('page'=>$this->Admin->pagename('products'),
					'id'=>$Product->id)),
					admin_url('admin.php'))));

		$ProductName = empty($Product->name)?'('.__('no product name','Shopp').')':$Product->name;

		$stripe = (!$even)?'alternate':''; $even = !$even;
		$classes = (empty($stripe)?'':' '.$stripe);

		?>
		<tr class="<?php echo $classes; ?>">
			<th scope="row" class='move-column'><button type="button" name="top" alt="<?php _e('Move to the top','Shopp'); ?>&hellip;" class="moveto top">&nbsp;</button><button type="button" name="bottom" alt="<?php _e('Move to the bottom','Shopp'); ?>&hellip;" class="moveto bottom">&nbsp;</button></th>
			<td><a class='row-title' href='<?php echo $editurl; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo $ProductName; ?>&quot;'><?php echo $ProductName; ?></a>
			<input type="hidden" name="position[<?php echo $Product->id; ?>]" value="<?php echo $Product->priority; ?>" /></td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	<?php else: ?>
		<tbody><tr><td colspan="6"><?php _e('No products found.','Shopp'); ?></td></tr></tbody>
	<?php endif; ?>
	</table>
	</form>
	<div class="tablenav">
		<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
		<div class="clear"></div>
	</div>
</div>

<script type="text/javascript">
var updates_url = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'),'wp_ajax_shopp_category_products_order'); ?>';
jQuery(document).ready( function() {
	pagenow = 'shopp_page_shopp-categories';
	columns.init(pagenow);
});
</script>