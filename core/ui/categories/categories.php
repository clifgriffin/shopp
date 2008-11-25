<div class="wrap shopp">
	<form action="" id="categories" method="get">
	<div>
		<input type="hidden" name="page" value="<?php echo $this->Admin->categories; ?>" />
	</div>
	<h2><?php _e('Categories','Shopp'); ?></h2>
	
	<p class="search-box"><button type="submit" name="edit" value="new" class="button"><?php _e('New Category','Shopp'); ?></button></p>
	<p id="post-search" class="search-box">
		<label class="hidden" for="post-search-input">Search Categories:</label>
		<input type="text" id="categories-search-input" class="search-input" name="s" value="<?php echo attribute_escape($_GET['s']); ?>" />
		<input type="submit" value="Search Categories" class="button" />
	</p>

	<div class="tablenav">
		<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
		<div class="alignleft actions"><button type="submit" id="delete-button" name="deleting" value="category" class="button-secondary"><?php _e('Delete','Shopp'); ?></button></div>
	</div>
	<br class="clear" />

	<table class="widefat">
		<thead>
		<tr>
			<th scope="col" class="check-column"><input type="checkbox" id="selectall" /></th>
	        <th scope="col"><?php _e('Name','Shopp'); ?></th>
	        <th scope="col"><?php _e('Description','Shopp'); ?></th>
	        <th scope="col" class="num"><?php _e('Products','Shopp'); ?></th>
		</tr>
		</thead>
	<?php if (sizeof($Categories) > 0): ?>
		<tbody id="categories" class="list categories">
		<?php $even = false; foreach ($Categories as $Category): ?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<th scope='row' class='check-column'><input type='checkbox' name='delete[]' value='<?php echo $Category->id; ?>' /></th>
			<td><a class='row-title' href='?page=<?php echo $this->Admin->categories; ?>&amp;edit=<?php echo $Category->id; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo $Category->name; ?>&quot;'><?php echo str_repeat("&#8212; ",$Category->depth); echo (!empty($Category->name))?$Category->name:'(no category name)'; ?></a></td>
			<td><?php echo $Category->description; ?></td>
			<td class="num"><?php echo $Category->products; ?></td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	<?php else: ?>
		<tbody><tr><td colspan="4"><?php _e('No categories found.','Shopp'); ?></td></tr></tbody>
	<?php endif; ?>
	</table>
	</form>
</div>
<script type="text/javascript">
	helpurl = "<?php echo SHOPP_DOCS; ?>Categories";

	$=jQuery.noConflict();
	$('#selectall').change( function() {
		$('#categories th input').each( function () {
			if (this.checked) this.checked = false;
			else this.checked = true;
		});
	});

	$('#delete-button').click(function() {
		if (confirm("<?php _e('Are you sure you want to delete the selected categories?','Shopp'); ?>")) {
			$('<input type="hidden" name="categories" value="list" />').appendTo($('#categories'));
			return true;
		} else return false;
	});
	
</script>