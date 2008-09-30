<div class="wrap shopp">
	<form action="" method="get">
	<div><input type="hidden" name="page" value="<?php echo $this->Admin->products; ?>" /></div>
	<h2><?php _e('Products','Shopp'); ?></h2>
	
	<?php include("navigation.php"); ?>

	<p class="controls"><button type="submit" name="edit" value="new" class="button"><?php _e('New Product','Shopp'); ?></button></p>

	<div class="tablenav">
		<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
		<div class="alignleft">
		<button type="submit" id="delete-button" name="deleting" value="product" class="button-secondary"><?php _e('Delete','Shopp'); ?></button>
		<select name='cat'>
		<?php echo $categories_menu; ?>
		</select>
		<input type="submit" id="filter-button" value="<?php _e('Filter','Shopp'); ?>" class="button-secondary">
		</div>
		<br class="clear" />
	</div>
	<br class="clear" />
	
	<table class="widefat">
		<thead>
		<tr>
			<th scope="col" class="check-column"><input type="checkbox" id="selectall" /></th>
	        <th scope="col"><?php _e('Name','Shopp'); ?></th>
	        <th scope="col"><?php _e('Category','Shopp'); ?></th>
	        <th scope="col"><?php _e('Price','Shopp'); ?></th>
	        <th scope="col" class="num"><?php _e('Featured','Shopp'); ?></th>
		</tr>
		</thead>
	<?php if (sizeof($Products) > 0): ?>
		<tbody id="products" class="list products">
		<?php $even = false; foreach ($Products as $Product): ?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<th scope='row' class='check-column'><input type='checkbox' name='delete[]' value='<?php echo $Product->id; ?>' /></th>
			<td><a class='row-title' href='?page=<?php echo $this->Admin->products; ?>&amp;edit=<?php echo $Product->id; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo $Product->name; ?>&quot;'><?php echo (!empty($Product->name))?$Product->name:'(no product name)'; ?></a></td>
			<td><?php echo $Product->categories; ?></td>
			<td><?php
				if ($Product->maxprice == $Product->minprice) echo money($Product->maxprice);
				else echo money($Product->minprice)."&mdash;".money($Product->maxprice);
			?></td>
			<td<?php echo ($Product->featured == "on")?' class="featured"':''; ?>>&nbsp;</td> 
		
		</tr>
		<?php endforeach; ?>
		</tbody>
	<?php else: ?>
		<tbody><tr><td colspan="5"><?php _e('No products found.','Shopp'); ?></td></tr></tbody>
	<?php endif; ?>
	</table>
	</form>
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
</script>