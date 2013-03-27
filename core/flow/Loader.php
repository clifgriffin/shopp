<?php
/**
 * ShoppLoader
 *
 * Controller for lazy loading application code
 *
 * @author Jonathan Davis
 * @version 1.3
 * @copyright Ingenesis Limited, March 2013
 * @package shopp
 * @subpackage autoload
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

class ShoppLoader {

	private static $instance;				// Singleton instance

	protected static $classmap = array();	// A map of class names to files
	protected static $basepath = '';		// Tracks the base path of files in the classmap

	private static $excludes = array('wp_atom_server');

	/**
	 * Setup the loader and register the autoloader
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $path The base path for this loader to use
	 * @return void
	 **/
	private function __construct () {
		spl_autoload_register(array($this,'load'));
	}

	static public function &instance () {
		if (!self::$instance instanceof self)
			self::$instance = new self;
		return self::$instance;
	}

	static public function basepath ( $path ) {
		self::$basepath = realpath($path);
	}

	/**
	 * Imports a new class map to the loader without overriding existing entries
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param array $new Associative array with class names as keys and file paths as values
	 * @param string $basepath The base path to use (if any). Use '' to use full paths, '.' to use paths relative to the base path of the loader instance, or pass a directory path to use as the base path
	 * @return boolean True if successful
	 **/
	public function map ( $new = array(), $basepath = '.' ) {
		if ( empty($new) ) return false;

		if ( '.' == $basepath ) $basepath = self::$basepath;

		$fullpath = create_function('$f','return "' . $basepath . '" . $f;');
		if ( ! empty($basepath) ) $new = array_map($fullpath, $new);

		self::$classmap = array_merge($new,self::$classmap);
		return true;
	}

	/**
	 * Adds a single new entry to map a class to a file for loading
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $classname The name of the class to add
	 * @param string $filepath The full path to the file, or begin path with . to use the base path of the loader instance
	 * @return boolean True if successful
	 **/
	public function add ( $classname, $filepath ) {
		$class = strtolower($classname);

		if ( empty($class) || isset(self::$classmap[ $class ]) ) return false;
		if ( empty($filepath) || ! is_readable($filepath) ) return false;

		self::$classmap[ $class ] = $filepath;
		return true;
	}

	/**
	 * Autoload handler
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $class The name of the class to load
	 * @return boolean True if successful, false otherwise
	 **/
	public function load ( $class ) {

		if ( $this->excluded($class) ) return true;
		elseif ( $this->classmap($class) ) return true;
		elseif ( SHOPP_DEBUG && $this->scanner($class) ) return true;

		return false;
	}

	/**
	 * Require a file based on the class map
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $class The name of the class to load
	 * @return boolean True if successful, false otherwise
	 **/
	protected function classmap ( $class ) {
		$classname = strtolower($class);
		if ( isset(self::$classmap[ $classname ]) )
			return (1 == require self::$classmap[ $classname ]);
		return false;
	}

	protected function excluded ( $class ) {
		$classname = strtolower($class);
		return in_array($classname,self::$excludes);
	}

	/**
	 * Recursively scan files in the base path to add to the classmap
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $class The name of the class to load
	 * @param string $path (optional) The path to scan. Uses the basepath of the loader by default.
	 * @return boolean True if succesful, false otherwise
	 **/
	protected function scanner ( $class, $path = '' ) {
		error_log("Had to scan for $class : ".debug_caller());
		$discovered = array();	// Track the classes not in the map

		if ( empty($path) ) $path = self::$basepath;
		$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator( $path ), RecursiveIteratorIterator::SELF_FIRST);
		foreach( $objects as $name => $object ) {

			$this->scanfile( $name, $path );

		} // endforeach;

		if ( $this->classmap($class) ) return true;
		return false;
	}

	/**
	 * Scans a file for class, interface and trait declarations and adds them to the class map
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $filename The full path to the file to scan
	 * @return boolean True if successful, false otherwise
	 **/
	protected function scanfile ( $filename ) {
		if ( false === strpos($filename,'.php') ) return;

		$comment = false; // Track comment blocks
		$file = fopen($filename, 'r');
		while ( false !== ($line = fgets($file,200)) ) {
			// Skip comment blocks;
			if ( $comment || false !== strpos($line,'/*') ) {
				$comment = true;
				if (false !== strpos($line,'*/')) $comment = false;
				continue;
			}

			// Find class/interface/trait definitions
			if ( false === ($token = strpos($line,'class '))
				 && false === ($token = strpos($line,'interface '))
				 && false === ($token = strpos($line,'trait '))
			) continue;

			// Skip inline comments and strings that start before the token
			if ( false !== ($comments = strpos($line,'//')) && $comments < $token ) continue;
			if ( false !== ($string = strpos($line,'"')) && $string < $token ) continue;
			if ( false !== ($string = strpos($line,"'")) && $string < $token ) continue;

			// Skip tokens that do not start the line or have whitespace preceding it
			if ( 0 != $token && ! in_array( substr($line,$token-1,1), array(' ',"\t","\n","\r")) ) continue;

			list($token,$classname) = explode( ' ', substr($line,$token) );
			$class = strtolower($classname);

			// Skip classes that already exist in the class map
			if ( isset(self::$classmap[ $class ]) ) continue;

			trigger_error("ShoppLoader discovered a missing class declared for $classname in $filename.",E_USER_NOTICE);

			$this->add($class,$filename);

		} // endwhile;

		fclose($file);
	}

}

