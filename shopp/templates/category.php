<div id="shopp">
<?php shopp('catalog','breadcrumb')?>
<h3><?php shopp('category','name'); ?></h3>

<?php if(shopp('category','hasproducts')): ?>
<ul class="products">
	<?php while(shopp('category','products')): ?>
	<li>
	<?php shopp('category','product','thumbnail&link'); ?>
	<h4><?php shopp('category','product','name&link'); ?></h4>
	</li>
	<?php endwhile; ?>
</ul>
<?php endif; ?>
</div>
