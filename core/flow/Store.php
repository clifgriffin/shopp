<?php
/**
 * Store
 * 
 * Flow controller for product management interfaces
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January 6, 2010
 * @package shopp
 * @subpackage products
 **/

/**
 * Store
 *
 * @since 1.1
 * @package products
 * @author Jonathan Davis
 **/
class Store extends AdminController {
	
	/**
	 * Store constructor
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function __construct () {
		parent::__construct();
		add_action('admin_print_scripts',array(&$this,'columns'));
		add_action('admin_head',array(&$this,'layout'));
		add_action('load-shopp_page_shopp-products',array(&$this,'workflow'));
		add_action('load-admin_page_shopp-products-edit',array(&$this,'workflow'));
	}
	
	/**
	 * Parses admin requests to determine which interface to display
	 *
	 * @return void
	 * @author Jonathan Davis
	 **/
	function admin () {
		if ($_GET['page'] == $this->Admin->pagename('products-edit')) $this->editor();
		else $this->products();
	}

	/**
	 * Handles loading, saving and deleting products in the context of workflows
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function workflow () {
		global $Shopp;
		$db =& DB::get();
		error_log('workflow');
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
			|| ($page != $this->Admin->pagename('products')
			&& $page != $this->Admin->pagename('products-edit')))
				return false;
		
		$adminurl = admin_url('admin.php');

		if ($page == $this->Admin->pagename('products')
				&& !empty($deleting) 
				&& !empty($delete) 
				&& is_array($delete)) {
			foreach($delete as $deletion) {
				$Product = new Product($deletion);
				$Product->delete();
			}
			$redirect = esc_url(add_query_arg(array_merge($_GET,array('delete'=>null,'deleting'=>null)),$adminurl));
			shopp_redirect($redirect);
		}
		
		if ($duplicate) {
			$Product = new Product();
			$Product->load($duplicate);
			$Product->duplicate();
			shopp_redirect(add_query_arg('page',$this->Admin->pagename('products'),$adminurl));
		}

		if (isset($id) && $id != "new") {
			$Shopp->Product = new Product($id);
			$Shopp->Product->load_data(array('prices','specs','categories','tags'));
		} else {
			$Shopp->Product = new Product();
			$Shopp->Product->published = "on";  
		}
		
		if ($save) {
			$this->save_product($Shopp->Product);
			$this->Notice = '<strong>'.stripslashes($Shopp->Product->name).'</strong> '.__('has been saved.','Shopp');
			
			if ($next) {
				if ($next == "new") {
					$Shopp->Product = new Product();
					$Shopp->Product->published = "on";  
				} else {
					$Shopp->Product = new Product($next);
					$Shopp->Product->load_data(array('prices','specs','categories','tags'));
				}
			} else {
				if (empty($id)) $id = $Shopp->Product->id;
				$Shopp->Product = new Product($id);
				$Shopp->Product->load_data(array('prices','specs','categories','tags'));			
			}
		}
				
	}

	/**
	 * Interface processor for the product list manager
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function products ($workflow=false) {
		global $Products;
		$db = DB::get();
		$Settings = &ShoppSettings();

		if ( !(is_shopp_userlevel() || current_user_can('shopp_products')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$defaults = array(
			'cat' => false,
			'pagenum' => 1,
			'per_page' => 20,
			's' => '',
			'sl' => '',
			'matchcol' => ''
			);
		
		$args = array_merge($defaults,$_GET);
		extract($args,EXTR_SKIP);

		if (!$workflow) {		
			if (empty($categories)) $categories = array('');
		
			$category_table = DatabaseObject::tablename(Category::$table);
			$query = "SELECT id,name,parent FROM $category_table ORDER BY parent,name";
			$categories = $db->query($query,AS_ARRAY);
			$categories = sort_tree($categories);
			if (empty($categories)) $categories = array();
		
			$categories_menu = '<option value="">'.__('View all categories','Shopp').'</option>';
			$categories_menu .= '<option value="-"'.($cat=='-'?' selected="selected"':'').'>'.__('Uncategorized','Shopp').'</option>';
			foreach ($categories as $category) {
				$padding = str_repeat("&nbsp;",$category->depth*3);
				if ($cat == $category->id) $categories_menu .= '<option value="'.$category->id.'" selected="selected">'.$padding.$category->name.'</option>';
				else $categories_menu .= '<option value="'.$category->id.'">'.$padding.$category->name.'</option>';
			}
			$inventory_filters = array(
				'all' => __('View all products','Shopp'),
				'is' => __('In stock','Shopp'),
				'ls' => __('Low stock','Shopp'),
				'oos' => __('Out-of-stock','Shopp'),
				'ns' => __('Not stocked','Shopp')
			);
			$inventory_menu = menuoptions($inventory_filters,$sl,true);
		}
		
		$pagenum = absint( $pagenum );
		if ( empty($pagenum) )
			$pagenum = 1;
		if( !$per_page || $per_page < 0 )
			$per_page = 20;
		$start = ($per_page * ($pagenum-1)); 
		
		$pd = DatabaseObject::tablename(Product::$table);
		$pt = DatabaseObject::tablename(Price::$table);
		$catt = DatabaseObject::tablename(Category::$table);
		$clog = DatabaseObject::tablename(Catalog::$table);

		$orderby = "pd.created DESC";
		
		$where = "true";
		$having = "";
		if (!empty($s)) {
			if (strpos($s,"sku:") !== false) { // SKU search
				$where .= ' AND pt.sku="'.substr($s,4).'"';
				$orderby = "pd.name";
			} else {                                   // keyword search
				$interference = array("'s","'",".","\"");
				$search = preg_replace('/(\s?)(\w+)(\s?)/','\1*\2*\3',str_replace($interference,"", stripslashes($s)));
				$match = "MATCH(pd.name,pd.summary,pd.description) AGAINST ('$search' IN BOOLEAN MODE)";
				$where .= " AND $match";
				$matchcol = ", $match AS score";
				$orderby = "score DESC";         
			}
		}
		// if (!empty($cat)) $where .= " AND cat.id='$cat' AND (clog.category != 0 OR clog.id IS NULL)";
		if (!empty($cat)) {
			if ($cat == "-") {
				$having = "HAVING COUNT(cat.id) = 0";
			} else {
				$matchcol .= ", GROUP_CONCAT(DISTINCT cat.id ORDER BY cat.id SEPARATOR ',') AS catids";
				$where .= " AND (clog.category != 0 OR clog.id IS NULL)";	
				$having = "HAVING FIND_IN_SET('$cat',catids) > 0";
			}
		}
		if (!empty($sl)) {
			switch($sl) {
				case "ns": $where .= " AND pt.inventory='off'"; break;
				case "oos": 
					$where .= " AND (pt.inventory='on')"; 
					$having .= (empty($having)?"HAVING ":" AND ")."SUM(pt.stock) = 0";
					break;
				case "ls":
					$ls = $Settings->get('lowstock_level');
					if (empty($ls)) $ls = '0';
					$where .= " AND (pt.inventory='on' AND pt.stock <= $ls AND pt.stock > 0)"; 
					break;
				case "is": $where .= " AND (pt.inventory='on' AND pt.stock > 0)";
			}
		}
		
		$base = $Settings->get('base_operations');
		if ($base['vat']) $taxrate = shopp_taxrate();
		if (empty($taxrate)) $taxrate = 0;
		
		$columns = "SQL_CALC_FOUND_ROWS pd.id,pd.name,pd.slug,pd.featured,pd.variations,GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name SEPARATOR ', ') AS categories,if(pt.options=0,IF(pt.tax='off',pt.price,pt.price+(pt.price*$taxrate)),-1) AS mainprice,IF(MAX(pt.tax)='off',MAX(pt.price),MAX(pt.price+(pt.price*$taxrate))) AS maxprice,IF(MAX(pt.tax)='off',MIN(pt.price),MIN(pt.price+(pt.price*$taxrate))) AS minprice,IF(pt.inventory='on','on','off') AS inventory,ROUND(SUM(pt.stock)/count(DISTINCT clog.id),0) AS stock";
		if ($workflow) $columns = "pd.id";
		// Load the products
		$query = "SELECT $columns $matchcol FROM $pd AS pd LEFT JOIN $pt AS pt ON pd.id=pt.product AND pt.type != 'N/A' LEFT JOIN $clog AS clog ON pd.id=clog.product LEFT JOIN $catt AS cat ON cat.id=clog.category WHERE $where GROUP BY pd.id $having ORDER BY $orderby LIMIT $start,$per_page";
		$Products = $db->query($query,AS_ARRAY);
		$productcount = $db->query("SELECT FOUND_ROWS() as total");

		$num_pages = ceil($productcount->total / $per_page);
		$page_links = paginate_links( array(
			'base' => add_query_arg(array("edit"=>null,'pagenum' => '%#%')),
			'format' => '',
			'total' => $num_pages,
			'current' => $pagenum,
		));

		if ($workflow) return $Products;
		
		include(SHOPP_ADMIN_PATH."/products/products.php");
	}

	/**
	 * Registers the column headers for the product list manager
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function columns () {
		register_column_headers('shopp_page_shopp-products', array(
			'cb'=>'<input type="checkbox" />',
			'name'=>__('Name','Shopp'),
			'category'=>__('Category','Shopp'),
			'price'=>__('Price','Shopp'),
			'inventory'=>__('Inventory','Shopp'),
			'featured'=>__('Featured','Shopp'))
		);
	}	
	
	/**
	 * Provides overall layout for the product editor interface
	 *
	 * Makes use of WordPress postboxes to generate panels (box) content
	 * containers that are customizable with drag & drop, collapsable, and
	 * can be toggled to be hidden or visible in the interface.
	 *
	 * @author Jonathan Davis
	 * @return
	 **/
	function layout () {
		global $Shopp;
		include(SHOPP_ADMIN_PATH."/products/ui.php");
	}

