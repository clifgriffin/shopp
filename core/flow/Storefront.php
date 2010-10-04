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

	static $_pages = array(
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
	var $referrer = false;
	var $search = false;		// The search query string
	var $searching = false;		// Flags if a search request has been made
	var $checkout = false;		// Flags when the checkout form is being processed
	var $pages = array();
	var $browsing = array();
	var $behaviors = array();	// Runtime JavaScript behaviors
	
	function __construct () {
		global $Shopp;
		parent::__construct();
		
		$this->Settings = &$Shopp->Settings;
		$this->Catalog = &$Shopp->Catalog;
		$this->Category = &$Shopp->Category;
		$this->Product = &$Shopp->Product;
		
		$pages = $this->Settings->get('pages');
		if (!empty($pages)) $this->pages = $pages;

		ShoppingObject::store('search',$this->search);
		ShoppingObject::store('browsing',$this->browsing);
		ShoppingObject::store('breadcrumb',$this->breadcrumb);
		ShoppingObject::store('referrer',$this->referrer);

		add_action('wp', array(&$this, 'pageid'));
		add_action('wp', array(&$this, 'security'));
		add_action('wp', array(&$this, 'cart'));
		add_action('wp', array(&$this, 'catalog'));
		add_action('wp', array(&$this, 'shortcodes'));
		add_action('wp', array(&$this, 'behaviors'));
		
		// Shopp product text filters
		add_filter('shopp_product_name','convert_chars');
		add_filter('shopp_product_summary','convert_chars');

		add_filter('shopp_product_description', 'wptexturize');
		add_filter('shopp_product_description', 'convert_chars');
		add_filter('shopp_product_description', 'wpautop');
		add_filter('shopp_product_description', 'do_shortcode', 11); // AFTER wpautop()	

		add_filter('shopp_product_spec', 'wptexturize');
		add_filter('shopp_product_spec', 'convert_chars');
		add_filter('shopp_product_spec', 'do_shortcode', 11); // AFTER wpautop()

		add_filter('shopp_order_lookup','shoppdiv');
		add_filter('shopp_order_confirmation','shoppdiv');
		add_filter('shopp_errors_page','shoppdiv');
		add_filter('shopp_cart_template','shoppdiv');
		add_filter('shopp_checkout_page','shoppdiv');
		add_filter('shopp_account_template','shoppdiv');
		add_filter('shopp_order_receipt','shoppdiv');
		add_filter('shopp_account_manager','shoppdiv');
		add_filter('shopp_account_vieworder','shoppdiv');

		add_filter('aioseop_canonical_url', array(&$this,'canonurls'));
		add_action('wp_enqueue_scripts', 'shopp_dependencies');

		$this->smartcategories();
		$this->searching();
		$this->account();
	}

	/**
	 * Identifies the currently loaded Shopp storefront page
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function pageid () {
		global $Shopp,$wp_query;
		if (empty($wp_query->post)) return false;

		// Identify the current page
		foreach ($this->pages as &$page) {
			if ($page['id'] == $wp_query->post->ID) {
				$this->Page = $page; break;
			}
		}
	}
	
	/**
	 * Forces SSL on pages when required and available
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function security () {
		global $Shopp;
		if (is_shopp_secure() || !$Shopp->Gateways->secure) return;
		switch ($this->Page['name']) {
			case "checkout": shopp_redirect(shoppurl($_GET,get_query_var('shopp_proc'),true)); break;
			case "account":	 shopp_redirect(shoppurl($_GET,'account',true)); break;
		}
	}
	
	/**
	 * Adds nocache headers on sensitive account pages
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function account () {
		global $wp;
		if (isset($wp->query_vars['acct']))
			add_filter('wp_headers',array(&$this,'nocache'));
			
	}
	
	/**
	 * Adds nocache headers to WP page headers
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $headers The current WP HTTP headers
	 * @return array Modified headers
	 **/
	function nocache ($headers) {
		$headers = array_merge($headers, wp_get_nocache_headers());
		return $headers;
	}

	/**
	 * Queues Shopp storefront javascript and styles as needed
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function behaviors () {
		global $Shopp;

		global $wp_query;
		$object = $wp_query->get_queried_object();

		if(is_shopp_secure()) {
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
		add_action('wp_print_styles',array(&$this, 'catalogcss'));
		add_action('wp_head', array(&$this, 'header'));
		add_action('wp_footer', array(&$this, 'footer'));
		wp_enqueue_style('shopp.catalog',SHOPP_ADMIN_URI.'/styles/catalog.css',array(),SHOPP_VERSION,'screen');
		wp_enqueue_style('shopp',SHOPP_TEMPLATES_URI.'/shopp.css',array(),SHOPP_VERSION,'screen');
		wp_enqueue_style('shopp.colorbox',SHOPP_ADMIN_URI.'/styles/colorbox.css',array(),SHOPP_VERSION,'screen');
		if (is_shopp_page('account') || (isset($wp->query_vars['shopp_proc']) && $wp->query_vars['shopp_proc'] == "sold"))
			wp_enqueue_style('shopp.printable',SHOPP_ADMIN_URI.'/styles/printable.css',array(),SHOPP_VERSION,'print');

		$loading = $this->Settings->get('script_loading');
		if (!$loading || $loading == "global" || $tag !== false) {
			shopp_enqueue_script("colorbox");
			shopp_enqueue_script("shopp");
			shopp_enqueue_script("catalog");
			shopp_enqueue_script("cart");
			shopp_custom_script('catalog',"\tvar pricetags = {};\n");
			$Shopp->settingsjs();
		}

		if ($tag == "checkout")	shopp_enqueue_script('checkout');		
		
	}
	
	/**
	 * Sets handlers for Shopp shortcodes
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function shortcodes () {

		$this->shortcodes = array();
		// Gateway page shortcodes
		$this->shortcodes['catalog'] = array(&$this,'catalog_page');
		$this->shortcodes['cart'] = array(&$this,'cart_page');
		$this->shortcodes['checkout'] = array(&$this,'checkout_page');
		$this->shortcodes['account'] = array(&$this,'account_page');
		
		// Additional shortcode functionality
		$this->shortcodes['product'] = array(&$this,'product_shortcode');
		$this->shortcodes['buynow'] = array(&$this,'buynow_shortcode');
		$this->shortcodes['category'] = array(&$this,'category_shortcode');

		foreach ($this->shortcodes as $name => &$callback)
			if ($this->Settings->get("maintenance") == "on" || !$this->Settings->available || $this->maintenance())
				add_shortcode($name,array(&$this,'maintenance_shortcode'));
			else add_shortcode($name,$callback);
		
	}
	
	/**
	 * Detects if maintenance mode is necessary
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function maintenance () {
		$Settings = &ShoppSettings();
		$db_version = intval($Settings->get('db_version'));
		if ($db_version != DB::$version) return true;
		return false;
	}
	
	/**
	 * Modifies the WP page title to include product/category names (when available)
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $title The current WP page title
	 * @param string $sep (optional) The page title separator to include between page titles
	 * @param string $placement (optional) The placement of the separator (defaults 'left')
	 * @return string The modified page title
	 **/
	function titles ($title,$sep="&mdash;",$placement="left") {
		global $wp;
		
		if (!isset($wp->query_vars['shopp_category']) 
			&& !isset($wp->query_vars['shopp_tag'])
			&& !isset($wp->query_vars['shopp_product'])
			&& !isset($wp->query_vars['shopp_pid'])) return $title;
		if (empty($this->Product->name) && empty($this->Category->name)) return $title;

		$_ = array();
		if (!empty($title))					$_[] = $title;
		if (!empty($this->Category->name))	$_[] = $this->Category->name;
		if (!empty($this->Product->name))	$_[] = $this->Product->name;
		
		if ("right" == $placement) $_ = array_reverse($_);
		
		$_ = apply_filters('shopp_document_titles',$_);
		$sep = trim($sep);
		if (empty($sep)) $sep = "&mdash;";
		return join(" $sep ",$_);
	}

	/**
	 * Override the WP page title for the extra checkout process pages
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $title The current WP page title
	 * @param int $post_id (optional) The post id
	 * @return string The modified title
	 **/
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

	/**
	 * Renders RSS feed link tags for category product feeds
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function feeds () {
		global $Shopp;		
		if (empty($this->Category->name)):?>

<link rel='alternate' type="application/rss+xml" title="<?php htmlentities(bloginfo('name')); ?> New Products RSS Feed" href="<?php echo (SHOPP_PRETTYURLS)?shoppurl('feed'):shoppurl(array('shopp_lookup'=>'newproducts-rss')); ?>" />
<?php
			else:
			$uri = 'category/'.$this->Category->uri;
			if ($this->Category->slug == "tag") $uri = $this->Category->slug.'/'.$this->Category->tag;
			$link = (SHOPP_PRETTYURLS)?shoppurl("$uri/feed"):shoppurl(array('shopp_category'=>urldecode($this->Category->uri),'src'=>'category_rss'));
			?>

<link rel='alternate' type="application/rss+xml" title="<?php echo esc_attr(bloginfo('name')); ?> <?php echo esc_attr($this->Category->name); ?> RSS Feed" href="<?php echo $link; ?>" />
<?php
		endif;
	}

	/**
	 * Updates the WP search query with the Shopp search in progress
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function updatesearch () {
		global $wp_query;
		$wp_query->query_vars['s'] = esc_attr(stripslashes($this->search));
	}

	/**
	 * Adds 'keyword' and 'description' <meta> tags into the page markup
	 * 
	 * The 'keyword' tag is a list of tags applied to a product.  No default 'keyword' meta
	 * is generated for categories, however, the 'shopp_meta_keywords' filter hook can be
	 * used to generate a custom list.
	 * 
	 * The 'description' tag is generated from the product summary or category description.
	 * It can also be customized with the 'shopp_meta_description' filter hook.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
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
		$keywords = esc_attr(apply_filters('shopp_meta_keywords',$keywords));
		$description = esc_attr(apply_filters('shopp_meta_description',$description));
		?>
		<?php if ($keywords): ?><meta name="keywords" content="<?php echo $keywords; ?>" />
		<?php endif; ?>
<?php if ($description): ?><meta name="description" content="<?php echo $description; ?>" />
		<?php endif;
	}

	/**
	 * Returns canonical product and category URLs
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $url The current url
	 * @return string The canonical url
	 **/
	function canonurls ($url) {
		global $Shopp;
		if (!empty($Shopp->Product->slug)) return $Shopp->Product->tag('url','echo=0');
		if (!empty($Shopp->Category->slug)) return $Shopp->Category->tag('url','echo=0');
		return $url;
	}
	
	/**
	 * Registers available smart categories
	 * 
	 * New smart categories can be added by creating a new smart category class
	 * in a custom plugin or the theme functions file.
	 * 
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function smartcategories () {
		Shopp::add_smartcategory('CatalogProducts');
		Shopp::add_smartcategory('NewProducts');
		Shopp::add_smartcategory('FeaturedProducts');
		Shopp::add_smartcategory('OnSaleProducts');
		Shopp::add_smartcategory('BestsellerProducts');
		Shopp::add_smartcategory('SearchResults');
		Shopp::add_smartcategory('TagProducts');
		Shopp::add_smartcategory('RelatedProducts');
		Shopp::add_smartcategory('RandomProducts');

		do_action('shopp_register_smartcategories');
	}

	/**
	 * Includes a canonical reference <link> tag for the catalog page
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function header () {
		global $wp;

		$canonurl = $this->canonurls(false);
		if (is_shopp_page('catalog') && !empty($canonurl)): ?><link rel='canonical' href='<?php echo $canonurl ?>' /><?php
		endif;
	}

	/**
	 * Adds a dynamic style declaration for the category grid view
	 * 
	 * Ties the presentation setting to the grid view category rendering 
	 * in the storefront.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function catalogcss () {
		$Settings = ShoppSettings();
		if (!isset($row_products)) $row_products = 3;
		$row_products = $Settings->get('row_products');
		$products_per_row = floor((100/$row_products));
?>
	<!-- Shopp dynamic catalog styles -->
	<style type="text/css">
	#shopp ul.products li.product { width: <?php echo $products_per_row; ?>%; } /* For grid view */
	</style>
	<!-- END Shopp dynamic catalog styles -->
<?php
	}
	
	/**
	 * Renders footer content and extra scripting as needed
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function footer () {
		$db = DB::get();
		global $wpdb;

		if (WP_DEBUG && current_user_can('manage_options')) {
			if (function_exists('memory_get_peak_usage'))
				$this->_debug->memory .= "End: ".number_format(memory_get_peak_usage(true)/1024/1024, 2, '.', ',') . " MB<br />";
			elseif (function_exists('memory_get_usage'))
				$this->_debug->memory .= "End: ".number_format(memory_get_usage(true)/1024/1024, 2, '.', ',') . " MB";

			add_storefrontjs("var memory_profile = '{$this->_debug->memory}',wpquerytotal = {$wpdb->num_queries},shoppquerytotal = ".count($db->queries).";",true);
		}
		
		$globals = false;
		if (isset($this->behaviors['global'])) {
			$globals = $this->behaviors['global'];
			unset($this->behaviors['global']);
		}
		
		$script = '';
		if (!empty($globals)) $script .= "\t".join("\n\t",$globals)."\n";
		if (!empty($this->behaviors)) {
			$script .= 'jQuery(window).ready(function(){ var $ = jqnc(); '."\n";
			$script .= "\t".join("\t\n",$this->behaviors)."\n";
			$script .= '});'."\n";
		}
		shopp_custom_script('catalog',$script);
	}

	/**
	 * Determines when to search the Shopp catalog instead of WP content
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function searching () {
		global $Shopp,$wp;
		
		$this->searching = false;
		
		if (!isset($_GET['s']) 								// No search query
			|| !isset($wp->query_vars['catalog']) 			// No catalog flag
			|| (isset($wp->query_vars['catalog']) 			// Catalog flag exists &
				&& $wp->query_vars['catalog'] == 'false'))	// explicitly turned off
					return false;							// ...not searching Shopp

		$this->search = $wp->query_vars['s'];
		$this->searching = true;
		unset($wp->query_vars['s']); // Not needed any longer
		$wp->query_vars['pagename'] = $this->pages['catalog']['name'];
		$wp->query_vars['shopp_category'] = "search-results";
		add_action('wp_head', array(&$this, 'updatesearch'));
		
	}

	/**
	 * Parses catalog page requests
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void
	 **/
	function catalog () {
		global $Shopp,$wp;
		
		$options = array();
		
		add_filter('redirect_canonical','canonical_home');

		$type = "catalog";
		if (isset($wp->query_vars['shopp_category']) &&
			$category = $wp->query_vars['shopp_category']) $type = "category";
		elseif (isset($wp->query_vars['shopp_pid']) && 
			$productid = $wp->query_vars['shopp_pid']) $type = "product";
		elseif (isset($wp->query_vars['shopp_product']) && 
			$productname = $wp->query_vars['shopp_product']) $type = "product";

		if (isset($wp->query_vars['shopp_tag']) && 
			$tag = $wp->query_vars['shopp_tag']) {
			$type = "category";
			$category = "tag";
		}

		// If a search query is stored, and this request is a product or the 
		// search results category repopulate the search box and set the 
		// category for the breadcrumb
		if (!empty($this->search) 
				&& ($type == "product" 
				|| ($type == "category" && $category == "search-results"))) {
			add_action('wp_head', array(&$this, 'updatesearch'));
			$category = "search-results";
		} else $this->search = $this->searching = false;

		// If a search request is being made, set the type to category
		if ($this->searching) {
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

			if (!empty($this->searching)) {
				$Shopp->Category->load_products(array('load'=>array('images','prices')));
				if (count($Shopp->Category->products) == 1) {
					reset($Shopp->Category->products);
					$type = 'product';
					$BestBet = current($Shopp->Category->products);
					shopp_redirect($BestBet->tag('url',array('return'=>true)));
				}
			}
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

		// Set the category context by following the breadcrumb
		if (empty($Shopp->Category->slug)) $Shopp->Category = Catalog::load_category($this->breadcrumb,$options);
		
		// No category context, use the CatalogProducts smart category
		if (empty($Shopp->Category->slug)) $Shopp->Category = Catalog::load_category('catalog',$options);

		// Find product by given ID
		if (!empty($productid) && empty($Shopp->Product->id))
			$Shopp->Product = new Product($productid);

		// Find product by product slug
		if (!empty($productname) && empty($Shopp->Product->id))
			$Shopp->Product = new Product(urlencode($productname),"slug");

		// Product must be published
		if ((!empty($Shopp->Product->id) && !$Shopp->Product->published()) || empty($Shopp->Product->id))
			$Shopp->Product = new Product(); // blank product displays "no product found" in storefront

		// No product found, try to load a page instead
		if ($type == "product" && !$Shopp->Product) 
			$wp->query_vars['pagename'] = $wp->request;

		$Shopp->Catalog = new Catalog($type);

		if ($type == "category") $Shopp->Requested = $Shopp->Category;
		else $Shopp->Requested = $Shopp->Product;
				
		add_filter('wp_title', array(&$this, 'titles'),10,3);
		add_action('wp_head', array(&$this, 'metadata'));
		add_action('wp_head', array(&$this, 'feeds'));
	}

	/**
	 * Cancels canonical redirects when the catalog is Set as the front page
	 *
	 * Added for WordPress 3.0 compatibility {@see wp-includes/canonical.php line 111}
	 * 
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param string $redirect The redirected URL
	 * @return mixed False when the Shopp catalog is set as the front page
	 **/
	function canonical_home ($redirect) {
		$pages = $this->Settings->get('pages');
		if (!function_exists('home_url')) return $redirect;
		list($url,$query) = explode("?",$redirect);
		if ($url == home_url('/') && $pages['catalog']['id'] == get_option('page_on_front'))
			return false;
		if ( isset($wp->query_vars['shopp_category']) && isset($wp->query_vars['paged']) )
			return false;
		return $redirect;
	}
	
	function catalog_page () {
		global $Shopp,$wp;
		if (SHOPP_DEBUG) new ShoppError('Displaying catalog page request: '.$_SERVER['REQUEST_URI'],'shopp_catalog',SHOPP_DEBUG_ERR);

		$this->referrer = get_bloginfo('url')."/".$wp->request;
		
		ob_start();
		switch ($Shopp->Catalog->type) {
			case "product": 
				if (file_exists(SHOPP_TEMPLATES."/product-{$Shopp->Product->id}.php"))
					include(SHOPP_TEMPLATES."/product-{$Shopp->Product->id}.php");
				else include(SHOPP_TEMPLATES."/product.php"); break;

			case "category":
				if (isset($Shopp->Category->slug) && 
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

		return apply_filters('shopp_catalog','<div id="shopp" class="'.$classes.'">'.$content.'</div>');
	}

	/**
	 * Handles shopping cart requests
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function cart () {
		global $Shopp;
		$Cart = $Shopp->Order->Cart;
		if (isset($_REQUEST['shopping']) && strtolower($_REQUEST['shopping']) == "reset") {
			$Shopp->Shopping->reset();
			shopp_redirect(shoppurl());
		}

		if (empty($_REQUEST['cart'])) return true;
		
		do_action('shopp_cart_request');

		if (isset($_REQUEST['ajax'])) {
			$Cart->totals();
			$Cart->ajax();
		}
		$redirect = false;
		if (isset($_REQUEST['redirect'])) $redirect = $_REQUEST['redirect'];
		switch ($redirect) {
			case "checkout": shopp_redirect(shoppurl(false,$redirect,$Shopp->Order->security())); break;
			default: 
				if (!empty($_REQUEST['redirect']))
					shopp_safe_redirect($_REQUEST['redirect']);
				else shopp_redirect(shoppurl(false,'cart'));
		}
	}

	/**
	 * Displays the cart template
	 * 
	 * Replaces the [cart] shortcode on the Cart page with 
	 * the processed template contents.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $attrs Shortcode attributes
	 * @return string The cart template content
	 **/
	function cart_page ($attrs=array()) {
		global $Shopp;
		$Order = &ShoppOrder();
		$Cart = $Order->Cart;
				
		ob_start();
		include(SHOPP_TEMPLATES."/cart.php");
		$content = ob_get_contents();
		ob_end_clean();

		return apply_filters('shopp_cart_template',$content);
	}
	
	/**
	 * Displays the appropriate checkout template
	 * 
	 * Replaces the [checkout] shortcode on the Checkout page with 
	 * the processed template contents.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $attrs Shortcode attributes
	 * @return string The processed template content
	 **/
	function checkout_page () {
		$Errors =& ShoppErrors();
		$Order =& ShoppOrder();
		$Cart =& $Order->Cart;
		$process = get_query_var('shopp_proc');

		do_action('shopp_init_checkout');
		switch ($process) {
			case "confirm-order": 
				do_action('shopp_init_confirmation'); 
				$Order->validated = $Order->isvalid();
				$errors = "";
				if ($Errors->exist(SHOPP_STOCK_ERR)) {
					ob_start();
					include(SHOPP_TEMPLATES."/errors.php");
					$errors = ob_get_contents();
					ob_end_clean();
				}
				$content = $errors.$this->order_confirmation();
				break;
			case "thanks":
			case "receipt": 
				$content = $this->thanks(); 
				break;
			default:
				ob_start();
				if ($Errors->exist(SHOPP_COMM_ERR)) include(SHOPP_TEMPLATES."/errors.php");
				$this->checkout = true;
				include(SHOPP_TEMPLATES."/checkout.php");
				$content = ob_get_contents();
				ob_end_clean();
		}

		return apply_filters('shopp_checkout_page',$content);
	}
	
	/**
	 * Displays the appropriate account page template
	 * 
	 * Replaces the [account] shortcode on the Account page with 
	 * the processed template contents.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $attrs Shortcode attributes
	 * @return string The cart template content
	 **/
	function account_page ($menuonly=false) {
		global $wp;
		$Order =& ShoppOrder();
		$Customer =& $Order->Customer;

		if (isset($Customer->login) && $Customer->login) do_action('shopp_account_management');
		
		ob_start();
		if (isset($wp->query_vars['shopp_download'])) include(SHOPP_TEMPLATES."/errors.php");
		elseif ($Customer->login) include(SHOPP_TEMPLATES."/account.php");
		else include(SHOPP_TEMPLATES."/login.php");
		$content = ob_get_contents();
		ob_end_clean();
		
		return apply_filters('shopp_account_template',$content);
		
	}
	
	/**
	 * Renders the order confirmation template
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return string The processed confirm.php template file
	 **/
	function order_confirmation () {
		global $Shopp;
		$Cart = $Shopp->Order->Cart;
		
		ob_start();
		include(SHOPP_TEMPLATES."/confirm.php");
		$content = ob_get_contents();
		ob_end_clean();
		return apply_filters('shopp_order_confirmation',$content);
	}

	/**
	 * Renders the thanks template
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return string The processed thanks.php template file
	 **/
	function thanks ($template="thanks.php") {
		global $Shopp;
		$Purchase = $Shopp->Purchase;
		
		ob_start();
		include(SHOPP_TEMPLATES."/$template");
		$content = ob_get_contents();
		ob_end_clean();
		return apply_filters('shopp_thanks',$content);
	}
	
	/**
	 * Renders the errors template
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return string The processed errors.php template file
	 **/
	function error_page ($template="errors.php") {
		global $Shopp;
		$Cart = $Shopp->Cart;
		
		ob_start();
		include(SHOPP_TEMPLATES."/$template");
		$content = ob_get_contents();
		ob_end_clean();
		return apply_filters('shopp_errors_page',$content);
	}

	/**
	 * Handles rendering the [product] shortcode
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $attrs The parsed shortcode attributes
	 * @return string The processed content
	 **/
	function product_shortcode ($atts) {
		global $Shopp;

		if (isset($atts['name'])) {
			$Shopp->Product = new Product($atts['name'],'name');
		} elseif (isset($atts['slug'])) {
			$Shopp->Product = new Product($atts['slug'],'slug');
		} elseif (isset($atts['id'])) {
			$Shopp->Product = new Product($atts['id']);
		} else return "";
		
		return apply_filters('shopp_product_shortcode',$Shopp->Catalog->tag('product',$atts).'');
	}

	/**
	 * Handles rendering the [category] shortcode
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $attrs The parsed shortcode attributes
	 * @return string The processed content
	 **/
	function category_shortcode ($atts) {
		global $Shopp;
		
		$tag = 'category';
		if (isset($atts['name'])) {
			$Shopp->Category = new Category($atts['name'],'name');
			unset($atts['name']);
		} elseif (isset($atts['slug'])) {
			foreach ($Shopp->SmartCategories as $SmartCategory) {
				$SmartCategory_slug = get_class_property($SmartCategory,'_slug');
				if ($atts['slug'] == $SmartCategory_slug) {
					$tag = "$SmartCategory_slug-products";
					if ($tag == "search-results-products") $tag = "search-products";
					unset($atts['slug']);
				}
			}
		} elseif (isset($atts['id'])) {
			$Shopp->Category = new Category($atts['id']);
			unset($atts['id']);
		} else return "";

		return apply_filters('shopp_category_shortcode',$Shopp->Catalog->tag($tag,$atts).'');
		
	}
	

	/**
	 * Handles rendering the maintenance message in place of all shortcodes
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $attrs The parsed shortcode attributes
	 * @return string The processed content
	 **/
	function maintenance_shortcode ($atts) {
		if (file_exists(SHOPP_TEMPLATES."/maintenance.php")) {
			ob_start();
			include(SHOPP_TEMPLATES."/maintenance.php");
			$content = ob_get_contents();
			ob_end_clean();
		} else $content = '<div id="shopp" class="update"><p>'.__("The store is currently down for maintenance.  We'll be back soon!","Shopp").'</p><div class="clear"></div></div>';
		
		return $content;
	}

	/**
	 * Handles rendering the [buynow] shortcode
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @param array $attrs The parsed shortcode attributes
	 * @return string The processed content
	 **/
	function buynow_shortcode ($atts) {
		global $Shopp;
		
		if (empty($Shopp->Product->id)) {
			if (isset($atts['name'])) {
				$Shopp->Product = new Product($atts['name'],'name');
			} elseif (isset($atts['slug'])) {
				$Shopp->Product = new Product($atts['slug'],'slug');
			} elseif (isset($atts['id'])) {
				$Shopp->Product = new Product($atts['id']);
			} else return "";
		}
		if (empty($Shopp->Product->id)) return "";

		ob_start();
		?>
		<form action="<?php shopp('cart','url'); ?>" method="post" class="shopp product">
			<input type="hidden" name="redirect" value="checkout" />
			<?php if (isset($atts['variations'])): $variations = empty($atts['variations'])?'mode=multiple&label=true&defaults='.__('Select an option','Shopp').'&before_menu=<li>&after_menu=</li>':$atts['variations']; ?>
				<?php if(shopp('product','has-variations')): ?>
				<ul class="variations">
					<?php shopp('product','variations',$variations); ?>			
				</ul>
				<?php endif; ?>
			<?php endif; ?>
			<?php if (isset($atts['addons'])): $addons = empty($atts['addons'])?'mode=menu&label=true&defaults='.__('Select an add-on','Shopp').'&before_menu=<li>&after_menu=</li>':$atts['addons']; ?>
				<?php if(shopp('product','has-addons')): ?>
					<ul class="addons">
						<?php shopp('product','addons','mode=menu&label=true&defaults='.__('Select an add-on','Shopp').'&before_menu=<li>&after_menu=</li>'); ?>			
					</ul>
				<?php endif; ?>
			<?php endif; ?>
			<p><?php if (isset($atts['quantity'])): $quantity = (empty($atts['quantity']))?'class=selectall&input=menu':$atts['quantity']; ?>
				<?php shopp('product','quantity',$quantity); ?>
			<?php endif; ?>
			<?php $button = empty($atts['button'])?'label='.__('Buy Now','Shopp'):$atts['button']; ?>
			<?php shopp('product','addtocart',$button); ?></p>
		</form>
		<?php
		$markup = ob_get_contents();
		ob_end_clean();

		return $markup;
	}
	
} // END class Storefront

?>