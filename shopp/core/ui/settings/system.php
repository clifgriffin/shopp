<div class="wrap shopp">
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>
	<h2><?php _e('System Settings','Shopp'); ?></h2>
	
	<form name="settings" id="system" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
		<?php wp_nonce_field('shopp-settings-system'); ?>
		
		<?php include("navigation.php"); ?>

		<table class="form-table"> 
			<tr>
				<th scope="row" valign="top"><label for="image-storage"><?php _e('Image Storage','Shopp'); ?></label></th> 
				<td><select name="settings[image_storage_pref]" id="image-storage">
					<?php echo menuoptions($filesystems,$this->Settings->get('image_storage_pref'),true); ?>
					</select>
					<p id="image-path-settings">
						<input type="text" name="settings[image_path]" id="image-path" value="<?php echo attribute_escape($this->Settings->get('image_path')); ?>" size="40" /><br class="clear" />
						<label for="image-path"><?php echo $imagepath_status; ?></label>
					</p>
	            </td>
			</tr>			
			<tr>
				<th scope="row" valign="top"><label for="product-storage"><?php _e('Product File Storage','Shopp'); ?></label></th> 
				<td><select name="settings[product_storage_pref]" id="product-storage">
					<?php echo menuoptions($filesystems,$this->Settings->get('product_storage_pref'),true); ?>
					</select>
					<p id="products-path-settings"><input type="text" name="settings[products_path]" id="products-path" value="<?php echo attribute_escape($this->Settings->get('products_path')); ?>" size="40" /><br class="clear" />
						<label for="products-path"><?php echo $productspath_status; ?></label>
						</p>
	            </td>
			</tr>			

		</table>
		<p class="submit"><input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>
	</form>
</div>
<script type="text/javascript">
helpurl = "<?php echo SHOPP_DOCS; ?>System_Settings";

(function($){
	
	$('#image-storage').change(function () {
		$('#image-path-settings').slideToggle(300);
	});
	
	$('#image-storage').ready(function () {
		if ($('#image-storage').val() == 'db') $('#image-path-settings').hide();
	});

	$('#product-storage').change(function () {
		$('#products-path-settings').slideToggle(300);
	});
	
	$('#product-storage').ready(function () {
		if ($('#product-storage').val() == 'db') $('#products-path-settings').hide();
	});

})(jQuery);

</script>