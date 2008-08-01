<h3><?php shopp('category','name'); ?></h3>

<?php if(shopp('category','hasproducts')): ?>
<div id="shopp">
<ul class="products">
	<?php while(shopp('category','products')): ?>
	<li>
	<?php shopp('category','product','thumbnail&link'); ?>
	<h4><?php shopp('category','product','name&link'); ?></h4>
	</li>
	<?php endwhile; ?>
</ul>
</div>
<?php endif; ?>
