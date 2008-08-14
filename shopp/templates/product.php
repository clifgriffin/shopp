<div id="shopp">
<?php shopp('catalog','breadcrumb')?>
<?php if (shopp('product','found')): ?>

	<h3><?php shopp('product','name'); ?></h3>
	<p class="headline"><?php shopp('product','summary'); ?></p>

	<?php shopp('product','gallery'); ?>

	<?php if (shopp('product','onsale')): ?>
		<h4 class="original price"><?php shopp('product','price'); ?></h4>
		<h4 class="sale price"><?php shopp('product','saleprice'); ?></h4>		
	<?php else: ?>
		<h4><?php shopp('product','price'); ?></h4>
	<?php endif; ?>

	<form action="<?php shopp('cart','url'); ?>" method="post" class="shopp product">
		<?php if(shopp('product','hasoptions')): ?>
		<ul class="options">
			<?php while(shopp('product','options','variations')): ?>
				<li><label><?php shopp('product','option','label'); ?></label>
				<?php shopp('product','option','menu&default=Select an option...'); ?></li>
			<?php endwhile; ?>
		</ul>
		<?php endif; ?>

		<?php shopp('product','buynow'); ?>
		<?php shopp('product','addtocart'); ?>
	
	</form>

	<div class="description"><?php shopp('product','description'); ?></div>

	<?php if(shopp('product','hasspecs')): ?>
	<ul class="details">
		<?php while(shopp('product','specs')): ?>
		<li><?php shopp('product','spec','name&content'); ?></li>
		<?php endwhile; ?>
	</ul>
	<?php endif; ?>
	
<?php else: ?>
<h3>Product Not Found</h3>
<p>Sorry!  The product you requested is not found in our catalog!</p>
<?php endif; ?>
</div>