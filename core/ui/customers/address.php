<div class="editing-controls alignright">
	<input type="button" id="load-${type}-address" name="load-${type}-customer" value="<?php Shopp::_e('Copy from %s', 'shipping' == $type ? Shopp::__('Billing') : Shopp::__('Shipping')); ?>" class="button button-secondary button-edit">
</div>
<p>
	<label for="address-address"><?php Shopp::_e('Street Address'); ?></label>
	<input type="text" name="${type}[address]" id="${type}-address" value="${address}" /><br />
	<input type="text" name="${type}[xaddress]" id="${type}-xaddress" value="${xaddress}" /><br />
</p>
<p class="inline-fields">
	<span class="">
	<label for="${type}-city"><?php Shopp::_e('City'); ?></label>
	<input type="text" name="${type}[city]" id="${type}-city" value="${city}" size="14" /><br />
	</span>
	<span id="${type}-state-inputs">
		<label for="${type}-state"><?php Shopp::_e('State / Province'); ?></label>
		<select name="${type}[state]" id="${type}-state-menu">${statemenu}</select>
		<input type="text" name="${type}[state]" id="${type}-state" value="${state}" size="12" disabled="disabled"  class="hidden" />
	</span>
</p>
<p class="inline-fields">
	<span>
	<label for="${type}-postcode"><?php Shopp::_e('Postal Code'); ?></label>
	<input type="text" name="${type}[postcode]" id="${type}-postcode" value="${postcode}" size="10" /><br />
	</span>
	<span>
		<label for="${type}-country"><?php Shopp::_e('Country'); ?></label>
		<select name="${type}[country]" id="${type}-country">
		<?php echo Shopp::menuoptions($Customer->_countries, ( 'billing' == $type ? $Customer->Billing->country : $Customer->Shipping->country ), true); ?>
		</select>
	</span>
</p>
