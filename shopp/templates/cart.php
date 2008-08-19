<?php if (shopp('cart','hasitems')): ?>
<form id="cart" action="<?php shopp('cart','url'); ?>" method="post" class="shopp">
<big>
	<a href="<?php shopp('catalog','url'); ?>">&laquo; Continue Shopping</a>
	<a href="<?php shopp('checkout','url'); ?>" class="right">Proceed to Checkout &raquo;</a>
</big>

<?php shopp('cart','function'); ?>
<table class="cart">
	<tr>
		<th scope="col" class="item">Cart Items</th>
		<th scope="col">Quantity</th>
		<th scope="col" class="money">Item Price</th>
		<th scope="col" class="money">Item Total</th>
	</tr>

	<?php while(shopp('cart','items')): ?>
		<tr>
			<td><a href="<?php shopp('cartitem','url'); ?>"><?php shopp('cartitem','name'); ?></a><?php shopp('cartitem','options'); ?></td>
			<td><?php shopp('cartitem','quantity','input=text'); ?>
				<?php shopp('cartitem','remove','input=button'); ?></td>
			<td class="money"><?php shopp('cartitem','unitprice'); ?></td>
			<td class="money"><?php shopp('cartitem','total'); ?></td>
		</tr>
	<?php endwhile; ?>

	<tr class="totals">
		<td colspan="2" rowspan="4">
			<?php if (shopp('cart','needs-shipped')): ?>
			<small>Select shipping country to calculate shipping and tax:</small>
			<?php shopp('cart','shipping-estimates'); ?>
			<?php endif; ?>
		</td>
		<th scope="row">Subtotal</th>
		<td class="money"><?php shopp('cart','subtotal'); ?></td>
	</tr>
	<tr class="totals">
		<th scope="row"><?php shopp('cart','shipping','label=Shipping'); ?></th>
		<td class="money"><?php shopp('cart','shipping'); ?></td>
	</tr>
	<tr class="totals">
		<th scope="row"><?php shopp('cart','tax','label=Tax'); ?></th>
		<td class="money"><?php shopp('cart','tax'); ?></td>
	</tr>
	<tr class="totals total">
		<th scope="row">Total</th>
		<td class="money"><?php shopp('cart','total'); ?></td>
	</tr>
	<tr class="buttons">
		<td colspan="4"><?php shopp('cart','update-button'); ?></td>
	</tr>
</table>

<big>
	<a href="<?php shopp('catalog','url'); ?>">&laquo; Continue Shopping</a>
	<a href="<?php shopp('checkout','url'); ?>" class="right">Proceed to Checkout &raquo;</a>
</big>


</form>
<?php else: ?>
	<p class="warning">There are currently no items in your shopping cart.</p>
<?php endif; ?>
