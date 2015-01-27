<script id="delivery-menu" type="text/x-jquery-tmpl"><?php
	$deliverymenu = Lookup::timeframes_menu();
	echo Shopp::menuoptions($deliverymenu, false, true);
?></script>

<div>
	<?php wp_nonce_field('shopp-settings-shiprate'); ?>
</div>

<div class="tablenav">
	<div class="actions">
		<select name="id" id="shipping-option-menu">
		<option value=""><?php _e('Add a shipping method&hellip;','Shopp'); ?></option>
		<?php echo Shopp::menuoptions($installed,false,true); ?>
		</select>
		<button type="submit" name="add-shipping-option" id="add-shipping-option" class="button-secondary hide-if-js" tabindex="9999"><?php _e('Add Shipping Option','Shopp'); ?></button>
	</div>
</div>

<table class="widefat" cellspacing="0">
	<thead>
	<tr><?php ShoppUI::print_column_headers($this->id); ?></tr>
	</thead>
	<tfoot>
	<tr><?php ShoppUI::print_column_headers($this->id, false); ?></tr>
	</tfoot>
	<tbody id="shiprates" class="list">
	<?php

		if ( $edit && ! isset($shiprates[ $edit ]) ) {
			$template_data = array(
				'${mindelivery_menu}' => Shopp::menuoptions($deliverymenu, false, true),
				'${maxdelivery_menu}' => Shopp::menuoptions($deliverymenu, false, true),
				'${cancel_href}' => $this->url
			);
			$editor = str_replace(array_keys($template_data),$template_data,$editor);
			$editor = preg_replace('/\${\w+}/','',$editor);

			echo $editor;
		}

		if (count($shiprates) == 0 && !$edit): ?>
			<tr id="no-shiprate-settings"><td colspan="6"><?php _e('No shipping methods, yet.','Shopp'); ?></td></tr>
		<?php
		endif;

		$hidden = get_hidden_columns('shopp_page_shopp-settings-shiprates');
		$even = false;
		foreach ($shiprates as $setting => $module):
			$shipping = shopp_setting($setting);
			$service = $Shipping->modules[$module]->name;
			if ( isset($shipping['fallback']) && Shopp::str_true($shipping['fallback']) ) $service = '<big title="'.__('Fallback shipping real-time rate lookup failures','Shopp').'">&#9100;</big>  '.$service;
			$destinations = array();

			$min = $max = false;
			if (isset($shipping['table']) && is_array($shipping['table']))
			foreach ($shipping['table'] as $tablerate) {

				$destination = false;
				$d = ShippingSettingsUI::parse_location($tablerate['destination']);
				if (!empty($d['zone'])) $destinations[] = $d['zone'].' ('.$d['countrycode'].')';
				elseif (!empty($d['area'])) $destinations[] = $d['area'];
				elseif (!empty($d['country'])) $destinations[] = $d['country'];
				elseif (!empty($d['region'])) $destinations[] = $d['region'];
			}
			if (!empty($destinations)) $destinations = array_keys(array_flip($destinations)); // Combine duplicate destinations
			if (isset($Shipping->active[$module]) && $Shipping->active[$module]->realtime) $destinations = array($Shipping->active[$module]->destinations);

			$label = $service;
			if (isset($shipping['label'])) $label = $shipping['label'];

			$editurl = wp_nonce_url(add_query_arg(array('id'=>$setting),$this->url));
			$deleteurl = wp_nonce_url(add_query_arg(array('delete'=>$setting),$this->url),'shopp_delete_shiprate');

			$classes = array();
			if (!$even) $classes[] = 'alternate'; $even = !$even;

			if ($edit && $edit == $setting) {
				$template_data = array(
					'${mindelivery_menu}' => menuoptions($deliverymenu,$shipping['mindelivery'],true),
					'${maxdelivery_menu}' => menuoptions($deliverymenu,$shipping['maxdelivery'],true),
					'${fallbackon}' => ('on' == $shipping['fallback'])?'checked="checked"':'',
					'${cancel_href}' => $this->url
				);
				$editor = str_replace(array_keys($template_data),$template_data,$editor);
				$editor = preg_replace('/\${\w+}/','',$editor);

				echo $editor;
				if ($edit == $setting) continue;
			}

		?>
	<tr class="<?php echo join(' ',$classes); ?>" id="shipping-setting-<?php echo sanitize_title_with_dashes($module); ?>">
		<td class="name column-name"><a href="<?php echo esc_url($editurl); ?>" title="<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($label); ?>&quot;" class="edit row-title"><?php echo esc_html($label); ?></a>
			<div class="row-actions">
				<span class='edit'><a href="<?php echo esc_url($editurl); ?>" title="<?php _e('Edit','Shopp'); ?> &quot;<?php echo esc_attr($label); ?>&quot;" class="edit"><?php _e('Edit','Shopp'); ?></a> | </span><span class='delete'><a href="<?php echo esc_url($deleteurl); ?>" title="<?php _e('Delete','Shopp'); ?> &quot;<?php echo esc_attr($label); ?>&quot;" class="delete"><?php _e('Delete','Shopp'); ?></a></span>
			</div>
		</td>
		<td class="type column-type"><?php echo $service; ?></td>
		<td class="supported column-supported"><?php echo join(', ',$destinations); ?></td>

	</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<?php wp_nonce_field('shopp-settings-shipping'); ?>

