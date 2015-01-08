	<script id="address-editor" type="text/x-jquery-tmpl">
	<?php
		$editaddress = ShoppAdminOrderBillingAddressBox::editor($Purchase, 'billing');
		echo $editaddress;
		$address = array(
			'${action}' => 'update-address',
			'${type}' => 'billing',
			'${firstname}' => $Purchase->firstname,
			'${lastname}' => $Purchase->lastname,
			'${address}' => $Purchase->address,
			'${xaddress}' => $Purchase->xaddress,
			'${city}' => $Purchase->city,
			'${state}' => $Purchase->state,
			'${postcode}' => $Purchase->postcode,
			'${country}' => $Purchase->country,
			'${statemenu}' => Shopp::menuoptions($Purchase->_billing_states, $Purchase->state, true),
			'${countrymenu}' => Shopp::menuoptions($Purchase->_countries, $Purchase->country, true)
		);
		$js = preg_replace('/\${([-\w]+)}/', '$1', json_encode($address));
		shopp_custom_script('orders', 'address["billing"] = ' . $js . ';');
	?>
	</script>

<?php if ( isset($_POST['edit-billing-address']) || empty(ShoppPurchase()->billing) ): ?>
	<form action="<?php echo $this->url(); ?>" method="post" id="billing-address-editor">
	<?php echo ShoppUI::template($editaddress, $address); ?>
	</form>
<?php return; endif; ?>

<form action="<?php echo $this->url(); ?>" method="post" id="billing-address-editor"></form>
<div class="display">
<form action="<?php echo $this->url(); ?>" method="post"><?php
$targets = shopp_setting('target_markets');
?>
	<input type="hidden" id="edit-billing-address-data" value="<?php
		echo esc_attr(json_encode($address));
		?>" />
	<input type="submit" id="edit-billing-address" name="edit-billing-address" value="<?php _e('Edit','Shopp'); ?>" class="button-secondary button-edit" />
</form>

<address>
<big><?php echo esc_html("{$Purchase->firstname} {$Purchase->lastname}"); ?></big><br />
<?php echo ! empty($Purchase->company)?esc_html($Purchase->company)."<br />":""; ?>
<?php echo esc_html($Purchase->address); ?><br />
<?php if ( ! empty($Purchase->xaddress) ) echo esc_html($Purchase->xaddress)."<br />"; ?>
<?php echo esc_html("{$Purchase->city}" . ( ! empty($Purchase->shipstate) ? ', ' : '') . " {$Purchase->state} {$Purchase->postcode}") ?><br />
<?php echo $targets[$Purchase->country]; ?>
</address>
<?php if ( ! empty($Customer->info) && is_array($Customer->info) ): ?>
	<ul>
		<?php foreach ( $Customer->info as $name => $value ): ?>
		<li><strong><?php echo esc_html($name); ?>:</strong> <?php echo esc_html($value); ?></li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
</div>