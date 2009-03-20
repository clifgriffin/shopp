<div class="wrap shopp">
	<h2><?php _e('Products','Shopp'); ?></h2>

	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>

	<form action="" method="get">
	<div>
		<input type="hidden" name="page" value="<?php echo $this->Admin->products; ?>" />
	</div>

	<p id="post-search" class="search-box">
		<input type="text" id="products-search-input" class="search-input" name="s" value="<?php echo attribute_escape($_GET['s']); ?>" />
		<input type="submit" value="<?php _e('Search Products','Shopp'); ?>" class="button" />
	</p>
	
	<p><button type="submit" name="edit" value="new" class="button"><?php _e('New Product','Shopp'); ?></button></p>

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
	<div class="clear"></div>
	
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
		<?php $even = false; foreach ($Products as $Product): ?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<th scope='row' class='check-column'><input type='checkbox' name='delete[]' value='<?php echo $Product->id; ?>' /></th>
			<td class="name column-name"><a class='row-title' href='?page=<?php echo $this->Admin->products; ?>&amp;edit=<?php echo $Product->id; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo $Product->name; ?>&quot;'><?php echo (!empty($Product->name))?$Product->name:'(no product name)'; ?></a></td>
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
</div>    
<div class="tablenav">
	<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
	<div class="clear"></div>
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

	$('#delete-button').click(function() {
		if (confirm("<?php _e('Are you sure you want to delete the selected products?','Shopp'); ?>")) return true;
		else return false;
	});
	columns.init('shopp_page_shopp/products');
	
</script>