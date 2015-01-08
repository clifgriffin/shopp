<?php if ( isset($_POST['edit-shipping-address']) || empty(ShoppPurchase()->shipping) ): ?>
	<form action="<?php echo $this->url(); ?>" method="post" id="shipping-address-editor">
	<?php
	$names = explode(' ', $Purchase->shipname);
	$firstname = array_shift($names);
	$lastname = join(' ', $names);
	$address = array(
		'${type}' => 'shipping',
		'${firstname}' => $firstname,
		'${lastname}' => $lastname,
		'${address}' => $Purchase->shipaddress,
		'${xaddress}' => $Purchase->shipxaddress,
		'${city}' => $Purchase->shipcity,
		'${state}' => $Purchase->shipstate,
		'${postcode}' => $Purchase->shippostcode,
		'${country}' => $Purchase->shipcountry,
		'${statemenu}' => Shopp::menuoptions($Purchase->_shipping_states,$Purchase->shipstate,true),
		'${countrymenu}' => Shopp::menuoptions($Purchase->_countries,$Purchase->shipcountry,true)
	);
	echo ShoppUI::template(ShoppAdminOrderShippingAddressBox::editor($Purchase, 'shipping'), $address);
	$js = preg_replace('/\${([-\w]+)}/', '$1', json_encode($address));
	shopp_custom_script('orders', 'address["shipping"] = ' . $js . ';');
	?>
	</form>
<?php return; endif; ?>

<form action="<?php echo $this->url(); ?>" method="post" id="shipping-address-editor"></form>
<div class="display">
	<form action="<?php echo $this->url(); ?>" method="post">
	<?php $targets = shopp_setting('target_markets'); ?>
	<input type="submit" id="edit-shipping-address" name="edit-shipping-address" value="<?php Shopp::_e('Edit'); ?>" class="button-secondary button-edit" />
	</form>

	<address><big><?php echo esc_html($Purchase->shipname); ?></big><br />
	<?php echo esc_html($Purchase->shipaddress); ?><br />
	<?php if ( ! empty($Purchase->shipxaddress) ) echo esc_html($Purchase->shipxaddress)."<br />"; ?>
	<?php echo esc_html("{$Purchase->shipcity}".(!empty($Purchase->shipstate)?', ':'')." {$Purchase->shipstate} {$Purchase->shippostcode}") ?><br />
	<?php echo $targets[$Purchase->shipcountry]; ?></address>
</div>