<div class="wrap shopp">
	<h2><?php _e('Products','Shopp'); ?></h2>

	<?php if (!empty($Shopp->Flow->Notice)): ?><div id="message" class="updated fade"><p><?php echo $Shopp->Flow->Notice; ?></p></div><?php endif; ?>

	<form action="" method="get" id="products-manager">
	<div>
		<input type="hidden" name="page" value="<?php echo $this->Admin->products; ?>" />
	</div>

	<p id="post-search" class="search-box">
		<input type="text" id="products-search-input" class="search-input" name="s" value="<?php echo stripslashes(attribute_escape($_GET['s'])); ?>" />
		<input type="submit" value="<?php _e('Search Products','Shopp'); ?>" class="button" />
	</p>
	
	<p><a href="<?php echo add_query_arg(array('page'=>$this->Admin->editproduct,'id'=>'new'),$Shopp->wpadminurl); ?>" class="button"><?php _e('New Product','Shopp'); ?></a></p>

	<div class="tablenav">
		<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
		<div class="alignleft actions">
		<button type="submit" id="delete-button" name="deleting" value="product" class="button-secondary"><?php _e('Delete','Shopp'); ?></button>
		<select name='cat'>
		<?php echo $categories_menu; ?>
		</select>
		<input type="submit" id="filter-button" value="<?php _e('Filter','Shopp'); ?>" class="button-secondary">
		</div>
		<div class="clear"></div>
	</div>
	<?php if (SHOPP_WP27): ?><div class="clear"></div>
	<?php else: ?><br class="clear" /><?php endif; ?>
	
	<table class="widefat" cellspacing="0">
		<thead>
		<tr><?php shopp_print_column_headers('shopp_page_shopp/products'); ?></tr>
		</thead>
		<?php if (SHOPP_WP27): ?>
		<tfoot>
		<tr><?php shopp_print_column_headers('shopp_page_shopp/products',false); ?></tr>
		</tfoot>
		<?php endif; ?>
	<?php if (sizeof($Products) > 0): ?>
		<tbody id="products" class="list products">
		<?php 
		$even = false; foreach ($Products as $key => $Product):
		$editurl = add_query_arg(array_merge($_GET,
			array('page'=>$this->Admin->editproduct,
					'id'=>$Product->id)),
					$this->Core->wpadminurl);
			
		
		?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<th scope='row' class='check-column'><input type='checkbox' `name='delete[]' value='<?php echo $Product->id; ?>' /></th>
			<td class="name column-name"><a class='row-title' href='<?php echo $editurl; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo $Product->name; ?>&quot;'><?php echo (!empty($Product->name))?$Product->name:'(no product name)'; ?></a>
				<div class="row-actions">
					<span class='edit'><a href="<?php echo $editurl; ?>" title="Edit this product"><?php _e('Edit','Shopp'); ?></a> | </span>
					<span class='edit'><a href="<?php echo add_query_arg(array_merge($_GET,array('duplicate'=>$Product->id)),$this->Core->wpadminurl); ?>" title="Duplicate this product"><?php _e('Duplicate','Shopp'); ?></a> | </span>
					<span class='delete'><a class='submitdelete' title='Delete this product' href='' rel="<?php echo $Product->id; ?>">Delete</a> | </span>
					<span class='view'><a href="<?php echo (SHOPP_PERMALINKS)?"$Shopp->shopuri/new/$Product->slug":add_query_arg('shopp_pid',$Product->id,$Shopp->shopuri); ?>" title="<?php _e('View','Shopp'); ?> &quot;<?php echo $Product->name; ?>&quot;" rel="permalink" target="_blank"><?php _e('View','Shopp'); ?></a></span>
				</div>
				</td>
			<td class="category column-category"><?php echo $Product->categories; ?></td>
			<td class="price column-price"><?php
				if ($Product->maxprice == $Product->minprice) echo money($Product->maxprice);
				else echo money($Product->minprice)."&mdash;".money($Product->maxprice);
			?></td>
			<td class="inventory column-inventory"><?php if ($Product->inventory == "on") echo $Product->stock; ?></td> 
			<td<?php echo ($Product->featured == "on")?' class="featured column-featured"':' class="column-featured"'; ?>>&nbsp;</td> 
		
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
	helpurl = "<?php echo SHOPP_DOCS; ?>Products";

	$=jQuery.noConflict();
	$('#selectall').change( function() {
		$('#products th input').each( function () {
			if (this.checked) this.checked = false;
			else this.checked = true;
		});
	});
	
	$('a.submitdelete').click(function () {
		if ( confirm("You are about to delete this product '<?php echo $Product->name; ?>'\n 'Cancel' to stop, 'OK' to delete.")) {
			$('<input type="hidden" name="delete[]" />').val($(this).attr('rel')).appendTo('#products-manager');
			$('<input type="hidden" name="deleting" />').val('product').appendTo('#products-manager');
			$('#products-manager').submit();
			return false;
		} else return false;
	});

	$('#delete-button').click(function() {
		if (confirm("<?php _e('Are you sure you want to delete the selected products?','Shopp'); ?>")) return true;
		else return false;
	});
	columns.init('shopp_page_shopp/products');
	
</script>