<?php
	$editaddress = ShoppAdminCustomerBillingAddressBox::editor($Customer, 'shipping');
	$address = array(
		'${type}' => 'shipping',
		'${address}' => $Customer->Shipping->address,
		'${xaddress}' => $Customer->Shipping->xaddress,
		'${city}' => $Customer->Shipping->city,
		'${state}' => $Customer->Shipping->state,
		'${postcode}' => $Customer->Shipping->postcode,
		'${country}' => $Customer->Shipping->country,
		'${statemenu}' => Shopp::menuoptions($Customer->_shipping_states, $Customer->Shipping->state, true),
		'${countrymenu}' => Shopp::menuoptions($Customer->_countries, $Customer->Shipping->country, true)
	);
	$js = preg_replace('/\${([-\w]+)}/', '$1', json_encode($address));
	shopp_custom_script('customers', 'address["shipping"] = ' . $js . ';');
?>
<div id="shipping-address-editor" class="editor">
<?php echo ShoppUI::template($editaddress, $address); ?>
</div>