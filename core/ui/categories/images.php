<ul id="lightbox">
	<?php if (isset($Category->images) && !empty($Category->images)): ?>
	<?php foreach ((array)$Category->images as $i => $Image): ?>
		<li id="image-<?php echo $Image->id; ?>"><input type="hidden" name="images[]" value="<?php echo $Image->id; ?>" />
		<div id="image-<?php echo $Image->id; ?>-details">
			<img src="?siid=<?php echo $Image->id; ?>&amp;<?php echo $Image->resizing(96,0,1); ?>" width="96" height="96" />
			<input type="hidden" name="imagedetails[<?php echo $i; ?>][id]" value="<?php echo $Image->id; ?>" />
			<input type="hidden" name="imagedetails[<?php echo $i; ?>][title]" value="<?php echo $Image->title; ?>" class="imagetitle" />
			<input type="hidden" name="imagedetails[<?php echo $i; ?>][alt]" value="<?php echo $Image->alt; ?>"  class="imagealt" />
			<?php
				if (count($Image->cropped) > 0):
					foreach ($Image->cropped as $cache):
						$cropping = join(',',array($cache->settings['dx'],$cache->settings['dy'],$cache->settings['cropscale']));
						$c = "$cache->width:$cache->height"; ?>
				<input type="hidden" name="imagedetails[<?php echo $i; ?>][cropping][<?php echo $cache->id; ?>]" alt="<?php echo $c; ?>" value="<?php echo $cropping; ?>" class="imagecropped" />
			<?php endforeach; endif;?>
		</div>
		<?php echo ShoppUI::button('delete', 'deleteImage', array('type' => 'button', 'class' => 'delete deleteButton', 'value' => $Image->id, 'title' => Shopp::__('Remove image&hellip;')) ); ?>
		</li>
	<?php endforeach; endif; ?>
</ul>
<div class="clear"></div>
<input type="hidden" name="category" value="<?php echo $_GET['id']; ?>" id="image-category-id" />
<input type="hidden" name="deleteImages" id="deleteImages" value="" />
<div id="swf-uploader-button"></div>
<div id="swf-uploader">
<button type="button" class="button-secondary" name="add-image" id="add-image" tabindex="10"><small><?php _e('Add New Image','Shopp'); ?></small></button></div>
<div id="browser-uploader">
	<button type="button" name="image_upload" id="image-upload" class="button-secondary"><small><?php _e('Add New Image','Shopp'); ?></small></button><br class="clear"/>
</div>

<p><?php _e('Double-click images to edit their details. Save the product to confirm deleted images.','Shopp'); ?></p>