function &ShoppLoader ( $path = '' ) {
	if ( ! empty($path) ) ShoppLoader::basepath($path);
	return ShoppLoader::instance();
}

ShoppLoader("$path/core");
ShoppLoader()->map(array(
    'account' => '/flow/Account.php',
    'accountstorefrontpage' => '/flow/Storefront.php',
    'address' => '/model/Address.php',
    'admincontroller' => '/flow/Flow.php',
    'alsoboughtproducts' => '/model/Collection.php',
    'amountvoidedevent' => '/model/Events.php',
    'amountvoidedeventrenderer' => '/ui/orders/events.php',
    'authedorderevent' => '/model/Events.php',
    'authedordereventrenderer' => '/ui/orders/events.php',
    'authfailorderevent' => '/model/Events.php',
    'authfailordereventrenderer' => '/ui/orders/events.php',
    'authorderevent' => '/model/Events.php',
    'authordereventrenderer' => '/ui/orders/events.php',
    'autoobjectframework' => '/Framework.php',
    'bestsellerproducts' => '/model/Collection.php',
    'billingaddress' => '/model/Address.php',
    'booleanparser' => '/model/Search.php',
    'callbacksubscription' => '/model/Error.php',
    'capturedorderevent' => '/model/Events.php',
    'capturedordereventrenderer' => '/ui/orders/events.php',
    'capturefailorderevent' => '/model/Events.php',
    'capturefailordereventrenderer' => '/ui/orders/events.php',
    'captureorderevent' => '/model/Events.php',
    'captureordereventrenderer' => '/ui/orders/events.php',
    'cart' => '/model/Cart.php',
    'cartdiscounts' => '/model/Cart.php',
    'cartpromotions' => '/model/Cart.php',
    'cartshipping' => '/model/Cart.php',
    'cartstorefrontpage' => '/flow/Storefront.php',
    'carttax' => '/model/Cart.php',
    'carttotals' => '/model/Cart.php',
    'catalog' => '/model/Catalog.php',
    'catalogproducts' => '/model/Collection.php',
    'catalogstorefrontpage' => '/flow/Storefront.php',
    'categorize' => '/flow/Categorize.php',
    'categoryimage' => '/model/Asset.php',
    'checkoutstorefrontpage' => '/flow/Storefront.php',
    'collectionstorefrontpage' => '/flow/Storefront.php',
    'confirmstorefrontpage' => '/flow/Storefront.php',
    'contentindex' => '/model/Search.php',
    'contentparser' => '/model/Search.php',
    'creditordereventmessage' => '/model/Events.php',
    'customer' => '/model/Customer.php',
    'customeraccountpage' => '/model/Customer.php',
    'customerscsvexport' => '/model/Customer.php',
    'customersexport' => '/model/Customer.php',
	'customersreport' => '/ui/reports/customers.php',
    'customerstabexport' => '/model/Customer.php',
    'customersxlsexport' => '/model/Customer.php',
    'databaseobject' => '/DB.php',
    'db' => '/DB.php',
    'debitordereventmessage' => '/model/Events.php',
    'decryptorderevent' => '/model/Events.php',
    'decryptordereventrenderer' => '/ui/orders/events.php',
    'discountsreport' => '/ui/reports/discounts.php',
    'downloadasset' => '/model/Asset.php',
    'downloadorderevent' => '/model/Events.php',
    'downloadordereventrenderer' => '/ui/orders/events.php',
    'emogrifier' => '/model/Email.php',
    'failureordereventrender' => '/ui/orders/events.php',
    'featuredproducts' => '/model/Collection.php',
    'fileasset' => '/model/Asset.php',
    'flowcontroller' => '/flow/Flow.php',
    'freeorder' => '/model/Gateway.php',
    'gatewayframework' => '/model/Gateway.php',
    'gatewaymodule' => '/model/Gateway.php',
    'gatewaymodules' => '/model/Gateway.php',
    'gatewaysettingsui' => '/model/Gateway.php',
    'imageasset' => '/model/Asset.php',
    'imageprocessor' => '/model/Image.php',
    'imageserver' => '/image.php',
    'imagesetting' => '/model/Asset.php',
    'imagesettings' => '/model/Asset.php',
    'indexproduct' => '/model/Search.php',
	'inventoryreport' => '/ui/reports/inventory.php',
    'invoicedorderevent' => '/model/Events.php',
    'invoicedordereventrenderer' => '/ui/orders/events.php',
    'item' => '/model/Item.php',
    'locationsreport' => '/ui/reports/locations.php',
    'login' => '/flow/Login.php',
    'lookup' => '/model/Lookup.php',
    'maintenancestorefrontpage' => '/flow/Storefront.php',
    'memberaccess' => '/model/Membership.php',
    'membercontent' => '/model/Membership.php',
    'memberplan' => '/model/Membership.php',
    'members' => '/flow/Members.php',
    'membership' => '/model/Membership.php',
    'memberstage' => '/model/Membership.php',
    'metaobject' => '/model/Meta.php',
    'metasetobject' => '/model/Meta.php',
    'mixproducts' => '/model/Collection.php',
    'modulefile' => '/model/Modules.php',
    'moduleloader' => '/model/Modules.php',
    'modulesettingsui' => '/model/Modules.php',
    'newproducts' => '/model/Collection.php',
    'noteorderevent' => '/model/Events.php',
    'noteordereventrenderer' => '/ui/orders/events.php',
    'noticeorderevent' => '/model/Events.php',
    'noticeordereventrenderer' => '/ui/orders/events.php',
    'nusoap_base' => '/model/SOAP.php',
    'nusoap_client' => '/model/SOAP.php',
    'nusoap_fault' => '/model/SOAP.php',
    'nusoap_parser' => '/model/SOAP.php',
    'nusoap_xmlschema' => '/model/SOAP.php',
    'objectmeta' => '/model/Meta.php',
    'onsaleproducts' => '/model/Collection.php',
    'order' => '/flow/Order.php',
    'ordertotalamount' => '/model/Totals.php',
    'orderamountaccountcredit' => '/model/Totals.php',
    'orderamountcredit' => '/model/Totals.php',
    'orderamountdebit' => '/model/Totals.php',
    'orderamountdiscount' => '/model/Totals.php',
    'orderamountfee' => '/model/Totals.php',
    'orderamountgiftcard' => '/model/Totals.php',
    'orderamountgiftcertificate' => '/model/Totals.php',
    'orderamountshipping' => '/model/Totals.php',
    'orderamounttax' => '/model/Totals.php',
    'orderevent' => '/model/Events.php',
    'ordereventmessage' => '/model/Events.php',
    'ordereventrenderer' => '/ui/orders/events.php',
    'ordertotal' => '/model/Totals.php',
    'ordertotals' => '/model/Totals.php',
    'paycard' => '/model/Gateway.php',
    'porterstemmer' => '/model/Search.php',
    'postcodemapping' => '/model/Address.php',
    'price' => '/model/Price.php',
    'product' => '/model/Product.php',
    'productcategory' => '/model/Collection.php',
    'productcategoryfacet' => '/model/Collection.php',
    'productcategoryfacetfilter' => '/model/Collection.php',
    'productcollection' => '/model/Collection.php',
    'productdownload' => '/model/Asset.php',
    'productimage' => '/model/Asset.php',
    'productsreport' => '/ui/reports/products.php',
    'productstorefrontpage' => '/flow/Storefront.php',
    'productsummary' => '/model/Product.php',
    'producttag' => '/model/Collection.php',
    'producttaxonomy' => '/model/Collection.php',
    'promoproducts' => '/model/Collection.php',
    'promote' => '/flow/Promote.php',
    'promotion' => '/model/Promotion.php',
    'purchase' => '/model/Purchase.php',
    'purchased' => '/model/Purchased.php',
    'purchaseorderevent' => '/model/Events.php',
    'purchasescsvexport' => '/model/Purchase.php',
    'purchasesexport' => '/model/Purchase.php',
    'purchasesiifexport' => '/model/Purchase.php',
    'purchasestabexport' => '/model/Purchase.php',
    'purchasestockallocation' => '/model/Purchase.php',
    'purchasesxlsexport' => '/model/Purchase.php',
    'randomproducts' => '/model/Collection.php',
    'rebillorderevent' => '/model/Events.php',
    'recapturedorderevent' => '/model/Events.php',
    'recapturefailorderevent' => '/model/Events.php',
    'refundedorderevent' => '/model/Events.php',
    'refundedordereventrenderer' => '/ui/orders/events.php',
    'refundfailorderevent' => '/model/Events.php',
    'refundfailordereventrenderer' => '/ui/orders/events.php',
    'refundorderevent' => '/model/Events.php',
    'refundordereventrenderer' => '/ui/orders/events.php',
    'registrymanager' => '/Framework.php',
    'relatedproducts' => '/model/Collection.php',
    'report' => '/flow/Report.php',
    'revieworderevent' => '/model/Events.php',
    'reviewordereventrenderer' => '/ui/orders/events.php',
    'saleorderevent' => '/model/Events.php',
    'saleordereventrenderer' => '/ui/orders/events.php',
    'salesreport' => '/ui/reports/sales.php',
    'searchparser' => '/model/Search.php',
    'searchresults' => '/model/Collection.php',
    'searchtextfilters' => '/model/Search.php',
    'service' => '/flow/Service.php',
    'sessionobject' => '/DB.php',
    'settings' => '/model/Settings.php',
    'setup' => '/flow/Setup.php',
    'shippedorderevent' => '/model/Events.php',
    'shippedordereventrenderer' => '/ui/orders/events.php',
    'shippingaddress' => '/model/Address.php',
    'shippingcarrier' => '/model/Shipping.php',
    'shippingframework' => '/model/Shipping.php',
    'shippingmodule' => '/model/Shipping.php',
    'shippingmodules' => '/model/Shipping.php',
    'shippingoption' => '/model/Cart.php',
    'shippingpackage' => '/model/Shipping.php',
    'shippingpackageinterface' => '/model/Shipping.php',
    'shippingpackageitem' => '/model/Shipping.php',
    'shippingpackager' => '/model/Shipping.php',
    'shippingpackaginginterface' => '/model/Shipping.php',
    'shippingreport' => '/ui/reports/shipping.php',
    'shippingsettingsui' => '/model/Shipping.php',
    'shopp_upgrader' => '/flow/Install.php',
    'shopp_upgrader_skin' => '/flow/Install.php',
    'shoppaccountwidget' => '/ui/widgets/account.php',
    'shoppaddon_upgrader' => '/flow/Install.php',
    'shoppadmin' => '/flow/Admin.php',
    'shoppadminlisttable' => '/flow/Admin.php',
    'shoppadminpage' => '/flow/Admin.php',
    'shoppajax' => '/flow/Ajax.php',
    'shoppapi' => '/model/API.php',
    'shoppapifile' => '/model/API.php',
    'shoppapimodules' => '/model/API.php',
    'shoppcartwidget' => '/ui/widgets/cart.php',
    'shoppcategorieswidget' => '/ui/widgets/categories.php',
    'shoppcategorysectionwidget' => '/ui/widgets/section.php',
    'shoppcore_upgrader' => '/flow/Install.php',
    'shoppdeveloperapi' => '/model/API.php',
    'shoppemaildefaultfilters' => '/model/Email.php',
    'shoppemailfilters' => '/model/Email.php',
    'shopperror' => '/model/Error.php',
    'shopperrorlogging' => '/model/Error.php',
    'shopperrornotification' => '/model/Error.php',
	'shopperrorstorefrontnotices' => '/model/Error.php',
    'shopperrors' => '/model/Error.php',
    'shoppfacetedmenuwidget' => '/ui/widgets/facetedmenu.php',
    'shoppflow' => '/flow/Flow.php',
    'shopping' => '/model/Shopping.php',
    'shoppingobject' => '/model/Shopping.php',
    'shoppinstallation' => '/flow/Install.php',
    'shoppkit' => '/Framework.php',
    'shopploader' => '/flow/Loader.php',
    'shoppproductwidget' => '/ui/widgets/product.php',
    'shoppremoteapifile' => '/model/API.php',
    'shoppremoteapimodules' => '/model/API.php',
    'shoppremoteapiserver' => '/remote.php',
    'shoppremoteapiserviceframework' => '/flow/Remote.php',
    'shoppreport' => '/flow/Report.php',
    'shoppreportchart' => '/flow/Report.php',
    'shoppreportcsvexport' => '/flow/Report.php',
    'shoppreportexportframework' => '/flow/Report.php',
    'shoppreportframework' => '/flow/Report.php',
    'shoppreporttabexport' => '/flow/Report.php',
    'shoppreportxlsexport' => '/flow/Report.php',
    'shoppresources' => '/flow/Resources.php',
    'shopprestservice' => '/flow/Remote.php',
    'shoppscripts' => '/flow/Scripts.php',
    'shoppsearchwidget' => '/ui/widgets/search.php',
    'shoppshopperswidget' => '/ui/widgets/shoppers.php',
    'shopptagcloudwidget' => '/ui/widgets/tagcloud.php',
    'shopptmceloader' => '/ui/behaviors/tinymce/dialog.php',
    'shoppui' => '/flow/Admin.php',
    'shortwordparser' => '/model/Search.php',
    'singletonframework' => '/Framework.php',
    'smartcollection' => '/model/Collection.php',
    'soap_fault' => '/model/SOAP.php',
    'soap_parser' => '/model/SOAP.php',
    'soap_transport_http' => '/model/SOAP.php',
    'soapclient' => '/model/SOAP.php',
    'soapval' => '/model/SOAP.php',
    'spec' => '/model/Product.php',
    'storageengine' => '/model/Asset.php',
    'storageengines' => '/model/Asset.php',
    'storagemodule' => '/model/Asset.php',
    'storagesettingsui' => '/model/Asset.php',
    'storefront' => '/flow/Storefront.php',
    'storefrontdashboardpage' => '/flow/Storefront.php',
    'storefrontpage' => '/flow/Storefront.php',
    'storefrontshortcodes' => '/flow/Storefront.php',
    'tagproducts' => '/model/Collection.php',
    'taxreport' => '/ui/reports/tax.php',
    'templateshippingui' => '/model/Shipping.php',
    'textify' => '/model/Email.php',
    'textifya' => '/model/Email.php',
    'textifyaddress' => '/model/Email.php',
    'textifyblockelement' => '/model/Email.php',
    'textifyblockquote' => '/model/Email.php',
    'textifybr' => '/model/Email.php',
    'textifycode' => '/model/Email.php',
    'textifydd' => '/model/Email.php',
    'textifydiv' => '/model/Email.php',
    'textifydl' => '/model/Email.php',
    'textifydt' => '/model/Email.php',
    'textifyem' => '/model/Email.php',
    'textifyfieldset' => '/model/Email.php',
    'textifyh1' => '/model/Email.php',
    'textifyh2' => '/model/Email.php',
    'textifyh3' => '/model/Email.php',
    'textifyh4' => '/model/Email.php',
    'textifyh5' => '/model/Email.php',
    'textifyh6' => '/model/Email.php',
    'textifyheader' => '/model/Email.php',
    'textifyhr' => '/model/Email.php',
    'textifyinlineelement' => '/model/Email.php',
    'textifylegend' => '/model/Email.php',
    'textifyli' => '/model/Email.php',
    'textifylistcontainer' => '/model/Email.php',
    'textifyol' => '/model/Email.php',
    'textifyp' => '/model/Email.php',
    'textifystrong' => '/model/Email.php',
    'textifytable' => '/model/Email.php',
    'textifytabletag' => '/model/Email.php',
    'textifytag' => '/model/Email.php',
    'textifytd' => '/model/Email.php',
    'textifyth' => '/model/Email.php',
    'textifytr' => '/model/Email.php',
    'textifyul' => '/model/Email.php',
    'thanksstorefrontpage' => '/flow/Storefront.php',
    'txnfailordereventrenderer' => '/ui/orders/events.php',
    'txnordereventrenderer' => '/ui/orders/events.php',
    'unstockorderevent' => '/model/Events.php',
    'unstockordereventrenderer' => '/ui/orders/events.php',
    'viewedproducts' => '/model/Collection.php',
    'voidedorderevent' => '/model/Events.php',
    'voidedordereventrenderer' => '/ui/orders/events.php',
    'voidfailorderevent' => '/model/Events.php',
    'voidfailordereventrenderer' => '/ui/orders/events.php',
    'voidorderevent' => '/model/Events.php',
    'voidordereventrenderer' => '/ui/orders/events.php',
    'warehouse' => '/flow/Warehouse.php',
    'wpdatabaseobject' => '/DB.php',
    'wpshoppobject' => '/DB.php',
    'wsdl' => '/model/SOAP.php',
    'xmlquery' => '/model/XML.php',
    'xmlschema' => '/model/SOAP.php'
));