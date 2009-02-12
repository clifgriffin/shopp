<div class="wrap shopp">
	<h2><?php _e('Promotions','Shopp'); ?></h2>

	<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" id="promotions" method="get">
	<div>
		<input type="hidden" name="page" value="<?php echo $this->Admin->promotions; ?>" />
	</div>

	<p id="post-search" class="search-box">
		<input type="text" id="promotions-search-input" name="s" class="search-input" value="<?php echo attribute_escape($_GET['s']); ?>" />
		<input type="submit" value="<?php _e('Search Promotions','Shopp'); ?>" class="button" />
	</p>

	<p><button type="submit" name="promotion" value="new" class="button"><?php _e('New Promotion','Shopp'); ?></button></p>

	<div class="tablenav">
		<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
		<div class="alignleft actions"><button type="submit" id="delete-button" name="deleting" value="promotion" class="button-secondary"><?php _e('Delete','Shopp'); ?></button></div>
		<div class="clear"></div>
	</div>

	<div class="clear"></div>

	<table class="widefat" cellspacing="0">
		<thead>
		<tr><?php shopp_print_column_headers('shopp_page_shopp/promotions'); ?></tr>
		</thead>
		<tfoot>
		<tr><?php shopp_print_column_headers('shopp_page_shopp/promotions',false); ?></tr>
		</tfoot>
	<?php if (sizeof($Promotions) > 0): ?>
		<tbody id="promotions" class="list promotions">
		<?php $even = false; foreach ($Promotions as $Promotion): ?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<th scope='row' class='check-column'><input type='checkbox' name='delete[]' value='<?php echo $Promotion->id; ?>' /></th>
			<td width="33%" class="name column-name"><a class='row-title' href='?page=<?php echo $this->Admin->promotions; ?>&amp;promotion=<?php echo $Promotion->id; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo $Promotion->name; ?>&quot;'><?php echo str_repeat("&#8212; ",$Promotion->depth).(!empty($Promotion->name))?$Promotion->name:'(no promotion name)'; ?></a></td>
			<td class="discount column-discount"><?php 
				if ($Promotion->type == "Percentage Off") echo percentage($Promotion->discount);
				if ($Promotion->type == "Amount Off") echo money($Promotion->discount);
				if ($Promotion->type == "Free Shipping") echo $this->Settings->get("free_shipping_text");
				if ($Promotion->type == "Buy X Get Y Free") echo __('Buy','Shopp').' '.$Promotion->buyqty.' '.__('Get','Shopp').' '.$Promotion->getqty.' '.__('Free','Shopp');
			?></td>
			<td class="applied column-applied"><?php echo $Promotion->scope; ?></td>
			<td class="eff column-eff"><strong><?php echo ucfirst($Promotion->status); ?></strong><?php
				if (mktimestamp($Promotion->starts > 1) && mktimestamp($Promotion->ends) > 1)
					echo "<br />".date(get_option(date_format),mktimestamp($Promotion->starts))." &mdash; ".date(get_option(date_format),mktimestamp($Promotion->ends));
				else echo "<br />".date(get_option(date_format),mktimestamp($Promotion->created)).", ".__('does not expire','Shopp');
			?></td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	<?php else: ?>
		<tbody><tr><td colspan="5"><?php _e('No promotions found.','Shopp'); ?></td></tr></tbody>
	<?php endif; ?>
	</table>
	</form>
</div>   
<div class="tablenav">
	<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
	<div class="clear"></div>
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
 	columns.init('toplevel_page_shopp/promotions');
   
</script>