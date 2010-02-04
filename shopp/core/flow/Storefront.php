<?php
/**
 * Storefront
 * 
 * Flow controller for the front-end shopping interfaces
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 12, 2010
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @subpackage storefront
 **/

/**
 * Storefront
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package shopp
 * @subpackage storefront
 **/
class Storefront extends FlowController {

	static $Pages = array(
		'catalog'	=> array('name'=>'shop','title'=>'Shop','shortcode'=>'[catalog]'),
		'cart'		=> array('name'=>'cart','title'=>'Cart','shortcode'=>'[cart]'),
		'checkout'	=> array('name'=>'checkout','title'=>'Checkout','shortcode'=>'[checkout]'),
		'account'	=> array('name'=>'account','title'=>'Your Orders','shortcode'=>'[account]')
	);
	var $Settings = false;
	var $Page = false;
	var $Catalog = false;
	var $Category = false;
	var $Product = false;
	var $breadcrumb = false;
	var $search = false;
	var $browsing = array();
	
	/**
	 * Storefront constructor
	 *
	 * @author Jonathan Davis
	 * 
	 * @return void
	 **/
	function __construct () {
		global $Shopp;
		parent::__construct();
		
		$this->Settings = &$Shopp->Settings;
		$this->Catalog = &$Shopp->Catalog;
		$this->Category = &$Shopp->Category;
		$this->Product = &$Shopp->Product;
		
		$Pages = $this->Settings->get('pages');
		if (!empty($Pages)) $this->Pages = $Pages;
		
		ShoppingObject::store('search',$this->search);
		ShoppingObject::store('browsing',$this->browsing);
		
		add_action('wp', array(&$this, 'pageid'));
		add_action('wp', array(&$this, 'cart'));
		add_action('wp', array(&$this, 'catalog'));
		add_action('wp', array(&$this, 'shortcodes'));
		add_action('wp', array(&$this, 'behaviors'));
		
	}
	
	/**
	 * parse function
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function pageid () {
		global $Shopp,$wp_query;
		if (empty($wp_query->posts)) return false;

		// Identify the current page
		foreach ($this->Pages as &$Page) {
			if ($Page['id'] == $wp_query->posts[0]->ID) $this->Page = $Page; break;
		}

	}

	/**
	 * behaviors()
	 * Dynamically includes necessary JavaScript and stylesheets as needed in 
	 * public shopping pages handled by Shopp */
	function behaviors () {
		global $Shopp;

		global $wp_query;
		$object = $wp_query->get_queried_object();

		if(isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == "on") {
			add_filter('option_siteurl', 'force_ssl');
			add_filter('option_home', 'force_ssl');
			add_filter('option_url', 'force_ssl');
			add_filter('option_wpurl', 'force_ssl');
			add_filter('option_stylesheet_url', 'force_ssl');
			add_filter('option_template_url', 'force_ssl');
			add_filter('script_loader_src', 'force_ssl');
		}

		// Determine which tag is getting used in the current post/page
		$tag = false;
		$tagregexp = join( '|', array_keys($this->shortcodes) );
		foreach ($wp_query->posts as $post) {
			if (preg_match('/\[('.$tagregexp.')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\1\])?/',$post->post_content,$matches))
				$tag = $matches[1];
		}

		// Include stylesheets and javascript based on whether shopp shortcodes are used
		add_action('wp_head', array(&$this, 'header'));
		add_action('wp_footer', array(&$this, 'footer'));

		$loading = $this->Settings->get('script_loading');
		if (!$loading || $loading == "global" || $tag !== false) {
			wp_enqueue_script('jquery');
			wp_enqueue_script('shopp-settings',add_query_arg('shopp_lookup','settings.js',get_bloginfo('url')));
			wp_enqueue_script("shopp-thickbox",SHOPP_PLUGINURI."/core/ui/behaviors/thickbox.js",array('jquery'),SHOPP_VERSION,true);
			wp_enqueue_script("shopp",SHOPP_PLUGINURI."/core/ui/behaviors/shopp.js",array('jquery'),SHOPP_VERSION,true);
		}

		if ($tag == "checkout")
			wp_enqueue_script('shopp_checkout',SHOPP_PLUGINURI."/core/ui/behaviors/checkout.js",array('jquery'),SHOPP_VERSION,true);		


	}

