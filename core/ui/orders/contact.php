<?php

$screen = get_current_screen();
$pre = 'page_';
$page = substr($screen->id, strpos($screen->id, $pre) + strlen($pre));

?>
<script id="customer-s" type="text/x-jquery-tmpl">
<?php
	$s = isset($_REQUEST['s']) ? $_REQUEST['s'] : false;
	ob_start();
	if ( isset($_POST['select-customer']) && empty($s) )
		$searchurl = wp_nonce_url($this->url(),'wp_ajax_shopp_select_customer');
	else $searchurl = wp_nonce_url(add_query_arg(array('action' => 'shopp_select_customer', 'page' => $page, 'id' => $Purchase->id),admin_url('admin-ajax.php')), 'wp_ajax_shopp_select_customer');
	if ( ! isset($_POST['select-customer']) || ( isset($_POST['select-customer']) && ! empty($s) ) ) $iframe = true;
	if ( ! empty($s) ) $searchurl = add_query_arg('s', $s, $searchurl);
?>
<p class="customer-chooser">
	<select id="select-customer" name="s" placeholder="<?php _e('Search for customer&hellip;','Shopp'); ?>" data-url="<?php echo $searchurl; ?>"></select>
</p>
<?php $search = ob_get_clean(); echo $search; ?>
</script>

<script id="customer-editor" type="text/x-jquery-tmpl">
<?php ob_start(); ?>

<?php echo ShoppUI::template( $search ); ?>

<div class="editor ${action}">
	<input type="hidden" name="order-action" value="${action}" id="customer-action" />
	<input type="hidden" name="customer[customer]" value="${id}" id="customer-id" />
	<p class="inline-fields">
		<span>
		<label for="address-city"><?php _e('First Name','Shopp'); ?></label>
		<input type="text" name="customer[firstname]" id="customer-firstname" value="${firstname}" /><br />
		</span><span>
		<label for="address-city"><?php _e('Last Name','Shopp'); ?></label>
		<input type="text" name="customer[lastname]" id="customer-lastname" value="${lastname}" /><br />
		</span>
	</p>
	<p>
		<label for="address-address"><?php _e('Company','Shopp'); ?></label>
		<input type="text" name="customer[company]" id="customer-company" value="${company}" /><br />
	</p>
	<p>
		<label for="customer-email"><?php _e('Email','Shopp'); ?></label>
		<input type="text" name="customer[email]" id="customer-email" value="${email}" /><br />
	</p>
	<p>
		<label for="customer-phone"><?php _e('Phone','Shopp'); ?></label>
		<input type="text" name="customer[phone]" id="customer-phone" value="${phone}" /><br />
	</p>
	<?php if ( 'wordpress' == shopp_setting('account_system') ): ?>
	<p class="loginname">
		<label for="customer-loginname"><?php _e('Login Name','Shopp'); ?></label>
		<input type="text" name="customer[loginname]" id="customer-loginname" value="${loginname}" /><br />
	</p>
	<?php endif; ?>
	<div class="editing-controls">
		<input type="submit" id="cancel-edit-customer" name="cancel-edit-customer" value="<?php Shopp::esc_attr_e('Cancel'); ?>" class="button-secondary" />
		<input type="submit" name="save" value="<?php Shopp::esc_attr_e('Update'); ?>" class="button-primary alignright" />
	</div>
</div>
<?php
	$editcustomer = ob_get_clean();

	echo $editcustomer;

	$customer = array(
		'${action}'    => 'update-customer',
		'${id}'        => $Purchase->customer,
		'${firstname}' => $Purchase->firstname,
		'${lastname}' => $Purchase->lastname,
		'${company}' => $Purchase->company,
		'${email}' => $Purchase->email,
		'${phone}' => $Purchase->phone,
		'${login}' => 'wordpress' == shopp_setting('account_system')
	);
	$js = preg_replace('/\${([-\w]+)}/','$1',json_encode($customer));
	shopp_custom_script('orders','var customer = '.$js.';');
