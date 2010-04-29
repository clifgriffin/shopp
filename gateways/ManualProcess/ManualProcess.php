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

class ManualProcess extends GatewayFramework implements GatewayModule {

	var $secure = true;
	var $cards = array();

	var $public_key = false;
	var $private_key = false;
	var $sec_prefix = false;
	var $path = false;

	function ManualProcess () {
		$paycards = Lookup::paycards();
		$this->cards = array_keys($paycards);
		parent::__construct();

		$this->path = sanitize_path(SHOPP_PLUGINURI."/gateways/{$this->module}/");
		$this->public_key = $this->settings['public_key'];
		if (defined('SECRET_AUTH_KEY') && SECRET_AUTH_KEY != '') $this->sec_prefix = SECRET_AUTH_KEY;
		else $this->sec_prefix = DatabaseObject::tablename("");
		
		wp_enqueue_script('shopp_rsa', $this->path."behaviors/rsa.js",array());
		wp_enqueue_script('shopp_mp_gateway', $this->path."behaviors/mp.js", array('json2'));
		wp_enqueue_script('shopp.ocupload',SHOPP_ADMIN_URI."/behaviors/ocupload.js",array('jquery'),SHOPP_VERSION,true);
		
		add_action('init', array(&$this, 'init'), 11);		
		add_action('admin_head', array(&$this, 'jserrors'));
		add_action('shopp_order_admin_script', array(&$this, 'decrypt'));
		add_action('wp_ajax_mp_reinstall_keys', array(&$this, 'reinstall_keypairs'));
		
	}
	
