<p><?php _e('Setup template values that will be copied into new products that are created and assigned this category.','Shopp'); ?></p>
<div id="templates"></div>

<div id="details-template" class="panel">
	<div class="pricing-label">
		<label><?php _e('Product Details','Shopp'); ?></label>
	</div>
	<div class="pricing-ui">

	<ul class="details multipane">
		<li><input type="hidden" name="deletedSpecs" id="deletedSpecs" value="" />
			<div id="details-menu" class="multiple-select options">
				<ul></ul>
			</div>
			<div class="controls">
			<button type="button" id="addDetail" class="button-secondary"><small><?php _e('Add Detail','Shopp'); ?></small></button>
			</div>
		</li>
		<li id="details-facetedmenu">
			<div id="details-list" class="multiple-select options">
				<ul></ul>
			</div>
			<div class="controls">
			<button type="button" id="addDetailOption" class="button-secondary"><small><?php _e('Add Option','Shopp'); ?></small></button>
			</div>
		</li>
	</ul>

	</div>
	<div class="clear"></div>
</div>
<div class="clear"></div>

<div id="price-ranges" class="panel">
	<div class="pricing-label">
		<label><?php _e('Price Range Search','Shopp'); ?></label>
	</div>
	<div class="pricing-ui">
	<select name="pricerange" id="pricerange-facetedmenu">
		<?php echo $pricemenu; ?>
	</select>
	<ul class="details multipane">
		<li><div id="pricerange-menu" class="multiple-select options"><ul class=""></ul></div>
			<div class="controls">
			<button type="button" id="addPriceLevel" class="button-secondary"><small><?php _e('Add Price Range','Shopp'); ?></small></button>
			</div>
		</li>
	</ul>
	<div class="clear"></div>

	<p><?php _e('Configure how you want price range options in this category to appear.','Shopp'); ?></p>

</div>
<div class="clear"></div>
<div id="pricerange"></div>
</div>

<div id="variations-template">
	<div id="variations-menus" class="panel">
		<div class="pricing-label">
			<label><?php _e('Variation Option Menus','Shopp'); ?></label>
		</div>
		<div class="pricing-ui">
			<p><?php _e('Create a predefined set of variation options for products in this category.','Shopp'); ?></p>
			<ul class="multipane">
				<li><div id="variations-menu" class="multiple-select options menu"><ul></ul></div>
					<div class="controls">
						<button type="button" id="addVariationMenu" class="button-secondary"><?php _e('Add Option Menu','Shopp'); ?></button>
					</div>
				</li>

				<li>
					<div id="variations-list" class="multiple-select options"></div>
					<div class="controls">
					<button type="button" id="addVariationOption" class="button-secondary"><?php _e('Add Option','Shopp'); ?></button>
					</div>
				</li>
			</ul>
			<div class="clear"></div>
		</div>
	</div>
<br />
<div id="variations-pricing"></div>
</div>