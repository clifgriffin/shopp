<?php

function save_meta_box ($Product) {
	global $Shopp;
	
	$workflows = array(
		"continue" => __('Continue Editing','Shopp'),
		"close" => __('Products Manager','Shopp'),
		"new" => __('New Product','Shopp'),
		"next" => __('Edit Next','Shopp'),
		"previous" => __('Edit Previous','Shopp')
		);
		
		
	$date_format = get_option('date_format');
	$time_format = get_option('time_format');

?>
	<div id="misc-publishing-actions">
		<input type="hidden" name="id" value="<?php echo $Product->id; ?>" />
		
		<div class="misc-pub-section misc-pub-section-last">
			<input type="hidden" name="status" value="draft" /><input type="checkbox" name="status" value="publish" id="published" tabindex="11" <?php if ($Product->status == "publish") echo ' checked="checked"'?> /><label for="published"><strong> <?php if ($Product->published() && !empty($Product->id)) _e('Published','Shopp'); else _e('Publish','Shopp'); ?></strong> <span id="publish-status"><?php if ($Product->publish>1) printf(__('on: %s'),"</span><br />".date($date_format.' @ '.$time_format,$Product->publish)); else echo "</span>"; ?></label> <span id="schedule-toggling"><button type="button" name="schedule-toggle" id="schedule-toggle" class="button-secondary"><?php if ($Product->publish>1) _e('Edit','Shopp'); else _e('Schedule','Shopp'); ?></button></span>

			<div id="scheduling">
				<div id="schedule-calendar" class="calendar-wrap">
					<?php
						$dateorder = date_format_order();
						foreach ($dateorder as $type => $format):
							if ("month" == $type): ?><input type="text" name="publish[month]" id="publish-month" title="<?php _e('Month','Shopp'); ?>" size="2" maxlength="2" value="<?php echo ($Product->publish>1)?date("n",$Product->publish):''; ?>" class="publishdate selectall" /><?php elseif ("day" == $type): ?><input type="text" name="publish[date]" id="publish-date" title="<?php _e('Day','Shopp'); ?>" size="2" maxlength="2" value="<?php echo ($Product->publish>1)?date("j",$Product->publish):''; ?>" class="publishdate selectall" /><?php elseif ("year" == $type): ?><input type="text" name="publish[year]" id="publish-year" title="<?php _e('Year','Shopp'); ?>" size="4" maxlength="4" value="<?php echo ($Product->publish>1)?date("Y",$Product->publish):''; ?>" class="publishdate selectall" /><?php elseif ($type[0] == "s"): echo "/"; endif; ?><?php endforeach; ?>
					 <br />
					<input type="text" name="publish[hour]" id="publish-hour" title="<?php _e('Hour','Shopp'); ?>" size="2" maxlength="2" value="<?php echo ($Product->publish>1)?date("g",$Product->publish):date('g'); ?>" class="publishdate selectall" />:<input type="text" name="publish[minute]" id="publish-minute" title="<?php _e('Minute','Shopp'); ?>" size="2" maxlength="2" value="<?php echo ($Product->publish>1)?date("i",$Product->publish):date('i'); ?>" class="publishdate selectall" />
					<select name="publish[meridiem]" class="publishdate">
					<?php echo menuoptions(array('AM' => __('AM','Shopp'),'PM' => __('PM','Shopp')),date('A',$Product->publish),true); ?>
					</select>
				</div>
			</div>

		</div>
		
	</div>
	<div id="major-publishing-actions">
		<select name="settings[workflow]" id="workflow">
		<?php echo menuoptions($workflows,$Shopp->Settings->get('workflow'),true); ?>
		</select>
	<input type="submit" class="button-primary" name="save" value="<?php _e('Save Product','Shopp'); ?>" />
	</div>
<?php
}
add_meta_box(
	'save-product', 
	__('Save','Shopp').$Admin->boxhelp('product-editor-save'), 
	'save_meta_box', 
	'shopp_page_shopp-products', 
	'side', 
	'core'
);