?>
</script>

<?php
	if ( isset($_POST['select-customer']) ) $customer = array();
	if ( isset($_REQUEST['s']) && isset($_REQUEST['select-customer']) ) {
		echo ShoppUI::template($search);
		return;
	} elseif ( empty($Purchase->customer) ) {
		echo ShoppUI::template($editcustomer);
		return;
	} elseif ( isset($_REQUEST['edit-customer'])) {
	?>
		<form action="<?php echo $this->url(array('id' => (int)$Purchase->id)); ?>" method="POST">
		<?php echo ShoppUI::template($editcustomer, $customer); ?>
		</form>
	<?php
		return;
	}
?>
<form action="<?php echo $this->url(array('id' => (int) $Purchase->id)); ?>" method="post" id="customer-editor-form"></form>
<div class="display">
	<form action="<?php echo $this->url(array('id' => $Purchase->id)); ?>" method="get">
	<?php $targets = shopp_setting('target_markets'); ?>
		<input type="hidden" id="edit-customer-data" value="<?php
			echo esc_attr(json_encode($customer));
		?>" />
		<input type="hidden" name="page" value="<?php echo $page; ?>" />
		<input type="hidden" name="id" value="<?php echo $Purchase->id; ?>" />
		<input type="submit" id="edit-customer" name="edit-customer" value="<?php _e('Edit','Shopp'); ?>" class="button-secondary button-edit" />
	</form>
<?php

$avatar = get_avatar( $Purchase->email, 64 );

$customer_url = add_query_arg(array('page' => 'shopp-customers', 'id' => $Purchase->customer), admin_url('admin.php'));
$customer_url = apply_filters('shopp_order_customer_url', $customer_url);

$email_url = 'mailto:' . $Purchase->email . '?subject=' . Shopp::__('RE: %s: Order #%s', get_bloginfo('sitename'), $Purchase->id);
$email_url = apply_filters('shopp_order_customer_email_url', $email_url);

$phone_url = 'callto:' . preg_replace('/[^\d+]/', '', $Purchase->phone);
$phone_url = apply_filters('shopp_order_customer_phone_url', $phone_url);

$accounts = shopp_setting('account_system');
$wp_user = false;

if ( 'wordpress' == $accounts ) {
	$Customer = new ShoppCustomer($Purchase->customer);
	$WPUser = get_userdata($Customer->wpuser);

	$edituser_url = add_query_arg('user_id',$Customer->wpuser,admin_url('user-edit.php'));
	$edituser_url = apply_filters('shopp_order_customer_wpuser_url',$edituser_url);
}

?>
<table>
	<tr>
		<td class="avatar"><?php echo $avatar; ?></td>
		<td><span class="fn"><?php echo esc_html("{$Purchase->firstname} {$Purchase->lastname}"); ?></span>
		<?php if ( ! empty($Purchase->company) ) echo '<br /> <div class="org">'.esc_html($Purchase->company).'</div>'; ?>
		<div class="actions"><a href="<?php echo $customer_url; ?>"><?php Shopp::_e('View'); ?></a><?php if  ( 'wordpress' == $accounts && ! empty($WPUser->user_login) ): ?> | <a href="<?php echo esc_attr($edituser_url); ?>"><span class="dashicons dashicons-admin-users"></span>&nbsp;<?php echo esc_html($WPUser->user_login); ?></a><?php endif; ?></div>
		</td>
	</tr>
	<?php if ( ! empty($Purchase->email) ) echo '<tr><td colspan="2" class="email"><span class="shoppui-envelope-alt shoppui-icons"></span><a href="'.esc_url($email_url).'">'.esc_html($Purchase->email).'</a></td></tr>'; ?>
<?php if ( ! empty($Purchase->phone) ) echo '<tr><td colspan="2" class="phone"><span class="shoppui-phone shoppui-icons"></span><a href="'.esc_attr($phone_url).'">'.esc_html($Purchase->phone).'</a></td></tr>'; ?>
</table>

</div>