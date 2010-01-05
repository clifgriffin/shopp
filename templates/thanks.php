<?php
/** 
 ** WARNING! DO NOT EDIT!
 **
 ** These templates are part of the core Shopp files 
 ** and will be overwritten when upgrading Shopp.
 **
 ** For editable templates, setup Shopp theme templates:
 ** http://docs.shopplugin.net/Setting_Up_Theme_Templates
 **
 **/
?>
<h3>Thank you for your order!</h3>

<?php if (shopp('purchase','notpaid')): ?> 
	<p>Your order has been received but the payment has not yet completed processing.</p>

	<?php if (shopp('purchase','hasdownloads')): ?> 
	<p>The download links on your order receipt will not work until the payment is received.</p>
	<?php endif; ?>

	<?php if (shopp('purchase','hasfreight')): ?> 
	<p>Your items will not ship out until the payment is received.</p>
	<?php endif; ?>

<?php endif; ?>

<?php shopp('checkout','receipt'); ?>

<?php if (shopp('customer','wpuser-created')): ?>
	<p>An email was sent with account login information to the email address provided for your order.  You can <a href="<?php shopp('customer','url'); ?>">login to your account</a> to access your orders, change your password and manage your checkout information.</p>
<?php endif; ?>