	/**
	 * shortcodes()
	 * Handles shortcodes used on Shopp-installed pages and used by
	 * site owner for including categories/products in posts and pages */
	function shortcodes () {

		$this->shortcodes = array();
		$this->shortcodes['catalog'] = array(&$this,'catalog_page');
		$this->shortcodes['cart'] = array(&$this,'cart_page');
		$this->shortcodes['checkout'] = array(&$this,'checkout_page');
		$this->shortcodes['account'] = array(&$this,'account_page');
		$this->shortcodes['product'] = array(&$this,'product_shortcode');
		$this->shortcodes['category'] = array(&$this,'category_shortcode');

		foreach ($this->shortcodes as $name => &$callback)
			if ($this->Settings->get("maintenance") == "on")
				add_shortcode($name,array(&$this,'maintenance_shortcode'));
			else add_shortcode($name,$callback);
	}

	/**
	 * titles ()
	 * Changes the Shopp catalog page titles to include the product
	 * name and category (when available) */
	function titles ($title,$sep=" &mdash; ",$placement="left") {
		if (empty($this->Product->name) && empty($this->Category->name)) return $title;

		if ($placement == "right") {
			if (!empty($this->Product->name)) $title = $this->Product->name." $sep ".$title;
			if (!empty($this->Category->name)) $title = $this->Category->name." $sep ".$title;
		} else {
			if (!empty($this->Category->name)) $title .= " $sep ".$this->Category->name;
			if (!empty($this->Product->name)) $title .=  " $sep ".$this->Product->name;
		}

		return $title;
	}

	// Override the post title for internal Shopp checkout process pages
	function pagetitle ($title,$post_id=false) {
		if (!$post_id) return $title;
		global $wp;

		$pages = $this->Settings->get('pages');

		if (isset($wp->query_vars['shopp_proc']) && 
			$post_id == $pages['checkout']['id']) {
			switch(strtolower($wp->query_vars['shopp_proc'])) {
				case "thanks": $title = apply_filters('shopp_thanks_pagetitle',__('Thank You!','Shopp')); break;
				case "confirm-order": $title = apply_filters('shopp_confirmorder_pagetitle',__('Confirm Order','Shopp')); break;
			}
		}
		return $title;
	}

	function feeds () {
		global $Shopp;
		if (empty($this->Category)):?>

<link rel='alternate' type="application/rss+xml" title="<?php htmlentities(bloginfo('name')); ?> New Products RSS Feed" href="<?php echo $this->shopuri.((SHOPP_PERMALINKS)?'feed/':'&shopp_lookup=newproducts-rss'); ?>" />
<?php
			else:
			$uri = 'category/'.$this->Category->uri;
			if ($this->Category->slug == "tag") $uri = $this->Category->slug.'/'.$this->Category->tag;

			if (SHOPP_PERMALINKS) $link = user_trailingslashit($Shopp->shopuri.urldecode($uri).'/feed');
			else $link = add_query_arg(array('shopp_category'=>urldecode($this->Category->uri),'shopp_lookup'=>'category-rss'),$this->shopuri);
			?>

<link rel='alternate' type="application/rss+xml" title="<?php htmlentities(bloginfo('name')); ?> <?php echo urldecode($this->Category->name); ?> RSS Feed" href="<?php echo $link; ?>" />
<?php
		endif;
	}

	function updatesearch () {
		global $wp_query;
		$wp_query->query_vars['s'] = $this->search;
	}

