<?php if (shopp('customer','accounts','return=true') == "none"): ?>
	<?php shopp('customer','order-lookup'); ?>
<?php return; endif; ?>

<form action="<?php shopp('customer','url'); ?>" method="post" class="shopp" id="login">
<ul>
	<?php if (shopp('customer','notloggedin')): ?>
	<li><?php shopp('customer','login-errors'); ?></li>
	<li>
		<label for="login">Account Login</label>
		<span><?php shopp('customer','email-login','size=20&title=Login'); ?><label for="login">Email Address</label></span>
		<span><?php shopp('customer','password-login','size=20&title=Password'); ?><label for="password">Password</label></span>
		<span><?php shopp('customer','submit-login','value=Login'); ?></span>
	</li>
	<li></li>
	<?php endif; ?>
	
</ul>
</form>
