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
	            <?php _e('Enables shipping cost calculations. Disable if you are exclusively selling intangible products.','Shopp'); ?></td>
			</tr>
				<tr>
					<th scope="row" valign="top"><label for="shipping-toggle"><?php _e('Track Inventory','Shopp'); ?></label></th>
					<td><input type="hidden" name="settings[inventory]" value="off" /><input type="checkbox" name="settings[inventory]" value="on" id="inventory-toggle"<?php if ($this->Settings->get('inventory') == "on") echo ' checked="checked"'?> /><label for="inventory-toggle"> <?php _e('Enabled','Shopp'); ?></label><br />
		            <?php _e('Enables inventory tracking. Disable if you are exclusively selling intangible products or not keeping track of product stock.','Shopp'); ?></td>
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
				<th scope="row" valign="top"><label for="order-processing-min"><?php _e('Order Processing','Shopp'); ?></label></th>
				<td>
				<select name="settings[order_processing_min]" id="order-processing">
						<?php echo menuoptions(Lookup::timeframes_menu(),$this->Settings->get('order_processing_min'),true); ?>
				</select> &mdash; <select name="settings[order_processing_max]" id="order-processing">
							<?php echo menuoptions(Lookup::timeframes_menu(),$this->Settings->get('order_processing_max'),true); ?>
				</select><br />
				<?php _e('Set the estimated time range for processing orders for shipment.','Shopp'); ?></td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="lowstock-level"><?php _e('Low Inventory','Shopp'); ?></label></th>
				<td>
					<?php
						$options = array_reverse(array_merge(range(0,25),range(30,50,5),range(60,100,10)));
						array_walk($options,create_function('&$val','$val = "$val%";'));
					?>
					<select name="settings[lowstock_level]" id="lowstock-level">
					<?php echo menuoptions($options,$lowstock); ?>
					</select><br />
	            	<?php _e('Enter the number for low stock level warnings.','Shopp'); ?>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="order_handling_fee"><?php _e('Order Handling Fee','Shopp'); ?></label></th>
				<td><input type="text" name="settings[order_shipfee]" value="<?php echo money($this->Settings->get('order_shipfee')); ?>" id="order_handling_fee" size="7" class="right selectall money" /><br />
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
		</table>

		<p class="submit"><input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>
	</form>
</div>

<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready(function($) {
	quickSelects();
});
/* ]]> */
</script>