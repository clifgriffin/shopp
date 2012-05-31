<div class="wrap shopp">

	<div class="icon32"></div>
	<h2><?php _e('Order','Shopp'); ?></h2>

	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>

	<?php include("navigation.php"); ?>
	<br class="clear" />

	<form action="<?php echo esc_url(add_query_arg(array('id'=>$Purchase->id),$this->url)); ?>" method="post" id="order-updates">
	<div id="order">
		<div class="title">
			<div id="titlewrap">
				<?php _e('Order','Shopp'); ?> #<?php echo $Purchase->id; ?><span class="date"><?php echo _d(get_option('date_format'), $Purchase->created); ?> <small><?php echo date(get_option('time_format'),$Purchase->created); ?></small></span>

				<div class="alignright">

					<?php if ($Purchase->shipped): ?>
					<div class="stamp shipped<?php if ($Purchase->isvoid()) echo ' void'; ?>"><div class="type"><?php _e('Shipped','Shopp'); ?></div><div class="ing">&nbsp;</div></div>
					<?php endif; ?>

					<?php if ($Purchase->isvoid()): ?>
					<div class="stamp void"><div class="type"><?php _e('Void','Shopp'); ?></div><div class="ing">&nbsp;</div></div>
					<?php elseif ($Purchase->ispaid()): ?>
					<div class="stamp paid"><div class="type"><?php _e('Paid','Shopp'); ?></div><div class="ing">&nbsp;</div></div>
					<?php endif; ?>

				</div>

			</div>
		</div>
		<?php if (sizeof($Purchase->purchased) > 0): ?>
		<table class="widefat" cellspacing="0">
			<thead>
			<tr>
				<th scope="col" class="item"><?php _e('Items Ordered','Shopp'); ?></th>
				<th scope="col"><?php _e('Quantity','Shopp'); ?></th>
				<th scope="col" class="money"><?php _e('Item Price','Shopp'); ?></th>
				<th scope="col" class="money"><?php _e('Item Total','Shopp'); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php
				$even = false;
				foreach ($Purchase->purchased as $id => $Item):
					$taxrate = round($Item->unittax/$Item->unitprice,4);
			?>
				<tr<?php if ($even) echo ' class="alternate"'; $even = !$even; ?>>
					<td>
						<a href="<?php echo add_query_arg(array('page' => 'shopp-products','id' => $Item->product),admin_url('admin.php')); ?>"><?php echo $Item->name; ?>
						<?php if (!empty($Item->optionlabel)) echo "({$Item->optionlabel})"; ?></a>
						<?php if (is_array($Item->data) || !empty($Item->sku) || !empty($Item->addons)): ?>
						<ul>
						<?php if (!empty($Item->sku)): ?><li><small><?php _e('SKU','Shopp'); ?>: <strong><?php echo $Item->sku; ?></strong></small></li><?php endif; ?>

						<?php if (isset($Item->addons) && isset($Item->addons->meta)): ?>
							<?php foreach ((array)$Item->addons->meta as $id => $addon):
								if ($Purchase->taxing == "inclusive")
									$addonprice = $addon->value->unitprice+($addon->value->unitprice*$taxrate);
								else $addonprice = $addon->value->unitprice;

								?>
								<li><small><?php echo apply_filters('shopp_purchased_addon_name',$addon->name); ?><?php if (!empty($addon->value->sku)) echo apply_filters('shopp_purchased_addon_sku',' [SKU: '.$addon->value->sku.']'); ?>: <strong><?php echo apply_filters('shopp_purchased_addon_unitprice',money($addonprice)); ?></strong></small></li>
							<?php endforeach; ?>
						<?php endif; ?>
						<?php foreach ($Item->data as $name => $value): ?>
							<li><small><?php echo apply_filters('shopp_purchased_data_name',$name); ?>: <strong><?php echo apply_filters('shopp_purchased_data_value',$value); ?></strong></small></li>
						<?php endforeach; ?>
						<?php endif; ?>
						<?php do_action_ref_array('shopp_after_purchased_data',array(&$Item,&$Purchase)); ?>
						</ul>
					</td>
					<td><?php echo $Item->quantity; ?></td>
					<td class="money"><?php $amount = $Item->unitprice+($Purchase->taxing == 'inclusive'?$Item->unittax:0);
						echo money($amount); ?></td>
					<td class="money total"><?php $amount = $Item->total+($Purchase->taxing == 'inclusive'?$Item->unittax*$Item->quantity:0);
						echo money($amount); ?></td>
				</tr>
			<?php endforeach; ?>
			<tr class="totals">
				<th scope="row" colspan="3" class="total"><?php _e('Subtotal','Shopp'); ?></th>
				<td class="money"><?php echo money($Purchase->subtotal); ?></td>
			</tr>
			<?php if ($Purchase->discount > 0): ?>
			<tr class="totals">
				<th scope="row" colspan="3" class="total"><?php _e('Discount','Shopp'); ?></th>
				<td class="money">-<?php echo money($Purchase->discount); ?>
					<?php if (!empty($Purchase->promos)): ?>
					<ul class="promos">
					<?php foreach ($Purchase->promos as $pid => $promo): ?>
						<li><small><a href="?page=shopp-promotions&amp;id=<?php echo $pid; ?>"><?php echo $promo; ?></a></small></li>
					<?php endforeach; ?>
					</ul>
					<?php endif; ?>
					</td>
			</tr>
			<?php endif; ?>
			<?php if ($Purchase->freight > 0): ?>
			<tr class="totals">
				<th scope="row" colspan="3" class="total shipping"><span class="method"><?php echo apply_filters('shopp_order_manager_shipping_method',$Purchase->shipoption); ?></span> <?php _e('Shipping','Shopp'); ?></th>
				<td class="money"><?php echo money($Purchase->freight); ?></td>
			</tr>
			<?php endif; ?>
			<?php if ($Purchase->tax > 0): ?>
			<tr class="totals">
				<th scope="row" colspan="3" class="total"><?php _e('Tax','Shopp'); ?></th>
				<td class="money"><?php echo money($Purchase->tax); ?></td>
			</tr>
			<?php endif; ?>
			<tr class="totals total">
				<th scope="row" colspan="3" class="total"><?php _e('Total','Shopp'); ?></th>
				<td class="money"><?php echo money($Purchase->total); ?></td>
			</tr>
			</tbody>
		</table>

		<?php else: ?>
			<p class="warning"><?php _e('There were no items found for this purchase.','Shopp'); ?></p>
		<?php endif; ?>

		<div id="poststuff" class="poststuff">

		<div class="meta-boxes">

			<div id="column-one" class="column left-column">
				<?php do_meta_boxes('toplevel_page_shopp-orders', 'side', $Purchase); ?>
			</div>
			<div id="main-column">
				<div id="column-two" class="column right-column">
					<?php do_meta_boxes('toplevel_page_shopp-orders', 'normal', $Purchase); ?>
				</div>
			</div>
			<br class="clear" />
		</div>

		<?php wp_nonce_field('shopp-save-order'); ?>
		<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
		<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
		</div>
	</div>
	</form>

