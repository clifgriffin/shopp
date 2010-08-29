<?php
global $Shopp;
function billto_meta_box ($Purchase) {
?>
	<address><big><?php echo esc_html("{$Purchase->firstname} {$Purchase->lastname}"); ?></big><br />
	<?php echo esc_html($Purchase->address); ?><br />
	<?php if (!empty($Purchase->xaddress)) echo esc_html($Purchase->xaddress)."<br />"; ?>
	<?php echo esc_html("{$Purchase->city}".(!empty($Purchase->shipstate)?', ':'')." {$Purchase->state} {$Purchase->postcode}") ?><br />
	<?php echo $targets[$Purchase->country]; ?></address>
	<?php if (!empty($Customer->info) && is_array($Customer->info)): ?>
		<ul>
			<?php foreach ($Customer->info as $name => $value): ?>
			<li><strong><?php echo esc_html($name); ?>:</strong> <?php echo esc_html($value); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
<?php
}
add_meta_box('order-billing', __('Billing Address','Shopp').$Admin->boxhelp('order-manager-billing'), 'billto_meta_box', 'toplevel_page_shopp-orders', 'side', 'core');

function shipto_meta_box ($Purchase) {
?>
		<address><big><?php echo esc_html("{$Purchase->firstname} {$Purchase->lastname}"); ?></big><br />
		<?php echo !empty($Purchase->company)?esc_html($Purchase->company)."<br />":""; ?>
		<?php echo esc_html($Purchase->shipaddress); ?><br />
		<?php if (!empty($Purchase->shipxaddress)) echo esc_html($Purchase->shipxaddress)."<br />"; ?>
		<?php echo esc_html("{$Purchase->shipcity}".(!empty($Purchase->shipstate)?', ':'')." {$Purchase->shipstate} {$Purchase->shippostcode}") ?><br />
		<?php echo $targets[$Purchase->shipcountry]; ?></address>
<?php
}
if (!empty($Shopp->Purchase->shipaddress))
	add_meta_box('order-shipto', __('Shipping Address','Shopp').$Admin->boxhelp('order-manager-shipto'), 'shipto_meta_box', 'toplevel_page_shopp-orders', 'side', 'core');

function contact_meta_box ($Purchase) {
	$customer_url = add_query_arg(array('page'=>'shopp-customers','id'=>$Purchase->customer),admin_url('admin.php'));
	$customer_url = apply_filters('shopp_order_customer_url',$customer_url);
	
	$email_url = 'mailto:'.($Purchase->email).'?subject='.sprintf(__('RE: %s: Order #%s','Shopp'),get_bloginfo('sitename'),$Purchase->id);
	$email_url = apply_filters('shopp_order_customer_email_url',$email_url);
	
	$phone_url = 'callto:'.preg_replace('/[^\d+]/','',$Purchase->phone);
	$phone_url = apply_filters('shopp_order_customer_phone_url',$phone_url);
	
	$Settings =& ShoppSettings();
	$accounts = $Settings->get('account_system');
	$wp_user = false;
	if ($accounts == "wordpress") {
		$Customer = new Customer($Purchase->customer);
		$wp_user = get_userdata($Customer->wpuser);
		$edituser_url = add_query_arg('user_id',$Customer->wpuser,admin_url('user-edit.php'));
		$edituser_url = apply_filters('shopp_order_customer_wpuser_url',$edituser_url);
	} 
	

?>
	<p class="customer name"><a href="<?php echo esc_url($customer_url); ?>"><?php echo esc_html("{$Purchase->firstname} {$Purchase->lastname}"); ?></a><?php
		if ($wp_user) echo ' (<a href="'.esc_url($edituser_url).'">'.esc_html($wp_user->user_login).'</a>)';
	?></p>
	<?php echo !empty($Purchase->company)?'<p class="customer company">'.esc_html($Purchase->company).'</p>':''; ?>
	<?php echo !empty($Purchase->email)?'<p class="customer email"><a href="'.esc_url($email_url).'">'.esc_html($Purchase->email).'</a></p>':''; ?>
	<?php echo !empty($Purchase->phone)?'<p class="customer phone"><a href="'.esc_attr($phone_url).'">'.esc_html($Purchase->phone).'</a></p>':''; ?>
	<p class="customer <?php echo ($Purchase->Customer->marketing == "yes")?'marketing':'nomarketing'; ?>"><?php ($Purchase->Customer->marketing == "yes")?_e('Agreed to marketing','Shopp'):_e('No marketing','Shopp'); ?></p>
<?php
}
add_meta_box('order-contact', __('Customer Contact','Shopp').$Admin->boxhelp('order-manager-contact'), 'contact_meta_box', 'toplevel_page_shopp-orders', 'side', 'core');

