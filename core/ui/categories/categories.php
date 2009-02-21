<div class="wrap shopp">
	<h2><?php _e('Categories','Shopp'); ?></h2>
	
	<form action="" id="categories" method="get">
	<div>
		<input type="hidden" name="page" value="<?php echo $this->Admin->categories; ?>" />
	</div>

	<p id="post-search" class="search-box">
		<input type="text" id="categories-search-input" class="search-input" name="s" value="<?php echo attribute_escape($_GET['s']); ?>" />
		<input type="submit" value="<?php _e('Search Categories','Shopp'); ?>" class="button" />
	</p>
	<p><button type="submit" name="edit" value="new" class="button"><?php _e('New Category','Shopp'); ?></button></p>

	<div class="tablenav">
		<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
		<div class="alignleft actions"><button type="submit" id="delete-button" name="deleting" value="category" class="button-secondary"><?php _e('Delete','Shopp'); ?></button></div>
		<div class="clear"></div>
	</div>
	<div class="clear"></div>

	<table class="widefat" cellspacing="0">
		<thead>
		<tr><?php shopp_print_column_headers('shopp_page_shopp/categories'); ?></tr>
		</thead>
		<tfoot>
		<tr><?php shopp_print_column_headers('shopp_page_shopp/categories',false); ?></tr>
		</tfoot>
	<?php if (sizeof($Categories) > 0): ?>
		<tbody id="categories" class="list categories">
		<?php $even = false; foreach ($Categories as $Category): ?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<th scope='row' class='check-column'><input type='checkbox' name='delete[]' value='<?php echo $Category->id; ?>' /></th>
			<td width="33%"><a class='row-title' href='?page=<?php echo $this->Admin->categories; ?>&amp;edit=<?php echo $Category->id; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo $Category->name; ?>&quot;'><?php echo str_repeat("&#8212; ",$Category->depth); echo (!empty($Category->name))?$Category->name:'(no category name)'; ?></a></td>
			<td width="30%" class="description column-description"><?php echo $Category->description; ?>&nbsp;</td>
			<td class="num links column-links"><?php echo $Category->total; ?></td>
			<td width="5%" class="templates column-templates"><?php if ($Category->spectemplate == "on") _e('On','Shopp'); ?></td>
			<td width="5%" class="menus column-menus"><?php if ($Category->facetedmenus == "on") _e('On','Shopp'); ?></td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	<?php else: ?>
		<tbody><tr><td colspan="6"><?php _e('No categories found.','Shopp'); ?></td></tr></tbody>
	<?php endif; ?>
	</table>
	</form>
</div>
<div class="tablenav">
	<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
	<div class="clear"></div>
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
	columns.init('toplevel_page_shopp/orders');	
</script>