<?php
/**
 * Categorize
 * 
 * Flow controller for category management interfaces
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 6, 2010
 * @package shopp
 * @subpackage categories
 **/

class Categorize extends AdminController {
	
	/**
	 * Categorize constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		parent::__construct();

		if (!empty($_GET['id']) && !isset($_GET['a'])) {
			
			wp_enqueue_script('postbox');
			if ( user_can_richedit() ) {
				wp_enqueue_script('editor');
				wp_enqueue_script('quicktags');
				add_action( 'admin_print_footer_scripts', 'wp_tiny_mce', 20 );
			}
			
			shopp_enqueue_script('colorbox');
			shopp_enqueue_script('editors');
			shopp_enqueue_script('category-editor');
			shopp_enqueue_script('priceline');
			shopp_enqueue_script('ocupload');
			shopp_enqueue_script('swfupload');
			shopp_enqueue_script('shopp-swfupload-queue');

			do_action('shopp_category_editor_scripts');
			add_action('admin_head',array(&$this,'layout'));
		} elseif (!empty($_GET['a']) && $_GET['a'] == 'arrange') {
			shopp_enqueue_script('category-arrange');
			do_action('shopp_category_arrange_scripts');
			add_action('admin_print_scripts',array(&$this,'arrange_cols'));
		} elseif (!empty($_GET['a']) && $_GET['a'] == 'products') {
			shopp_enqueue_script('products-arrange');
			do_action('shopp_category_products_arrange_scripts');
			add_action('admin_print_scripts',array(&$this,'products_cols'));
		} else add_action('admin_print_scripts',array(&$this,'columns'));
		do_action('shopp_category_admin_scripts');
		add_action('load-shopp_page_shopp-categories',array(&$this,'workflow'));
	}
	
	/**
	 * Parses admin requests to determine which interface to display
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function admin () {
		if (!empty($_GET['id']) && !isset($_GET['a'])) $this->editor();
		elseif (!empty($_GET['id']) && isset($_GET['a']) && $_GET['a'] == "products") $this->products();
		else $this->categories();
	}

	/**
	 * Handles loading, saving and deleting categories in a workflow context
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function workflow () {
		global $Shopp;
		$db =& DB::get();
		$defaults = array(
			'page' => false,
			'deleting' => false,
			'delete' => false,
			'id' => false,
			'save' => false,
			'duplicate' => false,
			'next' => false
			);
		$args = array_merge($defaults,$_REQUEST);
		extract($args,EXTR_SKIP);

		if (!defined('WP_ADMIN') || !isset($page)
			|| $page != $this->Admin->pagename('categories'))
				return false;

		$adminurl = admin_url('admin.php');		
			
		if ($page == $this->Admin->pagename('categories')
				&& !empty($deleting) 
				&& !empty($delete) 
				&& is_array($delete)) {
			foreach($delete as $deletion) {
				$Category = new Category($deletion);
				if (empty($Category->id)) continue;
				$db->query("UPDATE $Category->_table SET parent=0 WHERE parent=$Category->id");
				$Category->delete();
			}
			$redirect = (add_query_arg(array_merge($_GET,array('delete'=>null,'deleting'=>null)),$adminurl));
			shopp_redirect($redirect);
		}
		
		if ($id && $id != "new")
			$Shopp->Category = new Category($id);
		else $Shopp->Category = new Category();
						
		if ($save) {
			$this->save($Shopp->Category);
			$this->Notice = '<strong>'.stripslashes($Shopp->Category->name).'</strong> '.__('has been saved.','Shopp');

			if ($next) {
				if ($next != "new") 
					$Shopp->Category = new Category($next);
				else $Shopp->Category = new Category();
			} else {
				if (empty($id)) $id = $Shopp->Category->id;
				$Shopp->Category = new Category($id);
			}
				
		}
	}

	/**
	 * Interface processor for the category list manager
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function categories ($workflow=false) {
		global $Shopp;
		$db = DB::get();

		if ( !(is_shopp_userlevel() || current_user_can('shopp_categories')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$defaults = array(
			'pagenum' => 1,
			'per_page' => 20,
			's' => '',
			'a' => ''
			);
		$args = array_merge($defaults,$_GET);
		extract($args,EXTR_SKIP);

		if ('arrange' == $a)  {
			$this->init_positions();
			$per_page = 300;
		}

		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1)); 
		
		$filters = array();
		// $filters['limit'] = "$start,$per_page";
		if (!empty($s)) $filters['where'] = "cat.name LIKE '%$s%'";
		
		$table = DatabaseObject::tablename(Category::$table);
		$Catalog = new Catalog();
		$Catalog->outofstock = true;
		if ($workflow) {
			$filters['columns'] = "cat.id,cat.parent,cat.priority";
			$results = $Catalog->load_categories($filters,false,true);
			return array_slice($results,$start,$per_page);
		} else {
			if ('arrange' == $a) {
				$filters['columns'] = "cat.id,cat.parent,cat.priority,cat.name,cat.uri,cat.slug";
				$filters['parent'] = '0';
			} else $filters['columns'] = "cat.id,cat.parent,cat.priority,cat.name,cat.description,cat.uri,cat.slug,cat.spectemplate,cat.facetedmenus,count(DISTINCT pd.id) AS total";
			
			$Catalog->load_categories($filters);
			$Categories = array_slice($Catalog->categories,$start,$per_page);
		}

		$count = $db->query("SELECT count(*) AS total FROM $table");
		$num_pages = ceil($count->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( array('edit'=>null,'pagenum' => '%#%' )),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));
		
		$action = esc_url(
			add_query_arg(
				array_merge(stripslashes_deep($_GET),array('page'=>$this->Admin->pagename('categories'))),
				admin_url('admin.php')
			)
		);
		
		if ('arrange' == $a) {
			include(SHOPP_ADMIN_PATH."/categories/arrange.php");
			return;
		}
		
		
		include(SHOPP_ADMIN_PATH."/categories/categories.php");
	}

	/**
	 * Registers column headings for the category list manager
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function columns () {
		register_column_headers('shopp_page_shopp-categories', array(
			'cb'=>'<input type="checkbox" />',
			'name'=>__('Name','Shopp'),
			'links'=>__('Products','Shopp'),
			'templates'=>__('Templates','Shopp'),
			'menus'=>__('Menus','Shopp'))
		);
	}

	/**
	 * Provides the core interface layout for the category editor
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function layout () {
		global $Shopp;
		$Admin =& $Shopp->Flow->Admin;
		include(SHOPP_ADMIN_PATH."/categories/ui.php");
	}

	/**
	 * Registers column headings for the category list manager
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function arrange_cols () {
		register_column_headers('shopp_page_shopp-categories', array(
			'cat'=>__('Category','Shopp'),
			'move'=>'<div class="move">&nbsp;</div>')
		);
	}

	/**
	 * Interface processor for the category editor
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function editor () {
		global $Shopp,$CategoryImages;
		$db = DB::get();
		
		if ( !(is_shopp_userlevel() || current_user_can('shopp_categories')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (empty($Shopp->Category)) $Category = new Category();
		else $Category = $Shopp->Category;

		$Category->load_images();

		$Price = new Price();
		$priceTypes = array(
			array('value'=>'Shipped','label'=>__('Shipped','Shopp')),
			array('value'=>'Virtual','label'=>__('Virtual','Shopp')),
			array('value'=>'Download','label'=>__('Download','Shopp')),
			array('value'=>'Donation','label'=>__('Donation','Shopp')),
			array('value'=>'N/A','label'=>__('N/A','Shopp'))
		);

		// Build permalink for slug editor
		$permalink = trailingslashit(shoppurl())."category/";
		$Category->slug = apply_filters('editable_slug',$Category->slug);
		if (!empty($Category->slug))
			$permalink .= substr($Category->uri,0,strpos($Category->uri,$Category->slug));

		$pricerange_menu = array(
			"disabled" => __('Price ranges disabled','Shopp'),
			"auto" => __('Build price ranges automatically','Shopp'),
			"custom" => __('Use custom price ranges','Shopp'),
		);
		
		
		$categories_menu = $this->menu($Category->parent,$Category->id);
		$categories_menu = '<option value="0">'.__('Parent Category','Shopp').'&hellip;</option>'.$categories_menu;

		$uploader = $Shopp->Settings->get('uploader_pref');
		if (!$uploader) $uploader = 'flash';
		
		$workflows = array(
			"continue" => __('Continue Editing','Shopp'),
			"close" => __('Categories Manager','Shopp'),
			"new" => __('New Category','Shopp'),
			"next" => __('Edit Next','Shopp'),
			"previous" => __('Edit Previous','Shopp')
			);
		
		include(SHOPP_ADMIN_PATH."/categories/category.php");
	}

	/**
	 * Handles saving updated category information from the category editor
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function save ($Category) {
		global $Shopp;
		$Settings = &ShoppSettings();
		$db = DB::get();
		check_admin_referer('shopp-save-category');
		
		if ( !(is_shopp_userlevel() || current_user_can('shopp_categories')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));
		
		$Settings->saveform(); // Save workflow setting
		
		$Shopp->Catalog = new Catalog();
		$Shopp->Catalog->load_categories(array(
			'columns' => "cat.id,cat.parent,cat.name,cat.description,cat.uri,cat.slug", 
			'where' => array(), 
			'joins' => array(), 
			'orderby' => false, 
			'order' => false, 
			'outofstock' => true 
		));

		$Category->update_slug();

		if (!empty($_POST['deleteImages'])) {			
			$deletes = array();
			if (strpos($_POST['deleteImages'],","))	$deletes = explode(',',$_POST['deleteImages']);
			else $deletes = array($_POST['deleteImages']);
			$Category->delete_images($deletes);
		}

		// Variation price templates
		if (!empty($_POST['price']) && is_array($_POST['price'])) {
			foreach ($_POST['price'] as &$pricing) {
				$pricing['price'] = floatvalue($pricing['price'],false);
				$pricing['saleprice'] = floatvalue($pricing['saleprice'],false);
				$pricing['shipfee'] = floatvalue($pricing['shipfee'],false);
			}
			$Category->prices = stripslashes_deep($_POST['price']);
		} else $Category->prices = array();

		if (empty($_POST['specs'])) $Category->specs = array();
		else $_POST['specs'] = stripslashes_deep($_POST['specs']);
		
		if (empty($_POST['options']) 
			|| (count($_POST['options']['v'])) == 1 && !isset($_POST['options']['v'][1]['options'])) {
				$_POST['options'] = $Category->options = array();
				$_POST['prices'] = $Category->prices = array();
		} else $_POST['options'] = stripslashes_deep($_POST['options']);
		if (isset($_POST['content'])) $_POST['description'] = $_POST['content'];

		$Category->updates($_POST);
		$Category->save();
		
		if (!empty($_POST['images']) && is_array($_POST['images'])) {
			$Category->link_images($_POST['images']);
			$Category->save_imageorder($_POST['images']);
			if (!empty($_POST['imagedetails']) && is_array($_POST['imagedetails'])) {
				foreach($_POST['imagedetails'] as $i => $data) {
					$Image = new CategoryImage($data['id']);
					$Image->title = $data['title'];
					$Image->alt = $data['alt'];
					$Image->save();
				}
			}
		}

		do_action_ref_array('shopp_category_saved',array(&$Category));
		
		$updated = '<strong>'.$Category->name.'</strong> '.__('category saved.','Shopp');
		
	}
	
	/**
	 * Set
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 * 
	 * @return void Description...
	 **/
	function init_positions () {
		$db =& DB::get();
		// Load the entire catalog structure and update the category positions
		$Catalog = new Catalog();
		$Catalog->outofstock = true;
	
		$filters['columns'] = "cat.id,cat.parent,cat.priority";
		$Catalog->load_categories($filters);

		foreach ($Catalog->categories as $Category)
			if (!isset($Category->_priority) // Check previous priority and only save changes
					|| (isset($Category->_priority) && $Category->_priority != $Category->priority))
				$db->query("UPDATE $Category->_table SET priority=$Category->priority WHERE id=$Category->id");
		
	}

