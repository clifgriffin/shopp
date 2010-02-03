<?php
/**
 * Manual Processing
 *
 * @author John Dillick
 * @version 1.0
 * @copyright Ingenesis Limited, February 3, 2010
 * @package Shopp
 * @since 1.1 dev
 * @subpackage ManualProcess
 * 
 * $Id$
 **/

class ManualProcess extends GatewayFramework {

	var $secure = true;

	var $cards = array("Visa","MasterCard","Discover","American Express");
	var $public_key = false;
	var $private_key = false;
	var $sec_prefix = false;
	var $path = false;

	function ManualProcess () {
		parent::__construct();
		global $Shopp;

		$this->path = sanitize_path(SHOPP_PLUGINURI."/gateways/{$this->module}/");
		$this->public_key = $this->settings['public_key'];
		if (defined('SECRET_AUTH_KEY') && SECRET_AUTH_KEY != '') $this->sec_prefix = SECRET_AUTH_KEY;
		else $this->sec_prefix = DatabaseObject::tablename("");
		
		wp_enqueue_script('shopp_rsa', $this->path."behaviors/rsa.js",array());
		wp_enqueue_script('shopp_mp_gateway', $this->path."behaviors/mp.js", array('json2'));
		
		add_action('admin_head', array(&$this, 'jserrors'));
		add_action('shopp_process_order',array(&$this,'process'));
		add_action('shopp_order_admin_script', array(&$this, 'decrypt'));
		
		return true;
	}
	
	function jserrors() {
		$errors = "<script type=\"text/javascript\" charset=\"utf-8\">\n";
		$errors .= "//<![CDATA[\n";
		$errors .= "var BROWSER_UNSUPPORTED='".__('This browser is unsupported. Please use Safari 4, Internet Explorer 8, Mozilla Firefox 3.6, Opera 10.10 or later version of these browsers.','Shopp')."';\n";
		$errors .= "var LOCAL_STORAGE_QUOTA='".__('Browser Local Storage Quota Exceeded, Setup Failed','Shopp')."';\n";
		$errors .= "var LOCAL_STORAGE_ERROR='".__('Browser Local Storage Error: ','Shopp')."';\n";
		$errors .= "var DECRYPTION_ERROR='".__('There was an failure retrieving your private key from the browser.  Decryption failed.','Shopp')."';\n";
		$errors .= "var SECRET_DATA='".__('[ENCRYPTED]','Shopp')."';\n";
		$errors .= "//]]>\n";
		$errors .= "</script>";
		echo $errors;
	}
	
	function process () {
		global $Shopp;

		if (!$this->myorder()) return false; 

		if($this->Order->Billing->card && $this->Order->Billing->cvv) {
			$sensitive = array('card'=>$this->Order->Billing->card,'cvv'=>$this->Order->Billing->cvv);
			$this->Order->secured[$this->module] = $this->encrypt(json_encode($sensitive));
		}
		$this->Order->transaction($this->txnid());
	}
	