</div>

<iframe id="print-receipt" name="receipt" src="<?php echo wp_nonce_url(admin_url('admin-ajax.php').'?action=shopp_order_receipt&amp;id='.$Purchase->id,'wp_ajax_shopp_order_receipt'); ?>" width="400" height="100" class="invisible"></iframe>

<script type="text/javascript">
/* <![CDATA[ */
var carriers = <?php echo json_encode($carriers_json); ?>;

jQuery(document).ready(function() {
	var $=jqnc(),
		noteurl = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), 'wp_ajax_shopp_order_note_message'); ?>';

	// close postboxes that should be closed
	$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
	postboxes.add_postbox_toggles('toplevel_page_shopp-orders');

	$('.postbox a.help').click(function () {
		$(this).colorbox({iframe:true,open:true,innerWidth:768,innerHeight:480,scrolling:false});
		return false;
	});

	$('#notification').hide();
	$('#notify-customer').click(function () {
		$('#notification').animate({
			height: "toggle",
			opacity:"toggle"
		}, 500);
	});

	$('#notation').hide();
	$('#add-note-button').click(function (e) {
		e.preventDefault();
		$('#add-note-button').hide();
		$('#notation').animate({
			height: "toggle",
			opacity:"toggle"
		}, 500);
	});

	$('#cancel-note-button').click(function (e) {
		e.preventDefault();
		$('#add-note-button').animate({opacity:"toggle"},500);
		$('#notation').animate({
			height: "toggle",
			opacity:"toggle"
		}, 500);
	});

	$('#order-notes table tr').hover(function () {
		$(this).find('.notectrls').animate({
			opacity:"toggle"
		}, 500);

	},function () {
		$(this).find('.notectrls').animate({
			opacity:"toggle"
		}, 100);

	});

	$('td .deletenote').click(function (e) {
		if (!confirm('Are you sure you want to delete this note?'))
			e.preventDefault();
	});

	$('td .editnote').click(function () {
		var editbtn = $(this).attr('disabled',true).addClass('updating'),
			cell = editbtn.parents('td'),
			note = cell.find('div'),
			ctrls = cell.find('span.notectrls'),
			meta = cell.find('p.notemeta'),
			idattr = note.attr('id').split("-"),
			id = idattr[1];
		$.get(noteurl+'&action=shopp_order_note_message&id='+id,false,function (msg) {
			editbtn.removeAttr('disabled').removeClass('updating');
			if (msg == '1') return;
			var editor = $('<textarea name="note-editor['+id+']" cols="50" rows="10" />').val(msg).prependTo(cell);
				ui = $('<div class="controls alignright">'+
						'<button type="button" name="cancel" class="cancel-edit-note button-secondary">Cancel</button>'+
						'<button type="submit" name="edit-note['+id+']" class="save-note button-primary">Save Note</button></div>').appendTo(meta),
				cancel = ui.find('button.cancel-edit-note').click(function () {
						editor.remove();
						ui.remove();
						note.show();
						ctrls.addClass('notectrls');
					});
			note.hide();
			ctrls.hide().removeClass('notectrls');
		});

	});

	$('#customer').click(function () {
		window.location = "<?php echo add_query_arg(array('page'=>$this->Admin->pagename('customers'),'id'=>$Purchase->customer),admin_url('admin.php')); ?>";
	});

<?php do_action_ref_array('shopp_order_admin_script',array(&$Purchase)); ?>

});
/* ]]> */
</script>