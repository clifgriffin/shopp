<div class="wrap shopp">

	<div class="icon32"></div>
	<h2><?php _e('Products','Shopp'); ?> <a href="<?php echo esc_url( add_query_arg(array('page'=>$this->Admin->pagename('products'),'id'=>'new'),admin_url('admin.php'))); ?>" class="button add-new"><?php _e('Add New','Shopp'); ?></a></h2>

	<?php do_action('shopp_admin_notice'); ?>

	<form action="<?php echo esc_url($url); ?>" method="get" id="products-manager">
	<?php include('navigation.php'); ?>

	<div>
		<input type="hidden" name="page" value="<?php echo $this->Admin->pagename('products'); ?>" />
		<input type="hidden" name="view" value="<?php echo $this->view; ?>" />
	</div>

	<p id="post-search" class="search-box">
		<input type="text" id="products-search-input" class="search-input" name="s" value="<?php echo stripslashes(esc_attr($s)); ?>" />
		<input type="submit" value="<?php _e('Search Products','Shopp'); ?>" class="button" />
	</p>


	<div class="tablenav top">

		<div class="alignleft actions">
		<select name="action" id="actions">
			<option value="" selected="selected"><?php _e('Bulk Actions&hellip;','Shopp'); ?></option>
			<?php echo menuoptions($actions_menu,false,true); ?>
		</select>
		<input type="submit" value="<?php esc_attr_e('Apply','Shopp'); ?>" name="apply" id="apply" class="button-secondary action" />
		&nbsp;
		<?php echo $categories_menu; ?>
		<?php echo $inventory_menu; ?>
		<input type="submit" id="filter-button" value="<?php _e('Filter','Shopp'); ?>" class="button-secondary" />
		</div>
		<?php if ($is_trash): ?>
		<div class="alignleft actions">
			<input type="submit" name="delete_all" id="delete_all" class="button-secondary apply" value="<?php _e('Empty Trash','Shopp'); ?>"  />
		</div>
		<?php endif; ?>

		<?php $ListTable->pagination('top'); ?>

		<br class="clear" />
	</div>
	<div class="clear"></div>

	<table class="widefat" cellspacing="0">
		<thead>
		<tr><?php ShoppUI::print_column_headers('toplevel_page_shopp-products'); ?></tr>
		</thead>
		<tfoot>
		<tr><?php ShoppUI::print_column_headers('toplevel_page_shopp-products',false); ?></tr>
		</tfoot>
	<?php if ($Products->size() > 0): ?>
		<tbody id="products" class="list products">
		<?php
		$hidden = get_hidden_columns('toplevel_page_shopp-products');

		$even = false;
		foreach ($Products as $key => $Product):

		$editor_url = remove_query_arg(array('s','cat','sl'),$url);

		$editurl = esc_url( add_query_arg( array('id'=>$Product->id,'view'=>null),$editor_url ) );
		$trashurl = esc_url( add_query_arg( array('selected'=>$Product->id,'action'=>'trash'),$editor_url ) );
		$dupurl = esc_url( add_query_arg( array('duplicate'=>$Product->id), $editor_url ) );
		$restoreurl = esc_url( add_query_arg( array('selected'=>$Product->id,'action'=>'restore'),$editor_url ) );
		$delurl = esc_url( add_query_arg( array('selected'=>$Product->id,'action'=>'delete'),$editor_url ) );
		$category_url = add_query_arg(array('page'=>$this->Admin->pagename('categories')),admin_url('admin.php'));

		$ProductName = empty($Product->name)?'('.__('no product name','Shopp').')':$Product->name;

		$categories = array();
		foreach ($Product->categories as $id => $category) {

			$categories[] = sprintf( '<a href="%s">%s</a>',
				esc_url( add_query_arg( array( 'id' => $category->term_id ), $category_url ) ),
				esc_html( sanitize_term_field( 'name', $category->name, $category->term_id, 'category', 'display' ) )
			);

		}
		?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<th scope='row' class='check-column'><input type='checkbox' name='selected[]' value='<?php echo $Product->id; ?>' /></th>
			<td class="name column-name">
			<strong><?php if ($is_trash): ?><?php echo esc_html($ProductName); ?><?php else: ?><a class='row-title' href='<?php echo $editurl; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($ProductName); ?>&quot;'>

			<?php $Image = reset($Product->images); if (!empty($Image)): ?>
			<img src="?siid=<?php echo $Image->id; ?>&amp;<?php echo $Image->resizing(38,0,1); ?>" width="38" height="38" class="alignleft" />
			<?php endif; ?>

			<?php echo esc_html($ProductName); ?></a><?php endif; ?></strong>
				<?php if ($is_trash): ?>
					<div class="row-actions">
						<span class='untrash'><a title="<?php _e('Restore','Shopp'); ?> &quot;<?php echo esc_attr($ProductName); ?>&quot;" href="<?php echo $restoreurl; ?>"><?php _e('Restore','Shopp'); ?></a> | </span>
						<span class='delete'><a title="<?php echo esc_attr(sprintf(__('Delete %s permanently','Shopp'), "&quot;$ProductName&quot;")); ?>" href="<?php echo $delurl; ?>" rel="<?php echo $Product->id; ?>"><?php _e('Delete Permanently','Shopp'); ?></a></span>
					</div>
				<?php else: ?>
				<div class="row-actions">
					<span class='edit'><a href="<?php echo $editurl; ?>" title="<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($ProductName); ?>&quot;"><?php _e('Edit','Shopp'); ?></a> | </span>
					<span class='edit'><a href="<?php echo $dupurl; ?>" title="<?php _e('Duplicate','Shopp'); ?> &quot;<?php echo esc_attr($ProductName); ?>&quot;"><?php _e('Duplicate','Shopp'); ?></a> | </span>
					<span class='delete'><a class="delete" title="<?php echo esc_attr(sprintf(__('Move %s to the trash','Shopp'), "&quot;$ProductName&quot;")); ?>" href="<?php echo $trashurl; ?>" rel="<?php echo $Product->id; ?>"><?php _e('Trash','Shopp'); ?></a> | </span>
					<span class='view'><a href="<?php echo $Product->tag('url'); ?>" title="<?php _e('View','Shopp'); ?> &quot;<?php echo esc_attr($ProductName); ?>&quot;" rel="permalink" target="_blank"><?php _e('View','Shopp'); ?></a></span>
				</div>
				<?php endif; ?>
				</td>
			<?php if (!$is_trash && !$is_bestselling): ?>
			<td class="category column-category<?php echo in_array('category',$hidden)?' hidden':''; ?>"><?php echo join(', ',$categories); ?></td>
			<?php endif; ?>

			<?php if ($is_bestselling): ?>
			<td class="sold column-sold<?php echo in_array('sales',$hidden)?' hidden':''; ?>">
				<?php echo $Product->sold; ?>
			</td>
			<td class="grossed column-grossed<?php echo in_array('sales',$hidden)?' hidden':''; ?>">
				<?php echo money($Product->grossed); ?>
			</td>
			<?php endif; ?>


			<td class="price column-price<?php echo in_array('price',$hidden)?' hidden':''; if ($Product->sale == 'on') echo " sale"?>"><?php
				shopp($Product,'price');
				if (str_true($Product->sale)) echo '<span class="saletag">'.__('On Sale','Shopp').'</span>';
			?>
			</td>

			<?php if ('on' == shopp_setting('inventory')): ?>
				<td class="inventory column-inventory<?php echo in_array('inventory',$hidden)?' hidden':''; ?>">
					<?php // @todo Link inventory number to Inventory view while filtering for the product/variants
					if ('on' == $Product->inventory) {
						$stockclass = array('stock');
						if (!empty($Product->lowstock) && 'none' != $Product->lowstock) $stockclass[] = "lowstock $Product->lowstock";
					 	echo '<span class="'.join(' ',$stockclass).'">'.$Product->stock.'</span>';
					}
					?>
				</td>
			<?php endif; ?>

			<td class="featured column-featured<?php echo in_array('featured',$hidden)?' hidden':''; ?>">
				<button type="button" name="feature" value="<?php echo $Product->id; ?>" class="<?php echo ($Product->featured == "on")?' feature featured':'feature'; ?>">&nbsp;</button>
			</td>

			<td class="date column-date<?php echo in_array('date',$hidden)?' hidden':''; ?>">
			<?php

			if ( '0' == $Product->publish) {
				$t_time = $h_time = __('Unpublished');
				$time_diff = 0;
			} else {
				$t_time = get_the_time(__('Y/m/d g:i:s A'));
				$m_time = $Product->publish;
				$h_time = date(__('Y/m/d'), $m_time);
				$time_diff = current_time('timestamp') - $m_time;

				if ( $time_diff > 0 && $time_diff < 86400 )
					$h_time = sprintf( __('%s ago'), human_time_diff( $m_time, current_time('timestamp') ) );

			}

			echo '<abbr title="' . $t_time . '">' . apply_filters('shopp_product_date_column_time', $h_time, $Product) . '</abbr><br />';
			if ( 'publish' == $Product->status ) {
				_e('Published');
			} elseif ( 'future' == $Product->status ) {
				if ( $time_diff > 0 )
					echo '<strong class="attention">' . __('Missed schedule') . '</strong>';
				else
					_e('Scheduled');
			} else {
				_e('Last Modified');
			}

			?>
			</td>

		</tr>
		<?php endforeach; ?>
		</tbody>
	<?php else: ?>
		<tbody><tr><td colspan="6"><?php _e('No products found.','Shopp'); ?></td></tr></tbody>
	<?php endif; ?>
	</table>
	</form>
	<div class="tablenav bottom">
		<?php $ListTable->pagination( 'bottom' ); ?>
		<br class="clear" />
	</div>
