<?php if (shopp('cart','hasitems')): ?>
<li>
	<h3><a href="<?php shopp('cart','url'); ?>">Your Cart</a></h3>
	<ul>
		<li><?php shopp('cart','totalitems'); ?> <strong>Items</strong></li>
		<li><strong>Total</strong> <span class="money"><?php shopp('cart','total'); ?></span></li>
	</ul>
</li>	
<?php endif; ?>