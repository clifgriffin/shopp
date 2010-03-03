<div class="wrap shopp">
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>
	
	<div class="icon32"></div>
	<h2><?php _e('Settings','Shopp'); ?></h2>
	
	<form name="settings" id="general" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post">
		<?php wp_nonce_field('shopp-settings-general'); ?>

		<?php include("navigation.php"); ?>
		
		<table class="form-table"> 
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="update-key"><?php _e('Update Key','Shopp'); ?></label></th> 
				<td>
					<input type="<?php echo $type; ?>" name="updatekey" id="update-key" size="40" value="<?php echo $key; ?>"<?php echo ($activated)?' readonly="readonly"':''; ?> />
					<button type="button" id="activation-button" name="activation-button" class="button-secondary"><? echo (!$activated)?__('Activate Key','Shopp'):str_repeat('&nbsp;',25); ?></button>
					<br /><div id="activation-status" class="activating hidden"><?php printf(__('Activate your Shopp access key for automatic updates and official support services. If you don\'t have a Shopp key, feel free to support the project by <a href="%s">purchasing a key from shopplugin.net</a>.','Shopp'),SHOPP_HOME.'store/'); ?></div>
	            </td>
			</tr>			
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="merchant_email"><?php _e('Merchant Email','Shopp'); ?></label></th> 
				<td><input type="text" name="settings[merchant_email]" value="<?php echo esc_attr($this->Settings->get('merchant_email')); ?>" id="merchant_email" size="30" /><br /> 
	            <?php _e('Enter the email address for the owner of this shop to receive e-mail notifications.','Shopp'); ?></td>
			</tr>
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="base_operations"><?php _e('Base of Operations','Shopp'); ?></label></th> 
				<td><select name="settings[base_operations][country]" id="base_operations">
					<option></option>
						<?php echo menuoptions($countries,$operations['country'],true); ?>
					</select>
					<select name="settings[base_operations][zone]" id="base_operations_zone">
						<?php if (isset($zones)) echo menuoptions($zones,$operations['zone'],true); ?>
					</select>
					<br /> 
	            	<?php _e('Select your primary business location.','Shopp'); ?><br />
					<?php if (!empty($operations['country'])): ?>
		            <strong><?php _e('Currency','Shopp'); ?>: </strong><?php echo money(1000.00); ?>
					<?php if ($operations['vat']): ?><strong>+(VAT)</strong><?php endif; ?>
					<?php endif; ?>
				</td> 
			</tr>
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="target_markets"><?php _e('Target Markets','Shopp'); ?></label></th> 
				<td>
					<div id="target_markets" class="multiple-select">
						<ul>
							
							<?php $even = true; foreach ($targets as $iso => $country): ?>
								<li<?php if ($even) echo ' class="odd"'; $even = !$even; ?>><input type="checkbox" name="settings[target_markets][<?php echo $iso; ?>]" value="<?php echo $country; ?>" id="market-<?php echo $iso; ?>" checked="checked" /><label for="market-<?php echo $iso; ?>" accesskey="<?php echo substr($iso,0,1); ?>"><?php echo $country; ?></label></li>
							<?php endforeach; ?>
							<li<?php if ($even) echo ' class="odd"'; $even = !$even; ?>><input type="checkbox" name="selectall_targetmarkets"  id="selectall_targetmarkets" /><label for="selectall_targetmarkets"><strong><?php _e('Select All','Shopp'); ?></strong></label></li>							
							<?php foreach ($countries as $iso => $country): ?>
							<?php if (!in_array($country,$targets)): ?>
							<li<?php if ($even) echo ' class="odd"'; $even = !$even; ?>><input type="checkbox" name="settings[target_markets][<?php echo $iso; ?>]" value="<?php echo $country; ?>" id="market-<?php echo $iso; ?>" /><label for="market-<?php echo $iso; ?>" accesskey="<?php echo substr($iso,0,1); ?>"><?php echo $country; ?></label></li>
							<?php endif; endforeach; ?>
						</ul>
					</div>
					<br /> 
	            <?php _e('Select the markets you are selling products to.','Shopp'); ?></td> 
			</tr>
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="dashboard-toggle"><?php _e('Dashboard Widgets','Shopp'); ?></label></th> 
				<td><input type="hidden" name="settings[dashboard]" value="off" /><input type="checkbox" name="settings[dashboard]" value="on" id="dashboard-toggle"<?php if ($this->Settings->get('dashboard') == "on") echo ' checked="checked"'?> /><label for="dashboard-toggle"> <?php _e('Enabled','Shopp'); ?></label><br /> 
	            <?php _e('Check this to display store performance metrics and more on the WordPress Dashboard.','Shopp'); ?></td>
			</tr>			
			<tr class="form-required"> 
				<th scope="row" valign="top"><label for="cart-toggle"><?php _e('Order Status Labels','Shopp'); ?></label></th> 
				<td>
				<ol id="order-statuslabels">
				</ol>
				<?php _e('Add your own order processing status labels. Be sure to click','Shopp'); ?> <strong><?php _e('Save Changes','Shopp'); ?></strong> <?php _e('below!','Shopp'); ?></td>
			</tr>
		</table>
		
		<p class="submit"><input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>
	</form>
</div>
<script type="text/javascript">
helpurl = "<?php echo SHOPP_DOCS; ?>General_Settings";

