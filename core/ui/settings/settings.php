<?php echo $this->tabs(); ?>

<div class="wrap shopp">

	<div class="icon32"></div>
	<h2><?php echo $this->title(); ?></h2>

	<?php do_action('shopp_admin_notices'); ?>

	<form name="settings" id="<?php echo esc_attr($this->id); ?>" action="<?php echo esc_url($this->url()); ?>" method="post">

		<?php wp_nonce_field('shopp-setup'); ?>

		<?php do_action('shopp_settings_' . $this->id . '_screen_before'); ?>

		<?php include $this->template; ?>

		<?php do_action('shopp_settings_' . $this->id . '_screen_after'); ?>

	</form>

</div>