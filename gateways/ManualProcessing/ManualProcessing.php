<?php
/**
 * Manual Processing
 *
 * @author John Dillick
 * @version 1.0
 * @copyright Ingenesis Limited, February 3, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package Shopp
 * @since 1.1 dev
 * @subpackage ManualProcessing
 * 
 * $Id: ManualProcessing.php 810 2010-04-29 18:08:26Z jond $
 **/
class ManualProcessing extends GatewayFramework implements GatewayModule {

	// Standard GatewayModel vars
	var $secure = true;
	var $cards = array();

	// Manual Process vars
	var $public_key = false; // public RSA key
	var $private_key = false; // private RSA key
	var $private_pem = false; // private RSA pem
	var $sec_prefix = false; // security prefix for storing private key in local storage
	var $path = false; // URI path to ManualProcessing.php
	var $opensslconf = null; // openssl configuration

	function ManualProcessing () {
		$this->opensslconf = apply_filters('shopp_mp_opensslconf', array(
			'digest_alg' => 'sha1',
			'x509_extensions' => 'v3_ca',
			'private_key_bits' => 1024,
			'private_key_type' => OPENSSL_KEYTYPE_RSA
			));
		if (defined('SHOPP_OPENSSL_CONF') && false !== $config = realpath(sanitize_path(SHOPP_OPENSSL_CONF))) $this->opensslconf['config'] = $config;
		
		$paycards = Lookup::paycards();
		$this->cards = array_keys($paycards);
		parent::__construct();

		$this->path = sanitize_path(SHOPP_PLUGINURI."/gateways/{$this->module}/");
		$this->public_key = $this->settings['public_key'];
		if (defined('SECRET_AUTH_KEY') && SECRET_AUTH_KEY != '') $this->sec_prefix = SECRET_AUTH_KEY;
		else $this->sec_prefix = DatabaseObject::tablename("");
		
		add_action('init', array(&$this, 'init'), 11);
		add_action('admin_init', array(&$this, 'enqueue_scripts'));		
		add_action('admin_head', array(&$this, 'jsvars'));
		add_action('shopp_order_admin_script', array(&$this, 'decrypt'));
		add_action('wp_ajax_mp_reinstall_keys', array(&$this, 'reinstall_keypairs'));
		add_action('shopp_resource_mp_dl_pem', array(&$this, 'download'));
		add_action('shopp_gateway_ajax_manual-processing', array(&$this, 'destroy'));
		add_filter('shopp_orderui_payment_card', array(&$this, 'orderui'), 10, 2);
	}
	
	function init () {
		if (!empty($this->public_key)) force_ssl_admin(true); // force ssl admin only if Manual Processing is activated and setup complete
	}
	
	function enqueue_scripts () {
		wp_enqueue_script('shopp_rsa', $this->path."behaviors/rsa.js",array(),SHOPP_VERSION,true);
		wp_enqueue_script('shopp_mp_gateway', $this->path."behaviors/mp.js", array('json2'),SHOPP_VERSION,true);
		wp_enqueue_script('shopp.ocupload',SHOPP_ADMIN_URI."/behaviors/ocupload.js",array('jquery'),SHOPP_VERSION,true);		
	}
	
	function actions () {
		add_action('shopp_process_order',array(&$this,'process'));		
	}
	
	function jsvars() {
		$jsvars = "<script type=\"text/javascript\" charset=\"utf-8\">\n";
		$jsvars .= "//<![CDATA[\n";
		$jsvars .= "var BROWSER_UNSUPPORTED='".__('This browser is unsupported for Manual Processing administration. Please use Opera 10.50, Google Chrome 5, Apple Safari 4, Mozilla Firefox 3.6, Internet Explorer 8, or a later version of these browsers.','Shopp')."';\n";
		$jsvars .= "var LOCAL_STORAGE_QUOTA='".__('Browser Local Storage Quota Exceeded, Setup Failed','Shopp')."';\n";
		$jsvars .= "var LOCAL_STORAGE_ERROR='".__('Browser Local Storage Error: ','Shopp')."';\n";
		$jsvars .= "var DECRYPTION_ERROR='".__('There was a failure retrieving your private key from the browser, or this transaction was encrypted on a different keypair.  Data decryption failed.  You may only decrypt secure data from the browser with a proper private key installed.  See your Payment Settings to reinstall the correct private key.','Shopp')."';\n";
		$jsvars .= "var DATE_DESTRUCTION_ERROR='".__('Shopp was unable to destroy the sensitive card data from this order.  This could result from using a browser with AJAX disabled, try decrypting from a supported browswer.','Shopp')."';\n";
		$jsvars .= "var SECRET_DATA='".__('[ENCRYPTED]','Shopp')."';\n";
		$jsvars .= "var sec_card_url='".add_query_arg(array('action'=>'shopp_gateway'),wp_nonce_url(admin_url('admin-ajax.php'),'wp_ajax_shopp_gateway'))."';\n";
		$jsvars .= "//]]>\n";
		$jsvars .= "</script>";
		echo $jsvars;
	}
	
