<div id="shopp">
<?php shopp('catalog','breadcrumb')?>
<h3><?php shopp('category','name'); ?></h3>

<?php if(shopp('category','hasproducts')): ?>

<ul class="products">
	<li class="row"><ul>
	<?php while(shopp('category','products')): ?>
	<?php if(shopp('category','row','products=5')): ?></ul></li><li class="row"><ul><?php endif; ?>
		<li class="product">
		<?php shopp('category','product','thumbnail&link'); ?>
		<h4 class="name"><?php shopp('category','product','name&link'); ?></h4>
		<p class="price"><?php shopp('category','product','price'); ?></p>
		</li>
	<?php endwhile; ?>
	</ul></li>
</ul>

<?php endif; ?>
</div>
