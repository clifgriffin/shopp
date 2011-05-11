<?php
/**
 * Purchase class
 * Order invoice logging
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

require_once("Purchased.php");

class Purchase extends DatabaseObject {
	static $table = "purchase";
	var $api = 'purchase';

	var $purchased = array();
	var $columns = array();
	var $downloads = false;

	function Purchase ($id=false,$key=false) {

		$this->init(self::$table);
		if (!$id) return true;
		if ($this->load($id,$key)) return true;
		else return false;
	}

	function load_purchased () {
		$db = DB::get();

		$table = DatabaseObject::tablename(Purchased::$table);
		$meta = DatabaseObject::tablename(MetaObject::$table);
		if (empty($this->id)) return false;
		$this->purchased = $db->query("SELECT * FROM $table WHERE purchase=$this->id",AS_ARRAY);
		foreach ($this->purchased as &$purchase) {
			if (!empty($purchase->download)) $this->downloads = true;
			$purchase->data = unserialize($purchase->data);
			if ($purchase->addons == "yes") {
				$purchase->addons = new ObjectMeta($purchase->id,'purchased','addon');
				if (!$purchase->addons) $purchase->addons = new ObjectMeta();
			}
		}

		return true;
	}

	function notification ($addressee,$address,$subject,$template="order.php",$receipt="receipt.php") {
		global $Shopp;
		global $is_IIS;

		if ($template == "order.php" && file_exists(SHOPP_TEMPLATES."/order.html")) $template = SHOPP_TEMPLATES."/order.html";
		else $template = trailingslashit(SHOPP_TEMPLATES).$template;
		if (!file_exists($template))
			return new ShoppError(__('A purchase notification could not be sent because the template for it does not exist.','purchase_notification_template',SHOPP_ADMIN_ERR));

		// Send the e-mail receipt
		$email = array();
		$email['from'] = '"'.wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ).'"';
		if ($Shopp->Settings->get('merchant_email'))
			$email['from'] .= ' <'.$Shopp->Settings->get('merchant_email').'>';
		if($is_IIS) $email['to'] = $address;
		else $email['to'] = '"'.html_entity_decode($addressee,ENT_QUOTES).'" <'.$address.'>';
		$email['subject'] = $subject;
		$email['receipt'] = $this->receipt($receipt);
		$email['url'] = get_bloginfo('siteurl');
		$email['sitename'] = get_bloginfo('name');
		$email['orderid'] = $this->id;

		$email = apply_filters('shopp_email_receipt_data',$email);

		if (shopp_email($template,$email)) {
			if (SHOPP_DEBUG) new ShoppError('A purchase notification was sent to: '.$email['to'],false,SHOPP_DEBUG_ERR);
			return true;
		}

		if (SHOPP_DEBUG) new ShoppError('A purchase notification FAILED to be sent to: '.$email['to'],false,SHOPP_DEBUG_ERR);
		return false;
	}

	function copydata ($Object,$prefix="") {
		$ignores = array("_datatypes","_table","_key","_lists","id","created","modified");
		foreach(get_object_vars($Object) as $property => $value) {
			$property = $prefix.$property;
			if (property_exists($this,$property) &&
				!in_array($property,$ignores))
				$this->{$property} = $value;
		}
	}

	function exportcolumns () {
		$prefix = "o.";
		return array(
			$prefix.'id' => __('Order ID','Shopp'),
			$prefix.'ip' => __('Customer\'s IP Address','Shopp'),
			$prefix.'firstname' => __('Customer\'s First Name','Shopp'),
			$prefix.'lastname' => __('Customer\'s Last Name','Shopp'),
			$prefix.'email' => __('Customer\'s Email Address','Shopp'),
			$prefix.'phone' => __('Customer\'s Phone Number','Shopp'),
			$prefix.'company' => __('Customer\'s Company','Shopp'),
			$prefix.'card' => __('Credit Card Number','Shopp'),
			$prefix.'cardtype' => __('Credit Card Type','Shopp'),
			$prefix.'cardexpires' => __('Credit Card Expiration Date','Shopp'),
			$prefix.'cardholder' => __('Credit Card Holder\'s Name','Shopp'),
			$prefix.'address' => __('Billing Street Address','Shopp'),
			$prefix.'xaddress' => __('Billing Street Address 2','Shopp'),
			$prefix.'city' => __('Billing City','Shopp'),
			$prefix.'state' => __('Billing State/Province','Shopp'),
			$prefix.'country' => __('Billing Country','Shopp'),
			$prefix.'postcode' => __('Billing Postal Code','Shopp'),
			$prefix.'shipaddress' => __('Shipping Street Address','Shopp'),
			$prefix.'shipxaddress' => __('Shipping Street Address 2','Shopp'),
			$prefix.'shipcity' => __('Shipping City','Shopp'),
			$prefix.'shipstate' => __('Shipping State/Province','Shopp'),
			$prefix.'shipcountry' => __('Shipping Country','Shopp'),
			$prefix.'shippostcode' => __('Shipping Postal Code','Shopp'),
			$prefix.'shipmethod' => __('Shipping Method','Shopp'),
			$prefix.'promos' => __('Promotions Applied','Shopp'),
			$prefix.'subtotal' => __('Order Subtotal','Shopp'),
			$prefix.'discount' => __('Order Discount','Shopp'),
			$prefix.'freight' => __('Order Shipping Fees','Shopp'),
			$prefix.'tax' => __('Order Taxes','Shopp'),
			$prefix.'total' => __('Order Total','Shopp'),
			$prefix.'fees' => __('Transaction Fees','Shopp'),
			$prefix.'txnid' => __('Transaction ID','Shopp'),
			$prefix.'txnstatus' => __('Transaction Status','Shopp'),
			$prefix.'gateway' => __('Payment Gateway','Shopp'),
			$prefix.'status' => __('Order Status','Shopp'),
			$prefix.'data' => __('Order Data','Shopp'),
			$prefix.'created' => __('Order Date','Shopp'),
			$prefix.'modified' => __('Order Last Updated','Shopp')
			);
	}

	// Display a sales receipt
	function receipt ($template="receipt.php") {
		if (!file_exists(SHOPP_TEMPLATES."/$template")) $template = "receipt.php";
		if (empty($this->purchased)) $this->load_purchased();
		ob_start();
		include(SHOPP_TEMPLATES."/$template");
		$content = ob_get_contents();
		ob_end_clean();
		return apply_filters('shopp_order_receipt',$content);
	}

	/**
	 * Provides shopp('purchase') template API functionality
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @deprecated 1.2
	 *
	 * @return mixed
	 **/
	function tag ($property,$options=array()) {
		if (is_array($options)) $options['return'] = 'on';
		else $options .= (!empty($options)?"&":"").'return=on';
		return shopp($this,$property,$options);
	}

} // end Purchase class