	function init () {
		if (!empty($this->public_key)) force_ssl_admin(true); // force ssl admin only if Manual Processing is activated and setup complete
	}
	
	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));		
	}
	
	function jserrors() {
		$errors = "<script type=\"text/javascript\" charset=\"utf-8\">\n";
		$errors .= "//<![CDATA[\n";
		$errors .= "var BROWSER_UNSUPPORTED='".__('This browser is unsupported for Manual Processing administration. Please use Google Chrome 5, Apple Safari 4, Internet Explorer 8, Mozilla Firefox 3.6 or later version of these browsers.','Shopp')."';\n";
		$errors .= "var LOCAL_STORAGE_QUOTA='".__('Browser Local Storage Quota Exceeded, Setup Failed','Shopp')."';\n";
		$errors .= "var LOCAL_STORAGE_ERROR='".__('Browser Local Storage Error: ','Shopp')."';\n";
		$errors .= "var DECRYPTION_ERROR='".__('There was an failure retrieving your private key from the browser.  Decryption failed.','Shopp')."';\n";
		$errors .= "var SECRET_DATA='".__('[ENCRYPTED]','Shopp')."';\n";
		$errors .= "//]]>\n";
		$errors .= "</script>";
		echo $errors;
	}
	
	function process () {
		if($this->Order->Billing->card && $this->Order->Billing->cvv) {
			$sensitive = array('card'=>$this->Order->Billing->card,'cvv'=>$this->Order->Billing->cvv);
			$this->Order->secured[$this->module] = $this->encrypt(json_encode($sensitive));
		}
		$this->Order->transaction($this->txnid());
	}
	
	function settings () {
		if(isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == "on") {			
			$this->ui->cardmenu(0,array(
				'name' => 'cards',
				'selected' => $this->settings['cards']
			),$this->cards);

			if (empty($this->public_key)) { // not setup, generation needed
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
				
				$this->ui->hidden(0,array(
					'name' => 'public_key',
					'value' => $this->public_key
				));
				$this->ui->p(1, array(
					'name' => 'save-key',
					'label' => __('Save Your Private Key','Shopp'),
					'content' => __('To finalize the setup process, click the Finish button to save your settings.  Your private key file will automatically be downloaded.  It is important that this file be retained and remain private to the store owner. We also recommend storing a backup of this file, as secure payment information in your orders will be inaccessible without an installed private key. See <a href="http://docs.shopplugin.net/Manual_Processing">Manual Processing</a> documentation for more information','Shopp').
					'<br /><iframe id="dlframe" name="dlframe" style="width:0px; height:0px; border:0px;" ></iframe><a id="finish" class="button-secondary">'.__('Finish','Shopp').'</a>'
				));
				$this->finish_button_press();
			} else {  // all setup!
				$this->ui->hidden(0,array(
					'name' => 'public_key',
					'value' => $this->public_key
				));
				$this->ui->p(1, array(
					'name' => 'complete',
					'label' => __("Setup Complete",'Shopp'),
					'content' => __('The ManualProcess payment method setup is complete.  Please keep your private key in a secure place and backed up.  If you need to re-cache your private key in your browser, or use this key in another browser, click the "Reinstall Key" button to select your saved private key file.','Shopp')
				));
				$this->ui->button(1, array(
					'name' => 'reinstall',
					'label' => __('Reinstall Key','Shopp'),
					'id' => 'reinstall_key',
					'type' => 'button'
				));
				$this->reinstall_button_press(); // not implemented
			}
		} else { // require ssl to setup keys
			$this->ui->p(0, array(
				'name' => 'sslrequired',
				'label' => __('Unable to Complete Setup', 'Shopp'),
				'content' => '<p>'.__('This payment method requires an SSL enabled site, and also SSL security in the WordPress Admin.','Shopp').'</p><p>'.__('Please save to activate this module, and then login in secure mode to complete the setup. See the <a target="_blank" href="http://codex.wordpress.org/Administration_Over_SSL#To_Force_SSL_Logins_and_SSL_Admin_Access">WordPress Codex - Administration Over SSL</a> for more information on securing your WordPress Admin.','Shopp')
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
	function finish_button_press() {
		?>ManualProcess.behaviors = function (){
			var prefix='<?php echo $this->sec_prefix; ?>',
				msg='',
				test='<?php echo $this->encrypt('test'); ?>',
				button=$('#finish'),
				href = '<?php echo $this->path."util/private.php?private=".urlencode(base64_encode(serialize(array($this->private_key,$this->public_key)))); ?>';	

			dp.get(prefix);
			if(!dp.supported || dp.decrypt(test) != 'test') {
				msg = '<?php _e('Error saving private key into your browser.','Shopp'); ?>'; 
				status='upload failure'; 
			} else $('#dlframe').attr('src',href);
			
			$('<span class="'+status+'">'+msg+'</span>').insertAfter(button);
						
			$('#finish').click(function(e){
				$('<input type="hidden" name="save" value="true" />').appendTo('#payments');
				$('#payments').submit();
			}); 
		}
		<?php
	}

	function reinstall_button_press() {
		global $Shopp;
		?>ManualProcess.behaviors = function (){ 
			if(!dp.supported) { 
				alert(BROWSER_UNSUPPORTED);
				this.row.remove();
				return false; 
			}
			var ajaxurl = '<?php echo wp_nonce_url($Shopp->wpadminurl."admin-ajax.php", "shopp-mp_reinstall_keys"); ?>',
				prefix = '<?php echo $this->sec_prefix; ?>',
				button=$('#reinstall_key'), 
				buttonLabel=button.html(), 
				msg='<?php _e('Private key installed','Shopp'); ?>',
				status='',
				test='<?php echo $this->encrypt('test'); ?>';
			button.upload({
					name: 'pemfile',
					action: ajaxurl,
					enctype: 'multipart/form-data',
					params: {
						action:'mp_reinstall_keys'
					},
					autoSubmit: true,
					onSubmit: function() {
						button.attr('disabled',true).html('<?php _e('Uploading...','Shopp'); ?>').addClass('updating').parent().css('width','100%');
					},
					onComplete: function(results) {
						data = $.parseJSON(results);
						button.removeAttr('disabled').removeClass('updating').html(buttonLabel);
						status='upload success';
						if(!data) {
							msg = '<?php _e('Invalid private key file.','Shopp'); ?>';
							status='upload failure';
						}
						dp.store(data[0],prefix);
						dp.get(prefix);
						if(dp.decrypt(test) != 'test') {
							msg = '<?php _e('Invalid private key file or mismatched key.','Shopp'); ?>'; 
							status='upload failure'; 
						}
						$('<span class="'+status+'">'+msg+'</span>').insertAfter(button);
					}
				});
		}
		<?php
	}	

	function reinstall_keypairs() {
		check_admin_referer('shopp-mp_reinstall_keys');
		$fs = false;
		if (($fs = $_FILES['pemfile']['size']) > 0) {
			$file = fopen($_FILES['pemfile']['tmp_name'], 'r');
			$contents = fread($file, $fs);
			fclose($file);
			if ($contents !== false) {
				$lines = explode("\n", $contents);
				if (count($lines) == 3 && $lines[0] == "-----BEGIN SHOPP PRIVATE KEY-----") {
					$data = stripslashes(urldecode(base64_decode($lines[1])));
					$data = unserialize(base64_decode(urldecode($lines[1])));
					if($data) {
						$data[0] = json_encode($data[0]);
						echo json_encode($data); exit();
					}
				}
			}
		} 
		echo 0;
		exit();
	}
	
	function generate_keypairs() {
		if($this->public_key == 'generate') {
			$res_priv = openssl_pkey_new();
			$priv_details=openssl_pkey_get_details($res_priv);
			$this->private_key = array_map('bin2hex',$priv_details['rsa']);
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