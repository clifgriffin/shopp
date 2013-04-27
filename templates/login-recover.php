<?php
/**
 ** WARNING! DO NOT EDIT!
 **
 ** These templates are part of the core Shopp files
 ** and will be overwritten when upgrading Shopp.
 **
 *
 ** For editable templates, setup Shopp theme templates:
 ** http://docs.shopplugin.net/Setting_Up_Theme_Templates
 **
 **/
?>
<form action="<?php shopp('customer','url'); ?>" method="post" class="shopp" id="login">

<ul>
	<li><h3><?php _e('Recover your password','Shopp'); ?></h3></li>
	<li>
	<span><?php shopp('customer','account-login','size=20&title='.__('Login','Shopp')); ?><label for="login"><?php shopp('customer','login-label'); ?></label></span>
	<span><?php shopp('customer','recover-button'); ?></span>
	</li>
	<li></li>
</ul>

</form>