	function metadata () {
		$keywords = false;
		$description = false;
		if (!empty($this->Product)) {
			if (empty($this->Product->tags)) $this->Product->load_data(array('tags'));
			foreach($this->Product->tags as $tag)
				$keywords .= (!empty($keywords))?", {$tag->name}":$tag->name;
			$description = $this->Product->summary;
		} elseif (!empty($this->Category)) {
			$description = $this->Category->description;
		}
		$keywords = attribute_escape(apply_filters('shopp_meta_keywords',$keywords));
		$description = attribute_escape(apply_filters('shopp_meta_description',$description));
		?>
		<?php if ($keywords): ?><meta name="keywords" content="<?php echo $keywords; ?>" /><?php endif; ?>
		<?php if ($description): ?><meta name="description" content="<?php echo $description; ?>" /><?php endif;
	}

	function canonurls ($url) {
		global $Shopp;
		if (!empty($Shopp->Product->slug)) return $Shopp->Product->tag('url','echo=0');
		if (!empty($Shopp->Category->slug)) return $Shopp->Category->tag('url','echo=0');
		return $url;
	}

	/**
	 * header()
	 * Adds stylesheets necessary for Shopp public shopping pages */
	function header () {
		global $wp;
?>
<link rel='stylesheet' href='<?php echo htmlentities( add_query_arg(array('shopp_lookup'=>'catalog.css','ver'=>urlencode(SHOPP_VERSION)),get_bloginfo('url'))); ?>' type='text/css' />
<link rel='stylesheet' media='all' href='<?php echo SHOPP_TEMPLATES_URI; ?>/shopp.css?ver=<?php echo urlencode(SHOPP_VERSION); ?>' type='text/css' />
<link rel='stylesheet' href='<?php echo SHOPP_PLUGINURI; ?>/core/ui/styles/thickbox.css?ver=<?php echo urlencode(SHOPP_VERSION); ?>' type='text/css' />
<?php if (is_shopp_page('account') || (isset($wp->query_vars['shopp_proc']) && $wp->query_vars['shopp_proc'] == "sold")): ?>
<link rel='stylesheet' media='print' href='<?php echo SHOPP_PLUGINURI; ?>/core/ui/styles/printable.css?ver=<?php echo urlencode(SHOPP_VERSION); ?>' type='text/css' />
<?php endif; ?>
<?php 
	$canonurl = $this->canonurls(false);
	if (is_shopp_page('catalog') && !empty($canonurl)): ?><link rel='canonical' href='<?php echo $canonurl ?>' /><?php
	endif;
	}

	/**
	 * footer()
	 * Adds report information and custom debugging tools to the public and admin footers */
	function footer () {
		if (!WP_DEBUG) return true;
		if (!current_user_can('manage_options')) return true;
		$db = DB::get();
		global $wpdb;

		if (function_exists('memory_get_peak_usage'))
			$this->_debug->memory .= "End: ".number_format(memory_get_peak_usage(true)/1024/1024, 2, '.', ',') . " MB<br />";
		elseif (function_exists('memory_get_usage'))
			$this->_debug->memory .= "End: ".number_format(memory_get_usage(true)/1024/1024, 2, '.', ',') . " MB";

		echo '<script type="text/javascript">'."\n";
		echo '//<![CDATA['."\n";
		echo 'var memory_profile = "'.$this->_debug->memory.'";';
		echo 'var wpquerytotal = '.$wpdb->num_queries.';';
		echo 'var shoppquerytotal = '.count($db->queries).';';
		echo '//]]>'."\n";
		echo '</script>'."\n";

	}