class PurchasesExport {
	var $sitename = "";
	var $headings = false;
	var $data = false;
	var $defined = array();
	var $purchase_cols = array();
	var $purchased_cols = array();
	var $selected = array();
	var $recordstart = true;
	var $content_type = "text/plain";
	var $extension = "txt";
	var $date_format = 'F j, Y';
	var $time_format = 'g:i:s a';
	var $set = 0;
	var $limit = 1024;

	function PurchasesExport () {
		global $Shopp;

		$this->purchase_cols = Purchase::exportcolumns();
		$this->purchased_cols = Purchased::exportcolumns();
		$this->defined = array_merge($this->purchase_cols,$this->purchased_cols);

		$this->sitename = get_bloginfo('name');
		$this->headings = ($Shopp->Settings->get('purchaselog_headers') == "on");
		$this->selected = $Shopp->Settings->get('purchaselog_columns');
		$this->date_format = get_option('date_format');
		$this->time_format = get_option('time_format');
		$Shopp->Settings->save('purchaselog_lastexport',mktime());
	}

	function query ($request=array()) {
		$db =& DB::get();
		if (empty($request)) $request = $_GET;

		if (!empty($request['start'])) {
			list($month,$day,$year) = explode("/",$request['start']);
			$starts = mktime(0,0,0,$month,$day,$year);
		}

		if (!empty($request['end'])) {
			list($month,$day,$year) = explode("/",$request['end']);
			$ends = mktime(0,0,0,$month,$day,$year);
		}

		$where = "WHERE o.id IS NOT NULL AND p.id IS NOT NULL ";
		if (isset($request['status']) && !empty($request['status'])) $where .= "AND status='{$request['status']}'";
		if (isset($request['s']) && !empty($request['s'])) $where .= " AND (id='{$request['s']}' OR firstname LIKE '%{$request['s']}%' OR lastname LIKE '%{$request['s']}%' OR CONCAT(firstname,' ',lastname) LIKE '%{$request['s']}%' OR transactionid LIKE '%{$request['s']}%')";
		if (!empty($request['start']) && !empty($request['end'])) $where .= " AND  (UNIX_TIMESTAMP(o.created) >= $starts AND UNIX_TIMESTAMP(o.created) <= $ends)";

		$purchasetable = DatabaseObject::tablename(Purchase::$table);
		$purchasedtable = DatabaseObject::tablename(Purchased::$table);
		$offset = ($this->set*$this->limit);

		$c = 0; $columns = array();
		foreach ($this->selected as $column) $columns[] = "$column AS col".$c++;
		$query = "SELECT ".join(",",$columns)." FROM $purchasedtable AS p LEFT JOIN $purchasetable AS o ON o.id=p.purchase $where ORDER BY o.created ASC LIMIT $offset,$this->limit";
		$this->data = $db->query($query,AS_ARRAY);
	}

	// Implement for exporting all the data
	function output () {
		if (!$this->data) $this->query();
		if (!$this->data) return false;

		header("Content-type: $this->content_type; charset=UTF-8");
		header("Content-Disposition: attachment; filename=\"$this->sitename Purchase Log.$this->extension\"");
		header("Content-Description: Delivered by WordPress/Shopp ".SHOPP_VERSION);
		header("Cache-Control: maxage=1");
		header("Pragma: public");

		$this->begin();
		if ($this->headings) $this->heading();
		$this->records();
		$this->end();
	}

