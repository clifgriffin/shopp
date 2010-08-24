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
<?php if (!shopp('customer','process','return=true')): ?>
<?php if(shopp('customer','errors-exist')) shopp('customer','errors'); ?>

<ul class="shopp account">
<?php while (shopp('customer','menu')): ?>
	<li><h3><a href="<?php shopp('customer','management','url'); ?>"><?php shopp('customer','management'); ?></a></h3></li>
<?php endwhile; ?>
</ul>

<?php return true; endif; ?>

<form action="<?php shopp('customer','action'); ?>" method="post" class="shopp validate" autocomplete="off">

<?php if ("account" == shopp('customer','process','return=true')): ?>
	<?php if(shopp('customer','errors-exist')) shopp('customer','errors'); ?>
	<?php if(shopp('customer','password-changed')): ?>
	<div class="notice"><?php _e('Your password has been changed successfully.','Shopp'); ?></div>
	<?php endif; ?>
	<?php if(shopp('customer','profile-saved')): ?>
	<div class="notice"><?php _e('Your account has been updated.','Shopp'); ?></div>
	<?php endif; ?>
	
	
	<p><a href="<?php shopp('customer','url'); ?>">&laquo; <?php _e('Return to Account Management','Shopp'); ?></a></p>
	<ul>
		<li>
			<label for="firstname"><?php _e('Your Account','Shopp'); ?></label>
			<span><?php shopp('customer','firstname','required=true&minlength=2&size=8&title='.__('First Name','Shopp')); ?><label for="firstname"><?php _e('First','Shopp'); ?></label></span>
			<span><?php shopp('customer','lastname','required=true&minlength=3&size=14&title='.__('Last Name','Shopp')); ?><label for="lastname"><?php _e('Last','Shopp'); ?></label></span>
		</li>
		<li>
			<span><?php shopp('customer','company','size=20&title='.__('Company','Shopp')); ?><label for="company"><?php _e('Company','Shopp'); ?></label></span>
		</li>
		<li>
			<span><?php shopp('customer','phone','format=phone&size=15&title='.__('Phone','Shopp')); ?><label for="phone"><?php _e('Phone','Shopp'); ?></label></span>
		</li>
		<li>
			<span><?php shopp('customer','email','required=true&format=email&size=30&title='.__('Email','Shopp')); ?>
			<label for="email"><?php _e('Email','Shopp'); ?></label></span>
		</li>
		<li>
			<div class="inline"><label for="marketing"><?php shopp('customer','marketing','title='.__('I would like to continue receiving e-mail updates and special offers!','Shopp')); ?> <?php _e('I would like to continue receiving e-mail updates and special offers!','Shopp'); ?></label></div>
		</li>
		<?php while (shopp('customer','hasinfo')): ?>
		<li>
			<span><?php shopp('customer','info'); ?>
			<label><?php shopp('customer','info','mode=name'); ?></label></span>
		</li>
		<?php endwhile; ?>
		<li>
			<label for="password"><?php _e('Change Your Password','Shopp'); ?></label>
			<span><?php shopp('customer','password','size=14&title='.__('New Password','Shopp')); ?><label for="password"><?php _e('New Password','Shopp'); ?></label></span>
			<span><?php shopp('customer','confirm-password','&size=14&title='.__('Confirm Password','Shopp')); ?><label for="confirm-password"><?php _e('Confirm Password','Shopp'); ?></label></span>
		</li>
	</ul>	
	<p><?php shopp('customer','save-button','label='.__('Save','Shopp')); ?></p>
	<p><a href="<?php shopp('customer','url'); ?>">&laquo; <?php _e('Return to Account Management','Shopp'); ?></a></p>
	
<?php endif; // end account ?>

<?php if ("downloads" == shopp('customer','process','return=true')): ?>
	
	<h3><?php _e('Downloads','Shopp'); ?></h3>
	<p><a href="<?php shopp('customer','url'); ?>">&laquo; <?php _e('Return to Account Management','Shopp'); ?></a></p>
	<?php if (shopp('customer','has-downloads')): ?>
	<table cellspacing="0" cellpadding="0">
		<thead>
			<tr>
				<th scope="col"><?php _e('Product','Shopp'); ?></th>
				<th scope="col"><?php _e('Order','Shopp'); ?></th>
				<th scope="col"><?php _e('Amount','Shopp'); ?></th>
			</tr>
		</thead>
		<?php while(shopp('customer','downloads')): ?>
		<tr>
			<td><?php shopp('customer','download','name'); ?> <?php shopp('customer','download','variation'); ?><br />
				<small><a href="<?php shopp('customer','download','url'); ?>"><?php _e('Download File','Shopp'); ?></a> (<?php shopp('customer','download','size'); ?>)</small></td>
			<td><?php shopp('customer','download','purchase'); ?><br />
				<small><?php shopp('customer','download','date'); ?></small></td>
			<td><?php shopp('customer','download','total'); ?><br />
				<small><?php shopp('customer','download','downloads'); ?> <?php _e('Downloads','Shopp'); ?></small></td>
		</tr>
		<?php endwhile; ?>
	</table>
	<?php else: ?>
	<p><?php _e('You have no digital product downloads available.','Shopp'); ?></p>
	<?php endif; // end 'has-downloads' ?>
	<p><a href="<?php shopp('customer','url'); ?>">&laquo; <?php _e('Return to Account Management','Shopp'); ?></a></p>

<?php endif; // end downloads ?>

<?php if ("history" == shopp('customer','process','return=true')): ?>
	<?php if (shopp('customer','has-purchases')): ?>
		<p><a href="<?php shopp('customer','url'); ?>">&laquo; <?php _e('Return to Account Management','Shopp'); ?></a></p>
		<table cellspacing="0" cellpadding="0">
			<thead>
				<tr>
					<th scope="col"><?php _e('Date','Shopp'); ?></th>
					<th scope="col"><?php _e('Order','Shopp'); ?></th>
					<th scope="col"><?php _e('Status','Shopp'); ?></th>
					<th scope="col"><?php _e('Total','Shopp'); ?></th>
				</tr>
			</thead>
			<?php while(shopp('customer','purchases')): ?>
			<tr>
				<td><?php shopp('purchase','date'); ?></td>
				<td><?php shopp('purchase','id'); ?></td>
				<td><?php shopp('purchase','status'); ?></td>
				<td><?php shopp('purchase','total'); ?></td>
				<td><a href="<?php shopp('customer','order'); ?>"><?php _e('View Order','Shopp'); ?></a></td>
			</tr>
			<?php endwhile; ?>
		</table>
		<p><a href="<?php shopp('customer','url'); ?>">&laquo; <?php _e('Return to Account Management','Shopp'); ?></a></p>
	<?php else: ?>
	<p><?php _e('You have no orders, yet.','Shopp'); ?></p>
	<?php endif; // end 'has-purchases' ?>
	
<?php endif; // end history ?>

<?php if ("order" == shopp('customer','process','return=true')): ?>
	<p><a href="<?php shopp('customer','url'); ?>">&laquo; <?php _e('Return to Account Management','Shopp'); ?></a></p>

	<?php shopp('purchase','receipt'); ?>
	
	<p><a href="<?php shopp('customer','url'); ?>">&laquo; <?php _e('Return to Account Management','Shopp'); ?></a></p>
<?php endif; ?>

</form>
