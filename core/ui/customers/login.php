<?php if ( 'wordpress' == shopp_setting('account_system') ): ?>
<div class="alignleft avatar">
	<?php if ( $Customer->wpuser > 0 ): ?><a href="<?php echo esc_url($userlink); ?>"><?php endif; ?>
	<?php echo $avatar; ?><?php if ( $Customer->wpuser > 0 ):?></a><?php endif; ?>
</div>
<p class="clearfix">
	<span>
	<label for="userlogin"><?php Shopp::_e('WordPress Login'); ?></label>
	<input type="hidden" name="userid" id="userid" value="<?php echo esc_attr($Customer->wpuser); ?>" />
	<input type="text" name="userlogin" id="userlogin" value="<?php echo esc_attr($wp_user->user_login); ?>" size="20" class="selectall" /><br />
	</span>
<?php endif; ?>

<p class="clearfix">
	<label for="new-password"><?php Shopp::_e('New Password'); ?></label>
	<input type="password" name="new-password" id="new-password" value="" size="20" class="selectall" /><br />
</p>
<p class="clearfix">
	<label for="confirm-password"><?php Shopp::_e('Confirm Password'); ?></label>
	<input type="password" name="confirm-password" id="confirm-password" value="" size="20" class="selectall" /><br />
</p>
<div id="pass-strength-result"><?php _e('Strength indicator'); ?></div>
<br class="clear" />