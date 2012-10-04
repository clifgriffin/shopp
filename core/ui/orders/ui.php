<?php
function manage_meta_box ($Purchase) {
	$Gateway = $Purchase->gateway();

?>
<script id="address-editor" type="text/x-jquery-tmpl">
<?php ob_start(); ?>
<div class="misc-pub-section">

<div class="editaddress">
	<h4><big>${title}</big></h4>
	<input type="hidden" name="order-action" value="${action}" />
	<input type="hidden" name="address[type]" id="address-type" value="${type}" />
<p>
	<span>
	<input type="text" name="address[firstname]" id="address-firstname" value="${firstname}" size="20" /><br />
	<label for="address-city"><?php _e('First Name','Shopp'); ?></label>
	</span>
	<span>
	<input type="text" name="address[lastname]" id="address-lastname" value="${lastname}" size="25" /><br />
	<label for="address-city"><?php _e('Last Name','Shopp'); ?></label>
	</span>
</p>
<p>
	<input type="text" name="address[address]" id="address-address" value="${address}" /><br />
	<input type="text" name="address[xaddress]" id="address-xaddress" value="${xaddress}" /><br />
	<label for="address-address"><?php _e('Street Address','Shopp'); ?></label>
</p>
<p class="inline-fields">
	<span>
	<input type="text" name="address[city]" id="address-city" value="${city}" size="14" /><br />
	<label for="address-city"><?php _e('City','Shopp'); ?></label>
	</span>
	<span id="address-state-inputs">
		<select name="address[state]" id="address-state-menu">${statemenu}</select>
		<input type="text" name="address[state]" id="address-state" value="${state}" size="12" disabled="disabled"  class="hidden" />
	<label for="address-state"><?php _e('State / Province','Shopp'); ?></label>
	</span>
	<span>
	<input type="text" name="address[postcode]" id="address-postcode" value="${postcode}" size="10" /><br />
	<label for="address-postcode"><?php _e('Postal Code','Shopp'); ?></label>
	</span>
</p>
<p>
	<span>
		<select name="address[country]" id="address-country">${countrymenu}</select>
		<label for="address-country"><?php _e('Country','Shopp'); ?></label>
	</span>
</p>
		<input type="submit" id="cancel-edit-address" name="cancel-edit-address" value="<?php _e('Cancel','Shopp'); ?>" class="button-secondary" />
		<div class="alignright">
		<input type="submit" name="submit-address" value="<?php _e('Update Address','Shopp'); ?>" class="button-primary" />
		</div>
</div>
</div>
<?php $editaddress = ob_get_contents(); ob_end_clean(); echo $editaddress; ?>
</script>


<?php if ($Purchase->shipable): ?>
<script id="shipment-ui" type="text/x-jquery-tmpl">
<?php ob_start(); ?>
<li class="inline-fields">
	<span class="number">${num}.</span>
	<span><input type="text" name="shipment[${id}][tracking]" value="${tracking}" size="30" class="tracking" /><br />
	<label><?php _e('Tracking Number'); ?></label>
	</span>
	<span>
	<select name="shipment[${id}][carrier]">${carriermenu}</select><?php echo ShoppUI::button('delete','delete-shipment[${id}]'); ?><br />
	<label><?php _e('Carrier'); ?></label>
	</span>
</li>
<?php $shipmentui = ob_get_contents(); ob_end_clean(); echo $shipmentui; ?>
</script>

<script id="shipnotice-ui" type="text/x-jquery-tmpl">
<?php ob_start(); ?>
<div class="shipnotice misc-pub-section">
	<div class="shipment">
		<h4><big><?php _e('Shipments','Shopp'); ?></big></h4>
		<p><?php _e('An email will be sent to notify the customer.','Shopp'); ?></p>
		<input type="hidden" name="ship-notice" value="active" />
		<ol>
			${shipments}
			<li><span class="number">${shipmentnum}.</span> <input type="submit" id="addship-button" name="add-shipment" value="<?php _e('Add Shipment','Shopp'); ?>" class="button-secondary" /></li>
		</ol>

		<div class="submit">
			<input type="submit" id="cancel-ship" name="cancel-shipments" value="<?php _e('Cancel','Shopp'); ?>" class="button-secondary" />
			<div class="alignright">
			<input type="submit" name="submit-shipments" value="<?php _e('Send Shipping Notice','Shopp'); ?>" class="button-primary" />
			</div>
		</div>
	</div>
</div>
<?php $shipnotice_ui = ob_get_contents(); ob_end_clean(); echo $shipnotice_ui; ?>
</script>
<?php endif; ?>

<?php if (!$Purchase->isvoid()): ?>
<script id="refund-ui" type="text/x-jquery-tmpl">
<?php ob_start(); ?>
<div class="refund misc-pub-section">
	<div class="refunding">
		<h4><big>${title}</big></h4>
		An email will be sent to notify the customer.
		<input type="hidden" name="order-action" value="${action}" />

		<div><label for="message"><?php _e('Message to the customer','Shopp'); ?></label>
			<textarea name="message" id="message" cols="50" rows="7" ></textarea></div>

		<div class="alignright">
			<div class="inline-fields">
				<span>
				<select name="reason">
					<option>&mdash; Select &mdash;</option>
					<?php echo menuoptions(stripslashes_deep(shopp_setting('cancel_reasons')),false,true); ?>
				</select><br />
				<label>${reason}</label>
				</span>

				<span><input type="text" name="amount" value="<?php echo money($Purchase->total); ?>" ${disable_amount} /><br />
				<label><?php _e('Amount','Shopp'); ?></label></span>
			</div>
		</div>
		<div class="clear"></div>
		<div class="submit">
			<input type="submit" id="cancel-refund" name="cancel-refund" value="${cancel}" class="button-secondary" />
			<div class="alignright">
			<span class="mark-status">
				<input type="hidden" name="send" value="off" />
				<label title="<?php printf(__('Enable to process through the payment gateway (%s) and set the Shopp payment status. Disable to update only the Shopp payment status.','Shopp'),$Gateway->name); ?>"><input type="checkbox" name="send" value="on" <?php if ($Gateway && $Gateway->refunds) echo ' checked="checked"'; ?>/>&nbsp;${send}</label>
			</span>

			<input type="submit" name="process-refund" value="${process}" class="button-primary" />
			</div>
		</div>
	</div>
</div>
<?php $refundui = ob_get_contents(); ob_end_clean(); echo $refundui; ?>
</script>
<?php endif; ?>

<div class="minor-publishing">

	<div class="minor-publishing-actions headline">
	<div class="misc-pub-section controls">
	<?php
		$printurl = wp_nonce_url(admin_url('admin-ajax.php').'?action=shopp_order_receipt&amp;id='.$Purchase->id,'wp_ajax_shopp_order_receipt');
		$controls = '<div class="alignright"><a id="print-button" href="'.esc_url($printurl).'" class="button hide-if-no-js" target="_blank">'.__('Print Order','Shopp').'</a></div>';
		echo apply_filters('shopp_order_management_controls',$controls,$Purchase);
	?>
	</div>
		<div class="misc-pub-section">
			<div class="status">
			<?php
			if (isset($Purchase->txnevent)) {
				$UI = OrderEventRenderer::renderer($Purchase->txnevent);
				$event = array('<strong>'.$UI->name().'</strong>');
				if ('' != $UI->details()) $event[] = $UI->details();
				if ('' != $UI->date()) $event[] = $UI->date();
				echo '<p>'.join(' &mdash; ',$event).'</p>';
			} else { ?>
				<p><strong><?php _e('Processed by','Shopp'); ?> </strong><?php echo $Purchase->gateway; ?><?php echo (!empty($Purchase->txnid)?" ($Purchase->txnid)":""); ?></p>
				<?php
					$output = '';
					if (!empty($Purchase->card) && !empty($Purchase->cardtype))
						$output = '<p><strong>'.$Purchase->txnstatus.'</strong> '.
							__('to','Shopp').' '.
							(!empty($Purchase->cardtype)?$Purchase->cardtype:'').
							(!empty($Purchase->card)?sprintf(" (&hellip;%d)",$Purchase->card):'').'</p>';

					echo apply_filters('shopp_orderui_payment_card',$output, $Purchase);
			}

			if (isset($Purchase->shipevent)): $UI = OrderEventRenderer::renderer($Purchase->shipevent);
				echo '<p><strong>'.$UI->name().'</strong> '.$UI->details().' &mdash; '.$UI->date().'</p>';
			endif;
			?>
			</div>
		</div>
		<div class="manager-ui">
		<?php
			$action = false;
			if (isset($_POST['ship-notice']) && 'active' != $_POST['ship-notice']) $action = 'ship-notice';
			elseif (isset($_POST['edit-billing-address']) || isset($_POST['edit-shipping-address'])) $action = 'edit-address';
			elseif (isset($_POST['cancel-order']) || isset($_POST['refund-order'])) $action = 'refund-order';

			if (isset($_POST['cancel-shipments']) && 'ship-notice' == $action) $action = false;
			if (isset($_POST['cancel-refund']) && 'refund-order' == $action) $action = false;
			if ('ship-notice' == $action) {
				unset($_POST['cancel-order'],$_POST['refund-order']);
				$default = array('tracking'=>'','carrier'=>'');
				$shipment = isset($_POST['shipment'])?$_POST['shipment']:array($default);
				$shipments = (int)$_POST['shipments'];
				if (isset($_POST['delete-shipment'])) {
					$queue = array_keys($_POST['delete-shipment']);
					foreach ($queue as $index) array_splice($shipment,$index,1);
				}
				if (isset($_POST['add-shipment'])) $shipment[] = $default;

				global $carriers_menu;
				foreach ($shipment as $id => $package) {
					extract($package);
					$menu = menuoptions($carriers_menu,$carrier,true);
					$shipmentuis = ShoppUI::template($shipmentui, array('${id}' => $id,'${num}' => ($id+1),'${tracking}'=>$tracking,'${carriermenu}'=>$menu ));
				}
				echo ShoppUI::template($shipnotice_ui,array('${shipments}'=>$shipmentuis,'${shipmentnum}'=>count($shipment)+1));
			}

			if ('refund-order' == $action) {
				$data = array(
					'${action}' => 'refund',
					'${title}' => __('Refund Order','Shopp'),
					'${reason}' => __('Reason for refund','Shopp'),
					'${send}' => __('Send to gateway','Shopp'),
					'${cancel}' => __('Cancel Refund','Shopp'),
					'${process}' => __('Process Refund','Shopp')
				);

				if (isset($_POST['cancel-order'])) {
					$data = array(
						'${action}' => 'cancel',
						'${disable_amount}' =>  ' disabled="disabled"',
						'${title}' => __('Cancel Order','Shopp'),
						'${reason}' => __('Reason for cancellation','Shopp'),
						'${send}' => __('Send to gateway','Shopp'),
						'${cancel}' => __('Do Not Cancel','Shopp'),
						'${process}' => __('Cancel Order','Shopp')
					);
				}

				echo ShoppUI::template($refundui,$data);
			}

			if ('edit-address' == $action) {
				if ( isset($_POST['edit-billing-address']) ) {
					$data = array(
						'${type}' => 'billing',
						'${title}' => __('Edit Billing Address','Shopp'),
						'${firstname}' => $Purchase->firstname,
						'${lastname}' => $Purchase->lastname,
						'${address}' => $Purchase->address,
						'${xaddress}' => $Purchase->xaddress,
						'${city}' => $Purchase->city,
						'${state}' => $Purchase->state,
						'${postcode}' => $Purchase->postcode,
					);
					$data['${statemenu}'] = menuoptions($Purchase->_billing_states,$Purchase->state,true);
					$data['${countrymenu}'] = menuoptions($Purchase->_countries,$Purchase->country,true);
				}

				if ( isset($_POST['edit-shipping-address']) ) {
					$shipname = explode(' ',$Purchase->shipname);
					$shipfirst = array_shift($shipname);
					$shiplast = join(' ',$shipname);
					$data = array(
						'${type}' => 'shipping',
						'${title}' => __('Edit Shipping Address','Shopp'),
						'${firstname}' => $shipfirst,
						'${lastname}' => $shiplast,
						'${address}' => $Purchase->shipaddress,
						'${xaddress}' => $Purchase->shipxaddress,
						'${city}' => $Purchase->shipcity,
						'${state}' => $Purchase->shipstate,
						'${postcode}' => $Purchase->shippostcode,
					);

					$data['${statemenu}'] = menuoptions($Purchase->_shipping_states,$Purchase->shipstate,true);
					$data['${countrymenu}'] = menuoptions($Purchase->_countries,$Purchase->shipcountry,true);
				}
				$data['${action}'] = 'update-address';
				echo ShoppUI::template($editaddress,$data);
			}
		?>
		</div>
	</div>
</div>
<?php if (!($Purchase->isvoid() && $Purchase->refunded)): ?>
	<div id="major-publishing-actions">
		<?php if (!$Purchase->isvoid()): ?>
		<div class="alignleft">
			<?php if (!$Purchase->captured): ?>
				<input type="submit" id="cancel-order" name="cancel-order" value="<?php _e('Cancel Order','Shopp'); ?>" class="button-secondary cancel" />
			<?php endif; ?>
			<?php
			if ( ('CHARGED' == $Purchase->txnstatus) || ($Purchase->authorized && $Purchase->captured && $Purchase->refunded < $Purchase->total) ): ?>
				<input type="submit" id="refund-button" name="refund-order" value="<?php _e('Refund','Shopp'); ?>" class="button-secondary refund" />
			<?php endif; ?>
		</div>
		<?php endif; ?>
		&nbsp;
		<?php if ( $Purchase->authorized ): ?>
			<?php if ( $Purchase->shipable && 'ship-notice' != $action && is_array(shopp_setting('shipping_carriers')) ): ?>
			<input type="submit" id="shipnote-button" name="ship-notice" value="<?php _e('Send Shipment Notice','Shopp'); ?>" class="button-primary" />
			<?php endif; ?>
			<?php if ( ! $Purchase->captured && $Gateway && $Gateway->captures ): ?>
			<input type="submit" name="charge" value="<?php _e('Charge Order','Shopp'); ?>" class="button-primary" />
			<?php endif; ?>
		<?php endif; ?>
	</div>
<?php endif; ?>
<?php
}
add_meta_box('order-manage', __('Management','Shopp').$Admin->boxhelp('order-manager-manage'), 'manage_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core',2);