	/**
	 * Interface processor for the product editor
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function editor () {
		global $Shopp;
		$db = DB::get();
		
		if ( !(is_shopp_userlevel() || current_user_can('shopp_products')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		if (empty($Shopp->Product)) {
			$Product = new Product();
			$Product->published = "on";
		} else $Product = $Shopp->Product;
		
		// $Product->load_data(array('images'));
		// echo "<pre>"; print_r($Product->imagesets); echo "</pre>";
		
		$Product->slug = apply_filters('editable_slug',$Product->slug);
		$permalink = $Shopp->shopuri;

		require_once("$Shopp->path/core/model/Asset.php");
		require_once("$Shopp->path/core/model/Category.php");

		$Price = new Price();
		$priceTypes = array(
			array('value'=>'Shipped','label'=>__('Shipped','Shopp')),
			array('value'=>'Virtual','label'=>__('Virtual','Shopp')),
			array('value'=>'Download','label'=>__('Download','Shopp')),
			array('value'=>'Donation','label'=>__('Donation','Shopp')),
			array('value'=>'N/A','label'=>__('Disabled','Shopp')),
		);
		
		$workflows = array(
			"continue" => __('Continue Editing','Shopp'),
			"close" => __('Products Manager','Shopp'),
			"new" => __('New Product','Shopp'),
			"next" => __('Edit Next','Shopp'),
			"previous" => __('Edit Previous','Shopp')
			);
		
		$taglist = array();
		foreach ($Product->tags as $tag) $taglist[] = $tag->name;

		if ($Product->id) {
			$Assets = new Asset();
			$Images = $db->query("SELECT id,src,properties FROM $Assets->_table WHERE context='product' AND parent=$Product->id AND datatype='thumbnail' ORDER BY sortorder",AS_ARRAY);
			unset($Assets);			
		}

		$shiprates = $this->Settings->get('shipping_rates');
		if (!empty($shiprates)) ksort($shiprates);

		$uploader = $Shopp->Settings->get('uploader_pref');
		if (!$uploader) $uploader = 'flash';

		$process = (!empty($Product->id)?$Product->id:'new');
		$_POST['action'] = add_query_arg(array_merge($_GET,array('page'=>$this->Admin->pagename('products'))),admin_url('admin.php'));
		
		include(SHOPP_ADMIN_PATH."/products/editor.php");

	}

	/**
	 * Handles saving updates from the product editor
	 *
	 * Saves all product related information which includes core product data
	 * and supporting elements such as images, digital downloads, tags, 
	 * assigned categories, specs and pricing variations.
	 *
	 * @author Jonathan Davis
	 * @return void
	 **/
	function save_product ($Product) {
		$db = DB::get();
		$Settings = &ShoppSettings();
		check_admin_referer('shopp-save-product');

		if ( !(is_shopp_userlevel() || current_user_can('shopp_products')) )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		$Settings->saveform(); // Save workflow setting

		$base = $Settings->get('base_operations');
		$taxrate = 0;
		if ($base['vat']) $taxrate = shopp_taxrate();

		if (!$_POST['options']) $Product->options = array();
		else $_POST['options'] = stripslashes_deep($_POST['options']);

		if (empty($Product->slug)) $Product->slug = sanitize_title_with_dashes($_POST['name']);	

		// Check for an existing product slug
		$exclude_product = !empty($Product->id)?"AND id != $Product->id":"";
		$existing = $db->query("SELECT slug FROM $Product->_table WHERE slug='$Product->slug' $exclude_product LIMIT 1");
		if ($existing) {
			$suffix = 2;
			while($existing) {
				$altslug = substr($Product->slug, 0, 200-(strlen($suffix)+1)). "-$suffix";
				$existing = $db->query("SELECT slug FROM $Product->_table WHERE slug='$altslug' $exclude_product LIMIT 1");
				$suffix++;
			}
			$Product->slug = $altslug;
		}
		
		if (isset($_POST['content'])) $_POST['description'] = $_POST['content'];
		$Product->updates($_POST,array('categories'));
		$Product->save();

		$Product->save_categories($_POST['categories']);
		$Product->save_tags(explode(",",$_POST['taglist']));
		
		if (!empty($_POST['price']) && is_array($_POST['price'])) {

			// Delete prices that were marked for removal
			if (!empty($_POST['deletePrices'])) {
				$deletes = array();
				if (strpos($_POST['deletePrices'],","))	$deletes = explode(',',$_POST['deletePrices']);
				else $deletes = array($_POST['deletePrices']);
			
				foreach($deletes as $option) {
					$Price = new Price($option);
					$Price->delete();
				}
			}

			// Save prices that there are updates for
			foreach($_POST['price'] as $i => $option) {
				if (empty($option['id'])) {
					$Price = new Price();
					$option['product'] = $Product->id;
				} else $Price = new Price($option['id']);
				$option['sortorder'] = array_search($i,$_POST['sortorder'])+1;
				
				// Remove VAT amount to save in DB
				if ($base['vat'] && $option['tax'] == "on") {
					$option['price'] = floatvalue(floatvalue($option['price'])/(1+$taxrate));
					$option['saleprice'] = floatvalue(floatvalue($option['saleprice'])/(1+$taxrate));
				}
				
				$Price->updates($option);
				$Price->save();
				if (!empty($option['download'])) $Price->attach_download($option['download']);
				if (!empty($option['downloadpath'])) {
					chdir(WP_CONTENT_DIR); // relative path context for realpath
					$basepath = trailingslashit(sanitize_path(realpath($Settings->get('products_path'))));
					$download = $basepath.ltrim(sanitize_path($option['downloadpath']),"/");
					if (file_exists($download)) {
						$File = new Asset();
						$File->parent = 0;
						$File->context = "price";
						$File->datatype = "download";
						$File->name = basename($download);
						$File->value = substr(dirname($download),strlen($basepath));
						$File->size = filesize($download);
						$File->properties = array("mimetype" => file_mimetype($download,$File->name));
						$File->save();
						$Price->attach_download($File->id);
					}
				}
			}
			unset($Price);
		}
		
		// No variation options at all, delete all variation-pricelines
		if (empty($Product->options) && !empty($Product->prices) && is_array($Product->prices)) { 
			foreach ($Product->prices as $priceline) {
				// Skip if not tied to variation options
				if ($priceline->optionkey == 0) continue; 
				$Price = new Price($priceline->id);
				$Price->delete();
			}
		}
			
		if (!empty($_POST['details']) || !empty($_POST['deletedSpecs'])) {
			$deletes = array();
			if (!empty($_POST['deletedSpecs'])) {
				if (strpos($_POST['deletedSpecs'],","))	$deletes = explode(',',$_POST['deletedSpecs']);
				else $deletes = array($_POST['deletedSpecs']);
				foreach($deletes as $option) {
					$Spec = new Spec($option);
					$Spec->delete();
				}
				unset($Spec);
			}

			if (is_array($_POST['details'])) {
				foreach ($_POST['details'] as $i => $spec) {
					if (in_array($spec['id'],$deletes)) continue;
					if (isset($spec['new'])) {
						$Spec = new Spec();
						$spec['id'] = '';
						$spec['product'] = $Product->id;
					} else $Spec = new Spec($spec['id']);
					$spec['sortorder'] = array_search($i,$_POST['details-sortorder'])+1;
					
					$Spec->updates($spec);
					if (preg_match('/^.*?(\d+[\.\,\d]*).*$/',$spec['content']))
						$Spec->numeral = preg_replace('/^.*?(\d+[\.\,\d]*).*$/','$1',$spec['content']);
					
					$Spec->save();
				}
			}
		}
		
		if (!empty($_POST['deleteImages'])) {			
			$deletes = array();
			if (strpos($_POST['deleteImages'],","))	$deletes = explode(',',$_POST['deleteImages']);
			else $deletes = array($_POST['deleteImages']);
			$Product->delete_images($deletes);
		}
		
		if (!empty($_POST['images']) && is_array($_POST['images'])) {
			$Product->link_images($_POST['images']);
			$Product->save_imageorder($_POST['images']);
			if (!empty($_POST['imagedetails']))
				$Product->update_images($_POST['imagedetails']);
		}
		
		do_action_ref_array('shopp_product_saved',array(&$Product));
		
		unset($Product);
		return true;
	}
	