	function process () {
		if($this->Order->Billing->card && $this->Order->Billing->cvv) {
			$sensitive = array('card'=>$this->Order->Billing->card,'cvv'=>$this->Order->Billing->cvv);
			$this->Order->secured[$this->module] = $this->encrypt(json_encode($sensitive));
		}
		$this->Order->transaction($this->txnid());
	}
	
	function settings () {
		if ( is_shopp_secure() ) {			
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
				$success = $this->generate_keypairs();

				if ($success === false) { // catch keypair generation failure
					$this->ui->p(1, array(
						'name' => 'failure',
						'label' => __('Failed Creating Private Key','Shopp'),
						'content' => sprintf(__('Creation of your key pairs failed.  See your Shopp log, located in <a href="%s">System Settings</a>, for more information on the errors.  Resave your Payment Settings, and try again after correcting the configuration problem.','Shopp'),add_query_arg(array('page'=>'shopp-settings-system'),admin_url('admin.php')))
					));
					$this->ui->hidden(0,array(
						'name' => 'public_key',
						'value' => ''
					));
				} else {				
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
				}
			} else {  // all setup!
				$this->ui->hidden(0,array(
					'name' => 'public_key',
					'value' => $this->public_key
				));
				$this->ui->p(1, array(
					'name' => 'complete',
					'label' => __("Setup Complete",'Shopp'),
					'content' => __('The Manual Processing payment method setup is complete.  Please keep your private key in a secure place and backed up.  If you need to re-cache your private key in your browser, or use this key in another browser, click the "Reinstall Key" button to select your saved private key file.','Shopp')
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
		?>ManualProcessing.behaviors = function (){ 
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
		?>ManualProcessing.behaviors = function (){
			var prefix='<?php echo $this->sec_prefix; ?>',
				msg='',
				test='<?php echo $this->encrypt('test'); ?>',
				button=$('#finish'),
				href = '<?php echo admin_url('admin.php')."?src=mp_dl_pem&private=".urlencode($this->private_pem); ?>';	

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
		?>ManualProcessing.behaviors = function (){ 
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
					button.removeAttr('disabled').removeClass('updating').html(buttonLabel);
					status='upload success';
					if(!results) {
						msg = '<?php _e('Invalid private key file.','Shopp'); ?>';
						status='upload failure';
					}
					dp.store(results,prefix);
					dp.get(prefix);
					if(dp.decrypt(test) != 'test') {
						msg = '<?php _e('Invalid private key file or mismatched key.','Shopp'); ?>'; 
						status='upload failure'; 
					}
					$('<span class="'+status+'">'+msg+'</span>').insertAfter(button);
				}
			});
		}<?php
	}	

	function reinstall_keypairs() {
		check_admin_referer('shopp-mp_reinstall_keys');
		$fs = false;
		if ($_FILES['pemfile']['size'] > 0) {
			$contents = file_get_contents($_FILES['pemfile']['tmp_name']);
			$RSA = new PEMparser($contents);
			$this->private_key = $RSA->parse();
			echo json_encode($this->private_key); 
			exit();
		} 
		echo 0;
		exit();
	}
	
	function generate_keypairs() {
		if($this->public_key == 'generate') {
			$res_priv = openssl_pkey_new($this->opensslconf);
			if ($res_priv === false) {
				new ShoppError(__('Private key resource creation failed. openssl_error_string reports ','Shopp').openssl_error_string(),false,SHOPP_ERR);
				return false;
			}
						
			// Get private key
			$success = openssl_pkey_export($res_priv, $this->private_pem, null, $this->opensslconf);
			if ($success === false) {
				new ShoppError(__('Private key PEM export failed. openssl_error_string reports ','Shopp').openssl_error_string(),false,SHOPP_ERR);
				return false;
			}
			$RSA = new PEMparser($this->private_pem);
			$this->private_key = $RSA->parse();

			// Get public key
			$details=openssl_pkey_get_details($res_priv);
			if ($details === false) {
				new ShoppError(__('Unable to get public key. openssl_error_string reports ','Shopp').openssl_error_string(),false,SHOPP_ERR);
				return false;				
			}
			
			$this->public_key = urlencode($details['key']);

			echo "if(dp.supported) dp.store('".json_encode($this->private_key)."','".$this->sec_prefix."');\n";
		}
		return true;
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
		if($purchase->secured && current_user_can('shopp_financials')){
			$decrypt = "decrypt('".$purchase->secured[$this->module]."','".$this->sec_prefix."','".$purchase->id."');\n";
			echo '$(\'#reveal\').click(function(){'.$decrypt.'});';
		}
	}
	
	function download() {
		if($_REQUEST['private']) {
			//error_log("private: ".$_REQUEST['private']);
			header("Content-type: application/octet-stream");
			header("Content-Disposition: attachment; filename=\"private.pem\""); 
			echo $_REQUEST['private'];
			exit();
		}
	}
	
	/**
	 * orderui filters content for the transaction_metabox from the order ui
	 *
	 * @author John Dillick
	 * @since 1.1
	 * 
	 * @param string $content Description...
	 * @return void Description...
	 **/
	function orderui ($content, $Purchase) {
		if($Purchase->secured && current_user_can('shopp_financials')){
			$content = '';
			$content .= '<p class="error">'.__('Decrypt the secured card data only at transaction time.  Once revealed, sensitive card information is automatically and permanently destroyed.').'</p>';
			$content .= '<ul>';
			$content .= '<li><strong>'.__('Secured Card','Shopp').':</strong> <span id="card">'.__('[ENCRYPTED]','Shopp').'</span></li>';
			$content .= '<li><strong>'.__('Secured CVV','Shopp').':</strong> <span id="cvv">'.__('[ENCRYPTED]','Shopp').'</span></li>';
			$content .= '<li><strong>'.__('Expiration','Shopp').':</strong> '._d('m/Y', $Purchase->cardexpires).'</li>';
			$content .= '</ul>';
			$content .= '<form><div><button id="reveal" type="button" class="button" >'.__('Decrypt Card').'</button></div></form>';
			return $content;
		} else return $content;	
	}
	
	/**
	 * destroy clears out the secured card data
	 *
	 * @author John Dillick
	 * @since 1.1
	 * 
	 * @param array $args ajax request variables
	 * @return void 
	 **/
	function destroy ($args) {
		if(isset($args['pid']) && current_user_can('shopp_financials')) {
			$purchase = new Purchase($args['pid']);
			if($purchase->secured) $purchase->secured = false;
			$purchase->save();
			
			unset($purchase);
			$purchase = new Purchase($args['pid']);
			if($purchase->secured) { 
				if(SHOPP_DEBUG) new ShoppError("Unable to destroy sensitive card data for order ".$purchase->id,'manual_processing_destroy',SHOPP_DEBUG_ERR);
				die('-1'); 
			}
			else {
				if(SHOPP_DEBUG) new ShoppError("Successfully destroyed sensitive card data for order ".$purchase->id,'manual_processing_destroy',SHOPP_DEBUG_ERR);
			 die('1');
			}
		}
		else die('-1');
	}
	
	
} // END class ManualProcessing


/**
 * PEMparser class
 *
 * Parses an RSA Private Key in PEM-format
 * 
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 **/
class PEMparser extends ASNValue {

	static $fields = array('','n','e','d','p','q','dmp1','dmq1','iqmp');
	var $sequence = array();
	
	function __construct ($pem) {
		$DER = self::convert($pem);
		$this->decode($DER);
		$this->sequence = $this->GetSequence();
	}
	
	/**
	 * Parses the sequences into RSA private key fields
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return array The private key fields
	 **/
	function parse () {
		$_ = array();
		foreach ($this->sequence as $i => $entry) {
			if ($i == 0) continue;
			if ($i == 2) $_[self::$fields[$i]] = str_pad(dechex($entry->GetInt()),6,'0',STR_PAD_LEFT);
			else $_[self::$fields[$i]] = bin2hex($entry->GetIntBuffer());
		}
		return $_;
	}
	
	/**
	 * Converts a PEM string to binary DER-formatted data
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $pem The PEM string
	 * @return string Binary DER
	 **/
	protected static function convert ($pem) {
		$lines = explode("\n", trim($pem));
		$lines = array_slice($lines,1,count($lines)-2);
	    $string = implode('', $lines);
	    $der = base64_decode($string);
	    return $der;
	}		
}

/**
 * ASNValue class
 * 
 * Reads ASN.1 notation from DER-formatted data. Included with generous permission from Anton Oliinyk (Pumka.net).
 * 
 * @author Anton Oliinyk (contact@pumka.net)
 * @copyright December 19th, 2009 by Anton Oliinyk {@link http://blog.pumka.net/2009/12/19/reading-writing-and-converting-rsa-keys-in-pem-der-publickeyblob-and-privatekeyblob-formats/}
 * @since 1.1
 * @package shopp
 **/
class ASNValue {
    const TAG_INTEGER   = 0x02;
    const TAG_BITSTRING = 0x03;
    const TAG_SEQUENCE  = 0x30;
    
    public $tag;
    public $value;
    
    function __construct ($tag=0x00, $value='') {
        $this->tag = $tag;
        $this->value = $value;
    }
    
    function encode() {   
		$result = chr($this->tag);		// Write type
		$size = strlen($this->value);	// Write size
		if ($size < 127) {
			$result .= chr($size);		// Write size as is
		} else {
			// Prepare length sequence
			$sizeBuf = self::IntToBin($size);

			// Write length sequence
			$firstByte = 0x80 + strlen($sizeBuf);
			$result .= chr($firstByte) . $sizeBuf;
		}

		$result .= $this->value; // Write value

		return $result;
    }
    
    function decode (&$Buffer) {   
		$this->tag = self::ReadByte($Buffer);	// Read type
		$firstByte = self::ReadByte($Buffer);	// Read first byte

		if ($firstByte < 127) {
			$size = $firstByte;
		} else if ($firstByte > 127) {
			$sizeLen = $firstByte - 0x80;
			//Read length sequence
			$size = self::BinToInt(self::ReadBytes($Buffer, $sizeLen));
		} else {
			new ShoppError('Invalid ASN length value while decoding the exported PEM data for the generated private key.','manualprocess_asnvalue_decode',SHOPP_DEBUG_ERR);
		}

		$this->value = self::ReadBytes($Buffer, $size);
    }
    
    protected static function ReadBytes (&$Buffer, $Length) {
		$result = substr($Buffer, 0, $Length);
		$Buffer = substr($Buffer, $Length);

		return $result;
    }
    
    protected static function ReadByte(&$Buffer) {      
        return ord(self::ReadBytes($Buffer, 1));
    }
    
    protected static function BinToInt($Bin) {    
		$len = strlen($Bin);
		$result = 0;
		for ($i=0; $i<$len; $i++) {
			$curByte = self::ReadByte($Bin);
			$result += $curByte << (($len-$i-1)*8);
		}

		return $result;
    }
    
    protected static function IntToBin($Int) {
        $result = '';
        do {
            $curByte = $Int % 256;
            $result .= chr($curByte);

            $Int = ($Int - $curByte) / 256;
        } while ($Int > 0);

        $result = strrev($result);
        
        return $result;
    }
    
    function SetIntBuffer($Value) {
        if (strlen($Value) > 1) {
            $firstByte = ord($Value{0});
            if ($firstByte & 0x80) { //first bit set
                $Value = chr(0x00) . $Value;
            }
        }
        
        $this->value = $Value;
    }
    
    function GetIntBuffer() {        
        $result = $this->value;
        if (ord($result{0}) == 0x00) {
            $result = substr($result, 1);
        }
        
        return $result;
    }
    
    function SetInt($Value) {
        $Value = self::IntToBin($Value);
        
        $this->SetIntBuffer($Value);
    }   
    
    function GetInt() {
        $result = $this->GetIntBuffer();
        $result = self::BinToInt($result);
        
        return $result;
    }
    
    function SetSequence($Values) {
        $result = '';
        foreach ($Values as $item) {
            $result .= $item->Encode();            
        }   
        
        $this->value = $result;
    }   
    
    function GetSequence() {
        $result = array();
        $seq = $this->value;
        while (strlen($seq)) {
            $val = new ASNValue();
            $val->Decode($seq);
            $result[] = $val;
        }  
        
        return $result;
    }    
}

?>