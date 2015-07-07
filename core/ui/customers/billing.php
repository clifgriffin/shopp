<script id="address-editor" type="text/x-jquery-tmpl">
<?php
	$editaddress = ShoppAdminCustomerBillingAddressBox::editor($Customer, 'billing');
	echo $editaddress;
	$address = array(
		'${type}' => 'billing',
		'${address}' => $Customer->Billing->address,
		'${xaddress}' => $Customer->Billing->xaddress,
		'${city}' => $Customer->Billing->city,
		'${state}' => $Customer->Billing->state,
		'${postcode}' => $Customer->Billing->postcode,
		'${country}' => $Customer->Billing->country,
		'${statemenu}' => Shopp::menuoptions($Customer->_billing_states, $Customer->Billing->state, true),
		'${countrymenu}' => Shopp::menuoptions($Customer->_countries, $Customer->Billing->country, true)
	);
	$js = preg_replace('/\${([-\w]+)}/', '$1', json_encode($address));
	shopp_custom_script('customers', 'address["billing"] = ' . $js . ';');
?>
</script>
<div id="billing-address-editor" class="editor">
<?php echo ShoppUI::template($editaddress, $address); ?>
</div>