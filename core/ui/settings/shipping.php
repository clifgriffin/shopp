<div class="wrap shopp">
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>

	<div class="icon32"></div>
	<h2><?php _e('Shipping Settings','Shopp'); ?></h2>

	<?php $this->shipping_menu(); ?>

	<form name="settings" id="shipping" action="<?php echo esc_url($this->url); ?>" method="post">
		<?php wp_nonce_field('shopp-settings-shipping'); ?>

		<table class="form-table">
			<tr>
				<th scope="row" valign="top"><label for="shipping-toggle"><?php _e('Calculate Shipping','Shopp'); ?></label></th>
				<td><input type="hidden" name="settings[shipping]" value="off" /><input type="checkbox" name="settings[shipping]" value="on" id="shipping-toggle"<?php if ($this->Settings->get('shipping') == "on") echo ' checked="checked"'?> /><label for="shipping-toggle"> <?php _e('Enabled','Shopp'); ?></label><br />
	            <?php _e('Enables calculating shipping costs. Disable if you are exclusively selling intangible products.','Shopp'); ?></td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="weight-unit"><?php _e('Units','Shopp'); ?></label></th>
				<td>
				<select name="settings[weight_unit]" id="weight-unit">
						<?php
							if ($base['units'] == "imperial") $units = array("oz" => __("ounces (oz)","Shopp"),"lb" => __("pounds (lbs)","Shopp"));
							else $units = array("g"=>__("gram (g)","Shopp"),"kg"=>__("kilogram (kg)","Shopp"));
							echo menuoptions($units,$this->Settings->get('weight_unit'),true);
						?>
				</select>
				<select name="settings[dimension_unit]" id="dimension-unit">
						<?php
							if ($base['units'] == "imperial") $units = array("in" => __("inches (in)","Shopp"),"ft" => __("feet (ft)","Shopp"));
							else $units = array("cm"=>__("centimeters (cm)","Shopp"),"m"=>__("meters (m)","Shopp"));
							echo menuoptions($units,$this->Settings->get('dimension_unit'),true);
						?>
				</select><br />
				<?php _e('Standard weight &amp; dimension units used for all products.','Shopp'); ?></td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="packaging"><?php _e('Packaging','Shopp'); ?></label></th>
				<td>
				<select name="settings[shipping_packaging]" id="packaging">
						<?php
							$packaging = array("mass" => __("All together by weight","Shopp"),
										"all" => __("All together with dimensions","Shopp"),
										"like" => __("Only like items together","Shopp"),
										"piece" => __("Each piece separately","Shopp"));
							echo menuoptions($packaging,$this->Settings->get('shipping_packaging'),true);
						?>
				</select><br />
				<?php _e('Determines packaging method used for shipment.','Shopp'); ?></td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="order_handling_fee"><?php _e('Order Handling Fee','Shopp'); ?></label></th>
				<td><input type="text" name="settings[order_shipfee]" value="<?php echo money($this->Settings->get('order_shipfee')); ?>" id="order_handling_fee" size="7" class="right selectall" /><br />
	            <?php _e('Handling fee applied once to each order with shipped products.','Shopp'); ?></td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="free_shipping_text"><?php _e('Free Shipping Text','Shopp'); ?></label></th>
				<td><input type="text" name="settings[free_shipping_text]" value="<?php echo esc_attr($this->Settings->get('free_shipping_text')); ?>" id="free_shipping_text" /><br />
	            <?php _e('Text used to highlight no shipping costs (examples: Free shipping! or Shipping Included)','Shopp'); ?></td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="outofstock-text"><?php _e('Out-of-stock Notice','Shopp'); ?></label></th>
				<td><input type="text" name="settings[outofstock_text]" value="<?php echo esc_attr($this->Settings->get('outofstock_text')); ?>" id="outofstock-text" /><br />
	            <?php _e('Text used to notify the customer the product is out-of-stock or on backorder.','Shopp'); ?></td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="lowstock-level"><?php _e('Low Inventory','Shopp'); ?></label></th>
				<td><input type="text" name="settings[lowstock_level]" value="<?php echo esc_attr($lowstock); ?>" id="lowstock-level" size="5" class="selectall" /><br />
	            <?php _e('Enter the number for low stock level warnings.','Shopp'); ?></td>
			</tr>
		</table>

		<p class="submit"><input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>
	</form>
