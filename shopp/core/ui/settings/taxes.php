<div class="wrap shopp">
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>

	<div class="icon32"></div>
	<h2><?php _e('Tax Settings','Shopp'); ?></h2>

	<form name="settings" id="taxes" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post">
		<?php wp_nonce_field('shopp-settings-taxes'); ?>

		<?php include("navigation.php"); ?>
		
		<table class="form-table"> 
			<tr> 
				<th scope="row" valign="top"><label for="taxes-toggle"><?php _e('Calculate Taxes','Shopp'); ?></label></th> 
				<td><input type="hidden" name="settings[taxes]" value="off" /><input type="checkbox" name="settings[taxes]" value="on" id="taxes-toggle"<?php if ($this->Settings->get('taxes') == "on") echo ' checked="checked"'?> /><label for="taxes-toggle"> <?php _e('Enabled','Shopp'); ?></label><br /> 
	            <?php _e('Enables tax calculations.  Disable if you are exclusively selling non-taxable items.','Shopp'); ?></td>
			</tr>
			<tr>
					<th scope="row" valign="top"><label for="tax-shipping-toggle"><?php _e('Tax Shipping','Shopp'); ?></label></th> 
					<td><input type="hidden" name="settings[tax_shipping]" value="off" /><input type="checkbox" name="settings[tax_shipping]" value="on" id="tax-shipping-toggle"<?php if ($this->Settings->get('tax_shipping') == "on") echo ' checked="checked"'?> /><label for="tax-shipping-toggle"> <?php _e('Enabled','Shopp'); ?></label><br /> 
		            <?php _e('Enable to include shipping and handling in taxes.','Shopp'); ?></td>
				</tr>
		</table>

		<h3><?php _e('Tax Rates','Shopp'); ?></h3>
		<?php if ($this->Settings->get('target_markets')): ?>
		<table id="tax-rates" class="form-table"><tbody></tbody></table>
		<?php else: ?>
		<p><strong><?php _e('Note','Shopp'); ?>:</strong> <?php sprintf(__('You must select the target markets you will be selling to under %s before you can setup tax rates.','Shopp'),'<a href="?page='.$this->Admin->settings['settings'][0].'">'.__('General settings','Shopp').'</a>'); ?></p>
		<?php endif; ?>
		
		<div class="tablenav">
			<div class="alignright actions"><button type="button" id="add-taxrate" class="button-secondary"><img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="+" width="16" height="16" /> <?php _e('Add a Tax Rate','Shopp'); ?></button>
	        </div>
		</div>
	
		<p class="submit"><input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>
	</form>
</div>

<script type="text/javascript">
/* <![CDATA[ */
var ratetable,rates,base,countries,zones,localities,taxrates,
	ratesidx,countriesInUse,zonesInUse,allCountryZonesInUse,
	APPLY_LOGIC,LOCAL_RATES,LOCAL_RATE_INSTRUCTIONS,SHOPP_PLUGINURI,RULE_LANG,
	sugg_url='<?php echo wp_nonce_url(admin_url('admin-ajax.php'),'wp_ajax_shopp_suggestions'); ?>',
	upload_url='<?php echo wp_nonce_url(admin_url('admin-ajax.php'),'wp_ajax_shopp_upload_local_taxes'); ?>',
	ratetable = jQuery('#tax-rates'),
	rates = <?php echo json_encode($rates); ?>,
	base = <?php echo json_encode($base); ?>,
	countries = <?php echo json_encode($countries); ?>,
	zones = <?php echo json_encode($zones); ?>,
	localities = <?php echo json_encode(Lookup::localities()); ?>,
	taxrates = new Array(),
	ratesidx = 0,
	countriesInUse = new Array(),
	zonesInUse = new Array(),
	allCountryZonesInUse = new Array(),
	APPLY_LOGIC = '<?php _e("Apply tax rate when %s of the following conditions match","Shopp"); ?>',
	LOCAL_RATES = '<?php _e("Local Rates","Shopp"); ?>',
	LOCAL_RATE_INSTRUCTIONS = '<?php _e("No local regions have been setup for this location. Local regions can be specified by uploading a formatted local rates file.","Shopp"); ?>',
	LOCAL_RATES_UPLOADERR = '<?php _e("The file was uploaded successfully, but the data returned by the server cannot be used.","Shopp"); ?>',
	ANY_OPTION = '<?php _e("any","Shopp"); ?>',
	ALL_OPTION = '<?php _e("all","Shopp"); ?>',
	SHOPP_PLUGINURI = '<?php echo SHOPP_PLUGINURI; ?>',
	RULE_LANG = {
		"product-name":"<?php _e('Product name is','Shopp'); ?>",
		"product-tags":"<?php _e('Product is tagged','Shopp'); ?>",
		"product-category":"<?php _e('Product in category','Shopp'); ?>",
		"customer-type":"<?php _e('Customer type is','Shopp'); ?>"
	};
/* ]]> */
</script>