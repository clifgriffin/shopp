<div class="wrap shopp">
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>
	<div class="icon32"></div>
	<h2><?php _e('System Settings','Shopp'); ?></h2>
	
	<form name="settings" id="system" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post">
		<?php wp_nonce_field('shopp-settings-system'); ?>
		
		<?php include("navigation.php"); ?>

		<table class="form-table"> 
			<tr>
				<th scope="row" valign="top"><label for="image-storage"><?php _e('Image Storage','Shopp'); ?></label></th> 
				<td><select name="settings[image_storage]" id="image-storage">
					<?php echo menuoptions($storage,$this->Settings->get('image_storage'),true); ?>
					</select>
					<div id="image-storage-engine" class="storage-settings"></div>
	            </td>
			</tr>			
			<tr>
				<th scope="row" valign="top"><label for="product-storage"><?php _e('Product File Storage','Shopp'); ?></label></th> 
				<td><select name="settings[product_storage]" id="product-storage">
					<?php echo menuoptions($storage,$this->Settings->get('product_storage'),true); ?>
					</select>
					<div id="product-storage-engine" class="storage-settings"></div>
	            </td>
			</tr>

			<tr> 
				<th scope="row" valign="top"><label for="rebuild-index"><?php _e('Search Index','Shopp'); ?></label></th> 
				<td><button type="button" id="rebuild-index" name="rebuild" class="button-secondary"><?php _e('Rebuild Product Search Index','Shopp'); ?></button><br /> 
	            <?php _e('Update search indexes for all the products in the catalog.','Shopp'); ?></td>
			</tr>			

			<tr> 
				<th scope="row" valign="top"><label for="image-cache"><?php _e('Image Cache','Shopp'); ?></label></th> 
				<td><button type="submit" id="image-cache" name="rebuild" value="true" class="button-secondary"><?php _e('Delete Cached Images','Shopp'); ?></button><br />
	            <?php _e('Removes all cached images so that they will be recreated.','Shopp'); ?></td>
			</tr>			

			<tr> 
				<th scope="row" valign="top"><label for="uploader-toggle"><?php _e('Upload System','Shopp'); ?></label></th> 
				<td><input type="hidden" name="settings[uploader_pref]" value="browser" /><input type="checkbox" name="settings[uploader_pref]" value="flash" id="uploader-toggle"<?php if ($this->Settings->get('uploader_pref') == "flash") echo ' checked="checked"'?> /><label for="uploader-toggle"> <?php _e('Enable Flash-based uploading','Shopp'); ?></label><br /> 
	            <?php _e('Enable to use Adobe Flash uploads for accurate upload progress. Disable this setting if you are having problems uploading.','Shopp'); ?></td>
			</tr>			
			<tr> 
				<th scope="row" valign="top"><label for="script-server"><?php _e('Script Loading','Shopp'); ?></label></th> 
				<td><input type="hidden" name="settings[script_server]" value="script" /><input type="checkbox" name="settings[script_server]" value="plugin" id="script-server"<?php if ($this->Settings->get('script_server') == "plugin") echo ' checked="checked"'?> /><label for="script-server"> <?php _e('Load behavioral scripts through WordPress','Shopp'); ?></label><br /> 
	            <?php _e('Enable this setting when experiencing problems loading scripts with the Shopp Script Server','Shopp'); ?>
				<div><input type="hidden" name="settings[script_loading]" value="catalog" /><input type="checkbox" name="settings[script_loading]" value="global" id="script-loading"<?php if ($this->Settings->get('script_loading') == "global") echo ' checked="checked"'?> /><label for="script-loading"> <?php _e('Enable Shopp behavioral scripts site-wide','Shopp'); ?></label><br /> 
	            <?php _e('Enable this to make Shopp behaviors available across all of your WordPress posts and pages.','Shopp'); ?></div>
	
	</td>
			</tr>			
			<tr>
				<th scope="row" valign="top"><label for="error-notifications"><?php _e('Error Notifications','Shopp'); ?></label></th> 
				<td><ul id="error_notify">
					<?php foreach ($notification_errors as $id => $level): ?>
						<li><input type="checkbox" name="settings[error_notifications][]" id="error-notification-<?php echo $id; ?>" value="<?php echo $id; ?>"<?php if (in_array($id,$notifications)) echo ' checked="checked"'; ?>/><label for="error-notification-<?php echo $id; ?>"> <?php echo $level; ?></label></li>
					<?php endforeach; ?>
					</ul>
					<label for="error-notifications"><?php _e("Send email notifications of the selected errors to the merchant's email address.","Shopp"); ?></label>
	            </td>
			</tr>			
			<tr>
				<th scope="row" valign="top"><label for="error-logging"><?php _e('Error Logging','Shopp'); ?></label></th> 
				<td><select name="settings[error_logging]" id="error-logging">
					<?php echo menuoptions($errorlog_levels,$this->Settings->get('error_logging'),true); ?>
					</select><br />
					<label for="error-notifications"><?php _e("Limit logging errors up to the level of the selected error type.","Shopp"); ?></label>
	            </td>
			</tr>
			<?php if (count($recentlog) > 0): ?>
			<tr>
				<th scope="row" valign="top"><label for="error-logging"><?php _e('Error Log','Shopp'); ?></label></th> 
				<td><div id="errorlog"><ol><?php foreach ($recentlog as $line): ?>
					<li><?php echo esc_html($line); ?></li>
				<?php endforeach; ?></ol></div>
				<p class="alignright"><button name="resetlog" id="resetlog" value="resetlog" class="button"><small><?php _e("Reset Log","Shopp"); ?></small></button></p>
				</td>
			</tr>			
			<?php endif; ?>
		</table>
		<p class="submit"><input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>
	</form>
