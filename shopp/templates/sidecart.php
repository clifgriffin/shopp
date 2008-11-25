<div id="shopp-cart-ajax"></div>
<?php if (shopp('cart','hasitems')): ?>	
	<p class="status">
		<a href="<?php shopp('cart','url'); ?>"><span id="shopp-cart-items"><?php shopp('cart','totalitems'); ?></span> <strong>Items</strong> &mdash; <strong>Total</strong> <span id="shopp-cart-total" class="money"><?php shopp('cart','total'); ?></span></a>
	</p>
<?php else: ?>
	<p class="status">Your cart is empty.</p>
<?php endif; ?>