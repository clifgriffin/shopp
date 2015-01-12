	<div id="confirm-delete-images" class="notice hidden"><p><?php _e('Save the product to confirm deleted images.','Shopp'); ?></p></div>
	<ul id="lightbox">
	<?php foreach ( (array) $Product->images as $i => $Image ): ?>
		<li id="image-<?php echo (int)$Image->id; ?>"><input type="hidden" name="images[]" value="<?php echo $Image->id; ?>" />
			<div id="image-<?php echo (int)$Image->id; ?>-details" title="<?php _e('Double-click images to edit their details&hellip;','Shopp'); ?>">
				<img src="?siid=<?php echo (int)$Image->id; ?>&amp;<?php echo $Image->resizing(96,0,1); ?>" width="96" height="96" />
				<input type="hidden" name="imagedetails[<?php echo (int)$i; ?>][id]" value="<?php echo (int)$Image->id; ?>" />
				<input type="hidden" name="imagedetails[<?php echo (int)$i; ?>][title]" value="<?php echo $Image->title; ?>" class="imagetitle" />
				<input type="hidden" name="imagedetails[<?php echo (int)$i; ?>][alt]" value="<?php echo $Image->alt; ?>"  class="imagealt" />
				<?php
					if ( isset($Product->cropped) && count($Product->cropped) > 0 && isset($Product->cropped[ $Image->id ]) ):

						$cropped = is_array($Product->cropped[ $Image->id ]) ? $Product->cropped[ $Image->id ] : array($Product->cropped[$Image->id]);

						foreach ($cropped as $cache):
							$cropimage = unserialize($cache->value);
							$cropdefaults = array('dx' => '','dy' => '','cropscale' => '');
							$cropsettings = array_intersect_key($cropimage->settings, $cropdefaults);
							$cropping = ( array_filter($cropsettings) == array() ) ? '' : join(',', array_merge($cropdefaults, $cropsettings));
							$c = "$cropimage->width:$cropimage->height";
				?>
					<input type="hidden" name="imagedetails[<?php echo $i; ?>][cropping][<?php echo $cache->id; ?>]" alt="<?php echo $c; ?>" value="<?php echo $cropping; ?>" class="imagecropped" />
				<?php endforeach; endif; ?>
			</div>
			<?php echo ShoppUI::button('delete', 'deleteImage', array('type' => 'button', 'class' => 'delete', 'value' => $Image->id, 'title' => Shopp::__('Remove image&hellip;')) ); ?>
			</li>
	<?php endforeach; ?>
	</ul>
	<div class="clear"></div>
	<input type="hidden" name="product" value="<?php echo $_GET['id']; ?>" id="image-product-id" />
	<input type="hidden" name="deleteImages" id="deleteImages" value="" />
	<div id="swf-uploader-button"></div>
	<div id="browser-uploader">
		<button type="button" name="image_upload" id="image-upload" class="button-secondary"><small><?php _e('Add New Image','Shopp'); ?></small></button><br class="clear"/>
	</div>