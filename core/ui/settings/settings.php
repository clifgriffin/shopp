<div class="wrap shopp">
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>

	<div class="icon32"></div>
	<h2><?php _e('Settings','Shopp'); ?></h2>

	<form name="settings" id="general" action="<?php echo $this->url; ?>" method="post">
		<?php wp_nonce_field('shopp-settings-activation'); ?>

		<table class="form-table">
			<tr>
				<th scope="row" valign="top"><label for="update-key"><?php _e('Update Key','Shopp'); ?></label></th>
				<td>
					<input type="<?php echo $type; ?>" name="updatekey" id="update-key" size="54" value="<?php echo esc_attr($key); ?>"<?php echo ($activated)?' readonly="readonly"':''; ?> />
					<input type="submit" id="activation-button" name="activation-button" class="button-secondary" value="<? echo $button; ?>" />
					<input type="hidden" name="activation" value="<?php echo $action; ?>" />
					<div id="activation-status" class="hide-if-js<?php echo " $status_class"; ?>"><?php echo $keystatus; ?></div>
	            </td>
			</tr>
		</table>
	</form>

	<form name="settings" id="general" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post">
		<?php wp_nonce_field('shopp-settings-general'); ?>

		<table class="form-table">
			<tr>
				<th scope="row" valign="top"><label for="merchant_email"><?php _e('Merchant Email','Shopp'); ?></label></th>
				<td><input type="text" name="settings[merchant_email]" value="<?php echo esc_attr($this->Settings->get('merchant_email')); ?>" id="merchant_email" size="30" /><br />
	            <?php _e('Enter the email address for the owner of this shop to receive e-mail notifications.','Shopp'); ?></td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="base_operations"><?php _e('Base of Operations','Shopp'); ?></label></th>
				<td><select name="settings[base_operations][country]" id="base_operations">
					<option value="">&nbsp;</option>
						<?php echo menuoptions($countries,$operations['country'],true); ?>
					</select>
					<?php if (isset($zones)): ?>
					<select name="settings[base_operations][zone]" id="base_operations_zone">
						<?php echo menuoptions($zones,$operations['zone'],true); ?>
					</select>
					<?php endif; ?>
					<br />
	            	<?php _e('Select your primary business location.','Shopp'); ?><br />
					<?php if (!empty($operations['country'])): ?>
		            <strong><?php _e('Currency','Shopp'); ?>: </strong><?php echo money(1000.00); ?>
					<?php if ($operations['vat']): ?><strong>+(VAT)</strong><?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="target_markets"><?php _e('Target Markets','Shopp'); ?></label></th>
				<td>
					<div id="target_markets" class="multiple-select">
						<ul>
							<?php
								$even = true;
								foreach ($targets as $iso => $country):
									$classes = array();
									if ($even) $classes[] = 'odd';
							?>
								<li<?php if (!empty($classes)) echo ' class="'.join(' ',$classes).'"'; ?>><input type="checkbox" name="settings[target_markets][<?php echo $iso; ?>]" value="<?php echo $country; ?>" id="market-<?php echo $iso; ?>" checked="checked" /><label for="market-<?php echo $iso; ?>" accesskey="<?php echo substr($iso,0,1); ?>"><?php echo $country; ?></label></li>
							<?php $even = !$even; endforeach; $classes = array(); ?>
							<li<?php if ($even) $classes[] = 'odd'; $classes[] = 'hide-if-no-js'; if (!empty($classes)) echo ' class="'.join(' ',$classes).'"'; $even = !$even; ?>><input type="checkbox" name="selectall_targetmarkets"  id="selectall_targetmarkets" /><label for="selectall_targetmarkets"><strong><?php _e('Select All','Shopp'); ?></strong></label></li>
							<?php
								foreach ($countries as $iso => $country):
									$classes = array();
									if ($even) $classes[] = 'odd';
								?>
							<?php if (!in_array($country,$targets)): ?>
							<li<?php if (!empty($classes)) echo ' class="'.join(' ',$classes).'"'; ?>><input type="checkbox" name="settings[target_markets][<?php echo $iso; ?>]" value="<?php echo $country; ?>" id="market-<?php echo $iso; ?>" /><label for="market-<?php echo $iso; ?>" accesskey="<?php echo substr($iso,0,1); ?>"><?php echo $country; ?></label></li>
							<?php $even = !$even; endif; endforeach; ?>
						</ul>
					</div>
					<br />
	            <?php _e('Select the markets you are selling products to.','Shopp'); ?></td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="dashboard-toggle"><?php _e('Dashboard Widgets','Shopp'); ?></label></th>
				<td><input type="hidden" name="settings[dashboard]" value="off" /><input type="checkbox" name="settings[dashboard]" value="on" id="dashboard-toggle"<?php if ($this->Settings->get('dashboard') == "on") echo ' checked="checked"'?> /><label for="dashboard-toggle"> <?php _e('Enabled','Shopp'); ?></label><br />
	            <?php _e('Check this to display store performance metrics and more on the WordPress Dashboard.','Shopp'); ?></td>
			</tr>
		</table>

		<p class="submit"><input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>
	</form>