	function catalog () {
		global $Shopp,$wp;
		$options = array();

		// add_filter('redirect_canonical','cancel_canonical_redirect');

		$type = "catalog";
		if (isset($wp->query_vars['shopp_category']) &&
			$category = $wp->query_vars['shopp_category']) $type = "category";
		if (isset($wp->query_vars['shopp_pid']) && 
			$productid = $wp->query_vars['shopp_pid']) $type = "product";
		if (isset($wp->query_vars['shopp_product']) && 
			$productname = $wp->query_vars['shopp_product']) $type = "product";

		if (isset($wp->query_vars['shopp_tag']) && 
			$tag = $wp->query_vars['shopp_tag']) {
			$type = "category";
			$category = "tag";
		}

		$referer = wp_get_referer();
		$target = "blog";
		if (isset($wp->query_vars['st'])) $target = $wp->query_vars['st'];
		if (!empty($wp->query_vars['s']) && // Search query is present and...
			// The search target is set to shopp
			($target == "shopp" 
				// The referering page includes a Shopp catalog page path
				|| strpos($referer,$Shopp->link('catalog')) !== false || 
				strpos($referer,'page_id='.$this->Pages['catalog']['id']) !== false || 
				// Or the referer was a search that matches the last recorded Shopp search
				substr($referer,-1*(strlen($this->search))) == $this->search || 
				// Or the blog URL matches the Shopp catalog URL (Takes over search for store-only search)
				trailingslashit(get_bloginfo('url')) == $Shopp->link('catalog') || 
				// Or the referer is one of the Shopp cart, checkout or account pages
				$referer == $Shopp->link('cart') || $referer == $Shopp->link('checkout') || 
				$referer == $Shopp->link('account'))) {
			$this->search = $wp->query_vars['s'];
			$wp->query_vars['s'] = "";
			$wp->query_vars['pagename'] = $this->Pages['catalog']['name'];
			add_action('wp_head', array(&$this, 'updatesearch'));
			if ($type != "product") $type = "category"; 
			$category = "search-results";
		}

		// Load a category/tag
		if (!empty($category) || !empty($tag)) {
			if (isset($this->search)) $options = array('search'=>$this->search);
			if (isset($tag)) $options = array('tag'=>$tag);

			// Split for encoding multi-byte slugs
			$slugs = explode("/",$category);
			$category = join("/",array_map('urlencode',$slugs));

			// Load the category
			$Shopp->Category = Catalog::load_category($category,$options);
			$this->breadcrumb = (isset($tag)?"tag/":"").$Shopp->Category->uri;
		} 

		if (empty($category) && empty($tag) && 
			empty($productid) && empty($productname)) 
			$this->breadcrumb = "";

		// Category Filters
		if (!empty($Shopp->Category->slug)) {
			if (empty($this->browsing[$Shopp->Category->slug]))
				$this->browsing[$Shopp->Category->slug] = array();
			$CategoryFilters =& $this->browsing[$Shopp->Category->slug];

			// Add new filters
			if (isset($_GET['shopp_catfilters'])) {
				if (is_array($_GET['shopp_catfilters'])) {
					$CategoryFilters = array_filter(array_merge($CategoryFilters,$_GET['shopp_catfilters']));
					$CategoryFilters = stripslashes_deep($CategoryFilters);
					if (isset($wp->query_vars['paged'])) $wp->query_vars['paged'] = 1; // Force back to page 1
				} else unset($this->browsing[$Shopp->Category->slug]);
			}

		}

		// Catalog sort order setting
		if (isset($_GET['shopp_orderby']))
			$this->browsing['orderby'] = $_GET['shopp_orderby'];

		if (empty($Shopp->Category)) $Shopp->Category = Catalog::load_category($this->breadcrumb,$options);

		// Find product by given ID
		if (!empty($productid) && empty($Shopp->Product->id))
			$Shopp->Product = new Product($productid);

		// Find product by product slug
		if (!empty($productname) && empty($Shopp->Product->id))
			$Shopp->Product = new Product(urlencode($productname),"slug");

		// Product must be published
		if (!empty($Shopp->Product->id) && $Shopp->Product->published == "off" || empty($Shopp->Product->id))
			$Shopp->Product = false;

		// No product found, try to load a page instead
		if ($type == "product" && !$Shopp->Product) 
			$wp->query_vars['pagename'] = $wp->request;

		$Shopp->Catalog = new Catalog($type);
		add_filter('wp_title', array(&$this, 'titles'),10,3);
		add_action('wp_head', array(&$this, 'metadata'));
		add_action('wp_head', array(&$this, 'feeds'));
	}