</div>
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready(function() {
	var $ = jqnc();
	
	var handlers = new CallbackRegistry();
	handlers.options = {};
	handlers.enabled = [];
	handlers.register = function (name,object) {
		this.callbacks[name] = function () {object['storage']();}
		this.options[name] = object;
	}
	
	handlers.call = function(id,setting,name,arg1,arg2,arg3) {
		var module = this.options[name];
		module.element = $(id);
		module.setting = setting;
		this.callbacks[name](arg1,arg2,arg3);
		module.behaviors();
	}

	<?php do_action('shopp_storage_module_settings'); ?>
	
	$('#image-storage').change(function () {
		var module = $(this).val();
		var selected = $('#image-storage :selected');
		$('#image-storage-engine').empty();
		handlers.call('#image-storage-engine','image',module);
	}).change();

	$('#product-storage').change(function () {
		var module = $(this).val();
		var selected = $('#product-storage :selected');
		$('#product-storage-engine').empty();
		handlers.call('#product-storage-engine','download',module);
	}).change();
		
	$('#errorlog').scrollTop($('#errorlog').attr('scrollHeight'));


	var progressbar = false;
	var search_url = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'),'wp_ajax_shopp_rebuild_search_index'); ?>';
	var searchprog_url = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'),'wp_ajax_shopp_rebuild_search_index_progress'); ?>';
	function progress () {
		$.ajax({url:searchprog_url+'&action=shopp_rebuild_search_index_progress',
			type:"GET",
			timeout:500,
			dataType:'text',
			success:function (results) {
				var p = results.split(':'),
					width = Math.ceil((p[0]/p[1])*76);
				if (p[0] < p[1]) setTimeout(progress,1000);
				progressbar.animate({'width':width+'px'},500);
			}
		});
		
	}

	$('#rebuild-index').click(function () {
		$.fn.colorbox({'title':'<?php _e('Product Indexing','Shopp'); ?>', 
			'innerWidth':'250', 
			'innerHeight':'50', 
			'html':
			'<div id="progress"><div class="bar"><\/div><div class="gloss"><\/div><\/div><iframe id="process" width="0" height="0" src="'+search_url+'&action=shopp_rebuild_search_index"><\/iframe>',
			'onComplete':function () {
				progressbar = $('#progress div.bar');
				progress();
				$('#process').load(function () {
					progressbar.animate({'width':'100%'},500,'swing',function () {
						setTimeout($.fn.colorbox.close,1000);
					});
				});
			}
		});
	});
	

});
/* ]]> */
</script>