function orderdata_meta_box ($Purchase) {
	$_[] = '<ul>';
	foreach ($Purchase->data as $name => $value) {
		if (empty($value)) continue;
		$classname = 'shopp_orderui_orderdata_'.sanitize_title_with_dashes($name);
		$listing = '<li class="'.$classname.'"><strong>'.$name.':</strong> <span>';
		if (strpos($value,"\n")) $listing .= '<textarea name="orderdata['.esc_attr($name).']" readonly="readonly" cols="30" rows="4">'.esc_html($value).'</textarea>';
		else $listing .= esc_html($value);
		$listing .= '</span></li>';
		$_[] = apply_filters($classname,$listing);
	}
	$_[] = '</ul>';
	echo apply_filters('shopp_orderui_orderdata',join("\n",$_));
}
if (!empty($Shopp->Purchase->data) && is_array($Shopp->Purchase->data) && join("",$Shopp->Purchase->data) != ""
		|| apply_filters('shopp_orderui_show_orderdata',false)) {
			add_meta_box('order-data', __('Details','Shopp').$Admin->boxhelp('order-manager-details'), 'orderdata_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');
		}

function transaction_meta_box ($Purchase) {
?>
<p><strong><?php _e('Processed by','Shopp'); ?> </strong><?php echo $Purchase->gateway; ?><?php echo (!empty($Purchase->txnid)?" ($Purchase->txnid)":""); ?></p>
<?php 
	$output = '';
	if (!empty($Purchase->card) && !empty($Purchase->cardtype))
		$output = '<p><strong>'.$Purchase->txnstatus.'</strong> '.
			__('to','Shopp').' '.
			(!empty($Purchase->card)?sprintf("%'X16d",$Purchase->card):'').' '.
			(!empty($Purchase->cardtype)?'('.$Purchase->cardtype.')':'').'</p>';

	echo apply_filters('shopp_orderui_payment_card',$output, $Purchase);
}
add_meta_box('order-transaction', __('Payment Method','Shopp').$Admin->boxhelp('order-manager-transaction'), 'transaction_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');

function shipping_meta_box ($Purchase) {
?>
<?php
	if (!empty($Purchase->carrier)) {
		echo '<p><strong>';
		_e('Ship via','Shopp');
		echo '</strong> '.$Purchase->carrier;
		echo "($Purchase->shipmethod)</p>";
?>
	<p><span><input type="text" id="shiptrack" name="shiptrack" size="30" value="<?php echo $Purchase->shiptrack; ?>" /><br /><label for="shiptrack"><?php _e('Tracking ID','Shopp')?></label></span></p>
		
<?php
	} else {
		echo '<p><strong>';
		_e('Shipping Method','Shopp');
		echo ':</strong> '.$Purchase->shipmethod.'</p>';
	}
?>
<?php
}
if (!empty($Shopp->Purchase->shipmethod))
	add_meta_box('order-shipping', __('Shipping','Shopp').$Admin->boxhelp('order-manager-shipping'), 'shipping_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');

function downloads_meta_box ($Purchase) {
?>
	<ul>
	<?php foreach ($Purchase->purchased as $Item): ?>
		<?php $price = new Price($Item->price); if ($price->type == 'Download'): ?>
		<li><strong><?php echo $Item->name; ?></strong>: <?php echo $Item->downloads.' '.__('Downloads','Shopp'); ?></li>
		<?php endif; ?>
	<?php endforeach; ?>
	</ul>
<?php
}
if ($Shopp->Purchase->downloads !== false)
	add_meta_box('order-downloads', __('Downloads','Shopp').$Admin->boxhelp('order-manager-downloads'), 'downloads_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');