function categories_meta_box ($Product) {
	$db =& DB::get();
	$category_table = DatabaseObject::tablename(Category::$table);
	$categories = $db->query("SELECT id,name,parent FROM $category_table ORDER BY parent,name",AS_ARRAY);
	$categories = sort_tree($categories);
	if (empty($categories)) $categories = array();

	$categories_menu = '<option value="0">'.__('Parent Category','Shopp').'&hellip;</option>';
	foreach ($categories as $category) {
		$padding = str_repeat("&nbsp;",$category->depth*3);
		$categories_menu .= '<option value="'.$category->id.'">'.$padding.esc_html($category->name).'</option>';
	}		

	$selectedCategories = array();
	foreach ($Product->categories as $category) $selectedCategories[] = $category->id;
?>
<div id="category-menu" class="multiple-select short">
	<ul>
		<?php $depth = 0; foreach ($categories as $category): 
		if ($category->depth > $depth) echo "<li><ul>"; ?>
		<?php if ($category->depth < $depth): ?>
			<?php for ($i = $category->depth; $i < $depth; $i++): ?>
				</ul></li>
			<?php endfor; ?>
		<?php endif; ?>
		<li id="category-element-<?php echo $category->id; ?>"><input type="checkbox" name="categories[]" value="<?php echo $category->id; ?>" id="category-<?php echo $category->id; ?>" tabindex="3"<?php if (in_array($category->id,$selectedCategories)) echo ' checked="checked"'; ?> class="category-toggle" /><label for="category-<?php echo $category->id; ?>"><?php echo esc_html($category->name); ?></label></li>
		<?php $depth = $category->depth; endforeach; ?>
		<?php for ($i = 0; $i < $depth; $i++): ?>
			</ul></li>
		<?php endfor; ?>
	</ul>
</div>
<div>
<div id="new-category" class="hidden">
<input type="text" name="new-category" value="" size="15" id="new-category-name" /><br />
<select name="new-category-parent"><?php echo $categories_menu; ?></select>
<button id="add-new-category" type="button" class="button-secondary" tabindex="2"><small><?php _e('Add','Shopp'); ?></small></button>
</div>
<button id="new-category-button" type="button" class="button-secondary" tabindex="2"><small><?php _e('New Category','Shopp'); ?></small></button>
</div>

<?php
}
add_meta_box(
	'categories-box', 
	__('Categories','Shopp').$Admin->boxhelp('product-editor-categories'), 
	'categories_meta_box', 
	'shopp_page_shopp-products', 
	'side', 
	'core'
);

function tags_meta_box ($Product) {
	$taglist = array();
	foreach ($Product->tags as $tag) $taglist[] = $tag->name;
?>
<input name="newtags" id="newtags" type="text" size="16" tabindex="4" autocomplete="off" value="<?php _e('enter, new, tags','Shopp'); ?>…" title="<?php _e('enter, new, tags','Shopp'); ?>…" class="form-input-tip" />
	<button type="button" name="addtags" id="add-tags" class="button-secondary" tabindex="5"><small><?php _e('Add','Shopp'); ?></small></button><input type="hidden" name="taglist" id="tags" value="<?php echo join(",",esc_attrs($taglist)); ?>" /><br />
<label><?php _e('Separate tags with commas','Shopp'); ?></label>
<div id="taglist">
	<div id="tagchecklist" class="tagchecklist"></div>
</div>
<?php
}
add_meta_box(
	'product-tags', 
	__('Tags','Shopp').$Admin->boxhelp('product-editor-tags'), 
	'tags_meta_box', 
	'shopp_page_shopp-products', 
	'side', 
	'core'
);

function settings_meta_box ($Product) {
	global $Shopp;
	$Admin =& $Shopp->Flow->Admin;
	$taglist = array();
	foreach ($Product->tags as $tag) $taglist[] = $tag->name;
?>
	<p><input type="hidden" name="featured" value="off" /><input type="checkbox" name="featured" value="on" id="featured" tabindex="12" <?php if ($Product->featured == "on") echo ' checked="checked"'?> /><label for="featured"> <?php _e('Featured Product','Shopp'); ?></label></p>
	<p><input type="hidden" name="variations" value="off" /><input type="checkbox" name="variations" value="on" id="variations-setting" tabindex="13"<?php if ($Product->variations == "on") echo ' checked="checked"'?> /><label for="variations-setting"> <?php _e('Variations','Shopp'); ?><?php echo $Admin->boxhelp('product-editor-variations'); ?></label></p>
	<p><input type="hidden" name="addons" value="off" /><input type="checkbox" name="addons" value="on" id="addons-setting" tabindex="13"<?php if ($Product->addons == "on") echo ' checked="checked"'?> /><label for="addons-setting"> <?php _e('Add-ons','Shopp'); ?><?php echo $Admin->boxhelp('product-editor-addons'); ?></label></p>
<?php
}
add_meta_box(
	'product-settings', 
	__('Settings','Shopp').$Admin->boxhelp('product-editor-settings'), 
	'settings_meta_box', 
	'shopp_page_shopp-products', 
	'side', 
	'core'
);