	/**
	 * Renders a drop-down menu for selecting parent categories
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @param int $selection The id of the currently selected parent category
	 * @param int $current The id of the currently edited category
	 * @return void Description...
	 **/
	function menu ($selection=false,$current=false) {
		$db = DB::get();
		$table = DatabaseObject::tablename(Category::$table);			
		$categories = $db->query("SELECT id,name,parent FROM $table ORDER BY parent,name",AS_ARRAY);
		$categories = sort_tree($categories);

		$options = '';
		foreach ($categories as $category) {
			$padding = str_repeat("&nbsp;",$category->depth*3);
			$selected = ($category->id == $selection)?' selected="selected"':'';
			$disabled = ($current && $category->id == $current)?' disabled="disabled"':'';
			$options .= '<option value="'.$category->id.'"'.$selected.$disabled.'>'.$padding.esc_html($category->name).'</option>';
		}
		return $options;
	}

	/**
	 * Registers column headings for the category list manager
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @return void
	 **/
	function products_cols () {
		register_column_headers('shopp_page_shopp-categories', array(
			'move'=>'<img src="'.SHOPP_ADMIN_URI.'/icons/updating.gif" alt="updating" width="16" height="16" class="hidden" />',
			'p'=>__('Product','Shopp'))
		);
	}