function status_meta_box ($Purchase) {
	global $UI;
?>
<div class="inside-wrap">
<div id="notification">
	<p><label for="message"><?php _e('Message to customer:','Shopp'); ?></label>
		<textarea name="message" id="message" cols="50" rows="10" ></textarea></p>
	<p><span><input type="hidden" name="receipt" value="no" /><input type="checkbox" name="receipt" value="yes" id="include-order" checked="checked" /><label for="include-order">&nbsp;<?php _e('Include a copy of the order in the message','Shopp'); ?></label></span></p>
</div>

<p><span class="middle"><input type="hidden" name="notify" value="no" /><input type="checkbox" name="notify" value="yes" id="notify-customer" /><label for="notify-customer">&nbsp;<?php _e('Send a message to the customer','Shopp'); ?></label></span></p>

<p>
	<span>
<label for="txn_status_menu"><?php _e('Payment','Shopp'); ?>: <select name="txnstatus" id="txn_status_menu">
<?php echo menuoptions($UI->txnStatusLabels,$Purchase->txnstatus,true,true); ?>
</select></label>
&nbsp;
<label for="order_status_menu"><?php _e('Order Status','Shopp'); ?>: <select name="status" id="order_status_menu">
<?php echo menuoptions($UI->statusLabels,$Purchase->status,true); ?>
</select></label></span>
</p>
</div>
<div id="major-publishing-actions">
	<button type="submit" name="update" value="status" class="button-primary"><?php _e('Update Status','Shopp'); ?></button>
</div>
<?php
}
add_meta_box('order-status', __('Status','Shopp').$Admin->boxhelp('order-manager-status'), 'status_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core',2);

function notes_meta_box ($Purchase) {
	global $Notes;
	
	add_filter('shopp_order_note', 'esc_html');
	add_filter('shopp_order_note', 'wptexturize');
	add_filter('shopp_order_note', 'convert_chars');
	add_filter('shopp_order_note', 'make_clickable');
	add_filter('shopp_order_note', 'force_balance_tags');
	add_filter('shopp_order_note', 'convert_smilies');
	add_filter('shopp_order_note', 'wpautop');
	
?>
<?php if (!empty($Notes->meta)): ?>
<table>
	<?php foreach ($Notes->meta as $Note): $User = get_userdata($Note->value->author); ?>
	<tr>
		<th><?php echo esc_html($User->user_nicename); ?><br />
			<span><?php echo _d(get_option('date_format').' '.get_option('time_format'), $Note->created); ?></span></th>
		<td>
			<div id="note-<?php echo $Note->id; ?>">
			<?php echo apply_filters('shopp_order_note',$Note->value->message); ?>
			</div>
			<p class="notemeta">
				<span class="notectrls">
				<button type="submit" name="delete-note[<?php echo $Note->id; ?>]" value="delete" class="button-secondary deletenote"><small>Delete</small></button>
				<button type="button" name="edit-note[<?php echo $Note->id; ?>]" value="edit" class="button-secondary editnote"><small>Edit</small></button>
				<?php do_action('shopp_order_note_controls'); ?>
				</span>
			</p>
		</td>
	</tr>
	<?php endforeach; ?>
</table>
<?php endif; ?>

<div id="notation">
	<p><label for="notes"><?php _e('New Note','Shopp'); ?>:</label><br />
		<textarea name="note" id="note" cols="50" rows="10"></textarea></p>
		<?php do_action('shopp_order_new_note_ui'); ?>
		<p class="alignright">
			<button type="button" name="cancel-note" value="cancel" id="cancel-note-button" class="button-secondary"><?php _e('Cancel','Shopp'); ?></button>
			<button type="submit" name="save-note" value="save" class="button-primary"><?php _e('Save Note','Shopp'); ?></button>
			</p>
</div>
<p class="alignright" id="add-note">
	<button type="button" name="add-note" value="add" id="add-note-button" class="button-secondary"><?php _e('Add Note','Shopp'); ?></button></p>
	<br class="clear" />
<?php
}
add_meta_box('order-notes', __('Notes','Shopp').$Admin->boxhelp('order-manager-notes'), 'notes_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');

?>