	/**
	 * AJAX behavior to process uploaded files intended as digital downloads
	 *
	 * Handles processing a file upload from a temporary file to a 
	 * the correct storage container (DB, file system, etc)
	 *
	 * @author Jonathan Davis
	 * @return string JSON encoded result with DB id, filename, type & size
	 **/
	function downloads () {
		$error = false;
		if (isset($_FILES['Filedata']['error'])) $error = $_FILES['Filedata']['error'];
		if ($error) die(json_encode(array("error" => $this->uploadErrors[$error])));
			
		if (!file_exists($_FILES['Filedata']['tmp_name']))
			die(json_encode(array("error" => __('The file could not be saved because the upload was not found on the server.','Shopp'))));
			
		if (!is_readable($_FILES['Filedata']['tmp_name']))
			die(json_encode(array("error" => __('The file could not be saved because the web server does not have permission to read the upload.','Shopp'))));

		if ($_FILES['Filedata']['size'] == 0) 
			die(json_encode(array("error" => __('The file could not be saved because the uploaded file is empty.','Shopp'))));
		
		// Save the uploaded file
		$File = new Asset();
		$File->parent = 0;
		$File->context = "price";
		$File->datatype = "download";
		$File->name = $_FILES['Filedata']['name'];
		$File->size = filesize($_FILES['Filedata']['tmp_name']);
		$File->properties = array("mimetype" => file_mimetype($_FILES['Filedata']['tmp_name'],$File->name));
		$File->data = addslashes(file_get_contents($_FILES['Filedata']['tmp_name']));
		$File->save();
		unset($File->data); // Remove file contents from memory
		
		do_action('add_product_download',$File,$_FILES['Filedata']);
		
		echo json_encode(array("id"=>$File->id,"name"=>stripslashes($File->name),"type"=>$File->properties['mimetype'],"size"=>$File->size));
	}
	
