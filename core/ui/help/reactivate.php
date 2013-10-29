<div id="welcome" class="wrap shopp">
	<div class="icon32"></div>
	<h2><?php Shopp::_emi('Shopp Database **Upgrade Required**'); ?></h2>

	<?php Shopp::_em(
'Shopp has been updated!

Before you can use the new version of Shopp, your database needs to be upgraded.

Your storefront has been switched to maintenance mode until the database upgrade is completed.'); ?>

	<?php if ( current_user_can('activate_plugins') ): ?>

		<div class="error"><p><?php Shopp::_em('**IMPORTANT:** Be sure to backup your database to prevent a loss of data! [How do I backup?](%s)', ShoppSupport::DOCS . 'getting-started/upgrading/'); ?></p></div>

		<?php Shopp::_em(
'To upgrade, you simply need to reactivate Shopp:

- Click the **Continue&hellip;** button below to deactivate Shopp
- In the WordPress **Plugins** manager, click the **Activate** link for Shopp to reactivate and upgrade the Shopp database'); ?>
		<?php
			$plugin_file = SHOPP_PLUGINFILE;
			$deactivate = wp_nonce_url("plugins.php?action=deactivate&amp;plugin=$plugin_file&amp;s=Shopp&amp;paged=1", "deactivate-plugin_$plugin_file");
		?>
		<p><a href="<?php echo $deactivate; ?>" class="button-primary"><?php Shopp::_e('Continue&hellip;'); ?></a></p>

	<?php else: ?>
		<?php if ( isset($_GET['_shopp_upgrade_notice']) ): ?>
			<?php

				check_admin_referer('shopp_upgrade_notice');
				$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
				$homeurl = wp_specialchars_decode(get_option('home'), ENT_QUOTES);
				$admin = get_bloginfo('admin_email');
				$site = parse_url($homeurl);

				$_ = array();
				$_[] = 'From: "' . $blogname . '" <' . shopp_setting('merchant_email') . '>';
				$_[] = 'To: ' . $admin;
				$_[] = sprintf('Subject: Shopp Upgraded on %s', $site['host']);
				$_[] = '';
				$_[] = sprintf(__('The Shopp installation on %1$s has been upgraded to %2$s and requires a database upgrade. Please login to %1$s and perform the upgrade by deactivating and reactivating the Shopp plugin.', 'Shopp'), $homeurl, ShoppVersion::release());

				$message = apply_filters('shopp_upgrade_notice_message', join("\n", $_));

				if ( Shopp::email($message) )
					shopp_debug('A Shopp upgrade notification was sent.');

				Shopp::_em(
'### Upgrade Notice Sent

An upgrade notice has been sent to the site administrator.'); ?>
		<?php else: ?>

			<div class="error"><?php Shopp::_em(
'### Contact Your Site Administrator

You will need to notify a site administrator to perform the upgrade.'); ?>
			</div>
			<div class="alignright">
			<?php
				$plugin_file = basename(SHOPP_PATH).'/Shopp.php';
				$deactivate = wp_nonce_url("plugins.php?action=deactivate&amp;plugin=$plugin_file&amp;plugin_status=recent&amp;paged=1","deactivate-plugin_$plugin_file");
			?>
			<a href="<?php echo wp_nonce_url(add_query_arg('_shopp_upgrade_notice',true),'shopp_upgrade_notice'); ?>" class="button-primary"><span class="shoppui-envelope-alt">&nbsp;</span> <?php Shopp::_e('Send Upgrade Notice'); ?></a>
			</div>

	<?php endif; endif; ?>
</div>