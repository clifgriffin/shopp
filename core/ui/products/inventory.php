<div class="wrap shopp">

	<div class="icon32"></div>
	<h2><?php _e('Inventory','Shopp'); ?></h2>
	<?php if (!empty($Shopp->Flow->Notice)): ?><div id="message" class="updated fade"><p><?php echo $Shopp->Flow->Notice; ?></p></div><?php endif; ?>

	<form action="" method="get">
	<?php include("navigation.php"); ?>

	<div>
		<input type="hidden" name="page" value="<?php echo $this->Admin->pagename('products'); ?>" />
		<input type="hidden" name="f" value="i" />
	</div>

	<p id="post-search" class="search-box">
		<input type="text" id="products-search-input" class="search-input" name="s" value="<?php echo stripslashes(esc_attr($s)); ?>" />
		<input type="submit" value="<?php _e('Search Products','Shopp'); ?>" class="button" />
	</p>

	<div class="tablenav">
		<?php if ($page_links) echo "<div class='tablenav-pages'>$page_links</div>"; ?>
		<div class="alignleft actions filters">
		<select name="cat" class="filters">
		<?php echo $categories_menu; ?>
		</select>
		<select name="sl" class="filters">
		<?php echo $inventory_menu; ?>
		</select>
		<input type="submit" id="filter-button" value="<?php _e('Filter','Shopp'); ?>" class="button-secondary">
		</div>
		<div class="clear"></div>
	</div>
	</form>
	<div class="clear"></div>
	
	<form action="" method="post" id="inventory-manager">
	<table class="widefat" cellspacing="0">
		<thead>
		<tr><?php print_column_headers('shopp_page_shopp-products'); ?></tr>
		</thead>
		<tfoot>
		<tr><?php print_column_headers('shopp_page_shopp-products',false); ?></tr>
		</tfoot>
	<?php if (sizeof($Products) > 0): ?>
		<tbody id="products" class="list products">
		<?php 
		$hidden = get_hidden_columns('shopp_page_shopp-products');

		$even = false; 
		foreach ($Products as $key => $Product):
		$editurl = esc_url(esc_attr(add_query_arg(array_merge(stripslashes_deep($_GET),
			array('page'=>'shopp-products',
					'id'=>$Product->product,
					'f'=>null)),
					admin_url('admin.php'))));
		
		$ProductName = empty($Product->name)?'('.__('no product name','Shopp').')':$Product->name;
		if (!empty($Product->label) && $Product->optionkey > 0) $ProductName .= " ($Product->label)";
		?>
		<tr<?php if (!$even) echo " class='alternate'"; $even = !$even; ?>>
			<td class="inventory column-inventory">
			<input type="text" name="stock[<?php echo $Product->id; ?>]" value="<?php echo $Product->stock; ?>" size="6" class="stock selectall" />
			<input type="hidden" name="db[<?php echo $Product->id; ?>]" value="<?php echo $Product->stock; ?>" class="db" />
			</td> 
			<td class="sku column-sku"><?php echo $Product->sku; ?></td>
			<td class="name column-name"><a class='row-title' href='<?php echo $editurl; ?>' title='<?php _e('Edit','Shopp'); ?> &quot;<?php echo $ProductName; ?>&quot;'><?php echo $ProductName; ?></a></td>
		
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
/* <![CDATA[ */
jQuery(document).ready( function() {
	var $=jqnc(),
		updateurl = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'),'wp_ajax_shopp_update_inventory'); ?>';
	
	$('input.stock').change(function () {
		var $this = $(this),name = $this.attr('name'),bg = $this.css('background-color'),db = $this.nextAll('input.db');
		$this.addClass('updating');
		
		if ($this.val() == db.val()) return $this.removeClass('updating');
		
		$.ajaxSetup({error:function () {
				$this.val(db.val()).removeClass('updating').css('background-color','pink').dequeue().animate({backgroundColor:bg},500);
			}
		});
		$.get(updateurl,{
				'id':$this.attr('name').replace(new RegExp(/stock\[(\d+)\]$/),'$1'),
				'stock':$this.val(),
				'action':'shopp_update_inventory'
			},function (result) {
				if (result != '1') return this.error();
				db.val($this.val());
				$this.removeClass('updating').css('background-color','#FFFFE0').dequeue().animate({backgroundColor:bg},500);
		});
	});

	pagenow = 'shopp_page_shopp-products';
	columns.init(pagenow);

});
/* ]]> */
</script>