	function catalog_page () {
		global $Shopp;
		if (SHOPP_DEBUG) new ShoppError('Displaying catalog page request: '.$_SERVER['REQUEST_URI'],'shopp_catalog',SHOPP_DEBUG_ERR);

		ob_start();
		switch ($Shopp->Catalog->type) {
			case "product": 
				if (file_exists(SHOPP_TEMPLATES."/product-{$Shopp->Product->id}.php"))
					include(SHOPP_TEMPLATES."/product-{$Shopp->Product->id}.php");
				else include(SHOPP_TEMPLATES."/product.php"); break;

			case "category":
				if (isset($Shopp->Category->smart) && 
						file_exists(SHOPP_TEMPLATES."/category-{$Shopp->Category->slug}.php"))
					include(SHOPP_TEMPLATES."/category-{$Shopp->Category->slug}.php");
				elseif (isset($Shopp->Category->id) && 
					file_exists(SHOPP_TEMPLATES."/category-{$Shopp->Category->id}.php"))
					include(SHOPP_TEMPLATES."/category-{$Shopp->Category->id}.php");
				else include(SHOPP_TEMPLATES."/category.php"); break;

			default: include(SHOPP_TEMPLATES."/catalog.php"); break;
		}
		$content = ob_get_contents();
		ob_end_clean();

		$classes = $Shopp->Catalog->type;
		if (!isset($_COOKIE['shopp_catalog_view'])) {
			// No cookie preference exists, use shopp default setting
			$view = $Shopp->Settings->get('default_catalog_view');
			if ($view == "list") $classes .= " list";
			if ($view == "grid") $classes .= " grid";
		} else {
			if ($_COOKIE['shopp_catalog_view'] == "list") $classes .= " list";
			if ($_COOKIE['shopp_catalog_view'] == "grid") $classes .= " grid";
		}			

		return apply_filters('shopp_catalog','<div id="shopp" class="'.$classes.'">'.$content.'<div class="clear"></div></div>');
	}

	/**
	 * cart()
	 * Handles shopping cart requests */
	function cart () {
		global $Shopp;
		if (isset($_REQUEST['shopping']) && strtolower($_REQUEST['shopping']) == "reset") {
			$Shopp->Shopping->reset();
			shopp_redirect($Shopp->link('catalog'));
		}


		if (empty($_REQUEST['cart'])) return true;
		
		do_action('shopp_cart_request');
		
		// if ($_REQUEST['cart']) $Shopp->Order->Cart->request();

		// echo BR.BR."<pre>";
		// print_r($Shopp->Order->Cart);
		// echo BR.BR."</pre>";

		
		// if ($Shopp->Order->Cart->changed) $Shopp->Order->Cart->totals();
		if (isset($_REQUEST['ajax'])) $Shopp->Order->Cart->ajax();
		$redirect = false;
		if (isset($_REQUEST['redirect'])) $redirect = $_REQUEST['redirect'];
		switch ($redirect) {
			case "checkout": shopp_redirect($Shopp->link($redirect,true)); break;
			default: 
				if (!empty($_REQUEST['redirect']))
					shopp_redirect(esc_url($Shopp->link($_REQUEST['redirect'])));
				else shopp_redirect($Shopp->link('cart'));
		}
	}

