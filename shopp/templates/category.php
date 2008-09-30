<?php if(shopp('category','hasproducts')): ?>
	<div class="category">
	<?php shopp('catalog','breadcrumb'); ?>
	<h3><?php shopp('category','name'); ?></h3>
	<br class="clear" />
	<div class="alignright"><?php shopp('category','pagination'); ?></div>
	<br class="clear" />
	
	<ul class="products">
		<li class="row"><ul>
		<?php while(shopp('category','products')): ?>
		<?php if(shopp('category','row')): ?></ul></li><li class="row"><ul><?php endif; ?>
			<li class="product">
				<div class="frame">
				<?php shopp('category','product','thumbnail&link'); ?>
				<h4 class="name"><?php shopp('category','product','name&link'); ?></h4>
				<p class="price"><?php shopp('category','product','price'); ?></p>
				</div>
			</li>
		<?php endwhile; ?>
		</ul></li>
	</ul>
	<br class="clear" />
	<div class="alignright"><?php shopp('category','pagination'); ?></div>
	<br class="clear" />
	</div>
<?php endif; ?>
