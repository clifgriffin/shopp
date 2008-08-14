<?php if (shopp('cart','hasitems')): ?>
<div id="cart" class="shopp">
<table>
	<tr>
		<th scope="col" class="item">Cart Items</th>
		<th scope="col">Quantity</th>
		<th scope="col" class="money">Item Price</th>
		<th scope="col" class="money">Item Total</th>
	</tr>

	<?php while(shopp('cart','items')): ?>
		<tr>
			<td><a href="<?php shopp('cartitem','url'); ?>"><?php shopp('cartitem','name'); ?></a><?php shopp('cartitem','options'); ?></td>
			<td><?php shopp('cartitem','quantity'); ?></td>
			<td class="money"><?php shopp('cartitem','unitprice'); ?></td>
			<td class="money"><?php shopp('cartitem','total'); ?></td>
		</tr>
	<?php endwhile; ?>

	<tr class="totals">
		<th scope="row" colspan="3">Subtotal</th>
		<td class="money"><?php shopp('cart','subtotal'); ?></td>
	</tr>
	<tr class="totals">
		<th scope="row" colspan="3"><?php shopp('cart','shipping','label=Shipping'); ?></th>
		<td class="money"><?php shopp('cart','shipping'); ?></td>
	</tr>
	<tr class="totals">
		<th scope="row" colspan="3"><?php shopp('cart','tax','label=Taxes'); ?></th>
		<td class="money"><?php shopp('cart','tax'); ?></td>
	</tr>
	<tr class="totals total">
		<th scope="row" colspan="3">Total</th>
		<td class="money"><?php shopp('cart','total'); ?></td>
	</tr>
</table>
</div>
<?php else: ?>
	<p class="warning">There are currently no items in your shopping cart.</p>
<?php endif; ?>
