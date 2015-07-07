<div class="editor">
<p class="inline-fields">
	<span>
	<label for="firstname"><?php Shopp::_e('First Name'); ?></label>
	<input type="text" name="firstname" id="firstname" value="<?php echo esc_attr($Customer->firstname); ?>" />
	</span>
	<span>
	<label for="lastname"><?php Shopp::_e('Last Name'); ?></label>
	<input type="text" name="lastname" id="lastname" value="<?php echo esc_attr($Customer->lastname); ?>" />
	</span>
</p>
<p class="inline-fields">
	<label for="company"><?php Shopp::_e('Company'); ?></label>
	<input type="text" name="company" id="company" value="<?php echo esc_attr($Customer->company); ?>" />
</p>
<p class="inline-fields">
	<span>
	<label for="email"><?php Shopp::_e('Email'); ?> <em><?php Shopp::_e('(required)'); ?></em></label>
	<input type="text" name="email" id="email" value="<?php echo esc_attr($Customer->email); ?>" />
	</span>
	<span>
	<label for="phone"><?php Shopp::_e('Phone'); ?></label>
	<input type="text" name="phone" id="phone" value="<?php echo esc_attr($Customer->phone); ?>" />
	</span>
</p>
</div>