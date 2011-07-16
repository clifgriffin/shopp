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
	var $viewed = array();
	var $behaviors = array();	// Runtime JavaScript behaviors
	var $request = false;

	function __construct () {
		global $Shopp;
		parent::__construct();

		$this->Category = &$Shopp->Category;
		$this->Product = &$Shopp->Product;

		$Catalog = new Catalog();
		ShoppCatalog($Catalog);

		// @todo replace with storefront_pages setting?
		$pages = shopp_setting('pages');
		if (!empty($pages)) $this->pages = $pages;

		ShoppingObject::store('search',$this->search);
		ShoppingObject::store('browsing',$this->browsing);
		ShoppingObject::store('breadcrumb',$this->breadcrumb);
		ShoppingObject::store('referrer',$this->referrer);
		ShoppingObject::store('viewed',$this->viewed);

		// Setup WP_Query overrides
		add_action('parse_query', array($this, 'query'));
		add_filter('posts_request', array($this, 'noquery'));
		add_filter('posts_results', array($this, 'found'));
		add_filter('the_posts', array($this, 'posts'));

		add_action('wp', array($this, 'loaded'));
		// add_action('wp', array($this, 'pageid'));
		// add_action('wp', array($this, 'security'));
		add_action('wp', array($this, 'cart'));
		// add_action('wp', array($this, 'shortcodes'));
		add_action('wp', array($this, 'behaviors'));

		add_filter('the_title', array($this,'pagetitle'), 10, 2);
		add_filter('wp_get_nav_menu_items', array($this,'navmenus'), 10, 2);

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

		// add_filter('aioseop_canonical_url', array(&$this,'canonurls'));
		add_action('wp_enqueue_scripts', 'shopp_dependencies');

		add_action('shopp_storefront_init',array($this,'promos'));
		add_action('shopp_storefront_init',array($this,'collections'));
		add_action('shopp_storefront_init',array($this,'account'));

		add_filter('archive_template',array($this,'collection'));
		add_filter('search_template',array($this,'collection'));
		add_filter('page_template',array($this,'pages'));
		add_filter('single_template',array($this,'single'));

	}

	function is_shopp_request () {
		return $this->request;
	}

	function noquery ($request) {
		if ($this->is_shopp_request()) return false;
		return $request;
	}

	function found ($found_posts) {
		if ($this->is_shopp_request()) return true;
		return $found_posts;
	}

	function posts ($posts) {
		if ($this->is_shopp_request()) return array(1);
		return $posts;
	}

	function query ($wp_query) {
		global $Shopp;

		$page	 	= get_query_var('shopp_page');
		$posttype 	= get_query_var('post_type');
		$product 	= get_query_var(Product::$posttype);
		$category 	= get_query_var(ProductCategory::$taxonomy);
		$tag	 	= get_query_var(ProductTag::$taxonomy);
		$collection = get_query_var('shopp_collection');
		$sortorder 	= get_query_var('s_so');
		$searching 	= get_query_var('s_cs');
		$search 	= get_query_var('s');

		if (!empty($sortorder))	$this->browsing['sortorder'] = $sortorder;

		// Override the custom post type archive request to use the Shopp catalog page
		if ($posttype == Product::$posttype && '' == $product.$page) {
			$pages = Storefront::pages_settings();
			$page = $pages['catalog']['slug'];
			set_query_var('shopp_page',$page);
		}

		if ($category.$collection.$tag.$page.$searching == ''
			&& $posttype == Product::$posttype) return;

		$this->request = true;
		set_query_var('suppress_filters',false); // Override default WP_Query request

		if (!empty($page)) {
			// Overrides to enforce page behavior
			$wp_query->is_home = false;
			$wp_query->is_singular = false;
			$wp_query->is_archive = false;
			$wp_query->is_page = true;
			$wp_query->post_count = true;
			return;
		}

		if (is_archive() && !empty($category)) {
			$Shopp->Category = new ProductCategory($category,'slug');
		}

		if (is_archive() && !empty($tag)) {
			$Shopp->Category = new ProductTag($tag,'slug');
		}

		$options = array();
		if ($searching) { // Catalog search
			$collection = 'search-results';
			$options = array('search'=>$search);
		}

		// Promo Collection routing
		$promos = shopp_setting('active_catalog_promos');
		if (isset($promos[$collection])) {
			$options['id'] = $promos[$collection][0];
			$collection = 'promo';
		}

		if (!empty($collection)) {
			// Overrides to enforce archive behavior
			$wp_query->is_archive = true;
			$wp_query->is_post_type_archive = true;
			$wp_query->is_home = false;
			$wp_query->is_page = false;
			$wp_query->post_count = true;
			$Shopp->Category = Catalog::load_collection($collection,$options);
		}

		if (!empty($Shopp->Category)) {
			add_action('wp_head', array(&$this, 'metadata'));
			add_action('wp_head', array(&$this, 'feeds'));
		}

	}

	function loaded ($wp) {
		if (! (is_single() && get_query_var('post_type') == Product::$posttype)) return;

		global $wp_query;
		$object = $wp_query->get_queried_object();
		$Product = new Product();
		$Product->populate($object);
		ShoppProduct($Product);

		if (!in_array($Product->id,$this->viewed)) {
			array_unshift($this->viewed,$Product->id);
			$this->viewed = array_slice($this->viewed,0,
				apply_filters('shopp_recently_viewed_limit',25));
		}
	}

	function collection ($template) {
		global $Shopp;

		// Bail if not the product archive
		// or not a shopp taxonomy request
		if (empty($Shopp->Category) && get_query_var('post_type') != Product::$posttype) return $template;

		/* @todo Handle category/collection title */
		// add_filter('the_title',create_function('$title','if (!in_the_loop()) return $title; if (is_archive()) return "Store";'));
		add_filter('the_content',array(&$this,'category_template'));

		$templates = array('shopp-category.php', 'shopp.php', 'page.php');
		return locate_template($templates);
	}

	function pages ($template) {
		global $wp_query;

		$page = Storefront::slugpage( get_query_var('shopp_page') );

		if (empty($page)) return $template;

		$pagetitle = shopp_setting($page.'_page_title');

		add_filter('the_title',create_function('$title','return in_the_loop()?"'.$pagetitle.'":$title;'));
		add_filter('the_content',array(&$this,$page.'_page'));

		$templates = array("$page.php", 'shopp.php', 'page.php');
		return locate_template($templates);
	}

	function single ($template) {
		$post_type = get_query_var('post_type');

		if ($post_type != Product::$posttype) return $template;
		add_filter('the_content',array(&$this,'product_template'));

		$templates = array('single-' . $post_type . '.php', 'shopp.php', 'page.php');
		return locate_template($templates);
	}

	function product_template ($content) {
		$Product = ShoppProduct();
		ob_start();
		if (file_exists(SHOPP_TEMPLATES."/product-{$Product->id}.php"))
			include(SHOPP_TEMPLATES."/product-{$Product->id}.php");
		else include(SHOPP_TEMPLATES."/product.php");
		$content = ob_get_contents();
		ob_end_clean();

		return shoppdiv($content);
	}

	function category_template ($content) {
		global $Shopp,$wp_query;
		// Short-circuit the loop for the archive/category requests
		$wp_query->current_post = $wp_query->post_count;
		ob_start();
		if (empty($Shopp->Category)) include(SHOPP_TEMPLATES."/catalog.php");
		else {
			if (isset($Shopp->Category->slug) &&
				file_exists(SHOPP_TEMPLATES."/category-{$Shopp->Category->slug}.php"))
				include(SHOPP_TEMPLATES."/category-{$Shopp->Category->slug}.php");
			elseif (isset($Shopp->Category->id) &&
				file_exists(SHOPP_TEMPLATES."/category-{$Shopp->Category->id}.php"))
				include(SHOPP_TEMPLATES."/category-{$Shopp->Category->id}.php");
			else include(SHOPP_TEMPLATES."/category.php");
		}
		$content = ob_get_contents();
		ob_end_clean();

		return shoppdiv($content);
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
		global $wp_query;
		// print_r($wp_query);
		$pagename = get_query_var('pagename');
		if (empty($pagename)) return false;

		// Identify the current page
		foreach ($this->pages as &$page)
			if ($page['uri'] == $pagename) break;

		if (!empty($page)) $this->Page = $page;

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
			case "checkout": shopp_redirect(shoppurl($_GET,get_query_var('s_pr'),true)); break;
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
		$request = get_query_var('acct');
		if (!empty($request)) add_filter('wp_headers',array(&$this,'nocache'));
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

		if(is_shopp_secure()) {
			add_filter('option_siteurl', 'force_ssl');
			add_filter('option_home', 'force_ssl');
			add_filter('option_url', 'force_ssl');
			add_filter('option_wpurl', 'force_ssl');
			add_filter('option_stylesheet_url', 'force_ssl');
			add_filter('option_template_url', 'force_ssl');
			add_filter('script_loader_src', 'force_ssl');
		}

		// Include stylesheets and javascript based on whether shopp shortcodes are used
		add_action('wp_print_styles',array(&$this, 'catalogcss'));

		// Replace the WordPress canonical link
		remove_action('wp_head','rel_canonical');

		// add_action('wp_head', array(&$this, 'header'));
		add_action('wp_footer', array(&$this, 'footer'));
		wp_enqueue_style('shopp.catalog',SHOPP_ADMIN_URI.'/styles/catalog.css',array(),SHOPP_VERSION,'screen');
		wp_enqueue_style('shopp',SHOPP_TEMPLATES_URI.'/shopp.css',array(),SHOPP_VERSION,'screen');
		wp_enqueue_style('shopp.colorbox',SHOPP_ADMIN_URI.'/styles/colorbox.css',array(),SHOPP_VERSION,'screen');

		$page = $this->slugpage(get_query_var('shopp_page'));

		$thankspage = ('thanks' == $page);
		$orderhistory = ('account' == $page && !empty($_GET['id']));

		if ($thankspage || $orderhistory)
			wp_enqueue_style('shopp.printable',SHOPP_ADMIN_URI.'/styles/printable.css',array(),SHOPP_VERSION,'print');

		$loading = shopp_setting('script_loading');
		if (!$loading || 'global' == $loading || !empty($page)) {
			shopp_enqueue_script("colorbox");
			shopp_enqueue_script("shopp");
			shopp_enqueue_script("catalog");
			shopp_enqueue_script("cart");
			if (is_shopp_page('catalog'))
				shopp_custom_script('catalog',"var pricetags = {};\n");

			add_action('wp_head', array(&$Shopp, 'settingsjs'));

		}

		if ('checkout' == $page) shopp_enqueue_script('checkout');

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
		// $this->shortcodes['catalog'] = array(&$this,'catalog_page');
		// $this->shortcodes['cart'] = array(&$this,'cart_page');
		// $this->shortcodes['checkout'] = array(&$this,'checkout_page');
		// $this->shortcodes['account'] = array(&$this,'account_page');

		// Additional shortcode functionality
		$this->shortcodes['product'] = array(&$this,'product_shortcode');
		$this->shortcodes['buynow'] = array(&$this,'buynow_shortcode');
		$this->shortcodes['category'] = array(&$this,'category_shortcode');

		foreach ($this->shortcodes as $name => &$callback)
			if (shopp_setting("maintenance") == "on" || !ShoppSettings()->available() || $this->maintenance())
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
		$db_version = intval(shopp_setting('db_version'));
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

		$request = array();
		$vars = array('s_cat','s_tag','s_pd','s_pid');
		foreach ($vars as $v) $request[] = get_query_var($v);

		if (empty($request)) return $title;
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

		// @todo replace with storefront_pages setting?
		$pages = shopp_setting('pages');
		$process = get_query_var('s_pr');

		if (!empty($process) && $post_id == $pages['checkout']['id']) {
			switch($process) {
				case "thanks": $title = apply_filters('shopp_thanks_pagetitle',__('Thank You!','Shopp')); break;
				case "confirm-order": $title = apply_filters('shopp_confirmorder_pagetitle',__('Confirm Order','Shopp')); break;
			}
		}
		return $title;
	}

	function navmenus ($items) {
		foreach ($items as &$item) {
			if ('shopp_page' != $item->type) continue;
			$item->url = shoppurl(false,$item->object);
		}
		return $items;
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
		$Collection = ShoppCollection();
		if (empty($Collection->name)) return;
?>
	<link rel='alternate' type="application/rss+xml" title="<?php esc_attr_e(bloginfo('name')); ?> <?php esc_attr_e($Collection->name); ?> RSS Feed" href="<?php esc_attr_e(shopp('category','get-feed-url')); ?>" /><?php
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
		if (!empty($this->Product->id)) {
			if (empty($this->Product->tags)) $this->Product->load_data(array('tags'));
			foreach($this->Product->tags as $tag)
				$keywords .= (!empty($keywords))?", {$tag->name}":$tag->name;
			$description = $this->Product->summary;
		} elseif (!empty($this->Category->id)) {
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

		// Catalog landing as site landing, use site home URL
		if (is_front_page() && isset($Shopp->Catalog) && $Shopp->Catalog->tag('is-landing','return=1'))
			return user_trailingslashit(get_bloginfo('home'));

		// Catalog landing page URL
		if (is_shopp_page('catalog') && $Shopp->Catalog->tag('is-landing','return=1'))
			return $Shopp->Catalog->tag('url','echo=0');

		// Specific product/category URLs
		if (!empty($Shopp->Product->slug)) return $Shopp->Product->tag('url','echo=0');
		if (!!empty($Shopp->Category->slug)) return $Shopp->Category->tag('url','echo=0');
		return $url;
	}


	function promos () {
		if (!isset($this->promos) || empty($this->promos)) return;

		print_r($this->promos);

	}

	/**
	 * Registers available collections
	 *
	 * New collections can be added by creating a new Collection class
	 * in a custom plugin or the theme functions.php file.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void Description...
	 **/
	function collections () {

		do_action('shopp_register_smartcategories'); // Deprecated
		do_action('shopp_register_collections');

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
		if (!isset($row_products)) $row_products = 3;
		$row_products = shopp_setting('row_products');
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

		// if (SHOPP_QUERY_DEBUG) {
		// 	echo "<!--\n\nSHOPP QUERIES\n\n";
		// 	print_r($db->queries);
		// 	echo "\n\n-->";
		// }
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
		// @todo replace with storefront_pages setting?
		$pages = shopp_setting('pages');
		if (!function_exists('home_url')) return $redirect;
		list($url,) = explode("?",$redirect);
		if ($url == home_url('/') && $pages['catalog']['id'] == get_option('page_on_front'))
			return false;
		// Cancel WP pagination redirects for Shopp category pages
		if ( get_query_var('s_cat') && get_query_var('paged') > 0 )
			return false;
		return $redirect;
	}

	function catalog_page () {
		global $Shopp,$wp;
		if (SHOPP_DEBUG) new ShoppError('Displaying catalog page request: '.$_SERVER['REQUEST_URI'],'shopp_catalog',SHOPP_DEBUG_ERR);

		$referrer = get_bloginfo('url')."/".$wp->request;
		if (!empty($wp->query_vars)) $referrer = add_query_arg($wp->query_vars,$referrer);
		$this->referrer = $referrer;

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
			$view = shopp_setting('default_catalog_view');
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
			$Shopping = ShoppShopping();
			$Shopping->reset();
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
		$process = get_query_var('s_pr');

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

	function confirm_page () {
		$Errors = ShoppErrors();
		$Order = ShoppOrder();
		$Cart = $Order->Cart;

		do_action('shopp_init_confirmation');
		$Order->validated = $Order->isvalid();

		$errors = '';
		if ($Errors->exist(SHOPP_STOCK_ERR)) {
			ob_start();
			include(SHOPP_TEMPLATES.'/errors.php');
			$errors = ob_get_contents();
			ob_end_clean();
		}

		ob_start();
		include(SHOPP_TEMPLATES.'/confirm.php');
		$content = ob_get_contents();
		ob_end_clean();
		return apply_filters('shopp_order_confirmation',$errors.$content);
	}

	function thanks_page () {
		global $Shopp;
		$Errors = ShoppErrors();
		$Order = ShoppOrder();
		$Cart = $Order->Cart;
		$Purchase = $Shopp->Purchase;

		ob_start();
		include(SHOPP_TEMPLATES."/thanks.php");
		$content = ob_get_contents();
		ob_end_clean();
		return apply_filters('shopp_thanks',$content);
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

		$download_request = get_query_var('s_dl');
		if (isset($Customer->login) && $Customer->login) do_action('shopp_account_management');

		ob_start();
		if (!empty($download_request)) include(SHOPP_TEMPLATES."/errors.php");
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
	// function order_confirmation () {
	// 	global $Shopp;
	// 	$Cart = $Shopp->Order->Cart;
	//
	// 	ob_start();
	// 	include(SHOPP_TEMPLATES."/confirm.php");
	// 	$content = ob_get_contents();
	// 	ob_end_clean();
	// 	return apply_filters('shopp_order_confirmation',$content);
	// }

	/**
	 * Renders the thanks template
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return string The processed thanks.php template file
	 **/
	// function thanks ($template="thanks.php") {
	// 	global $Shopp;
	// 	$Purchase = $Shopp->Purchase;
	//
	// 	ob_start();
	// 	include(SHOPP_TEMPLATES."/$template");
	// 	$content = ob_get_contents();
	// 	ob_end_clean();
	// 	return apply_filters('shopp_thanks',$content);
	// }

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
			$Shopp->Category = new ProductCategory($atts['name'],'name');
			unset($atts['name']);
		} elseif (isset($atts['slug'])) {
			foreach ($Shopp->Collections as $Collection) {
				$Collection_slug = get_class_property($Collection,'_slug');
				if ($atts['slug'] == $Collection_slug) {
					$tag = "$Collection_slug-products";
					if ($tag == "search-results-products") $tag = "search-products";
					unset($atts['slug']);
				}
			}
		} elseif (isset($atts['id'])) {
			$Shopp->Category = new ProductCategory($atts['id']);
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
			<?php shopp('product','addtocart',$button.( isset($atts['ajax']) && 'on' == $atts['ajax'] ? '&ajax=on' : '' )); ?></p>
		</form>
		<?php
		$markup = ob_get_contents();
		ob_end_clean();

		return $markup;
	}

	static function default_pages () {
		return array(
			'catalog' => 	array('title' => __('Shop','Shopp'), 'slug' => 'shop', 'description'=>__('The page title and base slug for products, categories &amp; collections.','Shopp') ),
			'account' => 	array('title' => __('Account','Shopp'), 'slug' => 'account', 'description'=>__('Used to display customer account dashboard &amp; profile pages.','Shopp') ),
			'cart' => 		array('title' => __('Cart','Shopp'), 'slug' => 'cart', 'description'=>__('Displays the shopping cart.','Shopp') ),
			'checkout' => 	array('title' => __('Checkout','Shopp'), 'slug' => 'checkout', 'description'=>__('Displays the checkout form.','Shopp') ),
			'confirm' => 	array('title' => __('Confirm Order','Shopp'), 'slug' => 'confirm-order', 'description'=>__('Used to display an order summary to confirm changes in order price.','Shopp') ),
			'thanks' => 	array('title' => __('Thank You!','Shopp'), 'slug' => 'thanks', 'description'=>__('The final page of the ordering process.','Shopp') ),
		);
	}

	static function pages_settings ($updates=false) {
		$pages = self::default_pages();

		$ShoppSettings = ShoppSettings();
		if (!$ShoppSettings) $ShoppSettings = new Settings();

		$settings = $ShoppSettings->get('storefront_pages');
		// @todo Check if slug is unique amongst shopp_product post type records to prevent namespace conflicts
		foreach ($pages as $name => &$page) {
			if (is_array($settings) && isset($settings[$name]))
				$page = array_merge($page,$settings[$name]);
			if (is_array($updates) && isset($updates[$name]))
				$page = array_merge($page,$updates[$name]);
		}

		return $pages;
	}

	static function slug ($page='catalog') {
		$pages = self::pages_settings();
		if (!isset($pages[$page])) $page = 'catalog';
		return $pages[$page]['slug'];
	}

	static function slugpage ($slug) {
		$pages = self::pages_settings();
		foreach ($pages as $name => $page)
			if ($slug == $page['slug']) return $name;
		return false;
	}

} // END class Storefront

?>