</div>

<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready( function() {
	var $=jqnc(),
		featureurl = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'),'wp_ajax_shopp_feature_product'); ?>';

	$('input.current-page').unbind('mouseup.select').bind('mouseup.select',function () { this.select(); });

	$('#selectall').change( function() {
		$('#products th input').each( function () {
			if (this.checked) this.checked = false;
			else this.checked = true;
		});
	});

	$('a.submitdelete').click(function () {
		var name = $(this).attr('title');
		if ( confirm(<?php _jse('You are about to delete this product!\n \'Cancel\' to stop, \'OK\' to delete.','Shopp'); ?>)) {
			$('<input type="hidden" name="delete[]" />').val($(this).attr('rel')).appendTo('#products-manager');
			$('<input type="hidden" name="deleting" />').val('product').appendTo('#products-manager');
			$('#products-manager').submit();
			return false;
		} else return false;
	});

	$('#delete-button').click(function() {
		if (confirm("<?php echo addslashes(__('Are you sure you want to delete the selected products?','Shopp')); ?>")) return true;
		else return false;
	});

	$('button.feature').click(function () {
		var $this = $(this);
		$.get(featureurl,{'feature':$this.val(),'action':'shopp_feature_product'},function (result) {
			if (result == "on") $this.addClass('featured');
			else $this.removeClass('featured');
		});
	});

	pagenow = 'toplevel_page_shopp-products';
	columns.init(pagenow);

});
/* ]]> */
</script>