	/**
	 * checkout()
	 * Handles checkout process */
	function checkout ($wp) {
		// Set wp page-post titles for checkout process pages
		if (isset($wp->query_vars['shopp_proc'])) add_filter('the_title', array(&$this, 'pagetitle'),10,2);

		$pages = $this->Settings->get('pages');
		// If checkout page requested
		// Note: we have to use custom detection here as 
		// the wp->post vars are not available at this point
		// to make use of is_shopp_page()
		if (((SHOPP_PERMALINKS && isset($wp->query_vars['pagename']) 
			&& $wp->query_vars['pagename'] == $pages['checkout']['permalink'])
			|| (isset($wp->query_vars['page_id']) && $wp->query_vars['page_id'] == $pages['checkout']['id']))
		 	&& $wp->query_vars['shopp_proc'] == "checkout") {

			$this->Cart->updated();
			$this->Cart->totals();

			if (!$this->Cart->freeshipping && $this->Cart->data->ShippingPostcodeError) {
				header('Location: '.$Shopp->link('cart'));
				exit();
			}

			// Force secure checkout page if its not already
			$secure = true;
			$gateway = $this->Settings->get('payment_gateway');
			if (strpos($gateway,"TestMode") !== false 
					|| isset($wp->query_vars['shopp_xco']) 
					|| $this->Cart->orderisfree()) 
				$secure = false;

			if ($secure && !$this->secure && !SHOPP_NOSSL) {
				header('Location: '.$Shopp->link('checkout',$secure));
				exit();
			}
		}

		// Cancel this process if there is no order data
		if (!isset($this->Cart->data->Order)) return;
		$Order = $this->Cart->data->Order;

		// Intercept external checkout processing
		if (!empty($wp->query_vars['shopp_xco'])) {
			if ($this->gateway($wp->query_vars['shopp_xco'])) {
				if ($wp->query_vars['shopp_proc'] != "confirm-order" && 
						!isset($_POST['checkout'])) {
					$this->Gateway->checkout();
					$this->Gateway->error();
				}
			}
		}

		// Cancel if no checkout process detected
		if (empty($_POST['checkout'])) return true;
		// Handoff to order processing
		if ($_POST['checkout'] == "confirmed") return $this->Flow->order();
		// Cancel if checkout process is not ready for processing
		if ($_POST['checkout'] != "process") return true;
		// Cancel if processing a login from the checkout form
		if (isset($_POST['process-login']) 
			&& $_POST['process-login'] == "true") return true;

		// Start processing the checkout form
		$_POST = attribute_escape_deep($_POST);

		$_POST['billing']['cardexpires'] = sprintf("%02d%02d",$_POST['billing']['cardexpires-mm'],$_POST['billing']['cardexpires-yy']);

		// If the card number is provided over a secure connection
		// Change the cart to operate in secure mode
		if (isset($_POST['billing']['card']) && is_shopp_secure())
			$this->Cart->secured(true);

		// Sanitize the card number to ensure it only contains numbers
		$_POST['billing']['card'] = preg_replace('/[^\d]/','',$_POST['billing']['card']);

		if (isset($_POST['data'])) $Order->data = stripslashes_deep($_POST['data']);
		if (isset($_POST['info'])) $Order->data = stripslashes_deep($_POST['info']);

		if (empty($Order->Customer))
			$Order->Customer = new Customer();
		$Order->Customer->updates($_POST);

		if (isset($_POST['confirm-password']))
			$Order->Customer->confirm_password = $_POST['confirm-password'];

		if (empty($Order->Billing))
			$Order->Billing = new Billing();
		$Order->Billing->updates($_POST['billing']);

		if (!empty($_POST['billing']['cardexpires-mm']) && !empty($_POST['billing']['cardexpires-yy'])) {
			$Order->Billing->cardexpires = mktime(0,0,0,
					$_POST['billing']['cardexpires-mm'],1,
					($_POST['billing']['cardexpires-yy'])+2000
				);
		} else $Order->Billing->cardexpires = 0;

		$Order->Billing->cvv = preg_replace('/[^\d]/','',$_POST['billing']['cvv']);

		if ($this->Cart->data->Shipping) {
			if (empty($Order->Shipping))
				$Order->Shipping = new Shipping();

			if (isset($_POST['shipping'])) $Order->Shipping->updates($_POST['shipping']);
			if (!empty($_POST['shipmethod'])) $Order->Shipping->method = $_POST['shipmethod'];
			else $Order->Shipping->method = key($this->Cart->data->ShipCosts);

			// Override posted shipping updates with billing address
			if ($_POST['sameshipaddress'] == "on")
				$Order->Shipping->updates($Order->Billing,
					array("_datatypes","_table","_key","_lists","id","created","modified"));
		} else $Order->Shipping = new Shipping(); // Use blank shipping for non-Shipped orders

		$estimatedTotal = $this->Cart->data->Totals->total;
		$this->Cart->updated();
		$this->Cart->totals();
		if ($this->Cart->validate() !== true) return;
		else $Order->Customer->updates($_POST); // Catch changes from validation

		// If the cart's total changes at all, confirm the order
		if ($estimatedTotal != $this->Cart->data->Totals->total || 
				$this->Settings->get('order_confirmation') == "always") {
			$gateway = $this->Settings->get('payment_gateway');
			$secure = true;
			if (strpos($gateway,"TestMode") !== false 
				|| isset($wp->query_vars['shopp_xco'])
				|| $this->Cart->orderisfree()) 
				$secure = false;
			shopp_redirect($Shopp->link('confirm-order',$secure));
		} else $this->Flow->order();

	}

