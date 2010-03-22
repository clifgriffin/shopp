<?php
global $Shopp;
function billto_meta_box ($Purchase) {
?>
	<address><big><?php echo "{$Purchase->firstname} {$Purchase->lastname}"; ?></big><br />
	<?php echo $Purchase->address; ?><br />
	<?php if (!empty($Purchase->xaddress)) echo $Purchase->xaddress."<br />"; ?>
	<?php echo "{$Purchase->city}".(!empty($Purchase->shipstate)?', ':'')." {$Purchase->state} {$Purchase->postcode}" ?><br />
	<?php echo $targets[$Purchase->country]; ?></address>
	<?php if (!empty($Customer->info) && is_array($Customer->info)): ?>
		<ul>
			<?php foreach ($Customer->info as $name => $value): ?>
			<li><strong><?php echo $name; ?>:</strong> <?php echo $value; ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
<?php
}
add_meta_box('order-billing', __('Billing Address','Shopp'), 'billto_meta_box', 'toplevel_page_shopp-orders', 'side', 'core');

function shipto_meta_box ($Purchase) {
?>
		<address><big><?php echo "{$Purchase->firstname} {$Purchase->lastname}"; ?></big><br />
		<?php echo !empty($Purchase->company)?"$Purchase->company<br />":""; ?>
		<?php echo $Purchase->shipaddress; ?><br />
		<?php if (!empty($Purchase->shipxaddress)) echo $Purchase->shipxaddress."<br />"; ?>
		<?php echo "{$Purchase->shipcity}".(!empty($Purchase->shipstate)?', ':'')." {$Purchase->shipstate} {$Purchase->shippostcode}" ?><br />
		<?php echo $targets[$Purchase->shipcountry]; ?></address>
<?php
}
if (!empty($Shopp->Purchase->shipaddress))
	add_meta_box('order-shipto', __('Shipping Address','Shopp'), 'shipto_meta_box', 'toplevel_page_shopp-orders', 'side', 'core');

function contact_meta_box ($Purchase) {
?>
	<p class="customer name"><?php echo "{$Purchase->firstname} {$Purchase->lastname}"; ?></p>
	<?php echo !empty($Purchase->company)?'<p class="customer company">'.$Purchase->company.'</p>':''; ?>
	<?php echo !empty($Purchase->email)?'<p class="customer email"><a href="mailto:'.$Purchase->email.'">'.$Purchase->email.'</a></p>':''; ?>
	<?php echo !empty($Purchase->phone)?'<p class="customer phone">'.$Purchase->phone.'</p>':''; ?>
	<p class="customer <?php echo ($Purchase->Customer->marketing == "on")?'marketing':'nomarketing'; ?>"><?php ($Purchase->Customer->marketing == "on")?_e('Agreed to marketing','Shopp'):_e('No marketing','Shopp'); ?></p>
<?php
}
add_meta_box('order-contact', __('Customer Contact','Shopp'), 'contact_meta_box', 'toplevel_page_shopp-orders', 'side', 'core');

function orderdata_meta_box ($Purchase) {
?>
	<ul>
	<?php foreach ($Purchase->data as $name => $value): ?>
	<?php if (empty($value)) continue; ?>
	<li><strong><?php echo $name; ?>:</strong><span><?php if (strpos($value,"\n")): ?><textarea name="orderdata[<?php echo $name; ?>]" readonly="readonly" cols="30" rows="4"><?php echo $value; ?></textarea><?php else: echo $value; endif; ?></span></li>
	<?php endforeach; ?>
	</ul>
<?php
}
if (!empty($Shopp->Purchase->data) && is_array($Shopp->Purchase->data) && join("",$Shopp->Purchase->data) != "")
	add_meta_box('order-data', __('Details','Shopp'), 'orderdata_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');