</div>

<script type="text/javascript">
/* <![CDATA[ */
/*

	SHOPP_PLUGINURI = "<?php echo SHOPP_PLUGINURI; ?>",
	SHOPP_ACTIVATE_KEY = <?php _jse('Activate Key','Shopp'); ?>,
	SHOPP_DEACTIVATE_KEY = <?php _jse('Deactivate Key','Shopp'); ?>,
	SHOPP_CONNECTING = <?php _jse('Connecting','Shopp'); ?>,
	SHOPP_CUSTOMER_SERVICE = <?php printf(json_encode(__('Contact <a href="%s">customer service</a>.','Shopp')),SHOPP_CUSTOMERS); ?>,
	keyStatus = {
		'-000':<?php _jse('The server could not be reached because of a connection problem.','Shopp'); ?>,
		'-1':<?php _jse('An unkown error occurred.','Shopp'); ?>,
		'0':<?php _jse('This key has been deactivated.','Shopp'); ?>,
		'1':<?php _jse('This key has been activated.','Shopp'); ?>,
		'-100':<?php _jse('An unknown activation error occurred.','Shopp'); ?>+SHOPP_CUSTOMER_SERVICE,
		'-101':<?php _jse('The key provided is not valid.','Shopp'); ?>+SHOPP_CUSTOMER_SERVICE,
		'-102':<?php _jse('This site is not valid to activate the key.','Shopp'); ?>+SHOPP_CUSTOMER_SERVICE,
		'-103':<?php _jse('The key provided could not be validated by shopplugin.net.','Shopp'); ?>+SHOPP_CUSTOMER_SERVICE,
		'-104':<?php _jse('The key provided is already active on another site.','Shopp'); ?>+SHOPP_CUSTOMER_SERVICE,
		'-200':<?php _jse('An unkown deactivation error occurred.','Shopp'); ?>+SHOPP_CUSTOMER_SERVICE,
		'-201':<?php _jse('The key provided is not valid.','Shopp'); ?>+SHOPP_CUSTOMER_SERVICE,
		'-202':<?php _jse('The site is not valid to be able to deactivate the key.','Shopp'); ?>+SHOPP_CUSTOMER_SERVICE,
		'-203':<?php _jse('The key provided could not be validated by shopplugin.net.','Shopp'); ?>+SHOPP_CUSTOMER_SERVICE
	}, */
	var activated = <?php echo ($activated)?'true':'false'; ?>,
		zones_url = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), 'wp_ajax_shopp_country_zones'); ?>',
		act_key_url = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), 'wp_ajax_shopp_activate_key'); ?>',
		deact_key_url = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'), 'wp_ajax_shopp_deactivate_key'); ?>';
/* ]]> */
</script>