function billto_meta_box ($Purchase) {
	$targets = shopp_setting('target_markets');
?>
	<div class="alignright">
	<input type="hidden" id="edit-billing-address-data" value="<?php
		$address = array(
			'action' => 'update-address',
			'type' => 'billing',
			'firstname' => $Purchase->firstname,
			'lastname' => $Purchase->lastname,
			'address' => $Purchase->address,
			'xaddress' => $Purchase->xaddress,
			'city' => $Purchase->city,
			'state' => $Purchase->state,
			'postcode' => $Purchase->postcode,
			'country' => $Purchase->country,
			'statemenu' => menuoptions($Purchase->_billing_states,$Purchase->state,true),
			'countrymenu' => menuoptions($Purchase->_countries,$Purchase->country,true)
		);
		echo esc_attr(json_encode($address));
	?>" />
	<input type="submit" id="edit-billing-address" name="edit-billing-address" value="<?php _e('Edit','Shopp'); ?>" class="button-secondary" />
	</div>
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
	$targets = shopp_setting('target_markets');
?>
		<div class="alignright">
		<input type="hidden" id="edit-shipping-address-data" value="<?php
			$shipname = explode(' ',$Purchase->shipname);
			$shipfirst = array_shift($shipname);
			$shiplast = join(' ',$shipname);
			$address = array(
				'action' => 'update-address',
				'type' => 'shipping',
				'firstname' => $shipfirst,
				'lastname' => $shiplast,
				'address' => $Purchase->shipaddress,
				'xaddress' => $Purchase->shipxaddress,
				'city' => $Purchase->shipcity,
				'state' => $Purchase->shipstate,
				'postcode' => $Purchase->shippostcode,
				'country' => $Purchase->shipcountry,
				'statemenu' => menuoptions($Purchase->_shipping_states,$Purchase->shipstate,true),
				'countrymenu' => menuoptions($Purchase->_countries,$Purchase->shipcountry,true)

			);
			echo esc_attr(json_encode($address));
		?>" />
		<input type="submit" id="edit-shipping-address" name="edit-shipping-address" value="<?php _e('Edit','Shopp'); ?>" class="button-secondary" />
		</div>

		<address><big><?php echo esc_html($Purchase->shipname); ?></big><br />
		<?php echo !empty($Purchase->company)?esc_html($Purchase->company)."<br />":""; ?>
		<?php echo esc_html($Purchase->shipaddress); ?><br />
		<?php if (!empty($Purchase->shipxaddress)) echo esc_html($Purchase->shipxaddress)."<br />"; ?>
		<?php echo esc_html("{$Purchase->shipcity}".(!empty($Purchase->shipstate)?', ':'')." {$Purchase->shipstate} {$Purchase->shippostcode}") ?><br />
		<?php echo $targets[$Purchase->shipcountry]; ?></address>
<?php
}
if (!empty(ShoppPurchase()->shipaddress))
	add_meta_box('order-shipto', __('Shipping Address','Shopp').$Admin->boxhelp('order-manager-shipto'), 'shipto_meta_box', 'toplevel_page_shopp-orders', 'side', 'core');

