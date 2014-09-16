<div class="editor">
<p class="inline-fields">
	<span>
	<input type="text" name="${type}[firstname]" id="${type}-firstname" value="${firstname}" /><br />
	<label for="address-city"><?php _e('First Name','Shopp'); ?></label>
	</span>
	<span>
	<input type="text" name="${type}[lastname]" id="${type}-lastname" value="${lastname}" /><br />
	<label for="address-city"><?php _e('Last Name','Shopp'); ?></label>
	</span>
</p>
<p>
	<input type="text" name="${type}[address]" id="${type}-address" value="${address}" /><br />
	<input type="text" name="${type}[xaddress]" id="${type}-xaddress" value="${xaddress}" /><br />
	<label for="address-address"><?php _e('Street Address','Shopp'); ?></label>
</p>
<p class="inline-fields">
	<span>
	<input type="text" name="${type}[city]" id="${type}-city" value="${city}" size="14" /><br />
	<label for="address-city"><?php _e('City','Shopp'); ?></label>
	</span>
	<span id="${type}-state-inputs">
		<select name="${type}[state]" id="${type}-state-menu">${statemenu}</select>
		<input type="text" name="${type}[state]" id="${type}-state" value="${state}" size="12" disabled="disabled"  class="hidden" />
	<label for="address-state"><?php _e('State / Province','Shopp'); ?></label>
	</span>
	<span>
	<input type="text" name="${type}[postcode]" id="${type}-postcode" value="${postcode}" size="10" /><br />
	<label for="address-postcode"><?php _e('Postal Code','Shopp'); ?></label>
	</span>
	<span>
		<select name="${type}[country]" id="${type}-country">${countrymenu}</select>
		<label for="address-country"><?php _e('Country','Shopp'); ?></label>
	</span>
</p>
	<input type="submit" id="cancel-edit-address" name="cancel-edit-address" value="<?php Shopp::_e('Cancel'); ?>" class="button-secondary" />
	<div class="alignright">
	<input type="submit" name="submit-address" value="<?php Shopp::_e('Update'); ?>" class="button-primary" />
	</div>
</div>
