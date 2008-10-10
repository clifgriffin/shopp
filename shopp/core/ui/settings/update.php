<div class="wrap shopp">
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>
	<h2><?php _e('Upgrade Settings','Shopp'); ?></h2>
	<?php include("navigation.php"); ?>

	<form name="settings" id="update" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
		<?php wp_nonce_field('shopp-settings-update'); ?>
		
		<table class="form-table"> 
			<tr class="form-field form-required"> 
				<th scope="row" valign="top"><label for="showcase-order">Shopp Updates</label>
				</th> 
				<td><?php _e('Currently running Shopp','Shopp'); ?> <?php echo SHOPP_VERSION; ?>
					<div id="update-info">
					<p><button type="button" id="check-update" name="check-update" class="button-secondary"><?php _e('Check for Updates','Shopp'); ?></button></p>	
					</div>
					</td>
			</tr>			
			<tr class="form-field form-required"> 
				<th scope="row" valign="top"><label for="Update Key"><?php _e('Update Key','Shopp'); ?></label></th> 
				<td>
					<?php if ($this->Settings->get('updatekey_status') == "activated"): ?>
						<input type="text" name="settings[update_key]" id="update-key" size="40" value="<?php echo $this->Settings->get('update_key'); ?>" readonly="readonly" />
					<input type="submit" id="deactivate-button" name="activation" value="<?php _e('De-activate Key','Shopp'); ?>" class="button" />
					<?php else: ?>
					<input type="text" name="settings[update_key]" id="update-key" size="40" value="<?php echo $this->Settings->get('update_key'); ?>" />
					<input type="submit" id="activate-button" name="activation" value="<?php _e('Activate Key','Shopp'); ?>" class="button" />
					<?php endif; ?>
					<br /><?php echo $activation; ?>
	            </td>
			</tr>			
		</table>
	</form>
</div>

<script type="text/javascript">
(function($) {
	helpurl = "<?php echo SHOPP_DOCS; ?>Update_Settings";
	var ajaxurl = '<?php echo wp_nonce_url(get_bloginfo("siteurl")."/wp-admin/admin-ajax.php", "shopp-wp_ajax_shopp_update"); ?>';
	var adminurl = '<?php echo wp_nonce_url(get_bloginfo("siteurl")."/wp-admin/admin.php", "shopp-wp_ajax_shopp_update"); ?>';
	
	$('#check-update').click(function () {

		var target = $('#update-info');
		$('<div id="status" class="updating"><?php _e("Checking"); ?>&hellip;</div>').appendTo(target);
		$.ajax({
			type:"GET",
			url:ajaxurl+"&action=wp_ajax_shopp_version_check",
			timeout:10000,
			dataType:'json',
			success:function (data) {
				if (data.update) {
					$('#status').remove();
					target.html('<strong>Shopp '+data.version+' is available!</strong>');
					<?php if ($this->Settings->get('updatekey_status') == "activated"): ?>
					var wrap = $('<p></p>').appendTo(target);
					var update = $('<button type="button" name="update" id="update" class="button-secondary"><?php _e("Update Shopp","Shopp"); ?></button>').appendTo(wrap);
					update.click(function () {
						target.html('<div id="status" class="updating"><?php _e("Updating Shopp&hellip; be patient!","Shopp"); ?></div>');
						startupdate();
					});				
					<?php endif; ?>
				}
				else target.html("<strong><?php _e('Shopp is up-to-date!','Shopp'); ?></strong>");
			},
			error:function () {	$('#status').remove(); }
		});
		
	});

	function startupdate () {
		$.ajax({
			type:"GET",
			url:ajaxurl+"&action=wp_ajax_shopp_update",
			timeout:30000,
			datatype:'text',
			success:function (result) {
				// console.log(result);
				if (result == "ftp-failed") {
					window.location.href = adminurl+"&page=shopp/settings&edit=ftp";
				} else if (result == "updated") {
					$('#update-info').html('<strong><?php _e("Update Complete!","Shopp"); ?></strong><br /><?php _e("Click continue to upgrade the Shopp database.","Shopp"); ?>');
					var wrap = $('<p></p>').appendTo('#update-info');
					var reload = $('<button type="button" name="reload" value="reload" class="button-secondary"><?php _e('Continue','Shopp'); ?>&hellip;</button>').appendTo('#update-info');
					reload.click(function () {
						window.location.href = adminurl+'&page=shopp/settings&edit=update&updated=true';
					});
				} else {
					alert("<?php _e('An error occurred while trying to update.  The update failed.  This is what Shopp says happened:','Shopp'); ?>\n"+result);
					window.location.href = adminurl+'&page=shopp/settings&edit=update&updated=true';
				}
			},
			error:function () {
				alert("<?php _e('The update timed out and was not successful.','Shopp'); ?>\n"+result);
				window.location.href = adminurl+'&page=shopp/settings&edit=update&updated=true';
			}
		});
	}
	
})(jQuery)
</script>