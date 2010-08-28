<div class="wrap shopp">
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>

	<div class="icon32"></div>
	<h2><?php _e('Payments Settings','Shopp'); ?></h2>

	<form name="settings" id="payments" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post">
		<?php wp_nonce_field('shopp-settings-payments'); ?>
		<div><input type="hidden" id="active-gateways" name="settings[active_gateways]" /></div>
		
		<?php include("navigation.php"); ?>

		<table id="payment-settings" class="form-table"> 
 		</table>
		
		<br class="clear" />
		
		<div class="tablenav"><div class="alignright actions">
			<select name="payment-option-menu" id="payment-option-menu">
			</select>
			<button type="button" name="add-payment-option" id="add-payment-option" class="button-secondary" tabindex="9999"><?php _e('Add Payment Option','Shopp'); ?></button>
			</div>
		</div>
		
		
		<p class="submit"><input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>
	</form>
</div>

<script type="text/javascript">
/* <![CDATA[ */
var SHOPP_PAYMENT_OPTION = "<?php _e('Option Name','Shopp'); ?>",
	SHOPP_DELETE_PAYMENT_OPTION = "<?php echo addslashes(__('Are you sure you want to delete this payment option?','Shopp')); ?>",
	SHOPP_GATEWAY_MENU_PROMPT = "<?php _e('Select a payment system&hellip;','Shopp'); ?>",
	SHOPP_PLUGINURI = "<?php echo SHOPP_PLUGINURI; ?>",
	SHOPP_SELECT_ALL = "<?php _e('Select All','Shopp'); ?>",
	gateways = <?php echo json_encode($gateways); ?>;

jQuery(document).ready( function() {
	var $=jqnc(),
		handlers = new CallbackRegistry();

	handlers.options = {};
	handlers.enabled = [];
	handlers.register = function (name,object) {
		this.callbacks[name] = function () {object['payment']();}
		this.options[name] = object;
	}
	
	handlers.call = function(name,arg1,arg2,arg3) {
		
		this.callbacks[name](arg1,arg2,arg3);
		var module = this.options[name];
		module.behaviors();
	}

	<?php do_action('shopp_gateway_module_settings'); ?>
	
	// Populate the payment options menu
	var options = '';
	options += '<option disabled="disabled">'+SHOPP_GATEWAY_MENU_PROMPT+'<\/option>';
	$.each(handlers['options'],function (id,object) {
		var disabled = '';
		if ($.inArray(id,gateways) != -1) {
			handlers.call(id);
			if (!object.multi) disabled = ' disabled="disabled"';
		}
		options += '<option value="'+id+'"'+disabled+'>'+object.name+'<\/option>';
	});
	$('#payment-option-menu').html(options);
	
	$('#add-payment-option').click(function () {
		var module = $('#payment-option-menu').val(),
			selected = $('#payment-option-menu :selected');
		if (!selected.attr('disabled')) {
			handlers.call(module);
			if (!handlers.options[module].multi) selected.attr('disabled',true);
		}
	});
	
});
/* ]]> */
</script>