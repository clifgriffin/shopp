<div id="shopp">
<?php shopp('catalog','breadcrumb')?>
<?php if (shopp('product','found')): ?>

	<h3><?php shopp('product','name'); ?></h3>
	<p class="headline"><big><?php shopp('product','summary'); ?></big></p>

	<?php shopp('product','gallery'); ?>

	<?php if (shopp('product','onsale')): ?>
		<h4 class="original price"><?php shopp('product','price'); ?></h4>
		<h4 class="sale price"><?php shopp('product','saleprice'); ?></h4>		
	<?php else: ?>
		<h4><?php shopp('product','price'); ?></h4>
	<?php endif; ?>

	<form action="<?php shopp('cart','url'); ?>" method="post" class="shopp product">
		<?php if(shopp('product','has-variations')): ?>
		<ul class="variations">
			<?php shopp('product','variations','label=true&defaults=Select an option&before_menu=<li>&after_menu=</li>'); ?>
		</ul>
		<?php endif; ?>

		<?php if(shopp('product','has-addons')): ?>
		<ul class="options">
			<?php shopp('product','addons','mode=single&label=true&default=Select an option&before_menu=<li>&after_menu=</li>'); ?>
		</ul>
		<?php endif; ?>


		<?php shopp('product','addtocart'); ?>
	
	</form>

	<div class="description"><?php shopp('product','description'); ?></div>

	<?php if(shopp('product','has-specs')): ?>
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