</div>

<script type="text/javascript">
/* <![CDATA[ */
var weight_units = '<?php echo $Shopp->Settings->get("weight_unit"); ?>',
	uniqueMethods = new Array();

jQuery(document).ready(function() {
	var $ = jqnc();

	$('#order_handling_fee').change(function() { this.value = asMoney(this.value); });

	$('#weight-unit').change(function () {
		weight_units = $(this).val();
		$('#shipping-rates table.rate td.units span.weightunit').html(weight_units+' = ');
	});

	function addShippingRate (r) {
		if (!r) r = false;
		var i = shippingRates.length;
		var row = $('<tr></tr>').appendTo($('#shipping-rates'));
		var heading = $('<th scope="row" valign="top"><label for="name['+i+']" id="label-'+i+'"><?php echo addslashes(__('Option Name','Shopp')); ?></label><input type="hidden" name="priormethod-'+i+'" id="priormethod-'+i+'" /></th>').appendTo(row);
		$('<br />').appendTo(heading);
		var name = $('<input type="text" name="settings[shipping_rates]['+i+'][name]" value="" id="name-'+i+'" size="16" tabindex="'+(i+1)+'00" class="selectall" />').appendTo(heading);
		$('<br />').appendTo(heading);


		var deliveryTimesMenu = $('<select name="settings[shipping_rates]['+i+'][delivery]" id="delivery-'+i+'" class="methods" tabindex="'+(i+1)+'01"></select>').appendTo(heading);
		var lastGroup = false;
		$.each(deliveryTimes,function(range,label){
			if (range.indexOf("group")==0) {
				lastGroup = $('<optgroup label="'+label+'"></optgroup>').appendTo(deliveryTimesMenu);
			} else {
				if (lastGroup) $('<option value="'+range+'">'+label+'</option>').appendTo(lastGroup);
				else $('<option value="'+range+'">'+label+'</option>').appendTo(deliveryTimesMenu);
			}
		});

		$('<br />').appendTo(heading);

		var methodMenu = $('<select name="settings[shipping_rates]['+i+'][method]" id="method-'+i+'" class="methods" tabindex="'+(i+1)+'02"></select>').appendTo(heading);
		var lastGroup = false;
		$.each(methods,function(m,methodtype){
			if (m.indexOf("group")==0) {
				lastGroup = $('<optgroup label="'+methodtype+'"></optgroup>').appendTo(methodMenu);
			} else {
				var methodOption = $('<option value="'+m+'">'+methodtype+'</option>');
				for (var disabled in uniqueMethods)
					if (uniqueMethods[disabled] == m) methodOption.attr('disabled',true);
				if (lastGroup) methodOption.appendTo(lastGroup);
				else methodOption.appendTo(methodMenu);
			}
		});
		var rateTableCell = $('<td/>').appendTo(row);
		var deleteRateButton = $('<button type="button" name="deleteRate" class="delete deleteRate"></button>').appendTo(rateTableCell).hide();
		$('<img src="<?php echo SHOPP_PLUGINURI; ?>/core/ui/icons/delete.png" width="16" height="16"  />').appendTo(deleteRateButton);


		var rowBG = row.css("background-color");
		var deletingBG = "#ffebe8";
		row.hover(function () {
				deleteRateButton.show();
			}, function () {
				deleteRateButton.hide();
		});

		deleteRateButton.hover (function () {
				row.animate({backgroundColor:deletingBG},250);
			},function() {
				row.animate({backgroundColor:rowBG},250);
		}).click(function () {
			if (confirm("<?php echo addslashes(__('Are you sure you want to delete this shipping option?','Shopp')); ?>")) {
				row.remove();
				shippingRates.splice(i,1);
			}
		});

		var rateTable = $('<table class="rate"/>').appendTo(rateTableCell);

		$(methodMenu).change(function() {

			var id = $(this).attr('id').split("-"),
				methodid = id[1],
				priormethod = $('#priormethod-'+methodid).val(),
				uniqueMethodIndex = $.inArray(priormethod,uniqueMethods);

			if (priormethod != "" && uniqueMethodIndex != -1)
				uniqueMethods.splice(uniqueMethodIndex,1);

			$('#label-'+methodid).show();
			$('#name-'+methodid).show();
			$('#delivery-'+methodid).show();
			$('#shipping-rates select.methods').not($(methodMenu)).find('option').attr('disabled',false);
			$(uniqueMethods).each(function (i,method) {
				$('#shipping-rates select.methods').not($(methodMenu)).find('option[value='+method+']:not(:selected)').attr('disabled',true);
			});

			methodHandlers.call($(this).val(),i,rateTable,r);
			methodName = $(this).val().split("::");
			$(this).parent().attr('class',methodName[0].toLowerCase());
			$('#priormethod-'+methodid).val($('#method-'+methodid).val());
		});

		if (r) {
			name.val(r.name);
			methodMenu.val(r.method).change();
			deliveryTimesMenu.val(r.delivery);
		} else {
			methodMenu.change();
		}

		quickSelects();
		shippingRates.push(row);

	}

	function uniqueMethod (methodid,option) {
		$('#label-'+methodid).hide();
		$('#name-'+methodid).hide();
		$('#delivery-'+methodid).hide();

		var methodMenu = $('#method-'+methodid),
			methodOptions = methodMenu.children(),
			optionid = methodMenu.get(0).selectedIndex;

		if ($(methodOptions.get(optionid)).attr('disabled')) {
			methodMenu.val(methodMenu.find('option:not(:disabled):first').val()).change();
			return false;
		}
		$('#shipping-rates select.methods').not($(methodMenu)).find('option[value='+option+']').attr('disabled',true);

		uniqueMethods.push(option);
		return true;
	}

	var methodHandlers = new CallbackRegistry();

	<?php do_action('shopp_settings_shipping_ui'); ?>

	if ($('#shipping-rates')) {
		var shippingRates = new Array(),
			rates = <?php echo json_encode($rates); ?>,
			methods = <?php echo json_encode($methods); ?>,
			deliveryTimes = {"prompt":"<?php _e('Delivery time','Shopp'); ?>&hellip;","group1":"<?php _e('Business Days','Shopp'); ?>","1d-1d":"1 <?php _e('business day','Shopp'); ?>","1d-2d":"1-2 <?php _e('business days','Shopp'); ?>","1d-3d":"1-3 <?php _e('business days','Shopp'); ?>","1d-5d":"1-5 <?php _e('business days','Shopp'); ?>","2d-3d":"2-3 <?php _e('business days','Shopp'); ?>","2d-5d":"2-5 <?php _e('business days','Shopp'); ?>","2d-7d":"2-7 <?php _e('business days','Shopp'); ?>","3d-5d":"3-5 <?php _e('business days','Shopp'); ?>","group2":"<?php _e('Weeks','Shopp'); ?>","1w-2w":"1-2 <?php _e('weeks','Shopp'); ?>","2w-3w":"2-3 <?php _e('weeks','Shopp'); ?>"},
			domesticAreas = <?php echo json_encode($areas); ?>,
			region = '<?php echo $region; ?>';

		$('#add-shippingrate').click(function() {
			addShippingRate();
		});

		$('#shipping-rates').empty();
		if (!rates) $('#add-shippingrate').click();
		else {
			$.each(rates,function(i,rate) {
				addShippingRate(rate);
			});
		}
	}

});
/* ]]> */
</script>