function summary_meta_box ($Product) {
?>
	<textarea name="summary" id="summary" rows="2" cols="50" tabindex="6"><?php echo $Product->summary ?></textarea><br /> 
    <label for="summary"><?php _e('A brief description of the product to draw the customer\'s attention.','Shopp'); ?></label>
<?php
}
add_meta_box(
	'product-summary', 
	__('Summary','Shopp').$Admin->boxhelp('product-editor-summary'), 
	'summary_meta_box', 
	'shopp_page_shopp-products', 
	'normal', 
	'core'
);

function details_meta_box ($Product) {
?>
	<ul class="details multipane">
		<li>
			<div id="details-menu" class="multiple-select menu">
			<input type="hidden" name="deletedSpecs" id="test" class="deletes" value="" />
			<ul></ul>
			</div>
		</li>
		<li><div id="details-list" class="list"><ul></ul></div></li>
	</ul><br class="clear" />
	<div id="new-detail">
	<button type="button" id="addDetail" class="button-secondary" tabindex="8"><small><?php _e('Add Product Detail','Shopp'); ?></small></button>
	<p><?php _e('Build a list of detailed information such as dimensions or features of the product.','Shopp'); ?></p>
	</div>
	
	<p></p>
<?php
}
add_meta_box(
	'product-details-box', 
	__('Details &amp; Specs','Shopp').$Admin->boxhelp('product-editor-details'), 
	'details_meta_box', 
	'shopp_page_shopp-products', 
	'normal', 
	'core'
);

function images_meta_box ($Product) {
	global $ProductImages;
?>
	<ul id="lightbox">
	<?php foreach ((array)$ProductImages as $i => $Image): ?>
		<li id="image-<?php echo $Image->id; ?>"><input type="hidden" name="images[]" value="<?php echo $Image->id; ?>" />
			<div id="image-<?php echo $Image->id; ?>-details">
				<img src="?siid=<?php echo $Image->id; ?>&amp;<?php echo $Image->resizing(96,0,1); ?>" width="96" height="96" />
				<input type="hidden" name="imagedetails[<?php echo $i; ?>][id]" value="<?php echo $Image->id; ?>" />
				<input type="hidden" name="imagedetails[<?php echo $i; ?>][title]" value="<?php echo $Image->title; ?>" class="imagetitle" />
				<input type="hidden" name="imagedetails[<?php echo $i; ?>][alt]" value="<?php echo $Image->alt; ?>"  class="imagealt" />
				<?php 
					if (isset($Image->cropped) && count($Image->cropped) > 0): 
						foreach ($Image->cropped as $cache): 
							$cropping = false;
							if (join('',array($cache->settings['dx'],$cache->settings['dy'],$cache->settings['cropscale'])) != '')
								$cropping = join(',',array($cache->settings['dx'],$cache->settings['dy'],$cache->settings['cropscale'])); 
							$c = "$cache->width:$cache->height"; ?>
					<input type="hidden" name="imagedetails[<?php echo $i; ?>][cropping][<?php echo $cache->id; ?>]" alt="<?php echo $c; ?>" value="<?php echo $cropping; ?>" class="imagecropped" />
				<?php endforeach; endif;?>
			</div>
			<button type="button" name="deleteImage" value="<?php echo $Image->id; ?>" title="Delete product image&hellip;" class="deleteButton"><input type="hidden" name="ieisstupid" value="<?php echo $Image->id; ?>" /><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/delete.png" alt="-" width="16" height="16" /></button></li>
	<?php endforeach; ?>
	</ul>
	<div class="clear"></div>
	<input type="hidden" name="product" value="<?php echo $_GET['id']; ?>" id="image-product-id" />
	<input type="hidden" name="deleteImages" id="deleteImages" value="" />
	<div id="swf-uploader-button"></div>
	<div id="browser-uploader">
		<button type="button" name="image_upload" id="image-upload" class="button-secondary"><small><?php _e('Add New Image','Shopp'); ?></small></button><br class="clear"/>
	</div>

	<p><?php _e('Double-click images to edit their details. Save the product to confirm deleted images.','Shopp'); ?></p>
<?php
}
add_meta_box(
	'product-images',
	 __('Product Images','Shopp').$Admin->boxhelp('product-editor-images'), 
	'images_meta_box', 
	'shopp_page_shopp-products', 
	'normal', 
	'core'
);