function contact_meta_box ($Purchase) {
	$customer_url = add_query_arg(array('page'=>'shopp-customers','id'=>$Purchase->customer),admin_url('admin.php'));
	$customer_url = apply_filters('shopp_order_customer_url',$customer_url);

	$email_url = 'mailto:'.($Purchase->email).'?subject='.sprintf(__('RE: %s: Order #%s','Shopp'),get_bloginfo('sitename'),$Purchase->id);
	$email_url = apply_filters('shopp_order_customer_email_url',$email_url);

	$phone_url = 'callto:'.preg_replace('/[^\d+]/','',$Purchase->phone);
	$phone_url = apply_filters('shopp_order_customer_phone_url',$phone_url);

	$accounts = shopp_setting('account_system');
	$wp_user = false;
	if ($accounts == "wordpress") {
		$Customer = new Customer($Purchase->customer);
		$wp_user = get_userdata($Customer->wpuser);
		$avatar = get_avatar( $Customer->wpuser, 16 );

		$edituser_url = add_query_arg('user_id',$Customer->wpuser,admin_url('user-edit.php'));
		$edituser_url = apply_filters('shopp_order_customer_wpuser_url',$edituser_url);
	}

?>
	<p class="customer name"><a href="<?php echo esc_url($customer_url); ?>"><?php echo esc_html("{$Purchase->firstname} {$Purchase->lastname}"); ?></a></p>
	<p class="user name"><span class="avatar"><?php echo $avatar; ?></span><a href="<?php echo esc_url($edituser_url); ?>"><?php echo esc_html($wp_user->user_login); ?></a></p>
	<?php echo !empty($Purchase->company)?'<p class="customer company">'.esc_html($Purchase->company).'</p>':''; ?>
	<?php echo !empty($Purchase->email)?'<p class="customer email"><a href="'.esc_url($email_url).'">'.esc_html($Purchase->email).'</a></p>':''; ?>
	<?php echo !empty($Purchase->phone)?'<p class="customer phone"><a href="'.esc_attr($phone_url).'">'.esc_html($Purchase->phone).'</a></p>':''; ?>
	<p class="customer <?php echo ($Purchase->Customer->marketing == "yes")?'marketing':'nomarketing'; ?>"><?php ($Purchase->Customer->marketing == "yes")?_e('Agreed to marketing','Shopp'):_e('No marketing','Shopp'); ?></p>
<?php
}
add_meta_box('order-contact', __('Customer','Shopp').$Admin->boxhelp('order-manager-contact'), 'contact_meta_box', 'toplevel_page_shopp-orders', 'side', 'core');