function transaction_meta_box ($Purchase) {
?>
<p><strong><?php _e('Processed by','Shopp'); ?> </strong><?php echo $Purchase->gateway; ?><?php echo (!empty($Purchase->txnid)?" ($Purchase->txnid)":""); ?></p>
<?php if($Purchase->secured): ?>
<ul>
	<li><strong><?php _e('Secured Card','Shopp'); ?>:</strong> <span id="card" title="<?php _e('Click here to decrypt the card details&hellip;','Shopp'); ?>"><?php _e('[ENCRYPTED]','Shopp'); ?></span></li>	
	<li><strong><?php _e('Secured CVV','Shopp'); ?>:</strong> <span id="cvv" title="<?php _e('Click here to decrypt the card details&hellip;','Shopp'); ?>"><?php _e('[ENCRYPTED]','Shopp'); ?></span></li>
	<li><strong><?php _e('Expiration','Shopp'); ?>:</strong> <?php echo _d('m/Y', $Purchase->cardexpires); ?></li>
	<li><strong><?php _e('Payment','Shopp'); ?>:</strong> <?php echo $Purchase->txnstatus; ?></li>	
</ul>
<?php else: ?>
<?php if (!empty($Purchase->card) && !empty($Purchase->cardtype)): ?>
<p><strong><?php echo $Purchase->txnstatus; ?></strong> <?php _e('to','Shopp'); ?> <?php (!empty($Purchase->card))?printf("%'X16d",$Purchase->card):''; ?> <?php echo (!empty($Purchase->cardtype))?'('.$Purchase->cardtype.')':''; ?></p>
<?php endif; ?>

<?php endif;?>

<?php
}
add_meta_box('order-transaction', __('Payment Method','Shopp'), 'transaction_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');

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
	add_meta_box('order-shipping', __('Shipping','Shopp'), 'shipping_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');

function downloads_meta_box ($Purchase) {
?>
	<ul>
	<?php foreach ($Purchase->purchased as $Item): ?>
		<li><strong><?php echo $Item->name; ?></strong>: <?php echo $Item->downloads.' '.__('Downloads','Shopp'); ?></li>
	<?php endforeach; ?>
	</ul>
<?php
}
if (!empty($Shopp->Purchase->downloads))
	add_meta_box('order-downloads', __('Downloads','Shopp'), 'downloads_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');

function status_meta_box ($Purchase) {
	global $UI;
?>
<div class="inside-wrap">
<div id="notification">
	<p><label for="message"><?php _e('Message to customer:','Shopp'); ?></label>
		<textarea name="message" id="message" cols="50" rows="10" ></textarea></p>
	<p><span><input type="hidden" name="receipt" value="no" /><input type="checkbox" name="receipt" value="yes" id="include-order" checked="checked" /><label for="include-order">&nbsp;<?php _e('Include a copy of the order in the message','Shopp'); ?></label></span></p>
</div>

<p><span class="middle"><input type="hidden" name="notify" value="no" /><input type="checkbox" name="notify" value="yes" id="notify-customer" /><label for="notify-customer">&nbsp;<?php _e('Send a message to the customer','Shopp'); ?></label></span><br class="clear" /></p>

<p>
	<span>
<label for="txn_status_menu"><?php _e('Payment','Shopp'); ?>:</label>
<select name="txnstatus" id="txn_status_menu">
<?php echo menuoptions($UI->txnStatusLabels,$Purchase->txnstatus,true,true); ?>
</select>
&nbsp;
<label for="order_status_menu"><?php _e('Order Status','Shopp'); ?>:</label>
<select name="status" id="order_status_menu">
<?php echo menuoptions($UI->statusLabels,$Purchase->status,true); ?>
</select></span>
<br class="clear" />
</p>
</div>
<div id="major-publishing-actions">
	<button type="submit" name="update" value="status" class="button-primary"><?php _e('Update Status','Shopp'); ?></button>
</div>
<?php
}
add_meta_box('order-status', __('Status','Shopp'), 'status_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core',2);

function notes_meta_box ($Purchase) {
	global $Notes;
?>
<table>
	<?php foreach ($Notes->meta as $Note): $User = get_userdata($Note->value->author); ?>
	<tr>
		<th><?php echo $User->user_nicename?><br />
			<span><?php echo _d(get_option('date_format').' '.get_option('time_format'), $Note->created); ?></span></th>
		<td>
			<div id="note-<?php echo $Note->id; ?>">
			<?php echo wpautop($Note->value->message); ?>
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
add_meta_box('order-notes', __('Notes','Shopp'), 'notes_meta_box', 'toplevel_page_shopp-orders', 'normal', 'core');

do_action('do_meta_boxes', 'toplevel_page_shopp-orders', 'normal', $Shopp->Purchase);
do_action('do_meta_boxes', 'toplevel_page_shopp-orders', 'side', $Shopp->Purchase);

?>