	function settings () {
		if(SHOPP_DEBUG) new ShoppError('Manual Processing: '._object_r($_POST),false,SHOPP_DEBUG_ERR);
		if(isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == "on") {			
			if (empty($this->public_key)) {
				$this->ui->hidden(0,array(
					'name' => 'public_key',
					'value' => 'generate'
				));
				$this->ui->p(1,array(
					'name' => 'description',
					'label' => __('Getting Started','Shopp'),
					'content' => __('To begin setup, click the Generate button to create an encryption keypair.  For more information on this technology and important server and browser requirements, see <a href="http://docs.shopplugin.net/Manual_Processing">Manual Processing</a> documentation.','Shopp')
				));
				$this->ui->button(1, array(
					'name' => 'keygen',
					'label' => __('Generate Keys', 'Shopp'),
					'id' => 'keygen'
				));
				$this->gen_button_press();
			} else if($this->public_key == "generate") { //install key
				$this->generate_keypairs();
				$data = 'private='.urlencode(serialize($this->private_key));
				
				$this->ui->hidden(0,array(
					'name' => 'public_key',
					'value' => $this->public_key
				));
				$this->ui->p(1, array(
					'name' => 'save-key',
					'label' => __('Save Your Private Key','Shopp'),
					'content' => __('Your private key has be cached in your browser. To finalize the setup process, click the Download button to save your private key file on your system.  It is important that this file be retained and remain private to the store owner. We also recommend storing a backup of this file, as secure payment information in your orders will be inaccessible without an installed private key. See <a href="http://docs.shopplugin.net/Manual_Processing">Manual Processing</a> documentation for more information','Shopp').
					'<br /><a href="'.$this->path."/util/private.php?download_pkey=1&".
					$data.'" id="dlkey" target="_blank" class="button-secondary">'.__('Download','Shopp').'</a>'
				));
				$this->dl_button_press();
			} else {
				$this->ui->hidden(0,array(
					'name' => 'public_key',
					'value' => $this->public_key
				));
				$this->ui->p(1, array(
					'name' => 'complete',
					'label' => __("Setup Complete",'Shopp'),
					'content' => __('The ManualProcess payment method setup is complete.  Please keep your private key in a secure place and backed up.  If you need to re-cache your private key in your browser, click the "Upload Key" button to select your saved private key file.','Shopp')
				));
				$this->ui->button(1, array(
					'name' => 'reinstall',
					'label' => __('Upload Key','Shopp'),
					'id' => 'reinstall_key'
				));
				$this->reinstall_button_press(); // not implemented
			}
		} else { // require ssl to setup keys
			$this->ui->p(0, array(
				'name' => 'sslrequired',
				'label' => __('Unable to Complete Setup', 'Shopp'),
				'content' => __('<p>This payment method requires an SSL enabled site, and also SSL security in the WordPress Admin.</p>  <p>Please save to activate this module, and then login in secure mode to complete the setup. See the <a target="_blank" href="http://codex.wordpress.org/Administration_Over_SSL#To_Force_SSL_Logins_and_SSL_Admin_Access">WordPress Codex - Administration Over SSL</a> for more information on securing your WordPress Admin.</p>','Shopp')
			));
		}
	}

	function gen_button_press() {
		?>ManualProcess.behaviors = function (){ 
			if(!dp.supported) { 
				alert(BROWSER_UNSUPPORTED);
				this.row.remove();
				return false; 
			}
			$('#keygen').click(function(){
					$('<input type="hidden" name="save" value="true" />').appendTo('#payments');
					$('#payments').submit();
			}); 
		}
		<?php
	}
	function dl_button_press() {
		?>ManualProcess.behaviors = function (){ 
			if(!dp.supported) { 
				alert(BROWSER_UNSUPPORTED);
				this.row.remove();
				return false; 
			}
			$('#dlkey').click(function(){
					$('<input type="hidden" name="save" value="true" />').appendTo('#payments');
					$('#payments').submit();
			}); 
		}
		<?php
	}
	
	function reinstall_button_press() {}
	
	function generate_keypairs() {
		if($this->public_key == 'generate') {
			$res_priv = openssl_pkey_new();
			$priv_details=openssl_pkey_get_details($res_priv);
			$this->private_key = array_map('bin2hex',$priv_details['rsa']); // map private key setup values to hex
			$this->public_key = urlencode($priv_details['key']);
			echo "if(dp.supported) dp.store('".json_encode($this->private_key)."','".$this->sec_prefix."');\n";
		}
		return;
	} // end generate_keypairs

	function encrypt($data) {
		if(!$this->public_key) return false;
		else {
			$encrypted = rsa_encrypt($data,urldecode($this->public_key));
			return $encrypted ? bin2hex($encrypted) : false;
		}
	}
	
	function getcard($current=false,$options=false,&$purchase) {
		if(!$purchase) return false;
		return $purchase->securedcard ? $purchase->securedcard : false;
	}
	
	function decrypt(&$purchase) {
		if($purchase->secured){
			$decrypt = "decrypt('".$purchase->secured[$this->module]."','".$this->sec_prefix."');\n";
			echo '$(\'#card\').click(function(){'.$decrypt.'});';
			echo '$(\'#cvv\').click(function(){'.$decrypt.'});';
		}
	}

} // END class ManualProcess

?>