	/**
	 * AJAX behavior to process uploaded images
	 *
	 * TODO: Find a better place for this code so products & categories can both use it
	 *
	 * @author Jonathan Davis
	 * @return string JSON encoded result with thumbnail id and src
	 **/
	function images () {
		$QualityValue = array(100,92,80,70,60);
		$context = false;
		
		$error = false;
		if (isset($_FILES['Filedata']['error'])) $error = $_FILES['Filedata']['error'];
		if ($error) die(json_encode(array("error" => $this->uploadErrors[$error])));

		require(SHOPP_PATH."/core/model/Image.php");
		
		if (isset($_POST['type']) == "product") {
			$parent = $_POST['product'];
			$context = "product";
		}

		if (isset($_POST['type']) == "category") {
			$parent = $_POST['category'];
			$context = "category";
		}
		
		if (!$context)
			die(json_encode(array("error" => __('The file could not be saved because the server cannot tell whether to attach the asset to a product or a category.','Shopp'))));
			
		if (!file_exists($_FILES['Filedata']['tmp_name']))
			die(json_encode(array("error" => __('The file could not be saved because the upload was not found on the server.','Shopp'))));
			
		if (!is_readable($_FILES['Filedata']['tmp_name']))
			die(json_encode(array("error" => __('The file could not be saved because the web server does not have permission to read the upload.','Shopp'))));

		if ($_FILES['Filedata']['size'] == 0) 
			die(json_encode(array("error" => __('The file could not be saved because the uploaded file is empty.','Shopp'))));

		// Save the source image
		$Image = new Asset();
		$Image->parent = $parent;
		$Image->context = $context;
		$Image->datatype = "image";
		$Image->name = $_FILES['Filedata']['name'];
		list($width, $height, $mimetype, $attr) = getimagesize($_FILES['Filedata']['tmp_name']);
		$Image->properties = array(
			"width" => $width,
			"height" => $height,
			"mimetype" => image_type_to_mime_type($mimetype),
			"attr" => $attr);
		$Image->data = addslashes(file_get_contents($_FILES['Filedata']['tmp_name']));
		$Image->save();
		unset($Image->data); // Save memory for small image & thumbnail processing

		// Generate Small Size
		$SmallSettings = array();
		$SmallSettings['width'] = $this->Settings->get('gallery_small_width');
		$SmallSettings['height'] = $this->Settings->get('gallery_small_height');
		$SmallSettings['sizing'] = $this->Settings->get('gallery_small_sizing');
		$SmallSettings['quality'] = $this->Settings->get('gallery_small_quality');
		
		$Small = new Asset();
		$Small->parent = $Image->parent;
		$Small->context = $context;
		$Small->datatype = "small";
		$Small->src = $Image->id;
		$Small->name = "small_".$Image->name;
		$Small->data = file_get_contents($_FILES['Filedata']['tmp_name']);
		$SmallSizing = new ImageProcessor($Small->data,$width,$height);
		
		switch ($SmallSettings['sizing']) {
			// case "0": $SmallSizing->scaleToWidth($SmallSettings['width']); break;
			// case "1": $SmallSizing->scaleToHeight($SmallSettings['height']); break;
			case "0": $SmallSizing->scaleToFit($SmallSettings['width'],$SmallSettings['height']); break;
			case "1": $SmallSizing->scaleCrop($SmallSettings['width'],$SmallSettings['height']); break;
		}
		$SmallSizing->UnsharpMask(75);
		$Small->data = addslashes($SmallSizing->imagefile($QualityValue[$SmallSettings['quality']]));
		$Small->properties = array();
		$Small->properties['width'] = $SmallSizing->Processed->width;
		$Small->properties['height'] = $SmallSizing->Processed->height;
		$Small->properties['mimetype'] = "image/jpeg";
		unset($SmallSizing);
		$Small->save();
		unset($Small);
		
		// Generate Thumbnail
		$ThumbnailSettings = array();
		$ThumbnailSettings['width'] = $this->Settings->get('gallery_thumbnail_width');
		$ThumbnailSettings['height'] = $this->Settings->get('gallery_thumbnail_height');
		$ThumbnailSettings['sizing'] = $this->Settings->get('gallery_thumbnail_sizing');
		$ThumbnailSettings['quality'] = $this->Settings->get('gallery_thumbnail_quality');

		$Thumbnail = new Asset();
		$Thumbnail->parent = $Image->parent;
		$Thumbnail->context = $context;
		$Thumbnail->datatype = "thumbnail";
		$Thumbnail->src = $Image->id;
		$Thumbnail->name = "thumbnail_".$Image->name;
		$Thumbnail->data = file_get_contents($_FILES['Filedata']['tmp_name']);
		$ThumbnailSizing = new ImageProcessor($Thumbnail->data,$width,$height);
		
		switch ($ThumbnailSettings['sizing']) {
			// case "0": $ThumbnailSizing->scaleToWidth($ThumbnailSettings['width']); break;
			// case "1": $ThumbnailSizing->scaleToHeight($ThumbnailSettings['height']); break;
			case "0": $ThumbnailSizing->scaleToFit($ThumbnailSettings['width'],$ThumbnailSettings['height']); break;
			case "1": $ThumbnailSizing->scaleCrop($ThumbnailSettings['width'],$ThumbnailSettings['height']); break;
		}
		$ThumbnailSizing->UnsharpMask();
		$Thumbnail->data = addslashes($ThumbnailSizing->imagefile($QualityValue[$ThumbnailSettings['quality']]));
		$Thumbnail->properties = array();
		$Thumbnail->properties['width'] = $ThumbnailSizing->Processed->width;
		$Thumbnail->properties['height'] = $ThumbnailSizing->Processed->height;
		$Thumbnail->properties['mimetype'] = "image/jpeg";
		unset($ThumbnailSizing);
		$Thumbnail->save();
		unset($Thumbnail->data);
					
		echo json_encode(array("id"=>$Thumbnail->id,"src"=>$Thumbnail->src));
	}

	/**
	 * Loads all categories for the product list manager category filter menu
	 *
	 * @author Jonathan Davis
	 * @return string HTML for a drop-down menu of categories
	 **/
	function category ($id) {
		$db = DB::get();
		$catalog = DatabaseObject::tablename(Catalog::$table);
		$category = DatabaseObject::tablename(Category::$table);
		$products = DatabaseObject::tablename(Product::$table);
		$results = $db->query("SELECT p.id,p.name FROM $catalog AS catalog LEFT JOIN $category AS cat ON cat.id = catalog.category LEFT JOIN $products AS p ON p.id=catalog.product WHERE cat.id='$id' ORDER BY p.name ASC",AS_ARRAY);
		$products = array();
		
		$products[0] = __("Select a product&hellip;","Shopp");
		foreach ($results as $result) $products[$result->id] = $result->name;
		return menuoptions($products,0,true);
		
	}

} // end Store class

?>