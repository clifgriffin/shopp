<div id="welcome" class="wrap shopp">
	<div class="icon32"></div>
	<h2>Shopp</h2>


	<h3><?php _e('Database Upgrade Required','Shopp'); ?></h3>
	<p>Shopp has been updated!  Your storefront has been automatically switched to maintenance mode.</p>

	<p>Before you can continue, we need to upgrade your database to the newest format.</p>

	<?php if (current_user_can('activate_plugins')): ?>

		<div class="error">Be sure to backup your database to prevent a loss of data.</div>

		<p>To upgrade, you simply need to re-activate Shopp:</p>
	  	<ul>
			<li>Click the <strong>Continue&hellip;</strong> button below to de-activate Shopp</li>
			<li>In the WordPress plugin manager, click the <strong>Activate</strong> link for Shopp to re-activate and upgrade your Shopp database.</p>
		</ul>

		<div class="alignright"><br />
		<?php
			$plugin_file = basename(SHOPP_PATH).'/Shopp.php';
			$deactivate = wp_nonce_url("plugins.php?action=deactivate&amp;plugin=$plugin_file&amp;plugin_status=recent&amp;paged=1","deactivate-plugin_$plugin_file");
		?>
		<a href="<?php echo $deactivate; ?>" class="button-primary">Continue...</a>
		</div>

	<?php else: ?>
		<?php if (isset($_GET['_shopp_upgrade_notice'])): ?>
			<?php

				check_admin_referer('shopp_upgrade_notice');

				// @todo
				// $_ = array();
				// $_[] = 'From: "'.get_option('blogname').'" <'.shopp_setting('merchant_email').'>';
				// $_[] = 'To: '.$RecoveryCustomer->email;
				// $_[] = 'Subject: '.$subject;
				// $_[] = '';
				// $_[] = sprintf(__('Your new password for %s:','Shopp'),get_option('siteurl'));
				// $_[] = '';
				// if ($user_data)
				// 	$_[] = sprintf(__('Login name: %s','Shopp'), $user_data->user_login);
				// $_[] = sprintf(__('Password: %s'), $password) . "\r\n";
				// $_[] = '';
				// $_[] = __('Click here to login:').' '.shoppurl(false,'account');
				// $message = apply_filters('shopp_reset_password_message',join("\r\n",$_));

				// shopp_email()
			?>
			<h3>Upgrade Notice Sent</h3>
			<p>An upgrade notice has been sent to the site administrators.</p>
		<?php else: ?>

			<div class="error">
			<h3>Contact Your Site Administrator</h3>
			<p>You will need to contact a site administrator to perform the upgrade.</p>
			<br />
			</div>
			<div class="alignright">
			<?php
				$plugin_file = basename(SHOPP_PATH).'/Shopp.php';
				$deactivate = wp_nonce_url("plugins.php?action=deactivate&amp;plugin=$plugin_file&amp;plugin_status=recent&amp;paged=1","deactivate-plugin_$plugin_file");
			?>
			<a href="<?php echo wp_nonce_url(add_query_arg('_shopp_upgrade_notice',true),'shopp_upgrade_notice'); ?>" class="button-primary">Send Upgrade Notice &raquo</a>
			</div>

	<?php endif; endif; ?>
</div>