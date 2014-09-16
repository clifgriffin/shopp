<?php if ( isset($_POST['edit-shipping-address']) || empty(ShoppPurchase()->shipping) ): ?>
	<form action="<?php echo ShoppAdminController::url( array('page' => $page, 'id' => $Purchase->id) ); ?>" method="post" id="shipping-address-editor">
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
	echo ShoppUI::template(order_address_editor(), $address); ?>
	</form>
<?php return; endif; ?>

<form action="<?php echo ShoppAdminController::url(array('id' => $Purchase->id)); ?>" method="post" id="shipping-address-editor"></form>
<div class="display">
	<form action="<?php echo ShoppAdminController::url( array('id' => $Purchase->id) ); ?>" method="post">
	<?php $targets = shopp_setting('target_markets'); ?>
		<input type="hidden" id="edit-shipping-address-data" value="<?php
			$shipname = explode(' ',$Purchase->shipname);
			$shipfirst = array_shift($shipname);
			$shiplast = join(' ',$shipname);
			$address = array(
				'action' => 'update-address',
				'type' => 'shipping',
				'firstname' => $shipfirst,
				'lastname' => $shiplast,
				'address' => $Purchase->shipaddress,
				'xaddress' => $Purchase->shipxaddress,
				'city' => $Purchase->shipcity,
				'state' => $Purchase->shipstate,
				'postcode' => $Purchase->shippostcode,
				'country' => $Purchase->shipcountry,
				'statemenu' => Shopp::menuoptions($Purchase->_shipping_states,$Purchase->shipstate,true),
				'countrymenu' => Shopp::menuoptions($Purchase->_countries,$Purchase->shipcountry,true)

			);
			$js = preg_replace('/\${([-\w]+)}/','$1',json_encode($address));
			shopp_custom_script('orders','address["shipping"] = '.$js.';');
			echo esc_attr(json_encode($address));
		?>" />
		<input type="submit" id="edit-shipping-address" name="edit-shipping-address" value="<?php _e('Edit','Shopp'); ?>" class="button-secondary button-edit" />
	</form>

	<address><big><?php echo esc_html($Purchase->shipname); ?></big><br />
	<?php echo esc_html($Purchase->shipaddress); ?><br />
	<?php if ( ! empty($Purchase->shipxaddress) ) echo esc_html($Purchase->shipxaddress)."<br />"; ?>
	<?php echo esc_html("{$Purchase->shipcity}".(!empty($Purchase->shipstate)?', ':'')." {$Purchase->shipstate} {$Purchase->shippostcode}") ?><br />
	<?php echo $targets[$Purchase->shipcountry]; ?></address>
</div>