	/**
	 * Interface processor for the product list manager
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function products ($workflow=false) {
		global $Shopp;
		$db = DB::get();

		if ( !(is_shopp_userlevel() || current_user_can('shopp_categories')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$defaults = array(
			'pagenum' => 1,
			'per_page' => 500,
			'id' => 0,
			's' => ''
			);
		$args = array_merge($defaults,$_GET);
		extract($args,EXTR_SKIP);

		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1)); 
		
		$filters = array();
		// $filters['limit'] = "$start,$per_page";
		if (!empty($s)) 
			$filters['where'] = "cat.name LIKE '%$s%'";
		else $filters['where'] = "true";
		
		$Category = new Category($id);
		
		$catalog_table = DatabaseObject::tablename(Catalog::$table);
		$product_table = DatabaseObject::tablename(Product::$table);
		$columns = "c.id AS cid,p.id,c.priority,p.name";
		$where = "c.parent=$id AND type='category'";
		$query = "SELECT $columns FROM $catalog_table AS c LEFT JOIN $product_table AS p ON c.product=p.id WHERE $where ORDER BY c.priority ASC,p.name ASC LIMIT $start,$per_page";
		$products = $db->query($query);

		$count = $db->query("SELECT count(*) AS total FROM $table");
		$num_pages = ceil($count->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg( array('edit'=>null,'pagenum' => '%#%' )),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum
		));
		
		$action = esc_url(
			add_query_arg(
				array_merge(stripslashes_deep($_GET),array('page'=>$this->Admin->pagename('categories'))),
				admin_url('admin.php')
			)
		);
		
		
		include(SHOPP_ADMIN_PATH."/categories/products.php");
	}


} // END class Categorize

?>