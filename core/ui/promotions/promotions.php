<div class="wrap shopp">
	<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" id="categories" method="get">
	<div>
		<input type="hidden" name="page" value="<?php echo $this->Admin->promotions; ?>" />
	</div>
	<h2><?php _e('Promotions','Shopp'); ?></h2>

	<p id="post-search" class="search-box">
		<label class="hidden" for="post-search-input">Search Promotions:</label>
		<input type="text" id="promotions-search-input" name="s" class="search-input" value="<?php echo attribute_escape($_GET['s']); ?>" />
		<input type="submit" value="Search Promotions" class="button" />
	</p>

	<p class="search-box"><button type="submit" name="promotion" value="new" class="button"><?php _e('New Promotion','Shopp'); ?></button></p>

	<div class="tablenav">
		<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
		<div class="alignleft actions"><button type="submit" id="delete-button" name="deleting" value="promotion" class="button-secondary"><?php _e('Delete','Shopp'); ?></button></div>
		<br class="clear" />
	</div>

	<br class="clear" />

	<table class="widefat">
		<thead>
		<tr>
			<th scope="col" class="check-column"><input type="checkbox" id="selectall" /></th>
	        <th scope="col"><?php _e('Name','Shopp'); ?></th>
	        <th scope="col"><?php _e('Effective','Shopp'); ?></th>
	        <th scope="col"><?php _e('Status','Shopp'); ?></th>
		</tr>
		</thead>
	<?php if (sizeof($Promotions) > 0): ?>
		<tbody id="promotions" class="list promotions">
		<?php $even = false; foreach ($Promotions as $Promotion): ?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<th scope='row' class='check-column'><input type='checkbox' name='delete[]' value='<?php echo $Promotion->id; ?>' /></th>
			<td><a class='row-title' href='?page=<?php echo $this->Admin->promotions; ?>&amp;promotion=<?php echo $Promotion->id; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo $Promotion->name; ?>&quot;'><?php echo str_repeat("&#8212; ",$Promotion->depth).(!empty($Promotion->name))?$Promotion->name:'(no promotion name)'; ?></a></td>
			<td><?php
				if (mktimestamp($Promotion->starts > 1) && mktimestamp($Promotion->ends) > 1)
					echo date(get_option(date_format),mktimestamp($Promotion->starts))." &mdash; ".date(get_option(date_format),mktimestamp($Promotion->ends));
				else echo date(get_option(date_format),mktimestamp($Promotion->created)).", ".__('does not expire','Shopp');
			?></td>
			<td><?php echo ucfirst($Promotion->status); ?></td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	<?php else: ?>
		<tbody><tr><td colspan="4"><?php _e('No promotions found.','Shopp'); ?></td></tr></tbody>
	<?php endif; ?>
	</table>
	</form>
</div>
<script type="text/javascript">
	helpurl = "<?php echo SHOPP_DOCS; ?>Running_Sales_%26_Promotions";

	$=jQuery.noConflict();
	$('#selectall').change( function() {
		$('#promotions th input').each( function () {
			if (this.checked) this.checked = false;
			else this.checked = true;
		});
	});

	$('#delete-button').click(function() {
		if (confirm("<?php _e('Are you sure you want to delete the selected promotions?','Shopp'); ?>")) {
			$('<input type="hidden" name="promotions" value="list" />').appendTo($('#promotions'));
			return true;
		} else return false;
	});
	
</script>