	function cart_page ($attrs=array()) {
		$Order = &ShoppOrder();
		$Cart = $Order->Cart;

		ob_start();
		include(SHOPP_TEMPLATES."/cart.php");
		$content = ob_get_contents();
		ob_end_clean();

		return apply_filters('shopp_cart_template','<div id="shopp">'.$content.'</div>');
	}
	
	function checkout_page () {
		$Errors = &ShoppErrors();
		$Order = &ShoppOrder();
		$Cart = $Order->Cart;
		$process = get_query_var('shopp_proc');

		do_action('shopp_init_checkout');
		switch ($process) {
			case "confirm-order": 
				do_action('shopp_init_confirmation'); 
				$content = $this->order_confirmation(); 
				break;
			case "thanks":
			case "receipt": 
				$content = $this->thanks(); 
				break;//$content = $this->order_receipt(); break;
			default:
				ob_start();
				if ($Errors->exist(SHOPP_COMM_ERR)) include(SHOPP_TEMPLATES."/errors.php");
				include(SHOPP_TEMPLATES."/checkout.php");
				$content = ob_get_contents();
				ob_end_clean();
		}

		// Wrap with #shopp if not already wrapped
		if (strpos($content,'<div id="shopp">') === false) 
			$content = '<div id="shopp">'.$content.'</div>';

		return apply_filters('shopp_checkout_page',$content);
	}
	

	function account_page () {
		global $Shopp,$wp;
		
		$Customer = &$Shopp->Order->Customer;
		
		if (isset($Customer->login) && $Customer->login) 
			$Customer->management();
		
		if (isset($_GET['acct']) && $_GET['acct'] == "rp") $Customer->reset_password($_GET['key']);
		if (isset($_POST['recover-login'])) $Customer->recovery();
				
		ob_start();
		if (isset($wp->query_vars['shopp_download'])) include(SHOPP_TEMPLATES."/errors.php");
		elseif ($Customer->login) include(SHOPP_TEMPLATES."/account.php");
		else include(SHOPP_TEMPLATES."/login.php");
		$content = ob_get_contents();
		ob_end_clean();
		
		return apply_filters('shopp_account_template','<div id="shopp">'.$content.'</div>');
		
	}


	function shipping_estimate_page ($attrs) {
		$Cart = $this->Cart;

		ob_start();
		include(SHOPP_TEMPLATES."/shipping.php");
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}
	
	// Display the confirm order screen
	function order_confirmation () {
		global $Shopp;
		$Cart = $Shopp->Cart;
		
		ob_start();
		include(SHOPP_TEMPLATES."/confirm.php");
		$content = ob_get_contents();
		ob_end_clean();
		return apply_filters('shopp_order_confirmation','<div id="shopp">'.$content.'</div>');
	}

	// Display the thanks (transaction complete) page
	function thanks ($template="thanks.php") {
		global $Shopp;
		$Purchase = $Shopp->Purchase;
		
		ob_start();
		include(SHOPP_TEMPLATES."/$template");
		$content = ob_get_contents();
		ob_end_clean();
		return apply_filters('shopp_thanks',$content);
	}
	
	// Display an error page
	function error_page ($template="errors.php") {
		global $Shopp;
		$Cart = $Shopp->Cart;
		
		ob_start();
		include(SHOPP_TEMPLATES."/$template");
		$content = ob_get_contents();
		ob_end_clean();
		return apply_filters('shopp_errors_page','<div id="shopp">'.$content.'</div>');
	}
	

} // END class Storefront

?>