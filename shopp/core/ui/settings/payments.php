<div class="wrap shopp">
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>
	<h2><?php _e('Payments Settings','Shopp'); ?></h2>
	<?php include("navigation.php"); ?>

	<br class="clear" />
	<form name="settings" id="payments" action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
		<?php wp_nonce_field('shopp-settings-payments'); ?>
		
		<table class="form-table"> 
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="payment-gateway"><?php _e('Payment Gateway','Shopp'); ?></label></th> 
				<td><select name="settings[payment_gateway]" id="payment-gateway">
					<option value=""><?php _e('Select One','Shopp'); ?>&hellip;</option>
					<?php echo menuoptions($gateways,$this->Settings->get('payment_gateway'),true)?>
					</select><br /> 
	            <?php _e('Select the payment gateway processor you will be using to process credit card transactions.','Shopp'); ?></td>
			</tr>
			<tbody id="payment-settings">
				<?php foreach ($Processors as $Processor) $Processor->settings(); ?>
			</tbody>
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="paypalexpress-enabled">PayPal Express</label></th> 
				<td><input type="hidden" name="settings[PayPalExpress][enabled]" value="off" id="paypalexpress-disabled"/><input type="checkbox" name="settings[PayPalExpress][enabled]" value="on" id="paypalexpress-enabled"<?php echo ($PayPalExpress->settings['enabled'] == "on")?' checked="checked"':''; ?>/><label for="paypalexpress-enabled"> <?php _e('Enable','Shopp'); ?> PayPal Express</label>
					<div id="paypalexpress-settings">
						<?php echo $PayPalExpress->settings(); ?>
					</div>
				</td>
			</tr>
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="googlecheckout-enabled">Google Checkout</label></th> 
				<td><input type="hidden" name="settings[GoogleCheckout][enabled]" value="off" id="googlecheckout-disabled"/><input type="checkbox" name="settings[GoogleCheckout][enabled]" value="on" id="googlecheckout-enabled"<?php echo ($GoogleCheckout->settings['enabled'] == "on")?' checked="checked"':''; ?>/><label for="googlecheckout-enabled"> <?php _e('Enable','Shopp'); ?> Google Checkout</label>
					<div id="googlecheckout-settings">
						<?php echo $GoogleCheckout->settings(); ?>
					</div>
				</td>
			</tr>
		</table>
		
		<p class="submit"><input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>
	</form>
</div>

<script type="text/javascript">
helpurl = "<?php echo SHOPP_DOCS; ?>Payments_Settings";
(function($) {
if (!$('#paypalexpress-enabled').attr('checked')) $('#paypalexpress-settings').hide();
if (!$('#googlecheckout-enabled').attr('checked')) $('#googlecheckout-settings').hide();

var gatewayHandlers = new CallbackRegistry();

<?php foreach ($Processors as $Processor) $Processor->registerSettings(); ?>

$('#payment-gateway').change(function () {
	$('#payment-settings tr').hide();
	var target = '#'+gatewayHandlers.get(this.value);
	if (this.value.length > 0) $(target).show();
}).change();

$('#paypalexpress-enabled').change(function () {
	$('#paypalexpress-settings').slideToggle(250);
});

$('#googlecheckout-enabled').change(function () {
	$('#googlecheckout-settings').slideToggle(250);
});

})(jQuery);
</script>