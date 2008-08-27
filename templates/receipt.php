<div id="receipt" class="shopp">
<table class="transaction">
	<tr><th>Order Num:</th><td><?php shopp('purchase','id'); ?></td></tr>	
	<tr><th>Order Date:</th><td><?php shopp('purchase','date','format=F j, Y'); ?></td></tr>	
	<tr><th>Billed To:</th><td><?php shopp('purchase','card'); ?> (<?php shopp('purchase','cardtype'); ?>)</td></tr>	
	<tr><th>Transaction:</th><td><?php shopp('purchase','transactionid'); ?></td></tr>	
</table>

<fieldset>
	<legend><?php shopp('purchase','label'); ?></legend>
	<address><big><?php shopp('purchase','firstname'); ?> <?php shopp('purchase','lastname'); ?></big><br />
	<?php shopp('purchase','address'); ?><br />
	<?php shopp('purchase','xaddress'); ?>
	<?php shopp('purchase','city'); ?>, <?php shopp('purchase','state'); ?> <?php shopp('purchase','postcode'); ?><br />
	<?php shopp('purchase','country'); ?></address>
</fieldset>

<?php if (shopp('purchase','hasitems')): ?>
<table class="cart widefat">
	<thead>
	<tr>
		<th scope="col" class="item">Items Ordered</th>
		<th scope="col">Quantity</th>
		<th scope="col" class="money">Item Price</th>
		<th scope="col" class="money">Item Total</th>
	</tr>
	</thead>

	<?php while(shopp('purchase','items')): ?>
		<tr>
			<td><?php shopp('purchase','item-name'); ?><?php shopp('purchase','item-options','before= (&after=)'); ?><br />
				<?php shopp('purchase','item-sku')."<br />"; ?>
				<?php shopp('purchase','item-download'); ?>
				</td>
			<td><?php shopp('purchase','item-quantity'); ?></td>
			<td class="money"><?php shopp('purchase','item-unitprice'); ?></td>
			<td class="money"><?php shopp('purchase','item-total'); ?></td>
		</tr>
	<?php endwhile; ?>

	<tr class="totals">
		<th scope="row" colspan="3" class="total">Subtotal</th>
		<td class="money"><?php shopp('purchase','subtotal'); ?></td>
	</tr>
	<?php if (shopp('purchase','hasfrieght')): ?>
	<tr class="totals">
		<th scope="row" colspan="3" class="total">Shipping</th>
		<td class="money"><?php shopp('purchase','freight'); ?></td>
	</tr>
	<?php else: ?>
	<tr class="totals">
		<th scope="row" colspan="4" class="total"><?php //echo $this->Core->Settings->get('free_shipping_text'); ?></th>
	</tr>
	<?php endif; ?>
	<?php if (shopp('purchase','hastax')): ?>
	<tr class="totals">
		<th scope="row" colspan="3" class="total">Tax</th>
		<td class="money"><?php shopp('purchase','tax'); ?></td>
	</tr>
	<?php endif; ?>
	<tr class="totals">
		<th scope="row" colspan="3" class="total">Total</th>
		<td class="money"><?php shopp('purchase','total'); ?></td>
	</tr>
</table>
<?php else: ?>
	<p class="warning">There were no items found for this purchase.</p>
<?php endif; ?>
</div>