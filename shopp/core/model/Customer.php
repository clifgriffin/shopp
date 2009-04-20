<?php
/**
 * Customer class
 * Customer contact information
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @package shopp
 **/

class Customer extends DatabaseObject {
	static $table = "customer";
	
	var $management = array(
		"account" => "My Account",
		"downloads" => "Downloads",
		"history" => "Order History",
		"status" => "Order Status",
		"logout" => "Logout"
		);
	
	function Customer ($id=false,$key=false) {
		$this->init(self::$table);
		if ($this->load($id,$key)) return true;
		else return false;
	}
	
	function management () {
		global $Shopp;
		
		switch ($_GET['acct']) {
			case "receipt": break;
			case "history": $this->load_orders(); break;
			case "downloads": $this->load_downloads(); break;
			case "logout": $Shopp->Cart->logout(); break;
		}
		
		if (!empty($_POST['vieworder']) && !empty($_POST['purchaseid'])) {
			
			$Purchase = new Purchase($_POST['purchaseid']);
			if ($Purchase->email == $_POST['email']) {
				$Shopp->Cart->data->Purchase = $Purchase;
				$Purchase->load_purchased();
				ob_start();
				include(SHOPP_TEMPLATES."/receipt.php");
				$content = ob_get_contents();
				ob_end_clean();
				return '<div id="shopp">'.$content.'</div>';
			}
		}

		if (!empty($_GET['acct']) && !empty($_GET['id'])) {
			$Purchase = new Purchase($_GET['id']);
			$Shopp->Cart->data->Purchase = $Purchase;
			$Purchase->load_purchased();
			ob_start();
			include(SHOPP_TEMPLATES."/receipt.php");
			$content = ob_get_contents();
			ob_end_clean();
			echo '<div id="shopp">'.$content.'</div>';
			return false;
		}

		
		if (!empty($_POST['customer'])) {
			$this->updates($_POST);
			if ($_POST['password'] == $_POST['confirm-password'])
				$this->password = wp_hash_password($_POST['password']);
			$this->save();
		}
		
	}
	
	function load_downloads () {
		$db =& DB::get();
		
		$orders = DatabaseObject::tablename(Purchase::$table);
		$purchases = DatabaseObject::tablename(Purchased::$table);
		$pricing = DatabaseObject::tablename(Price::$table);
		$asset = DatabaseObject::tablename(Asset::$table);
		$query = "SELECT p.*,f.name as filename,f.size,f.properties FROM $purchases AS p LEFT JOIN $orders AS o ON o.id=p.purchase LEFT JOIN $asset AS f ON f.parent=p.price WHERE o.customer=$this->id AND f.size > 0";
		$this->downloads = $db->query($query,AS_ARRAY);
		
	}

	function load_orders ($filters=array()) {
		global $Shopp;
		$db =& DB::get();
		
		if (isset($filters['where'])) $where = " AND {$filters['where']}";
		$orders = DatabaseObject::tablename(Purchase::$table);
		$purchases = DatabaseObject::tablename(Purchased::$table);
		$query = "SELECT o.* FROM $orders AS o LEFT JOIN $purchases AS p ON p.purchase=o.id WHERE o.customer=$this->id $where ORDER BY created DESC";
		$Shopp->purchases = $db->query($query,AS_ARRAY);
		foreach($Shopp->purchases as &$p) {
			$Purchase = new Purchase();
			$Purchase->updates($p);
			$p = $Purchase;
		}
	}
	