(function($){
var labels = <?php echo json_encode($statusLabels); ?>;
var labelInputs = new Array();
var activated = <?php echo ($activated)?'true':'false'; ?>;
var SHOPP_ACTIVATE_KEY = "<?php _e('Activate Key','Shopp'); ?>";
var SHOPP_DEACTIVATE_KEY = "<?php _e('Deactivate Key','Shopp'); ?>";
var SHOPP_CONNECTING = "<?php _e('Connecting','Shopp'); ?>";

var keyStatus = {
	'-1':"<?php _e('An unkown error occurred.','Shopp'); ?>",
	'0':"<?php _e('This key has been deactivated.','Shopp'); ?>",
	'1':"<?php _e('This key has been activated.','Shopp'); ?>",
	'-100':"<?php _e('An unknown activation error occurred.','Shopp'); ?>",
	'-101':"<?php __('The key provided is not valid.','Shopp'); ?>",
	'-102':"<?php _e('The site is not able to be activated.','Shopp'); ?>",
	'-103':"<?php _e('The key provided could not be validated by shopplugin.net.','Shopp'); ?>",
	'-104':"<?php printf(__('The key provided is already active on another site. Contact <a href=\"%s\">customer service</a>.','Shopp'),SHOPP_CUSTOMERS); ?>",
	'-200':"<?php _e('An unkown deactivation error occurred.','Shopp'); ?>",
	'-201':"<?php _e('The key provided is not valid.','Shopp'); ?>",
	'-202':"<?php _e('The site is not able to be activated.','Shopp'); ?>",
	'-203':"<?php _e('The key provided could not be validated by shopplugin.net.','Shopp'); ?>"
}

function activation (key,success,status) {
	var button = $('#activation-button').attr('disabled',false).removeClass('updating');
	var keyin = $('#update-key');
	
	if (button.hasClass('deactivation')) button.html(SHOPP_DEACTIVATE_KEY);
	else button.html(SHOPP_DEACTIVATE_KEY);

	if (key[0] == "1") {
		if (key[2] == "dev" && keyin.attr('type') == "text") keyin.replaceWith('<input type="password" name="updatekey" id="update-key" value="'+keyin.val()+'" readonly="readonly" size="40" />');
		else keyin.attr('readonly',true);
		button.html(SHOPP_DEACTIVATE_KEY).addClass('deactivation');
	}
	
	if (key[0] == "0") {
		if (keyin.attr('type') == "password") 
			keyin.replaceWith('<input type="text" name="updatekey" id="update-key" value="" size="40" />');
		else keyin.attr('readonly',false);
		button.html(SHOPP_ACTIVATE_KEY).removeClass('deactivation');
	}
	
	if (key instanceof Array) 
		$('#activation-status').html(keyStatus[key[0]]);
	
	if (status) $('#activation-status').removeClass('activating').show();
	else $('#activation-status').addClass('activating').show();
}

if (activated) activation([1],'success',true);
else $('#activation-status').show();

$('#activation-button').click(function () {
	$(this).html(SHOPP_CONNECTING+"&hellip;").attr('disabled',true).addClass('updating');
	if ($(this).hasClass('deactivation'))
		$.getJSON(ajaxurl+'?action=shopp_deactivate_key',activation);
	else $.getJSON(ajaxurl+'?action=shopp_activate_key&key='+$('#update-key').val(),activation);
});




if (!$('#base_operations').val() || $('#base_operations').val() == '') $('#base_operations_zone').hide();
if (!$('#base_operations_zone').val()) $('#base_operations_zone').hide();

$('#base_operations').change(function() {
	if ($('#base_operations').val() == '') {
		$('#base_operations_zone').hide();
		return true;
	}

	$.getJSON(ajaxurl+'?action=shopp_country_zones&country='+$('#base_operations').val(),
		function(data) {
			$('#base_operations_zone').hide();
			$('#base_operations_zone').empty();
			if (!data) return true;
			
			$.each(data, function(value,label) {
				option = $('<option></option>').val(value).html(label).appendTo('#base_operations_zone');
			});
			$('#base_operations_zone').show();
			
	});
});

$('#selectall_targetmarkets').change(function () { 
	if ($(this).attr('checked')) $('#target_markets input').not(this).attr('checked',true); 
	else $('#target_markets input').not(this).attr('checked',false); 
});

var addLabel = function (id,label,location) {
	
	var i = labelInputs.length+1;
	if (!id) id = i;
	
	if (!location) var li = $('<li id="item-'+i+'"></li>').appendTo('#order-statuslabels');
	else var li = $('<li id="item-'+i+'"></li>').insertAfter(location);

	var wrap = $('<span></span>').appendTo(li);
	var input = $('<input type="text" name="settings[order_status]['+id+']" id="label-'+i+'" size="14" />').appendTo(wrap);
	var deleteButton = $('<button type="button" class="delete"></button>').appendTo(wrap).hide();
	var deleteIcon = $('<img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/delete.png" alt="Delete" width="16" height="16" />').appendTo(deleteButton);
	var addButton = $('<button type="button" class="add"></button>').appendTo(wrap);
	var addIcon = $('<img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/add.png" alt="Add" width="16" height="16" />').appendTo(addButton);
	
	if (i > 0) {
		wrap.hover(function() {
			deleteButton.show();
		},function () {
			deleteButton.hide();
		});
	}
	
	addButton.click(function () {
		addLabel(null,null,'#'+$(li).attr('id'));
	});
	
	deleteButton.click(function () {
		if (confirm("<?php echo addslashes(__('Are you sure you want to remove this order status label?','Shopp')); ?>"))
			li.remove();
	});
	
	if (label) input.val(label);
	
	labelInputs.push(li);
	
}

if (labels) {
	for (var id in labels) {		
		addLabel(id,labels[id]);
	}
} else addLabel();

})(jQuery)
</script>