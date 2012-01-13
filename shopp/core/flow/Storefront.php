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
	var $referrer = false;
	var $search = false;		// The search query string
	var $searching = false;		// Flags if a search request has been made
	var $checkout = false;		// Flags when the checkout form is being processed
	var $pages = array();
	var $browsing = array();
	var $viewed = array();
	var $behaviors = array();	// Runtime JavaScript behaviors
	var $request = false;
	var $shortcoded = array();

	var $account = false;		// Account dashboard requests
	var $dashboard = array();	// Registry of account dashboard pages
	var $menus = array();		// Account dashboard menu registry

	function __construct () {
		parent::__construct();

		ShoppCatalog(new Catalog());

		ShoppingObject::store('search',$this->search);
		ShoppingObject::store('browsing',$this->browsing);
		ShoppingObject::store('referrer',$this->referrer);
		ShoppingObject::store('viewed',$this->viewed);

		// Setup WP_Query overrides
		add_action('parse_query', array($this, 'query'));
		add_filter('posts_request', array($this, 'noquery'));
		add_filter('posts_results', array($this, 'found'));
		add_filter('the_posts', array($this, 'posts'));

		add_action('wp', array($this, 'loaded'));
		add_action('wp', array($this, 'security'));
		add_action('wp', array($this, 'cart'));
		add_action('wp', array($this, 'shortcodes'));
		add_action('wp', array($this, 'behaviors'));

		add_filter('wp_get_nav_menu_items', array($this,'navmenus'), 10, 2);

		// Shopp product text filters
		add_filter('shopp_product_name','convert_chars');
		add_filter('shopp_product_summary','convert_chars');

		add_filter('shopp_product_description', 'wptexturize');
		add_filter('shopp_product_description', 'convert_chars');
		add_filter('shopp_product_description', 'wpautop');
		add_filter('shopp_product_description', 'do_shortcode', 11); // AFTER wpautop()

		add_filter('shopp_checkout_clickwrap_terms', 'wptexturize');
		add_filter('shopp_checkout_clickwrap_terms', 'convert_chars');
		add_filter('shopp_checkout_clickwrap_terms', 'wpautop');
		add_filter('shopp_checkout_clickwrap_terms', 'do_shortcode', 11); // AFTER wpautop()


		add_filter('shopp_product_spec', 'wptexturize');
		add_filter('shopp_product_spec', 'convert_chars');
		add_filter('shopp_product_spec', 'do_shortcode', 11); // AFTER wpautop()


		add_filter('shopp_order_lookup','shoppdiv');
		add_filter('shopp_order_confirmation','shoppdiv');
		add_filter('shopp_errors_page','shoppdiv');
		add_filter('shopp_catalog_template','shoppdiv');
		add_filter('shopp_cart_template','shoppdiv');
		add_filter('shopp_checkout_page','shoppdiv');
		add_filter('shopp_account_template','shoppdiv');
		add_filter('shopp_category_template','shoppdiv');
		add_filter('shopp_order_receipt','shoppdiv');
		add_filter('shopp_account_manager','shoppdiv');
		add_filter('shopp_account_vieworder','shoppdiv');

		add_filter('the_content',array($this,'autowrap'),99);

		add_action('wp_enqueue_scripts', 'shopp_dependencies');

		add_action('shopp_storefront_init',array($this,'promos'));
		add_action('shopp_storefront_init',array($this,'collections'));
		add_action('shopp_storefront_init',array($this,'account'));
		add_action('shopp_storefront_init',array($this,'dashboard'));

		add_filter('wp_nav_menu_objects',array($this,'menus'));

		if ($this->maintenance()) {
			add_filter('archive_template',array($this,'maintenance_page'));
			add_filter('search_template',array($this,'maintenance_page'));
			add_filter('page_template',array($this,'maintenance_page'));
			add_filter('single_template',array($this,'maintenance_page'));
			return;
		}

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

		if ( $this->is_shopp_request() ) {
			global $wp_query;
			$stub = new WPDatabaseObject();
			$stub->init('posts');
			$stub->ID = -42; // 42, the answer to everything. Force the stub to an unusable post ID
			$stub->comment_status = 'closed'; // Force comments closed
			return array($stub);
		}

		if (count($posts) == 1) { // @deprecated Legacy support to redirect old shortcode pages
			$shortcodes = join('|', array_keys( self::pages_settings() ) );
			if (preg_match("/\[($shortcodes)\]/",$posts[0]->post_content,$matches)) {
				$shortcode = next($matches);
				if ('catalog' == $shortcode) $shortcode = '';
				shopp_redirect( shoppurl($shortcode) );
				exit();
			}
		}

		return $posts;
	}

	function query ($wp_query) {

		// Only run once when WordPress is loaded
		// to handle the WordPress global $wp_query instance
		remove_action('parse_query',array($this,'query'));

		$page	 	= get_query_var('shopp_page');
		$posttype 	= get_query_var('post_type');
		$product 	= get_query_var(Product::$posttype);
		$collection = get_query_var('shopp_collection');
		$sortorder 	= get_query_var('s_so');
		$searching 	= get_query_var('s_cs');
		$search 	= get_query_var('s');

		if (!empty($sortorder))	$this->browsing['sortorder'] = $sortorder;

		// Override the custom post type archive request to use the Shopp catalog page
		if ($wp_query->is_archive && $posttype == Product::$posttype && '' == $product.$page) {
			$page = Storefront::slug('catalog'); set_query_var('shopp_page',$page);
		} else {

			if ($posttype == Product::$posttype && '' == $page) return;

			if (!is_shopp_taxonomy() && $collection.$page.$searching == ''
				&& $posttype != Product::$posttype) return;

		}

		// Shopp request, remove noindex
		remove_action( 'wp_head', 'noindex', 1 );
		$this->request = true;
		set_query_var('suppress_filters',false); // Override default WP_Query request

		// Restore paged query var for Shopp's alpha-pagination support
		if (isset($wp_query->query['paged']) && false != preg_match('/([A-Z]|0\-9)/i',$wp_query->query['paged']))
			$wp_query->query_vars['paged'] = strtoupper($wp_query->query['paged']);

		if (!empty($page)) {
			// Overrides to enforce page behavior
			$wp_query->is_home = false;
			$wp_query->is_singular = false;
			$wp_query->is_archive = false;
			$wp_query->is_page = true;
			$wp_query->post_count = true;
			$wp_query->shopp_page = true;
			return;
		}

		// Handle Taxonomies
		if (is_archive()) {
			$taxonomies = get_object_taxonomies(Product::$posttype, 'object');
			foreach ( $taxonomies as $t ) {
				if (get_query_var($t->query_var) == '') continue;
				$taxonomy = get_query_var($t->query_var);
				if ($t->hierarchical) ShoppCollection( new ProductCategory($taxonomy,'slug',$t->name) );
				else ShoppCollection( new ProductTag($taxonomy,'slug',$t->name) );
			}
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

			ShoppCollection( Catalog::load_collection($collection,$options) );
			if ('' == get_query_var('feed'))
				ShoppCollection()->load(array('load'=>array('coverimages')));

			// Provide a stub to the queried object for smart collections since WP has no parallel
			$post_archive = new stdClass();
			$post_archive->labels = new stdClass();
			$post_archive->labels->name = ShoppCollection()->name;
			$wp_query->queried_object = $post_archive;
			$wp_query->queried_object_id = 0;

		}

		$Collection = ShoppCollection();
		if (!empty($Collection)) {
			add_action('wp_head', array(&$this, 'metadata'));
			remove_action('wp_head','feed_links',2);
			add_action('wp_head', array(&$this, 'feedlinks'),2);
		}

		if (is_feed()) $this->feed();

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
		$Collection = ShoppCollection();

		// Bail if not the product archive
		// or not a shopp taxonomy request
		if (empty($Collection) && get_query_var('post_type') != Product::$posttype) return $template;

		add_filter('the_title',create_function('$title,$id','return in_the_loop() && is_archive() && -42 == $id?shopp("category","get-name"):$title;'),10,2);
		add_filter('the_content',array(&$this,'category_template'),11);

		$templates = array('shopp-collection.php', 'shopp-category.php', 'shopp.php', 'page.php');
		return locate_template($templates);
	}

	function pages ($template) {
		global $wp_query;

		$page = self::slugpage( get_query_var('shopp_page') );

		if (empty($page)) return $template;

		$pages = self::pages_settings();
		$pagetitle = apply_filters($page.'_page_title',$pages[$page]['title']);

		add_filter('the_title',create_function('$title,$id','return in_the_loop() && -42 == $id?"'.$pagetitle.'":$title;'),10,2);
		add_filter('the_content',array($this,$page.'_page'),20);

		$templates = array("$page.php", 'shopp.php', 'page.php');
		return locate_template($templates);
	}

	function single ($template) {
		$post_type = get_query_var('post_type');

		if ($post_type != Product::$posttype) return $template;
		add_filter('the_content',array(&$this,'product_template'),11);

		$templates = array('single-' . $post_type . '.php', 'shopp.php', 'page.php');
		return locate_template($templates);
	}

	function product_template ($content) {
		$Product = ShoppProduct();

		$templates = array('product.php');
		if (isset($Product->id) && !empty($Product->id))
			array_unshift($templates,'product-'.$Product->id.'.php');

		if (isset($Product->slug) && !empty($Product->slug))
			array_unshift($templates,'product-'.$Product->slug.'.php');

		// Load product summary data, before checking inventory
		if (!isset($Product->summed)) $Product->load_data(array('summary'));

		if ( str_true($Product->inventory) && $Product->stock < 1 )
			array_unshift($templates,'product-outofstock.php');

		ob_start();
		locate_shopp_template($templates,true);
		$content = ob_get_contents();
		ob_end_clean();
		return shoppdiv($content);
	}

	function category_template ($content) {
		global $wp_query;
		$Collection = ShoppCollection();

		// Short-circuit the loop for the archive/category requests
		$wp_query->current_post = $wp_query->post_count;
		ob_start();
		if (empty($Collection)) locate_shopp_template(array('catalog.php'),true);
		else {
			$templates = array('category.php','collection.php');
			$ids = array('slug','id');
			foreach ($ids as $property) {
				if (isset($Collection->$property)) $id = $Collection->$property;
				array_unshift($templates,'category-'.$id.'.php','collection-'.$id.'.php');
			}
			locate_shopp_template($templates,true);
		}
		$content = ob_get_contents();
		ob_end_clean();

		return apply_filters('shopp_category_template',$content);
	}

	/**
	 * Handles RSS-feed requests
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function feed ($feed) {
		if ('' == get_query_var('feed')) return;
		$Collection = ShoppCollection();

	    $base = shopp_setting('base_operations');

		add_filter('shopp_rss_description','wptexturize');
		add_filter('shopp_rss_description','convert_chars');
		add_filter('shopp_rss_description','make_clickable',9);
		add_filter('shopp_rss_description','force_balance_tags', 25);
		add_filter('shopp_rss_description','convert_smilies',20);
		add_filter('shopp_rss_description','wpautop',30);
		add_filter('shopp_rss_description','ent2ncr');

		do_action_ref_array('shopp_collection_feed',array(&$Collection));

		$rss = array('title' => trim(get_bloginfo('name')." ".$Collection->name),
			 			'link' => shopp($Collection,'get-feed-url'),
					 	'description' => $Collection->description,
						'sitename' => get_bloginfo('name').' ('.get_bloginfo('url').')',
						'xmlns' => array('shopp'=>'http://shopplugin.net/xmlns',
							'g'=>'http://base.google.com/ns/1.0',
							'atom'=>'http://www.w3.org/2005/Atom',
							'content'=>'http://purl.org/rss/1.0/modules/content/')
						);
		$rss = apply_filters('shopp_rss_meta',$rss);

		$tax_inclusive = shopp_setting_enabled('tax_inclusive');

		$template = locate_shopp_template(array('feed-'.$Collection->slug.'.php','feed.php'));
		if (!$template) $template = SHOPP_ADMIN_PATH.'/categories/feed.php';

		header("Content-type: application/rss+xml; charset=".get_option('blog_charset'));
		include($template);
		exit();
	}

	/**
	 * Renders RSS feed link tags for category product feeds
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function feedlinks () {
		if (empty(ShoppCollection()->name)) return;
		$title = apply_filters('shopp_collection_feed_title', sprintf('%s %s %s', get_bloginfo('name'), ShoppCollection()->name, __('Feed','Shopp')) );
		echo '<link rel="alternate" type="'.feed_content_type('rss').'" title="'.esc_attr($title).'" href="'.esc_attr(shopp('collection','get-feed-url')).'" />'."\n";
	}

	/**
	 * Forces SSL on pages when required by gateways that handle sensitive payment details onsite
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * @version 1.2
	 *
	 * @return void
	 **/
	function security () {

		global $Shopp;
		if (is_ssl() || !$Shopp->Gateways->secure) return;

		if (is_checkout_page())	shopp_redirect( shoppurl($_GET,'checkout',true) );
		if (is_confirm_page())	shopp_redirect( shoppurl($_GET,'confirm',true) );
		if (is_account_page())	shopp_redirect( shoppurl($_GET,'account',true) );

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

		if(is_ssl()) {
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

		add_action('wp_head', array(&$this, 'header'));
		add_action('wp_footer', array(&$this, 'footer'));
		wp_enqueue_style('shopp.catalog',SHOPP_ADMIN_URI.'/styles/catalog.css',array(),20110511,'screen');
		wp_enqueue_style('shopp',shopp_template_url('shopp.css'),array(),20110511,'screen');
		wp_enqueue_style('shopp.colorbox',SHOPP_ADMIN_URI.'/styles/colorbox.css',array(),20110511,'screen');

		$page = $this->slugpage(get_query_var('shopp_page'));

		$thankspage = ('thanks' == $page);
		$orderhistory = ('account' == $page && !empty($_GET['id']));

		if ($thankspage || $orderhistory)
			wp_enqueue_style('shopp.printable',SHOPP_ADMIN_URI.'/styles/printable.css',array(),20110511,'print');

		$loading = shopp_setting('script_loading');
		if (!$loading || 'global' == $loading || !empty($page)) {
			shopp_enqueue_script('colorbox');
			shopp_enqueue_script('shopp');
			shopp_enqueue_script('catalog');
			shopp_enqueue_script('cart');
			if (is_shopp_page('catalog'))
				shopp_custom_script('catalog',"var pricetags = {};\n");

			add_action('wp_head', array(&$Shopp, 'settingsjs'));

		}

		if ('checkout' == $page) shopp_enqueue_script('checkout');

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
	function titles ($title,$sep='&mdash;',$placement='left') {

		$request = array();
		$vars = array('s_cat','s_tag','s_pd','s_pid');
		foreach ($vars as $v) $request[] = get_query_var($v);

		if (empty($request)) return $title;
		if (empty(ShoppProduct()->name) && empty(ShoppCollection()->name)) return $title;

		$_ = array();
		if (!empty($title))						$_[] = $title;
		if (!empty(ShoppCollection()->name))	$_[] = ShoppCollection()->name;
		if (!empty(ShoppProduct()->name))		$_[] = ShoppProduct()->name;

		if ('right' == $placement) $_ = array_reverse($_);

		$_ = apply_filters('shopp_document_titles',$_);
		$sep = trim($sep);
		if (empty($sep)) $sep = '&mdash;';
		return join(" $sep ",$_);
	}

	function navmenus ($items) {
		foreach ($items as &$item) {
			switch (strtolower($item->type)) {
				case 'shopp_page': $item->url = shoppurl(false,$item->object); break;
				case 'shopp_collection':
					$namespace = get_class_property( 'SmartCollection' ,'namespace');
					$taxonomy = get_class_property( 'SmartCollection' ,'taxonomy');
					$prettyurls = ( '' != get_option('permalink_structure') );
					$item->url = shoppurl( $prettyurls ? "$namespace/$item->object" : array($taxonomy=>$item->object),false );
					break;
			}
		}
		return $items;
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
		if (!empty(ShoppProduct()->id)) {
			if (empty(ShoppProduct()->tags)) ShoppProduct()->load_data(array('tags'));
			foreach(ShoppProduct()->tags as $tag)
				$keywords .= (!empty($keywords))?", {$tag->name}":$tag->name;
			$description = ShoppProduct()->summary;
		} elseif (!empty(ShoppCollection()->id)) {
			$description = ShoppCollection()->description;
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
		// Product catalog archive (landing) page URL
		if (is_post_type_archive() && is_shopp_page('catalog'))
			return shopp('catalog','get-url');

		// Specific product/category URLs
		if (!empty($Shopp->Product->slug)) return shopp('product','get-url');
		if (!empty($Shopp->Category->slug)) {
			$paged = (int)get_query_var('paged');
			$url = shopp('category','get-url');
			if ($paged > 1) $url = shopp('category','get-url',"page=$paged");
		}
		return $url;
	}


	function promos () {
		if (!isset($this->promos) || empty($this->promos)) return;
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
	 * @return void
	 **/
	function collections () {

		do_action('shopp_register_smartcategories'); // @deprecated
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
		$canonurl = $this->canonurls(false);
		// Add canonical URLs
		if (is_shopp_page('catalog') && !empty($canonurl))
			echo '<link rel="canonical" href="'.apply_filters('shopp_canonical_link',$canonurl).'" />';

		// Add noindex for cart, checkout, account pages
		if (is_shopp_page('cart') || is_shopp_page('checkout') || is_shopp_page('account'))
			noindex();
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

		$globals = false;
		if (isset($this->behaviors['global'])) {
			$globals = $this->behaviors['global'];
			unset($this->behaviors['global']);
		}

		$script = '';
		if (!empty($globals)) $script .= "\t".join("\n\t",$globals)."\n";
		if (!empty($this->behaviors)) {
			$script .= 'jQuery(window).ready(function($){'."\n";
			$script .= "\t".join("\t\n",$this->behaviors)."\n";
			$script .= '});'."\n";
		}
		shopp_custom_script('catalog',$script);
	}

	function menus ($menuitems) {

		$is_shopp_page = is_shopp_page();
		$keymap = array();
		$parents = array();
		foreach ($menuitems as $key => $item) {
			$page = false;
			// Remove the faulty wp_page_menu (deprecated) class for Shopp pages
			if ($is_shopp_page && in_array('current_page_parent',$item->classes))
				unset($item->classes[ array_search('current_page_parent',$item->classes) ]);

			// Otherwise, skip dealing with any non-Shopp page
			if ('shopp_page' == $item->type) {
				// Determine the queried Shopp page object name
				$page = Storefront::slugpage( get_query_var('shopp_page') );

				// Set the catalog as current page parent
				if ('catalog' == $item->object && $is_shopp_page) $item->classes[] = 'current-page-parent';

				$keymap[$item->db_id] = $key;
			}

			if ('shopp_collection' == $item->type) {
				$page = get_query_var($item->type);
				$keymap[$item->db_id] = $key;
			}

			if ($page == $item->object) {
				$item->classes[] = 'current-page-item';
				$item->classes[] = 'current-menu-item';
				$parents[] = $item->menu_item_parent;
			}

		}

		foreach ((array)$parents as $parentid) {
			if (!isset($keymap[$parentid])) continue;
			$parent = $menuitems[ $keymap[$parentid] ];
			$parent->classes[] = 'current-menu-parent';
			$parent->classes[] = 'current-page-parent';
			$parent->classes[] = 'current-menu-ancestor';
			$parent->classes[] = 'current-page-ancestor';

			$ancestor = $parent;
			while($ancestor->menu_item_parent != 0) {
				$ancestor = $menuitems[ $keymap[ $ancestor->menu_item_parent ] ];
				$ancestor->classes[] = 'current-menu-ancestor';
				$ancestor->classes[] = 'current-page-ancestor';
			}
		}

		return $menuitems;
	}

	/**
	 * Handles rendering the maintenance message
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return string The processed content
	 **/
	function maintenance_page ($template) {
		if (!$this->is_shopp_request()) return $template;
		global $wp_query;

		if ( '' != locate_shopp_template(array('maintenance.php')) ) {
			ob_start();
			locate_shopp_template(array('maintenance.php'));
			$content = ob_get_contents();
			ob_end_clean();
		} else $content = '<div id="shopp" class="update"><p>'.__("The store is currently down for maintenance.  We'll be back soon!","Shopp").'</p><div class="clear"></div></div>';

		$stub = new WPDatabaseObject();
		$stub->init('posts');
		$stub->ID = -42; // 42, the answer to everything. Force the stub to an unusable post ID
		$stub->comment_status = 'closed'; // Force comments closed
		$stub->post_title = __("We're Sorry!",'Shopp');
		$stub->post_content = $content;
		$wp_query->posts = array($stub);

		$templates = array("shopp-maintenance.php", 'shopp.php', 'page.php');
		return locate_template($templates);
	}

	function catalog_page () {
		global $Shopp,$wp,$wp_query;
		if (SHOPP_DEBUG) new ShoppError('Displaying catalog page request: '.$_SERVER['REQUEST_URI'],'shopp_catalog',SHOPP_DEBUG_ERR);

		// $referrer = get_bloginfo('url')."/".$wp->request;
		// if (!empty($wp->query_vars)) $referrer = add_query_arg($wp->query_vars,$referrer);
		// $this->referrer = $referrer;

		ob_start();
		locate_shopp_template(array('catalog.php'),true);
		$content = ob_get_contents();
		ob_end_clean();

		return apply_filters('shopp_catalog_template',$content);
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
	function cart_page ($content) {
		$Order = ShoppOrder();
		$Cart = $Order->Cart;

		ob_start();
		locate_shopp_template(array('cart.php'),true);
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
		$Errors = ShoppErrors();
		$Order = ShoppOrder();
		$Cart = $Order->Cart;
		$process = get_query_var('s_pr');

		do_action('shopp_init_checkout');

		ob_start();
		if ($Errors->exist(SHOPP_COMM_ERR)) locate_shopp_template(array('errors.php'),true);
		$this->checkout = true;
		locate_shopp_template(array('checkout.php'),true);
		$content = ob_get_contents();
		ob_end_clean();

		return apply_filters('shopp_checkout_page',$content);
	}

	function confirm_page () {
		$Errors = ShoppErrors();
		$Order = ShoppOrder();
		$Cart = $Order->Cart;

		do_action('shopp_init_confirmation');
		$Order->validated = $Order->isvalid();

		$errors = '';
		if ($Errors->exist(SHOPP_COMM_ERR)) {
			ob_start();
			locate_shopp_template(array('errors.php'),true);
			$errors = ob_get_contents();
			ob_end_clean();
		}

		ob_start();
		locate_shopp_template(array('confirm.php'),true);
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
		locate_shopp_template(array('thanks.php'),true);
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
	function error_page ($template='errors.php') {
		global $Shopp;
		$Cart = $Shopp->Orders->Cart;

		ob_start();
		locate_shopp_template(array($template),true);
		$content = ob_get_contents();
		ob_end_clean();
		return apply_filters('shopp_errors_page',$content);
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
	function account_page () {

		$download_request = get_query_var('s_dl');
		$request = $this->account['request'];
		$templates = array('account-'.$request.'.php','account.php');

		if ('login' == $request || !ShoppCustomer()->logged_in()) $templates = array('login-'.$request.'.php','login.php');
		else do_action('shopp_account_management');

		ob_start();
		if (!empty($download_request)) locate_shopp_template(array('errors.php'),true);
		locate_shopp_template($templates,true);
		$content = ob_get_contents();
		ob_end_clean();

		return apply_filters('shopp_account_template',$content);

	}

	function dashboard () {
		$Order = ShoppOrder();

		$this->add_dashboard('logout',__('Logout','Shopp'));
		$this->add_dashboard('orders',__('Your Orders','Shopp'),true,array(ShoppCustomer(),'load_orders'));
		$this->add_dashboard('downloads',__('Downloads','Shopp'),true,array(ShoppCustomer(),'load_downloads'));
		$this->add_dashboard('profile',__('My Account','Shopp'));

		// Pages not in menu navigation
		$this->add_dashboard('login',__('Login to your Account'),false);
		$this->add_dashboard('recover','Password Recovery',false);
		$this->add_dashboard('rp','Password Recovery',false);
		$this->add_dashboard('menu',__('Dashboard','Shopp'),false);

		do_action('shopp_account_menu');

		add_action('shopp_account_management',array($this,'dashboard_handler'));

		$query = $_SERVER['QUERY_STRING'];
		$query = html_entity_decode($query);
		$query  = explode('&', $query);

		$request = 'menu';
		$id = false;

		foreach ($query as $queryvar) {
			$value = false;
			if (false !== strpos($queryvar,'=')) list($key,$value) = explode('=',$queryvar);
			else $key = $queryvar;

			if ( in_array($key,array_keys($this->dashboard))) {
				$request = $key;
				$id = $value;
			}
		}

		$this->account = compact('request','id');

		$download_request = get_query_var('s_dl');
		if (!ShoppCustomer()->logged_in()) {
			$screens = array('login','recover','rp');
			if (!in_array($this->account['request'],$screens))
				$this->account = array('request' => 'login','id' => false);
		}

		do_action('shopp_account_management');

		if ('rp' == $request) $this->account_resetpwd($_GET['rp']);
		if (isset($_POST['recover-login'])) $this->account_recovery();

	}

	function dashboard_handler () {
		$request = $this->account['request'];

		if (isset($this->dashboard[$request])
			&& is_callable($this->dashboard[$request]->handler))
				call_user_func($this->dashboard[$request]->handler);

	}

	function add_dashboard ($request,$label,$visible=true,$callback=false,$position=0) {
		$this->dashboard[$request] = new StorefrontDashboardPage($request,$label,$callback);
		if ($visible) array_splice($this->menus,$position,0,array(&$this->dashboard[$request]));
	}


	/**
	 * Password recovery processing
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @return void
	 **/
	function account_recovery () {
		$errors = array();

		// Check email or login supplied
		if (empty($_POST['account-login'])) {
			if ( 'wordpress' == shopp_setting('account_system') ) $errors[] = new ShoppError(__('Enter an email address or login name','Shopp'));
			else $errors[] = new ShoppError(__('Enter an email address','Shopp'));
		} else {
			// Check that the account exists
			if (strpos($_POST['account-login'],'@') !== false) {
				$RecoveryCustomer = new Customer($_POST['account-login'],'email');
				if (!$RecoveryCustomer->id)
					$errors[] = new ShoppError(__('There is no user registered with that email address.','Shopp'),'password_recover_noaccount',SHOPP_AUTH_ERR);
			} else {
				$user_data = get_userdatabylogin($_POST['account-login']);
				$RecoveryCustomer = new Customer($user_data->ID,'wpuser');
				if (empty($RecoveryCustomer->id))
					$errors[] = new ShoppError(__('There is no user registered with that login name.','Shopp'),'password_recover_noaccount',SHOPP_AUTH_ERR);
			}
		}

		// return errors
		if (!empty($errors)) return;

		// Generate new key
		$RecoveryCustomer->activation = wp_generate_password(20, false);
		do_action_ref_array('shopp_generate_password_key', array(&$RecoveryCustomer));
		$RecoveryCustomer->save();

		$subject = apply_filters('shopp_recover_password_subject', sprintf(__('[%s] Password Recovery Request','Shopp'),get_option('blogname')));

		$_ = array();
		$_[] = 'From: "'.get_option('blogname').'" <'.shopp_setting('merchant_email').'>';
		$_[] = 'To: '.$RecoveryCustomer->email;
		$_[] = 'Subject: '.$subject;
		$_[] = '';
		$_[] = __('A request has been made to reset the password for the following site and account:','Shopp');
		$_[] = get_option('siteurl');
		$_[] = '';
		if (isset($_POST['email-login']))
			$_[] = sprintf(__('Email: %s','Shopp'), $RecoveryCustomer->email);
		if (isset($_POST['loginname-login']))
			$_[] = sprintf(__('Login name: %s','Shopp'), $user_data->user_login);
		$_[] = '';
		$_[] = __('To reset your password visit the following address, otherwise just ignore this email and nothing will happen.');
		$_[] = '';
		$_[] = add_query_arg(array('rp'=>$RecoveryCustomer->activation),shoppurl(false,'account'));
		$message = apply_filters('shopp_recover_password_message',$_);

		if (!shopp_email(join("\n",$message))) {
			new ShoppError(__('The e-mail could not be sent.'),'password_recovery_email',SHOPP_ERR);
			shopp_redirect(add_query_arg('acct','recover',shoppurl(false,'account')));
		} else {
			new ShoppError(__('Check your email address for instructions on resetting the password for your account.','Shopp'),'password_recovery_email',SHOPP_ERR);
		}

	}

	function account_resetpwd ($activation) {
		if ( 'none' == shopp_setting('account_system') ) return;

		$user_data = false;
		$activation = preg_replace('/[^a-z0-9]/i', '', $activation);

		$errors = array();
		if (empty($activation) || !is_string($activation))
			$errors[] = new ShoppError(__('Invalid key','Shopp'));

		$RecoveryCustomer = new Customer($activation,'activation');
		if (empty($RecoveryCustomer->id))
			$errors[] = new ShoppError(__('Invalid key','Shopp'));

		if (!empty($errors)) return false;

		// Generate a new random password
		$password = wp_generate_password();

		do_action_ref_array('password_reset', array(&$RecoveryCustomer,$password));

		$RecoveryCustomer->password = wp_hash_password($password);
		if ( 'wordpress' == shopp_setting('account_system') ) {
			$user_data = get_userdata($RecoveryCustomer->wpuser);
			wp_set_password($password, $user_data->ID);
		}

		$RecoveryCustomer->activation = '';
		$RecoveryCustomer->save();

		$subject = apply_filters('shopp_reset_password_subject', sprintf(__('[%s] New Password','Shopp'),get_option('blogname')));

		$_ = array();
		$_[] = 'From: "'.get_option('blogname').'" <'.shopp_setting('merchant_email').'>';
		$_[] = 'To: '.$RecoveryCustomer->email;
		$_[] = 'Subject: '.$subject;
		$_[] = '';
		$_[] = sprintf(__('Your new password for %s:','Shopp'),get_option('siteurl'));
		$_[] = '';
		if ($user_data)
			$_[] = sprintf(__('Login name: %s','Shopp'), $user_data->user_login);
		$_[] = sprintf(__('Password: %s'), $password) . "\r\n";
		$_[] = '';
		$_[] = __('Click here to login:').' '.shoppurl(false,'account');
		$message = apply_filters('shopp_reset_password_message',$_);

		if (!shopp_email(join("\n",$message))) {
			new ShoppError(__('The e-mail could not be sent.'),'password_reset_email',SHOPP_ERR);
			shopp_redirect(add_query_arg('acct','recover',shoppurl(false,'account')));
		} else new ShoppError(__('Check your email address for your new password.','Shopp'),'password_reset_email',SHOPP_ERR);

		unset($_GET['acct']);
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

		// Additional shortcode functionality
		$this->shortcodes['catalog-product'] = array(&$this,'product_shortcode');
		$this->shortcodes['catalog-buynow'] = array(&$this,'buynow_shortcode');
		$this->shortcodes['catalog-collection'] = array(&$this,'collection_shortcode');

		// @deprecated shortcodes
		$this->shortcodes['product'] = array(&$this,'product_shortcode');
		$this->shortcodes['buynow'] = array(&$this,'buynow_shortcode');
		$this->shortcodes['category'] = array(&$this,'collection_shortcode');

		foreach ($this->shortcodes as $name => &$callback)
			if (shopp_setting('maintenance') == 'on' || !ShoppSettings()->available() || $this->maintenance())
				add_shortcode($name,array(&$this,'maintenance_shortcode'));
			else add_shortcode($name,$callback);

	}

	function autowrap ($content) {
		if ( ! in_array(get_the_ID(),$this->shortcoded) ) return $content;
		return shoppdiv($content);
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
		$atts['template'] = array('product-shortcode.php','product.php');
		$this->shortcoded[] = get_the_ID();
		return apply_filters('shopp_product_shortcode',shopp('catalog','get-product',$atts));
	}

	/**
	 * Handles rendering the [catalog-collection] shortcode
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $attrs The parsed shortcode attributes
	 * @return string The processed content
	 **/
	function collection_shortcode ($atts) {
		global $Shopp;
		$tag = 'category';
		if (isset($atts['name'])) {
			$Collection = new ProductCategory($atts['name'],'name');
			unset($atts['name']);
		} elseif (isset($atts['slug'])) {
			foreach ($Shopp->Collections as $SmartCollection) {
				$Collection_slug = get_class_property($SmartCollection,'_slug');
				if ($atts['slug'] == $Collection_slug) {
					$tag = "$Collection_slug-products";
					unset($atts['slug']);
					break;
				}
			}

		} elseif (isset($atts['id'])) {
			$Collection = new ProductCategory($atts['id']);
			unset($atts['id']);
		} else return "";

		ShoppCollection($Collection);

		$markup = shopp('catalog',"get-$tag",$atts);
		$this->shortcoded[] = get_the_ID();

		// @deprecated in favor of the shopp_collection_shortcode
		$markup = apply_filters('shopp_category_shortcode',$markup);
		return apply_filters('shopp_collection_shortcode',$markup);
	}

	/**
	 * Handles rendering the [product-buynow] shortcode
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $attrs The parsed shortcode attributes
	 * @return string The processed content
	 **/
	function buynow_shortcode ($atts) {

		$properties = array('name','slug','id');
		foreach ($properties as $prop) {
			if (!isset($atts[$prop])) continue;
			$Product = new Product($atts[ $prop ],$prop);
		}

		if (empty($Product->id)) return "";

		ShoppProduct($Product);

		ob_start();
		?>
		<form action="<?php shopp('cart','url'); ?>" method="post" class="shopp product">
			<input type="hidden" name="redirect" value="checkout" />
			<?php if (isset($atts['variations'])): ?>
				<?php if(shopp('product','has-variations')): ?>
				<ul class="variations">
					<?php shopp('product','variations','mode=multiple&label=true&defaults='.__('Select an option','Shopp').'&before_menu=<li>&after_menu=</li>'); ?>
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

		$this->shortcoded[] = get_the_ID();

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

		// Remove pages if the shopping cart is disabled
		if (!shopp_setting_enabled('shopping_cart'))
			unset($pages['cart'],$pages['checkout'],$pages['confirm'],$pages['thanks']);

		return $pages;
	}

	/**
	 * Provides the Storefront page slug by its named system ID
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 * @see Storefront::default_pages()
	 *
	 * @return string Named ID of the page
	 **/
	static function slug ($page='catalog') {
		$pages = self::pages_settings();
		if (!isset($pages[$page])) $page = 'catalog';
		return $pages[$page]['slug'];
	}

	/**
	 * Provides the system named ID from a Storefront page slug
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return string The page slug
	 **/
	static function slugpage ($slug) {
		$pages = self::pages_settings();
		foreach ($pages as $name => $page)
			if ($slug == $page['slug']) return $name;
		return false;
	}

} // END class Storefront

/**
 * CustomerAccountPage class
 *
 * A property container for Shopp's customer account page meta
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package customer
 **/
class StorefrontDashboardPage {
	var $request = "";
	var $label = "";
	var $handler = false;

	function __construct ($request,$label,$handler) {
		$this->request = $request;
		$this->label = $label;
		$this->handler = $handler;
	}

} // END class StorefrontDashboardPage

?>