function orderdata_meta_box ($Purchase) {
	$_[] = '<ul>';
	foreach ($Purchase->data as $name => $value) {
		if (empty($value)) continue;
		$classname = 'shopp_orderui_orderdata_'.sanitize_title_with_dashes($name);
		$listing = '<li class="'.$classname.'"><strong>'.$name.':</strong> <span>';
		if (is_string($value) && strpos($value,"\n")) $listing .= '<textarea name="orderdata['.esc_attr($name).']" readonly="readonly" cols="30" rows="4">'.esc_html($value).'</textarea>';
		else $listing .= esc_html($value);
		$listing .= '</span></li>';
		$_[] = apply_filters($classname,$listing);
	}
	$_[] = '</ul>';
	echo apply_filters('shopp_orderui_orderdata',join("\n",$_));
}
if (!empty(ShoppPurchase()->data) && is_array(ShoppPurchase()->data) && join("",ShoppPurchase()->data) != ""
		|| apply_filters('shopp_orderui_show_orderdata',false)) {
			add_meta_box('order-data', __('Details','Shopp').$Admin->boxhelp('order-manager-details'), 'orderdata_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');
		}

function history_meta_box ($Purchase) {
	echo '<table class="widefat history">';
	echo '<tfoot>';
	echo '<tr class="balance"><td colspan="3">'.__('Order Balance','Shopp').'</td><td>'.money($Purchase->balance).'</td></tr>';
	echo '</tfoot>';
	echo '<tbody>';
	foreach ($Purchase->events as $id => $Event)
		echo apply_filters('shopp_order_manager_event',$Event);
	echo '</tbody>';
	echo '</table>';
}
if (count(ShoppPurchase()->events) > 0)
	add_meta_box('order-history', __('Order History','Shopp').$Admin->boxhelp('order-manager-history'), 'history_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');

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
if (ShoppPurchase()->downloads !== false)
	add_meta_box('order-downloads', __('Downloads','Shopp').$Admin->boxhelp('order-manager-downloads'), 'downloads_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');

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
		<th class="column-author column-username"><?php echo get_avatar($User->ID,32); ?>
			<?php echo esc_html($User->display_name); ?><br />
			<span><?php echo _d(get_option('date_format'), $Note->created); ?></span>
			<span><?php echo _d(get_option('time_format'), $Note->created); ?></span></th>
		<td>
			<div id="note-<?php echo $Note->id; ?>">
			<?php if($Note->value->sent == 1): ?>
				<p class="notesent"><?php _e('Sent to the Customer:','Shopp'); ?> </p>
			<?php endif; ?>
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
	<div class="alignright options">
		<input type="checkbox" name="send-note" id="send-note" value="1">
		<label for="send-note"><?php _e('Send to customer','Shopp'); ?></label>
	</div>
</div>
<p class="alignright" id="add-note">
	<button type="button" name="add-note" value="add" id="add-note-button" class="button-secondary"><?php _e('Add Note','Shopp'); ?></button></p>
	<br class="clear" />
<?php
}
add_meta_box('order-notes', __('Notes','Shopp').$Admin->boxhelp('order-manager-notes'), 'notes_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');

?>