	function tag ($property,$options=array()) {
		global $Shopp;
		
		// Return strings with no options
		switch ($property) {
			case "url": return $Shopp->link('account');
			case "process":
				if (isset($_GET['acct'])) return $_GET['acct'];
				return false;

			case "loggedin": return $Shopp->Cart->data->login; break;
			case "notloggedin": return (!$Shopp->Cart->data->login && $Shopp->Settings->get('account_system') != "none"); break;
			case "loginname-login": 
				if (!empty($_POST['loginname-login']))
					$options['value'] = $_POST['loginname-login']; 
				return '<input type="text" name="loginname-login" id="loginname-login"'.inputattrs($options).' />';
				break;
			case "email-login": 
				if (!empty($_POST['email-login']))
					$options['value'] = $_POST['email-login']; 
				return '<input type="text" name="email-login" id="email-login"'.inputattrs($options).' />';
				break;
			case "password-login": 
				if (!empty($_POST['password-login']))
					$options['value'] = $_POST['password-login']; 
				return '<input type="password" name="password-login" id="password-login"'.inputattrs($options).' />';
				break;
			case "submit-login": // Deprecating
			case "login-button":
				$string = '<input type="hidden" name="process-login" id="process-login" value="true" />';
				$string .= '<input type="submit" name="submit-login" id="submit-login"'.inputattrs($options).' />';
				return $string;
				break;
			case "login-errors":
				$Errors =& ShoppErrors();
				$result = "";
				if (!$Errors->exist()) return false;
				$errors = $Errors->get(SHOPP_COMM_ERR);
				foreach ((array)$errors as $error) 
					if (!empty($error)) $result .= '<p class="error">'.$error->message().'</p>';
				$Errors->reset();				
				return $result;
				break;

			case "menu":
				if (!$this->looping) {
					reset($this->management);
					$this->looping = true;
				} else next($this->management);
				
				if (current($this->management)) return true;
				else {
					$this->looping = false;
					reset($this->management);
					return false;
				}
				break;
			case "management":
				if (array_key_exists('url',$options)) return add_query_arg('acct',key($this->management),$_SERVER['REQUEST_URI']);
				if (array_key_exists('action',$options)) return key($this->management);
				return current($this->management);
			case "accounts": return $Shopp->Settings->get('account_system'); break;
			case "order-lookup":
				$auth = $Shopp->Settings->get('account_system');
				if ($auth != "none") return true;
			
				if (!empty($_POST['vieworder']) && !empty($_POST['purchaseid'])) {
					require_once("Purchase.php");
					$Purchase = new Purchase($_POST['purchaseid']);
					if ($Purchase->email == $_POST['email']) {
						$Shopp->Cart->data->Purchase = $Purchase;
						$Purchase->load_purchased();
						ob_start();
						include(SHOPP_TEMPLATES."/receipt.php");
						$content = ob_get_contents();
						ob_end_clean();
						return '<div id="shopp">'.$content.'</div>';
					}
				}

				ob_start();
				include(SHOPP_ADMINPATH."/orders/account.php");
				$content = ob_get_contents();
				ob_end_clean();
				return '<div id="shopp">'.$content.'</div>';
				break;

			case "firstname": 
				if (!empty($this->firstname))
					$options['value'] = $this->firstname; 
				return '<input type="text" name="firstname" id="firstname"'.inputattrs($options).' />';
				break;
			case "lastname":
				if (!empty($this->lastname))
					$options['value'] = $this->lastname; 
				return '<input type="text" name="lastname" id="lastname"'.inputattrs($options).' />'; 
				break;
			case "email":
				if (!empty($this->email))
					$options['value'] = $this->email; 
				return '<input type="text" name="email" id="email"'.inputattrs($options).' />';
				break;
			case "loginname":
				if (!empty($this->login))
					$options['value'] = $this->login; 
				return '<input type="text" name="login" id="login"'.inputattrs($options).' />';
				break;
			case "password":
				if (!empty($this->password))
					$options['value'] = $this->password; 
				return '<input type="password" name="password" id="password"'.inputattrs($options).' />';
				break;
			case "confirm-password":
				if (!empty($this->confirm_password))
					$options['value'] = $this->confirm_password; 
				return '<input type="password" name="confirm-password" id="confirm-password"'.inputattrs($options).' />';
				break;
			case "phone": 
			if (!empty($this->phone))
				$options['value'] = $this->phone; 
				return '<input type="text" name="phone" id="phone"'.inputattrs($options).' />'; 
				break;
			case "hasinfo":
			case "has-info":
				if (empty($this->info)) return false;
				if (!$this->looping) {
					reset($this->info);
					$this->looping = true;
				} else next($this->info);
				
				if (current($this->info)) return true;
				else {
					$this->looping = false;
					reset($this->info);
					return false;
				}
				break;
			case "info":
				$info = current($this->info);
				$name = key($this->info);
				$allowed_types = array("text","password","hidden","checkbox","radio");
				if (empty($options['type'])) $options['type'] = "hidden";
				if (in_array($options['type'],$allowed_types)) {
					$options['value'] = $info;
					return '<input type="text" name="info['.$name.']" id="customer-info-'.$name.'"'.inputattrs($options).' />'; 
				}
				break;
			case "save-button":
				if (!isset($options['label'])) $options['label'] = __('Save','Shopp');
				$result = '<input type="hidden" name="customer" value="true" />';
				$result .= '<input type="submit" name="save" id="save-button"'.inputattrs($options).' />'; 
				return $result;
				break;
			
			
			// Downloads UI tags
			case "hasdownloads":
			case "has-downloads": return (!empty($this->downloads)); break;
			case "downloads":
				if (empty($this->downloads)) return false;
				if (!$this->looping) {
					reset($this->downloads);
					$this->looping = true;
				} else next($this->downloads);
			
				if (current($this->downloads)) return true;
				else {
					$this->looping = false;
					reset($this->downloads);
					return false;
				}
				break;
			case "download":
				$download = current($this->downloads);
				$df = get_option('date_format');
				$properties = unserialize($download->properties);
				$string = '';
				if (array_key_exists('id',$options)) $string .= $download->download;
				if (array_key_exists('purchase',$options)) $string .= $download->purchase;
				if (array_key_exists('name',$options)) $string .= $download->name;
				if (array_key_exists('variation',$options)) $string .= $download->optionlabel;
				if (array_key_exists('downloads',$options)) $string .= $download->downloads;
				if (array_key_exists('key',$options)) $string .= $download->dkey;
				if (array_key_exists('created',$options)) $string .= $download->created;
				if (array_key_exists('total',$options)) $string .= money($download->total);
				if (array_key_exists('filetype',$options)) $string .= $properties['mimetype'];
				if (array_key_exists('size',$options)) $string .= readableFileSize($download->size);
				if (array_key_exists('date',$options)) $string .= date($df,mktimestamp($download->created));
				if (array_key_exists('url',$options)) $string .= (SHOPP_PERMALINKS) ?
					$Shopp->shopuri."download/".$download->dkey : 
					add_query_arg('shopp_download',$download->dkey,$Shopp->shopuri);
				
				return $string;
				break;
				
			// Downloads UI tags
			case "haspurchases":
			case "has-purchases": 
				$filters = array();
				if (isset($options['daysago'])) 
					$filters['where'] = "UNIX_TIMESTAMP(o.created) > UNIX_TIMESTAMP()-".($options['daysago']*86400);
				if (empty($Shopp->purchases)) $this->load_orders($filters);
				return (!empty($Shopp->purchases));
				break;
			case "purchases":
				if (!$this->looping) {
					reset($Shopp->purchases);
					$Shopp->Cart->data->Purchase = current($Shopp->purchases);
					$this->looping = true;
				} else {
					$Shopp->Cart->data->Purchase = next($Shopp->purchases);
				}

				if (current($Shopp->purchases)) {
					$Shopp->Cart->data->Purchase = current($Shopp->purchases);
					return true;
				}
				else {
					$this->looping = false;
					return false;
				}
				break;
			case "receipt":
				return add_query_arg(
					array(
						'acct'=>'receipt',
						'id'=>$Shopp->Cart->data->Purchase->id),
						$Shopp->link('account'));

		}
	}

} // end Customer class

?>