<table class="form-table">

	<tr>
		<th scope="row" valign="top"><label><?php _e('Shipping Carriers','Shopp'); ?></label></th>
		<td>
		<div id="carriers" class="multiple-select">
			<ul>
				<li<?php $even = true;
				$classes[] = 'odd hide-if-no-js'; if (!empty($classes)) echo ' class="'.join(' ',$classes).'"'; $even = !$even; ?>><input type="checkbox" name="selectall"  id="selectall" /><label for="selectall"><strong><?php _e('Select All','Shopp'); ?></strong></label><input type="hidden" name="settings[shipping_carriers]" value="off" /></li>
				<?php
					foreach ($carriers as $code => $carrier):
						$classes = array();
						if ($even) $classes[] = 'odd';
				?>
					<li<?php if (!empty($classes)) echo ' class="'.join(' ',$classes).'"'; ?>><input type="checkbox" name="settings[shipping_carriers][]" value="<?php echo $code; ?>" id="carrier-<?php echo $code; ?>"<?php if (in_array($code,$shipping_carriers)) echo ' checked="checked"'; ?> /><label for="carrier-<?php echo $code; ?>" accesskey="<?php echo substr($code,0,1); ?>"><?php echo $carrier; ?></label></li>
				<?php $even = !$even; endforeach; ?>
			</ul>
		</div><br />
		<label><?php _e('Select the shipping carriers you will be using for shipment tracking.','Shopp'); ?></label>
		</td>
	</tr>
	<?php $Shopp = Shopp::object(); if ($Shopp->Shipping->realtime): ?>
	<tr>
		<th scope="row" valign="top"><label for="packaging"><?php _e('Packaging','Shopp'); ?></label></th>
		<td>
		<select name="settings[shipping_packaging]" id="packaging">
				<?php echo menuoptions(Lookup::packaging_types(), shopp_setting('shipping_packaging'),true); ?>
		</select><br />
		<?php _e('Determines packaging method used for real-time shipping quotes.','Shopp'); ?></td>
	</tr>
	<tr>
		<th scope="row" valign="top"><label for="packaging"><?php _e('Package Limit','Shopp'); ?></label></th>
		<td>
		<select name="settings[shipping_package_weight_limit]" id="packaging_weight_limit">
				<?php echo menuoptions(apply_filters('shopp_package_weight_limits', array('-1'=>'∞','10'=>10,'20'=>20,'30'=>30,'40'=>40,'50'=>50,'60'=>60,'70'=>70,'80'=>80,'90'=>90,'100'=>100,'150'=>150,'200'=>200,'250'=>250,'300'=>300,'350'=>350,'400'=>400,'450'=>450,'500'=>500,'550'=>550,'600'=>600,'650'=>650,'700'=>700,'750'=>750,'800'=>800)),
						shopp_setting('shipping_package_weight_limit'),true); ?>
		</select><br />
		<?php _e('The maximum weight allowed for a package.','Shopp'); ?></td>
	</tr>
	<?php endif; ?>
	<tr>
		<th scope="row" valign="top"><label for="weight-unit"><?php _e('Units','Shopp'); ?></label></th>
		<td>
		<select name="settings[weight_unit]" id="weight-unit">
				<?php echo $weightsmenu; ?>
		</select>
		<select name="settings[dimension_unit]" id="dimension-unit">
				<?php echo $dimsmenu; ?>
		</select><br />
		<?php _e('Standard weight &amp; dimension units used for all products.','Shopp'); ?></td>
	</tr>

</table>

<p class="submit"><input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>

<?php do_action('shopp_shipping_module_settings'); ?>

<script type="text/javascript">
/* <![CDATA[ */
var shipping = <?php echo json_encode(array_map('sanitize_title_with_dashes',array_keys($installed))); ?>,
	defaults = <?php echo json_encode($defaults); ?>,
	settings = <?php echo json_encode($settings); ?>,
	lookup = <?php echo json_encode($lookup); ?>;

jQuery(document).ready(function($) {
	quickSelects();
	$('#selectall').change(function () {
		if ($(this).attr('checked')) $('#carriers input').not(this).attr('checked',true);
		else $('#carriers input').not(this).attr('checked',false);
	});

});
/* ]]> */
</script>
