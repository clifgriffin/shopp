<div class="wrap shopp">
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>
	<h2><?php _e('Order','Shopp'); ?></h2>

	<?php include("navigation.php"); ?>
	
	<div id="order">

		<div id="receipt" class="shopp">
		<table class="transaction">
			<tr><th><?php _e('Order Num','Shopp'); ?>:</th><td><?php echo $Purchase->id; ?></td></tr>	
			<tr><th><?php _e('Order Date','Shopp'); ?>:</th><td><?php echo date('F j, Y', $Purchase->created); ?></td></tr>	
			<?php if (!empty($Purchase->card) && !empty($Purchase->cardtype)): ?><tr><th><?php _e('Billed To','Shopp'); ?>:</th><td><?php (!empty($Purchase->card))?printf("%'X16d",$Purchase->card):''; ?> <?php echo (!empty($Purchase->cardtype))?'('.$Purchase->cardtype.')':''; ?></td></tr><?php endif; ?>
			<tr><th><?php _e('Transaction','Shopp'); ?>:</th><td><?php echo $Purchase->transactionid; ?></td></tr>	
			<?php if ($Purchase->gateway == "Google Checkout"):?>
				<tr><th><?php _e('Status','Shopp'); ?>:</th><td><?php echo $Purchase->transtatus; ?></td></tr>	
			<?php endif; ?>
		</table>

		<?php if (!empty($Purchase->shipaddress)): ?>
		<fieldset>
			<legend>Ship To</legend>
			<address><big><?php echo "{$Purchase->firstname} {$Purchase->lastname}"; ?></big><br />
			<?php echo $Purchase->shipaddress; ?><br />
			<?php if (!empty($Purchase->shipxaddress)) echo $Purchase->shipxaddress."<br />"; ?>
			<?php echo "{$Purchase->shipcity}, {$Purchase->shipstate} {$Purchase->shippostcode}" ?><br />
			<?php echo $Purchase->shipcountry ?></address>
			<p>Shipping: <?php echo $Purchase->shipmethod; ?></p>
		</fieldset>
		<?php else: ?>
			<fieldset>
				<legend><?php _e('Customer','Shopp'); ?></legend>
				<address><big><?php echo "{$Purchase->firstname} {$Purchase->lastname}"; ?></big><br />
				<?php echo $Purchase->address; ?><br />
				<?php if (!empty($Purchase->xaddress)) echo $Purchase->xaddress."<br />"; ?>
				<?php echo "{$Purchase->city}, {$Purchase->state} {$Purchase->postcode}" ?><br />
				<?php echo $Purchase->country ?></address>
			</fieldset>
		<?php endif; ?>
		
		<?php if (sizeof($Purchase->purchased) > 0): ?>
		<table class="cart widefat">
			<thead>
			<tr>
				<th scope="col" class="item"><?php _e('Items Ordered','Shopp'); ?></th>
				<th scope="col"><?php _e('Quantity','Shopp'); ?></th>
				<th scope="col" class="money"><?php _e('Item Price','Shopp'); ?></th>
				<th scope="col" class="money"><?php _e('Item Total','Shopp'); ?></th>
			</tr>
			</thead>
			<?php $even = false; foreach ($Purchase->purchased as $id => $Item): ?>
				<tr<?php if (!even) echo 'class="alternate"'; $even = !$even; ?>>
					<td>
						<?php echo $Item->name; ?>
						<?php if (!empty($Item->optionlabel)) echo "({$Item->optionlabel})"; ?>
					</td>
					<td><?php echo $Item->quantity; ?></td>
					<td class="money"><?php echo money($Item->unitprice); ?></td>
					<td class="money total"><?php echo money($Item->total); ?></td>
				</tr>
			<?php endforeach; ?>
			<tr class="totals">
				<th scope="row" colspan="3" class="total"><?php _e('Subtotal','Shopp'); ?></th>
				<td class="money"><?php echo money($Purchase->subtotal); ?></td>
			</tr>
			<?php if ($Purchase->discount > 0): ?>
			<tr class="totals">
				<th scope="row" colspan="3" class="total"><?php _e('Discount','Shopp'); ?></th>
				<td class="money">-<?php echo money($Purchase->discount); ?></td>
			</tr>
			<?php endif; ?>
			<?php if ($Purchase->freight > 0): ?>
			<tr class="totals">
				<th scope="row" colspan="3" class="total"><?php _e('Shipping','Shopp'); ?></th>
				<td class="money"><?php echo money($Purchase->freight); ?></td>
			</tr>
			<?php else: ?>
			<tr class="totals">
				<th scope="row" colspan="4" class="total"><?php //echo $this->Core->Settings->get('free_shipping_text'); ?></th>
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
		</table>
		<?php else: ?>
			<p class="warning"><?php _e('There were no items found for this purchase.','Shopp'); ?></p>
		<?php endif; ?>
		</div>
		
		<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post" id="order-status">
		<?php wp_nonce_field('shopp-save-order'); ?>
		<div id="notification">
		<div class="tablenav"><p class="alignright"><input type="hidden" name="receipt" value="no" /><input type="checkbox" name="receipt" value="yes" id="include-order" checked="checked" /><label for="include-order">&nbsp;<?php _e('Include a copy of the order in the message','Shopp'); ?></label></p>
		</div>
		<br class="clear" />
		<p><textarea name="message" id="message" cols="50" rows="10" ></textarea></p>
		</div>
		<div class="tablenav">
			<p class="alignright"><label for="order_status_menu"><?php _e('Order Status','Shopp'); ?>:</label>
				<select name="status" id="order_status_menu">
				<?php echo menuoptions($statusLabels,$Purchase->status,true); ?>
				</select>
				<span class="middle"><input type="hidden" name="notify" value="no" /><input type="checkbox" name="notify" value="yes" id="notify-customer" /><label for="notify-customer">&nbsp;<?php _e('Send customer notification','Shopp'); ?></label></span>
				<button type="submit" name="update" value="status" class="button-secondary"><?php _e('Update Status','Shopp'); ?></button></p>
		</div>
		</form>
	</div>
	
</div>

<script type="text/javascript">
$=jQuery.noConflict();

$('#notification').hide();
$('#notify-customer').click(function () {
	$('#notification').slideToggle(500);
});

</script>

