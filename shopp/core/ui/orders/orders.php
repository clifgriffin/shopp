<div class="wrap shopp">
	<h2><?php _e('Orders','Shopp'); ?></h2>

	<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="get">
	<div>
		<input type="hidden" name="page" value="<?php echo $_GET['page']; ?>" />
	</div>
	<?php include("navigation.php"); ?>

	<br class="clear" />
	<p id="post-search" class="search-box">
		<input type="text" id="orders-search-input" class="search-input" name="s" value="<?php echo attribute_escape($_GET['s']); ?>" />
		<input type="submit" value="<?php _e('Search Orders','Shopp'); ?>" class="button" />
	</p>
	
	<div class="tablenav">
		<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
		<div class="alignleft actions"><button type="submit" id="delete-button" name="deleting" value="order" class="button-secondary"><?php _e('Delete','Shopp'); ?></button></div>
		<div class="clear"></div>
	</div>
	<div class="clear"></div>

	<table class="widefat" cellspacing="0">
		<thead>
		<tr><?php shopp_print_column_headers('toplevel_page_shopp/orders'); ?></tr>
		</thead>
		<?php if (SHOPP_WP27): ?>
		<tfoot>
		<tr><?php shopp_print_column_headers('toplevel_page_shopp/orders',false); ?></tr>
		</tfoot>
		<?php endif; ?>
	<?php if (sizeof($Orders) > 0): ?>
		<tbody id="orders" class="list orders">
		<?php $even = false; foreach ($Orders as $Order): ?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<th scope='row' class='check-column'><input type='checkbox' name='delete[]' value='<?php echo $Order->id; ?>' /></th>
			<td class="order column-order"><?php echo $Order->id; ?></td>
			<td class="name column-name"><a class='row-title' href='?page=<?php echo $this->Admin->default; ?>&amp;manage=<?php echo $Order->id; ?>' title='<?php _e('View','Shopp'); ?> &quot;<?php echo $Order->id; ?>&quot;'><?php echo (empty($Order->firstname) && empty($Order->lastname))?"("._e('no contact name').")":"{$Order->firstname} {$Order->lastname}"; ?></a></td>
			<td class="destination column-destination"><?php 
				$location = '';
				$location = $Order->shipcity;
				if (!empty($location) && !empty($Order->shipstate)) $location .= ', ';
				$location .= $Order->shipstate;
				if (!empty($location) && !empty($Order->shipcountry))
					$location .= ' &mdash; ';
				$location .= $Order->shipcountry;
				echo $location;
				 ?></td>
			<td class="total column-total"><?php echo money($Order->total); ?></td>
			<td class="date column-date"><?php echo date("Y/m/d",mktimestamp($Order->created)); ?><br />
				<strong><?php echo $statusLabels[$Order->status]; ?></strong></td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	<?php else: ?>
		<tbody><tr><td colspan="6"><?php _e('No','Shopp'); ?><?php if (isset($_GET['status'])) echo ' '.strtolower($statusLabels[$_GET['status']]); ?> <?php _e('orders, yet.','Shopp'); ?></td></tr></tbody>
	<?php endif; ?>
	</table>
	
	</form>
</div>        
<div class="tablenav">
	<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
	<div class="clear"></div>
</div>

<script type="text/javascript">
helpurl = "<?php echo SHOPP_DOCS; ?>Managing Orders";

$=jQuery.noConflict();

$('#delete-button').click(function() {
	if (confirm("<?php _e('Are you sure you want to delete the selected orders?','Shopp'); ?>")) return true;
	else return false;
});                       
columns.init('toplevel_page_shopp/orders');
</script>