	function begin() {}

	function heading () {
		foreach ($this->selected as $name)
			$this->export($this->defined[$name]);
		$this->record();
	}

	function records () {
		while (!empty($this->data)) {
			foreach ($this->data as $key => $record) {
				foreach(get_object_vars($record) as $column)
					$this->export($this->parse($column));
				$this->record();
			}
			$this->set++;
			$this->query();
		}
	}

	function parse ($column) {
		if (preg_match("/^[sibNaO](?:\:.+?\{.*\}$|\:.+;$|;$)/",$column)) {
			$list = unserialize($column);
			$column = "";
			foreach ($list as $name => $value)
				$column .= (empty($column)?"":";")."$name:$value";
		}
		return $column;
	}

	function end() {}

	// Implement for exporting a single value
	function export ($value) {
		echo ($this->recordstart?"":"\t").$value;
		$this->recordstart = false;
	}

	function record () {
		echo "\n";
		$this->recordstart = true;
	}

	function settings () {}

}

class PurchasesTabExport extends PurchasesExport {
	function PurchasesTabExport () {
		parent::PurchasesExport();
		$this->output();
	}
}

class PurchasesCSVExport extends PurchasesExport {
	function PurchasesCSVExport () {
		parent::PurchasesExport();
		$this->content_type = "text/csv";
		$this->extension = "csv";
		$this->output();
	}

	function export ($value) {
		$value = str_replace('"','""',$value);
		if (preg_match('/^\s|[,"\n\r]|\s$/',$value)) $value = '"'.$value.'"';
		echo ($this->recordstart?"":",").$value;
		$this->recordstart = false;
	}

}

class PurchasesXLSExport extends PurchasesExport {
	function PurchasesXLSExport () {
		parent::PurchasesExport();
		$this->content_type = "application/vnd.ms-excel";
		$this->extension = "xls";
		$this->c = 0; $this->r = 0;
		$this->output();
	}

	function begin () {
		echo pack("ssssss", 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);
	}

	function end () {
		echo pack("ss", 0x0A, 0x00);
	}

	function export ($value) {
		if (preg_match('/^[\d\.]+$/',$value)) {
		 	echo pack("sssss", 0x203, 14, $this->r, $this->c, 0x0);
			echo pack("d", $value);
		} else {
			$l = strlen($value);
			echo pack("ssssss", 0x204, 8+$l, $this->r, $this->c, 0x0, $l);
			echo $value;
		}
		$this->c++;
	}

	function record () {
		$this->c = 0;
		$this->r++;
	}
}

class PurchasesIIFExport extends PurchasesExport {
	function PurchasesIIFExport () {
		global $Shopp;
		parent::PurchasesExport();
		$this->content_type = "application/qbooks";
		$this->extension = "iif";
		$account = $Shopp->Settings->get('purchaselog_iifaccount');
		if (empty($account)) $account = "Merchant Account";
		$this->selected = array(
			"'\nTRNS'",
			"DATE_FORMAT(o.created,'\"%m/%d/%Y\"')",
			"'\"$account\"'",
			"CONCAT('\"',o.firstname,' ',o.lastname,'\"')",
			"'\"Shopp Payment Received\"'",
			"o.total-o.fees",
			"''",
			"'\nSPL'",
			"DATE_FORMAT(o.created,'\"%m/%d/%Y\"')",
			"'\"Other Income\"'",
			"CONCAT('\"',o.firstname,' ',o.lastname,'\"')",
			"o.total*-1",
			"'\nSPL'",
			"DATE_FORMAT(o.created,'\"%m/%d/%Y\"')",
			"'\"Other Expenses\"'",
			"'Fee'",
			"o.fees",
			"''",
			"'\nENDTRNS'"
		);
		$this->output();
	}

	function begin () {
		echo "!TRNS\tDATE\tACCNT\tNAME\tCLASS\tAMOUNT\tMEMO\n!SPL\tDATE\tACCNT\tNAME\tAMOUNT\tMEMO\n!ENDTRNS";
	}

	function export ($value) {
		echo (substr($value,0,1) != "\n")?"\t".$value:$value;
	}

	function record () { }

	function settings () {
		global $Shopp;
		?>
		<div id="iif-settings" class="hidden">
			<input type="text" id="iif-account" name="settings[purchaselog_iifaccount]" value="<?php echo $Shopp->Settings->get('purchaselog_iifaccount'); ?>" size="30"/><br />
			<label for="iif-account"><small><?php _e('QuickBooks account name for transactions','Shopp'); ?></small></label>
		</div>
		<script type="text/javascript">
		/* <![CDATA[ */
		jQuery(document).ready( function() {
			var $=jqnc();
			$('#purchaselog-format').change(function () {
				if ($(this).val() == "iif") {
					$('#export-columns').hide();
					$('#iif-settings').show();
					$('#iif-account').focus();
				} else {
					$('#export-columns').show();
					$('#iif-settings').hide();
				}
			}).change();
		});
		/* ]]> */
		</script>
		<?php
	}
}


?>