<div class="wrap shopp"> 

<h2><?php _e('Product Editor','Shopp'); ?></h2> 

<div id="ajax-response"></div> 

<form name="product" id="product" action="?page=<?php echo $this->Admin->products; ?>&amp;edit=<?php echo $_GET['edit']; ?>" method="post" enctype="multipart/form-data">
	<?php wp_nonce_field('shopp-save-product'); ?>
	
	<table class="form-table"> 
	<tbody>
		<tr class="form-field form-required"> 
			<th scope="row" valign="top"><label for="name"><?php _e('Product Name','Shopp'); ?></label></th> 
			<td><input name="name" id="name" type="text" value="<? echo $Product->name ?>" size="40" tabindex="1" /><br /> 
            <?php _e('The name of the product to be displayed in the catalog, shopping cart and order reciept.','Shopp'); ?></td> 
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="category-menu"><?php _e('Categories','Shopp'); ?></label>
				<div id="new-category">
				<input type="text" name="new-category" value="" size="15" id="new-category" /><br />
				<select name="new-category-parent"><?php echo $categories_menu; ?></select>
				<button id="add-new-category" type="button" class="button-secondary" tabindex="2"><small><?php _e('Add New Category','Shopp'); ?></small></button>
				</div>
				</th> 
			<td> 
				<div id="category-menu" class="multiple-select short">
					<ul>
						<?php $depth = 0; foreach ($categories as $category): 
						if ($category->depth > $depth) echo "<li><ul>"; ?>
						<?php if ($category->depth < $depth): ?>
							<?php for ($i = $category->depth; $i < $depth; $i++): ?>
								</ul></li>
							<?php endfor; ?>
						<?php endif; ?>
						<li id="category-element-<?php echo $category->id; ?>"><input type="checkbox" name="categories[]" value="<?php echo $category->id; ?>" id="category-<?php echo $category->id; ?>" tabindex="3"<?php if (in_array($category->id,$selectedCategories)) echo ' checked="checked"'; ?> class="category-toggle" /><label for="category-<?php echo $category->id; ?>"><?php echo $category->name; ?></label></li>
						<?php $depth = $category->depth; endforeach; ?>
						<?php for ($i = 0; $i < $depth; $i++): ?>
							</ul></li>
						<?php endfor; ?>
					</ul>
				</div><br />
                <?php _e('Use categories to organize the products in your catalog.','Shopp'); ?></td> 
		</tr>
		<tr class="form-field form-required"> 
			<th scope="row" valign="top"><label for="name"><?php _e('Tags','Shopp'); ?></label></th> 
			<td><input name="newtags" id="newtags" type="text" size="16" tabindex="4" autocomplete="off" value="enter, new, tags…" title="enter, new, tags…" class="form-input-tip" />
				<button type="button" name="addtags" id="add-tags" class="button-secondary" tabindex="5"><small>Add</small></button><input type="hidden" name="taglist" id="tags" value="<?php echo join(",",$taglist); ?>"><br />
            <?php _e('Separate tags with commas','Shopp'); ?><br />
			<div id="taglist">
				<label><big><strong><?php _e('Tags for this product:','Shopp'); ?></strong></big></label><br />
				<div id="tagchecklist"></div>
			</div>
			</td> 
		<tr class="form-field"> 
			<th scope="row" valign="top"><label for="summary"><?php _e('Summary','Shopp'); ?></label></th> 
			<td><textarea name="summary" id="summary" rows="2" cols="50" tabindex="6" style="width: 97%;"><? echo $Product->summary ?></textarea><br /> 
            <?php _e('A brief description of the product to draw the customer\'s attention.','Shopp'); ?></td> 
		</tr> 
		<tr class="form-field"> 
			<th scope="row" valign="top"><label for="description"><?php _e('Description','Shopp'); ?></label></th> 
			<td><textarea name="description" id="description" rows="8" cols="50" tabindex="7" style="width: 97%;"><? echo $Product->description ?></textarea><br /> 
            <?php _e('Provide in-depth information about the product to be displayed on the product page.','Shopp'); ?></td> 
		</tr> 
		<tr class="form-field">
			<th><label><?php _e('Details &amp; Specs','Shopp'); ?></label>
				<div id="new-detail">
				<button type="button" id="addDetail" class="button-secondary" tabindex="8"><small><?php _e('Add Product Detail','Shopp'); ?></small></button>
				</div>
			</th>
			<td>
				<ul class="details multipane">
					<li><input type="hidden" name="deletedSpecs" id="deletedSpecs" value="" />
						<div id="details-menu" class="multiple-select menu"><ul></ul></div></li>
					<li><div id="details-list" class="list"><ul></ul></div></li>
				</ul>
				<div class="clear"></div>
				<?php _e('Build a list of detailed information such as dimensions or features of the product.','Shopp'); ?>
			</td>
		</tr>		
		<tr id="product-images" class="form-field form-required"> 
			<th scope="row" valign="top"><label><?php _e('Product Images','Shopp'); ?></label>
				<div id="swf-uploader">
				<input type="hidden" name="product" value="<?php echo $_GET['edit']; ?>" id="image-product-id" />
				<input type="hidden" name="deleteImages" id="deleteImages" value="" />
				<button type="button" class="button-secondary" name="add-image" id="add-product-image" tabindex="10"><small><?php _e('Add New Image','Shopp'); ?></small></button></div></th> 
			<td>
				<ul id="lightbox">
				<?php foreach ($Images as $thumbnail): ?>
					<li id="image-<?php echo $thumbnail->src; ?>"><input type="hidden" name="images[]" value="<?php echo $thumbnail->src; ?>" /><img src="<?php echo get_option('siteurl'); ?>/wp-admin/admin.php?page=shopp/lookup&amp;id=<?php echo $thumbnail->id; ?>" width="96" height="96" />
						<button type="button" name="deleteImage" value="<?php echo $thumbnail->src; ?>" title="Delete product image&hellip;" class="deleteButton"><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/delete.png" alt="-" width="16" height="16" /></button></li>
				<?php endforeach; ?>
				</ul>
				<div class="clear"></div>
				<?php _e('Images shown here will be out of proportion, but will be correctly sized for shoppers.','Shopp'); ?>
			</td> 
		</tr>
		<tr class="form-field"> 
			<th scope="row" valign="top"><label for="featured"><?php _e('Settings','Shopp'); ?></label></th> 
			<td><p><input type="hidden" name="featured" value="off" /><input type="checkbox" name="featured" value="on" id="featured" tabindex="11" <?php if ($Product->featured == "on") echo ' checked="checked"'?> /><label for="featured"> <?php _e('Featured Product','Shopp'); ?></label></p>
				<ul>
					<li><input type="hidden" name="variations" value="off" /><input type="checkbox" name="variations" value="on" id="variations-setting" tabindex="12"<?php if ($Product->variations == "on") echo ' checked="checked"'?> /><label for="variations-setting"> <?php _e('Variations &mdash; Selectable product options','Shopp'); ?></label></li>
				</ul>
				</td>
		</tr>
	</tbody>
	<tbody id="product-pricing">
	</tbody>
	</table>
	
	<div id="variations">
	<h3><?php _e('Variations','Shopp'); ?></h3>
	<table class="form-table pricing">
	<tbody>
		<tr>
			<th><?php _e('Option Menus','Shopp'); ?></th>
			<td>
				<?php _e('Create the menus and menu options for the product\'s variations.','Shopp'); ?><br />
				<ul class="multipane options">
					<li><div id="variations-menu" class="multiple-select menu"><ul></ul></div>
					<div class="controls">
						<button type="button" id="addVariationMenu" class="button-secondary" tabindex="14"><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="-" width="16" height="16" /><small> <?php _e('Add Option Menu','Shopp'); ?></small></button>
					</div>
				</li>
				
				<li>
					<div id="variations-list" class="multiple-select options"></div><br />
					<div class="controls right">
					<button type="button" id="addVariationOption" class="button-secondary" tabindex="15"><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="-" width="16" height="16" /><small> <?php _e('Add Option','Shopp'); ?></small></button>
					</div>
				</li>
				</ul>
			</td>
		</tr>
	</tbody>
	<tbody id="variations-pricing"></tbody>
	</table>
	</div>
	
	<div><input type="hidden" name="deletePrices" id="deletePrices" value="" />
		<input type="hidden" name="prices" value="" id="prices" /></div>
		
	
	<p class="submit"><input type="submit" class="button" name="save" value="<?php _e('Save Product','Shopp'); ?>" /></p>
</form>

</div>

<script type="text/javascript">
var prices = <?php echo json_encode($Product->prices) ?>;
var specs = <?php echo json_encode($Product->specs) ?>;
var options = <?php echo json_encode($Product->options) ?>;
var priceTypes = <?php echo json_encode($priceTypes) ?>;
var shiprates = <?php echo json_encode($shiprates); ?>;
var rsrcdir = '<?php echo SHOPP_PLUGINURI; ?>';
var siteurl = '<?php echo get_option('siteurl'); ?>';
var filesizeLimit = <?php echo wp_max_upload_size(); ?>;
var weightUnit = '<?php echo $this->Settings->get('weight_unit'); ?>';
var currencyFormat = <?php $base = $this->Settings->get('base_operations'); echo json_encode($base['currency']['format']); ?>;

init();
</script>