function pricing_meta_box ($Product) {
?>
<div id="prices-loading" class="updating"></div>
<div id="product-pricing"></div>

<div id="variations">
	<div id="variations-menus" class="panel">
		<div class="pricing-label">
			<label><?php _e('Variation Option Menus','Shopp'); ?></label>
		</div>
		<div class="pricing-ui">
			<p><?php _e('Create the menus and menu options for the product\'s variations.','Shopp'); ?></p>
			<ul class="multipane options">
				<li><div id="variations-menu" class="multiple-select menu"><ul></ul></div>
					<div class="controls">
						<button type="button" id="addVariationMenu" class="button-secondary" tabindex="14"><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="+" width="16" height="16" /><small> <?php _e('Add Option Menu','Shopp'); ?></small></button>
					</div>
				</li>

				<li>
					<div id="variations-list" class="multiple-select options"></div>
					<div class="controls right">
						<button type="button" id="linkOptionVariations" class="button-secondary" tabindex="17"><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/linked.png" alt="link" width="16" height="16" /><small> <?php _e('Link All Variations','Shopp'); ?></small></button>
					<button type="button" id="addVariationOption" class="button-secondary" tabindex="15"><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="+" width="16" height="16" /><small> <?php _e('Add Option','Shopp'); ?></small></button>
					</div>
				</li>
			</ul>
			<div class="clear"></div>
		</div>
	</div>
<br />
<div id="variations-pricing"></div>
</div>

<div id="addons">
	<div id="addons-menus" class="panel">
		<div class="pricing-label">
			<label><?php _e('Add-on Option Menus','Shopp'); ?></label>
		</div>
		<div class="pricing-ui">
			<p><?php _e('Create the menus and menu options for the product\'s add-ons.','Shopp'); ?></p>
			<ul class="multipane options">
				<li><div id="addon-menu" class="multiple-select menu"><ul></ul></div>
					<div class="controls">
						<button type="button" id="newAddonGroup" class="button-secondary" tabindex="14"><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="+" width="16" height="16" /><small> <?php _e('New Add-on Group','Shopp'); ?></small></button>
					</div>
				</li>

				<li>
					<div id="addon-list" class="multiple-select options"></div>
					<div class="controls right">
					<button type="button" id="addAddonOption" class="button-secondary" tabindex="15"><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="+" width="16" height="16" /><small> <?php _e('Add Option','Shopp'); ?></small></button>
					</div>
				</li>
			</ul>
			<div class="clear"></div>
		</div>
	</div>
<div id="addon-pricing"></div>
</div>

<div><input type="hidden" name="deletePrices" id="deletePrices" value="" />
	<input type="hidden" name="prices" value="" id="prices" /></div>

<div id="chooser">
	<p><label for="import-url"><?php _e('Attach file by URL','Shopp'); ?>&hellip;</label></p>
	<p><input type="text" name="url" id="import-url" class="fileimport" /><button class="button-secondary" id="attach-file"><small><?php _e('Attach File','Shopp'); ?></small></button><br /><span><label for="import-url">file:///path/to/file.zip, http://server.com/file.zip</label></span></p>
	<label class="alignleft"><?php _e('Select a file from your computer','Shopp'); ?>:</label>
	<div class=""><div id="flash-upload-file"></div><button id="ajax-upload-file" class="button-secondary"><small><?php _e('Upload File','Shopp'); ?></small></button></div>
</div>

<?php
}
add_meta_box(
	'product-pricing-box', 
	__('Pricing','Shopp').$Admin->boxhelp('product-editor-pricing'), 
	'pricing_meta_box', 
	'shopp_page_shopp-products',
	'advanced', 
	'core'
);

?>