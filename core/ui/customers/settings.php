<p class="clearfix">
	<span>
	<input type="hidden" name="marketing" value="no" />
	<input type="checkbox" id="marketing" name="marketing" value="yes"<?php echo $Customer->marketing == 'yes'?' checked="checked"':''; ?>/>
	<label for="marketing" class="inline">&nbsp;<?php _e('Subscribes to marketing','Shopp'); ?></label>
	</span>
</p>
<p class="clearfix">
	<span>
	<label for="customer-type"><?php _e('Customer Type','Shopp'); ?></label>
	<select id="customer-type" name="type"><?php echo Shopp::menuoptions(Lookup::customer_types(),$Customer->type); ?></select>
	</span>
</p>
