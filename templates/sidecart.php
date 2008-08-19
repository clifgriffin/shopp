<?php if (shopp('cart','hasitems')): ?>
	<ul>
		<li><a href="<?php shopp('cart','url'); ?>"><?php shopp('cart','totalitems'); ?> <strong>Items</strong></a></li>
		<li><a href="<?php shopp('cart','url'); ?>"><strong>Total</strong> <span class="money"><?php shopp('cart','total'); ?></span></a></li>
	</ul>
<?php else: ?>
	<ul>
		<li>Your cart is empty.</li>
	</ul>
<?php endif; ?>