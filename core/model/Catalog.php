<?php
/**
 * Catalog class
 *
 * Catalog navigational experience data manager
 *
 * @author Jonathan Davis
 * @version 1.1
 * @since 1.0
 * @copyright Ingenesis Limited, 24 June, 2010
 * @package shopp
 * @subpackage storefront
 **/

require_once("Product.php");
require_once("Category.php");
require_once("Tag.php");

class Catalog extends DatabaseObject {
	static $table = "catalog";

	var $categories = array();
	var $outofstock = false;

	function __construct ($type="catalog") {
		global $Shopp;
		$this->init(self::$table);
		$this->type = $type;
		$this->outofstock = ($Shopp->Settings->get('outofstock_catalog') == "on");
	}

	/**
	 * Load categories from the catalog index
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $loading (optional) Loading options for building the query
	 * @param boolean $showsmart (optional) Include smart categories in the listing
	 * @param boolean $results (optional) Return the raw structure of results without aggregate processing
	 * @return boolean|object True when categories are loaded and processed, object of results when $results is set
	 **/
	function load_categories ($loading=array(),$showsmart=false,$results=false) {
		$db = DB::get();
		$category_table = DatabaseObject::tablename(Category::$table);
		$product_table = DatabaseObject::tablename(Product::$table);
		$price_table = DatabaseObject::tablename(Price::$table);

		$defaults = array(
			'columns' => "cat.id,cat.parent,cat.name,cat.description,cat.uri,cat.slug,count(DISTINCT pd.id) AS total,IF(SUM(IF(pt.inventory='off',1,0) OR pt.inventory IS NULL)>0,'off','on') AS inventory, SUM(pt.stock) AS stock",
			'where' => array(),
			'joins' => array(
				"LEFT JOIN $this->_table AS sc ON sc.parent=cat.id AND sc.type='category'",
				"LEFT JOIN $product_table AS pd ON sc.product=pd.id",
				"LEFT JOIN $price_table AS pt ON pt.product=pd.id AND pt.type != 'N/A'"
			),
			'limit' => false,
			'orderby' => 'name',
			'order' => 'ASC',
			'parent' => false,
			'ancestry' => false,
			'outofstock' => $this->outofstock
		);
		$options = array_merge($defaults,$loading);
		extract($options);

		if (!is_array($where)) $where = array($where);

		if (!$outofstock) $where[] = "(pt.inventory='off' OR (pt.inventory='on' AND pt.stock > 0))";

		if ($parent !== false) $where[] = "cat.parent=".$parent;
		else $parent = 0;

		if ($ancestry) {
			if (!empty($where))	$where = array("cat.id IN (SELECT parent FROM $category_table WHERE parent != 0) OR (".join(" AND ",$where).")");
			else $where = array("cat.id IN (SELECT parent FROM $category_table WHERE parent != 0)");
		}

		switch(strtolower($orderby)) {
			case "id": $orderby = "cat.id"; break;
			case "slug": $orderby = "cat.slug"; break;
			case "count": $orderby = "total"; break;
			default: $orderby = "cat.name";
		}

		switch(strtoupper($order)) {
			case "DESC": $order = "DESC"; break;
			default: $order = "ASC";
		}

		if ($limit !== false) $limit = "LIMIT $limit";

		$joins = join(' ',$joins);
		if (!empty($where)) $where = "WHERE ".join(' AND ',$where);
		else $where = false;

		$query = "SELECT $columns FROM $category_table AS cat $joins $where GROUP BY cat.id ORDER BY cat.parent DESC,cat.priority,$orderby $order $limit";
		$categories = $db->query($query,AS_ARRAY);

		if (count($categories) > 1) $categories = sort_tree($categories, $parent);
		if ($results) return $categories;

		foreach ($categories as $category) {
			$category->outofstock = false;
			if (isset($category->inventory)) {
				if ($category->inventory == "on" && $category->stock == 0)
					$category->outofstock = true;

				if (!$this->outofstock && $category->outofstock) continue;
			}
			$id = '_'.$category->id;

			$this->categories[$id] = new Category();
			$this->categories[$id]->populate($category);

			if (isset($category->depth))
				$this->categories[$id]->depth = $category->depth;
			else $this->categories[$id]->depth = 0;

			if (isset($category->total))
				$this->categories[$id]->total = $category->total;
			else $this->categories[$id]->total = 0;

			if (isset($category->stock))
				$this->categories[$id]->stock = $category->stock;
			else $this->categories[$id]->stock = 0;


			if (isset($category->outofstock))
				$this->categories[$id]->outofstock = $category->outofstock;

			$this->categories[$id]->_children = false;
			if (isset($category->total)
				&& $category->total > 0 && isset($this->categories[$category->parent])) {
				$ancestor = $category->parent;

				// Recursively flag the ancestors as having children
				while (isset($this->categories[$ancestor])) {
					$this->categories[$ancestor]->_children = true;
					$ancestor = $this->categories[$ancestor]->parent;
				}
			}

		}

		if ($showsmart == "before" || $showsmart == "after")
			$this->smart_categories($showsmart);

		return true;
	}

	/**
	 * Returns a list of known built-in smart categories
	 *
	 * Operates on the list of already loaded categories in the $this->category property
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param string $method Add smart categories 'before' the list of the loaded categores or 'after' (defaults after)
	 * @return void
	 **/
	function smart_categories ($method="after") {
		global $Shopp;
		$internal = array('CatalogProducts','SearchResults','TagProducts','RelatedProducts','RandomProducts');
		foreach ($Shopp->SmartCategories as $SmartCategory) {
			if (in_array($SmartCategory,$internal)) continue;
			$category = new $SmartCategory(array("noload" => true));
			switch($method) {
				case "before": array_unshift($this->categories,$category); break;
				default: array_push($this->categories,$category);
			}
		}
	}

	/**
	 * Load the tags assigned to products across the entire catalog
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.0
	 *
	 * @param array $limits Query limits in the format of [offset,count]
	 * @return boolean True when tags are loaded
	 **/
	function load_tags ($limits=false) {
		$db = DB::get();

		if ($limits) $limit = " LIMIT {$limits[0]},{$limits[1]}";
		else $limit = "";

		$tagtable = DatabaseObject::tablename(Tag::$table);
		$query = "SELECT t.*,count(sc.product) AS products FROM $this->_table AS sc LEFT JOIN $tagtable AS t ON sc.parent=t.id WHERE sc.type='tag' GROUP BY t.id ORDER BY t.name ASC$limit";
		$this->tags = $db->query($query,AS_ARRAY);
		return true;
	}

	/**
	 * Load a any category from the catalog including smart categories
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param string|int $category The identifying element of a category (by id/slug or uri)
	 * @param array $options (optional) Any shopp() tag-compatible options to pass on to smart categories
	 * @return object The loaded Category object
	 **/
	function load_category ($category,$options=array()) {
		global $Shopp;
		$SmartCategories = array_reverse($Shopp->SmartCategories);
		foreach ($SmartCategories as $SmartCategory) {
			$SmartCategory_slug = get_class_property($SmartCategory,'_slug');
			if ($category == $SmartCategory_slug)
				return new $SmartCategory($options);
		}

		$key = "id";
		if (!preg_match("/^\d+$/",$category)) $key = "uri";
		return new Category($category,$key);

	}

	/**
	 * shopp('catalog','...') tags
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 * @see http://docs.shopplugin.net/Catalog_Tags
	 *
	 * @param string $property The property to handle
	 * @param array $options (optional) The tag options to process
	 * @return mixed
	 **/
	function tag ($property,$options=array()) {
		global $Shopp;

		$Storefront =& ShoppStorefront();

		switch ($property) {
			case "url": return shoppurl(false,'catalog'); break;
			case "display":
			case "type": return $this->type; break;
			case "is-landing":
			case "is-catalog": return (is_shopp_page('catalog') && $this->type == "catalog"); break;
			case "is-category": return (is_shopp_page('catalog') && $this->type == "category"); break;
			case "is-product": return (is_shopp_page('catalog') && $this->type == "product"); break;
			case "is-cart": return (is_shopp_page('cart')); break;
			case "is-checkout": return (is_shopp_page('checkout')); break;
			case "is-account": return (is_shopp_page('account')); break;
			case "tagcloud":
				if (!empty($options['levels'])) $levels = $options['levels'];
				else $levels = 7;
				if (empty($this->tags)) $this->load_tags();
				$min = -1; $max = -1;
				foreach ($this->tags as $tag) {
					if ($min == -1 || $tag->products < $min) $min = $tag->products;
					if ($max == -1 || $tag->products > $max) $max = $tag->products;
				}
				if ($max == 0) $max = 1;
				$string = '<ul class="shopp tagcloud">';
				foreach ($this->tags as $tag) {
					$level = floor((1-$tag->products/$max)*$levels)+1;
					$link = SHOPP_PRETTYURLS?shoppurl("tag/$tag->name"):shoppurl(array('shopp_tag'=>$tag->name));
					$string .= '<li class="level-'.$level.'"><a href="'.$link.'" rel="tag">'.$tag->name.'</a></li> ';
				}
				$string .= '</ul>';
				return $string;
				break;
			case "hascategories":
			case "has-categories":
				$showsmart = isset($options['showsmart'])?$options['showsmart']:false;
				if (empty($this->categories)) $this->load_categories(array('where'=>'true'),$showsmart);
				if (count($this->categories) > 0) return true; else return false; break;
			case "categories":
				if (!isset($this->_category_loop)) {
					reset($this->categories);
					$Shopp->Category = current($this->categories);
					$this->_category_loop = true;
				} else {
					$Shopp->Category = next($this->categories);
				}

				if (current($this->categories) !== false) return true;
				else {
					unset($this->_category_loop);
					reset($this->categories);
					return false;
				}
				break;
			case "category-list":
				$defaults = array(
					'title' => '',
					'before' => '',
					'after' => '',
					'class' => '',
					'exclude' => '',
					'orderby' => 'name',
					'order' => 'ASC',
					'depth' => 0,
					'childof' => 0,
					'parent' => false,
					'showall' => false,
					'linkall' => false,
					'linkcount' => false,
					'dropdown' => false,
					'hierarchy' => false,
					'products' => false,
					'wraplist' => true,
					'showsmart' => false
					);

				$options = array_merge($defaults,$options);
				extract($options, EXTR_SKIP);

				$this->load_categories(array("ancestry"=>true,"where"=>array("(pd.status='publish' OR pd.id IS NULL)"),"orderby"=>$orderby,"order"=>$order),$showsmart);

				$string = "";
				$depthlimit = $depth;
				$depth = 0;
				$exclude = explode(",",$exclude);
				$classes = ' class="shopp_categories'.(empty($class)?'':' '.$class).'"';
				$wraplist = value_is_true($wraplist);

				if (value_is_true($dropdown)) {
					if (!isset($default)) $default = __('Select category&hellip;','Shopp');
					$string .= $title;
					$string .= '<form><select name="shopp_cats" id="shopp-categories-menu"'.$classes.'>';
					$string .= '<option value="">'.$default.'</option>';
					foreach ($this->categories as &$category) {
						// If the parent of this category was excluded, add this to the excludes and skip
						if (!empty($category->parent) && in_array($category->parent,$exclude)) {
							$exclude[] = $category->id;
							continue;
						}
						if (!empty($category->id) && in_array($category->id,$exclude)) continue; // Skip excluded categories
						if ($category->total == 0 && !isset($category->smart) && !$category->_children) continue; // Only show categories with products
						if ($depthlimit && $category->depth >= $depthlimit) continue;

						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = '/'.$parent->slug;
						}

						if (value_is_true($hierarchy))
							$padding = str_repeat("&nbsp;",$category->depth*3);

						$category_uri = empty($category->id)?$category->uri:$category->id;
						$link = SHOPP_PRETTYURLS?shoppurl("category/$category->uri"):shoppurl(array('shopp_category'=>$category_uri));

						$total = '';
						if (value_is_true($products) && $category->total > 0) $total = ' ('.$category->total.')';

						$string .= '<option value="'.$link.'">'.$padding.$category->name.$total.'</option>';
						$previous = &$category;
						$depth = $category->depth;

					}
					$string .= '</select></form>';

					$script = "$('#shopp-categories-menu').change(function (){";
					$script .= "document.location.href = $(this).val();";
					$script .= "});";
					add_storefrontjs($script);

				} else {
					$string .= $title;
					if ($wraplist) $string .= '<ul'.$classes.'>';
					foreach ($this->categories as &$category) {
						if (!isset($category->total)) $category->total = 0;
						if (!isset($category->depth)) $category->depth = 0;

						// If the parent of this category was excluded, add this to the excludes and skip
						if (!empty($category->parent) && in_array($category->parent,$exclude)) {
							$exclude[] = $category->id;
							continue;
						}

						if (!empty($category->id) && in_array($category->id,$exclude)) continue; // Skip excluded categories
						if ($depthlimit && $category->depth >= $depthlimit) continue;
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = $parent->slug;
							if (substr($string,-5,5) == "</li>") // Keep everything but the
								$string = substr($string,0,-5);  // last </li> to re-open the entry
							$active = '';

							if (isset($Shopp->Category->uri) && !empty($parent->slug)
									&& preg_match('/(^|\/)'.$parent->path.'(\/|$)/',$Shopp->Category->uri)) {
								$active = ' active';
							}

							$subcategories = '<ul class="children'.$active.'">';
							$string .= $subcategories;
						}

						if (value_is_true($hierarchy) && $category->depth < $depth) {
							for ($i = $depth; $i > $category->depth; $i--) {
								if (substr($string,strlen($subcategories)*-1) == $subcategories) {
									// If the child menu is empty, remove the <ul> to avoid breaking standards
									$string = substr($string,0,strlen($subcategories)*-1).'</li>';
								} else $string .= '</ul></li>';
							}
						}

						$category_uri = empty($category->id)?$category->uri:$category->id;
						$link = SHOPP_PRETTYURLS?shoppurl("category/$category->uri"):shoppurl(array('shopp_category'=>$category_uri));

						$total = '';
						if (value_is_true($products) && $category->total > 0) $total = ' <span>('.$category->total.')</span>';

						$current = '';
						if (isset($Shopp->Category->slug) && $Shopp->Category->slug == $category->slug)
							$current = ' class="current"';

						$listing = '';
						if ($category->total > 0 || isset($category->smart) || $linkall)
							$listing = '<a href="'.$link.'"'.$current.'>'.$category->name.($linkcount?$total:'').'</a>'.(!$linkcount?$total:'');
						else $listing = $category->name;

						if (value_is_true($showall) ||
							$category->total > 0 ||
							isset($category->smart) ||
							$category->_children)
							$string .= '<li'.$current.'>'.$listing.'</li>';

						$previous = &$category;
						$depth = $category->depth;
					}
					if (value_is_true($hierarchy) && $depth > 0)
						for ($i = $depth; $i > 0; $i--) {
							if (substr($string,strlen($subcategories)*-1) == $subcategories) {
								// If the child menu is empty, remove the <ul> to avoid breaking standards
								$string = substr($string,0,strlen($subcategories)*-1).'</li>';
							} else $string .= '</ul></li>';
						}
					if ($wraplist) $string .= '</ul>';
				}
				return $string;
				break;
			case "views":
				if (isset($Shopp->Category->controls)) return false;
				$string = "";
				$string .= '<ul class="views">';
				if (isset($options['label'])) $string .= '<li>'.$options['label'].'</li>';
				$string .= '<li><button type="button" class="grid"></button></li>';
				$string .= '<li><button type="button" class="list"></button></li>';
				$string .= '</ul>';
				return $string;
			case "orderby-list":
				if (isset($Shopp->Category->controls)) return false;
				if (isset($Shopp->Category->loading['order']) || isset($Shopp->Category->loading['orderby'])) return false;

				$menuoptions = Category::sortoptions();
				// Don't show custom product order for smart categories
				if (isset($Shopp->Category->smart)) unset($menuoptions['custom']);

				$title = "";
				$string = "";
				$dropdown = isset($options['dropdown'])?$options['dropdown']:true;
				$default = $Shopp->Settings->get('default_product_order');
				if (empty($default)) $default = "title";

				if (isset($options['default'])) $default = $options['default'];
				if (isset($options['title'])) $title = $options['title'];

				if (value_is_true($dropdown)) {
					if (isset($Shopp->Flow->Controller->browsing['orderby']))
						$default = $Shopp->Flow->Controller->browsing['orderby'];
					$string .= $title;
					$string .= '<form action="'.esc_url($_SERVER['REQUEST_URI']).'" method="get" id="shopp-'.$Shopp->Category->slug.'-orderby-menu">';
					if (!SHOPP_PRETTYURLS) {
						foreach ($_GET as $key => $value)
							if ($key != 'shopp_orderby') $string .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
					}
					$string .= '<select name="shopp_orderby" class="shopp-orderby-menu">';
					$string .= menuoptions($menuoptions,$default,true);
					$string .= '</select>';
					$string .= '</form>';
				} else {
					$link = "";
					$query = "";
					if (strpos($_SERVER['REQUEST_URI'],"?") !== false)
						list($link,$query) = explode("\?",$_SERVER['REQUEST_URI']);
					$query = $_GET;
					unset($query['shopp_orderby']);
 					$query = http_build_query($query);
					if (!empty($query)) $query .= '&';

					foreach($menuoptions as $value => $option) {
						$label = $option;
						$href = esc_url($link.'?'.$query.'shopp_orderby='.$value);
						$string .= '<li><a href="'.$href.'">'.$label.'</a></li>';
					}

				}
				return $string;
				break;
			case "breadcrumb":

				$defaults = array(
					'separator' => '&nbsp;&raquo; ',
					'depth'		=> 7
				);
				$options = array_merge($defaults,$options);
				extract($options);

				if (isset($Shopp->Category->controls)) return false;
				if (empty($this->categories)) $this->load_categories(array('outofstock' => true));

				$category = false;
				if (isset($Shopp->Flow->Controller->breadcrumb))
					$category = $Shopp->Flow->Controller->breadcrumb;

				$trail = false;
				$search = array();
				if (isset($Shopp->Flow->Controller->search)) $search = array('search'=>$Shopp->Flow->Controller->search);
				$path = explode("/",$category);
				if ($path[0] == "tag") {
					$category = "tag";
					$search = array('tag'=>urldecode($path[1]));
				}
				$Category = Catalog::load_category($category,$search);

				if (!empty($Category->uri)) {
					$type = "category";
					if (isset($Category->tag)) $type = "tag";

					$category_uri = isset($Category->smart)?$Category->slug:$Category->id;

					$link = SHOPP_PRETTYURLS?
						shoppurl("$type/$Category->uri") :
						shoppurl(array_merge($_GET,array('shopp_category'=>$category_uri,'shopp_pid'=>null)));

					$filters = false;
					if (!empty($Shopp->Cart->data->Category[$Category->slug]))
						$filters = ' (<a href="?shopp_catfilters=cancel">'.__('Clear Filters','Shopp').'</a>)';

					if (!empty($Shopp->Product))
						$trail .= '<li><a href="'.$link.'">'.$Category->name.(!$trail?'':$separator).'</a></li>';
					elseif (!empty($Category->name))
						$trail .= '<li>'.$Category->name.$filters.(!$trail?'':$separator).'</li>';

					// Build category names path by going from the target category up the parent chain
					$parentkey = (!empty($Category->id)
						&& isset($this->categories['_'.$Category->id]->parent)?
							'_'.$this->categories['_'.$Category->id]->parent:'_0');

					while ($parentkey != '_0' && $depth-- > 0) {
						$tree_category = $this->categories[$parentkey];

						$link = SHOPP_PRETTYURLS?
							shoppurl("category/$tree_category->uri"):
							shoppurl(array_merge($_GET,array('shopp_category'=>$tree_category->id,'shopp_pid'=>null)));

						$trail = '<li><a href="'.$link.'">'.$tree_category->name.'</a>'.
							(empty($trail)?'':$separator).'</li>'.$trail;

						$parentkey = '_'.$tree_category->parent;
					}
				}
				$pages = $Shopp->Settings->get('pages');

				$trail = '<li><a href="'.shoppurl().'">'.$pages['catalog']['title'].'</a>'.(empty($trail)?'':$separator).'</li>'.$trail;
				return '<ul class="breadcrumb">'.$trail.'</ul>';
				break;
			case "searchform":
				ob_start();
				get_search_form();
				$content = ob_get_contents();
				ob_end_clean();

				preg_match('/^(.*?<form[^>]*>)(.*?)(<\/form>.*?)$/is',$content,$_);
				list($all,$open,$content,$close) = $_;

				$markup = array(
					$open,
					$content,
					'<div><input type="hidden" name="catalog" value="true" /></div>',
					$close
				);

				return join('',$markup);
				break;
			case "search":
				global $wp;

				$defaults = array(
					'type' => 'hidden',
					'option' => 'shopp',
					'blog_option' => __('Search the blog','Shopp'),
					'shop_option' => __('Search the shop','Shopp'),
					'label_before' => '',
					'label_after' => '',
					'checked' => false
				);
				$options = array_merge($defaults,$options);
				extract($options);

				$searching = is_search(); // Flag when searching (the blog or shopp)
				$shopsearch = ($Storefront !== false && $Storefront->searching); // Flag when searching shopp

				$allowed = array("accesskey","alt","checked","class","disabled","format", "id",
					"minlength","maxlength","readonly","required","size","src","tabindex","title","value");

				$options['value'] = ($option == "shopp");

				// Reset the checked option
				unset($options['checked']);

				// If searching the blog, check the non-store search option
				if ($searching && !$shopsearch && $option != "shopp") $options['checked'] = "checked";

				// If searching the storefront, mark the store search option
				if ($shopsearch && $option == "shopp") $options['checked'] = "checked";

				// Override any other settings with the supplied default 'checked' option
				if (!$searching && $checked) $options['checked'] = $checked;

				switch ($type) {
					case "checkbox":
						$input =  '<input type="checkbox" name="catalog"'.inputattrs($options,$allowed).' />';
						break;
					case "radio":
						$input =  '<input type="radio" name="catalog"'.inputattrs($options,$allowed).' />';
						break;
					case "menu":
						$allowed = array("accesskey","alt","class","disabled","format", "id",
							"readonly","required","size","tabindex","title");

						$input = '<select name="catalog"'.inputattrs($options,$allowed).'>';
						$input .= '<option value="">'.$blog_option.'</option>';
						$input .= '<option value="1"'.($shopsearch || (!$searching && $option == 'shopp')?' selected="selected"':'').'>'.$shop_option.'</option>';
						$input .= '</select>';
						break;
					default:
						$allowed = array("alt","class","disabled","format","id","readonly","title","value");
						$input =  '<input type="hidden" name="catalog"'.inputattrs($options,$allowed).' />';
						break;
				}

				$before = (!empty($label_before))?'<label>'.$label_before:'<label>';
				$after = (!empty($label_after))?$label_after.'</label>':'</label>';
				return $before.$input.$after;
				break;
			case "zoom-options":
				$defaults = array(				// Colorbox 1.3.15
					'transition' => 'elastic',	// The transition type. Can be set to 'elastic', 'fade', or 'none'.
					'speed' => 350,				// Sets the speed of the fade and elastic transitions, in milliseconds.
					'href' => false,			// This can be used as an alternative anchor URL or to associate a URL for non-anchor elements such as images or form buttons. Example: $('h1').colorbox({href:'welcome.html'})
					'title' => false,			// This can be used as an anchor title alternative for ColorBox.
					'rel' => false,				// This can be used as an anchor rel alternative for ColorBox. This allows the user to group any combination of elements together for a gallery, or to override an existing rel so elements are not grouped together. Example: $('#example a').colorbox({rel:'group1'}) Note: The value can also be set to 'nofollow' to disable grouping.
					'width' => false,			// Set a fixed total width. This includes borders and buttons. Example: '100%', '500px', or 500
					'height' => false,			// Set a fixed total height. This includes borders and buttons. Example: '100%', '500px', or 500
					'innerWidth' => false,		// This is an alternative to 'width' used to set a fixed inner width. This excludes borders and buttons. Example: '50%', '500px', or 500
					'innerHeight' => false,		// This is an alternative to 'height' used to set a fixed inner height. This excludes borders and buttons. Example: '50%', '500px', or 500
					'initialWidth' => 300,		// Set the initial width, prior to any content being loaded.
					'initialHeight' => 100,		// Set the initial height, prior to any content being loaded.
					'maxWidth' => false,		// Set a maximum width for loaded content. Example: '100%', 500, '500px'
					'maxHeight' => false,		// Set a maximum height for loaded content. Example: '100%', 500, '500px'
					'scalePhotos' => true,		// If 'true' and if maxWidth, maxHeight, innerWidth, innerHeight, width, or height have been defined, ColorBox will scale photos to fit within the those values.
					'scrolling' => true,		// If 'false' ColorBox will hide scrollbars for overflowing content. This could be used on conjunction with the resize method (see below) for a smoother transition if you are appending content to an already open instance of ColorBox.
					'iframe' => false,			// If 'true' specifies that content should be displayed in an iFrame.
					'inline' => false,			// If 'true' a jQuery selector can be used to display content from the current page. Example:  $('#inline').colorbox({inline:true, href:'#myForm'});
					'html' => false,			// This allows an HTML string to be used directly instead of pulling content from another source (ajax, inline, or iframe). Example: $.colorbox({html:'<p>Hello</p>'});
					'photo' => false,			// If true, this setting forces ColorBox to display a link as a photo. Use this when automatic photo detection fails (such as using a url like 'photo.php' instead of 'photo.jpg', 'photo.jpg#1', or 'photo.jpg?pic=1')
					'opacity' => 0.85,			// The overlay opacity level. Range: 0 to 1.
					'open' => false,			// If true, the lightbox will automatically open with no input from the visitor.
					'returnFocus' => true,		// If true, focus will be returned when ColorBox exits to the element it was launched from.
					'preloading' => true,		// Allows for preloading of 'Next' and 'Previous' content in a shared relation group (same values for the 'rel' attribute), after the current content has finished loading. Set to 'false' to disable.
					'overlayClose' => true,		// If false, disables closing ColorBox by clicking on the background overlay.
					'escKey' => true, 			// If false, will disable closing colorbox on esc key press.
					'arrowKey' => true, 		// If false, will disable the left and right arrow keys from navigating between the items in a group.
					'loop' => true, 			// If false, will disable the ability to loop back to the beginning of the group when on the last element.
					'slideshow' => false, 		// If true, adds an automatic slideshow to a content group / gallery.
					'slideshowSpeed' => 2500, 	// Sets the speed of the slideshow, in milliseconds.
					'slideshowAuto' => true, 	// If true, the slideshow will automatically start to play.

					'slideshowStart' => __('start slideshow','Shopp'),	// Text for the slideshow start button.
					'slideshowStop' => __('stop slideshow','Shopp'),	// Text for the slideshow stop button
					'previous' => __('previous','Shopp'), 				// Text for the previous button in a shared relation group (same values for 'rel' attribute).
					'next' => __('next','Shopp'), 						// Text for the next button in a shared relation group (same values for 'rel' attribute).
					'close' => __('close','Shopp'),						// Text for the close button. The 'Esc' key will also close ColorBox.

					// Text format for the content group / gallery count. {current} and {total} are detected and replaced with actual numbers while ColorBox runs.
					'current' => sprintf(__('image %s of %s','Shopp'),'{current}','{total}'),

					'onOpen' => false,			// Callback that fires right before ColorBox begins to open.
					'onLoad' => false,			// Callback that fires right before attempting to load the target content.
					'onComplete' => false,		// Callback that fires right after loaded content is displayed.
					'onCleanup' => false,		// Callback that fires at the start of the close process.
					'onClosed' => false			// Callback that fires once ColorBox is closed.
				);
				$options = array_diff($options, $defaults);

				$js = 'var cbo = '.json_encode($options).';';
				add_storefrontjs($js,true);
				break;
			case "catalog-products":
				if ($property == "catalog-products") $Shopp->Category = new CatalogProducts($options);
			case "new-products":
				if ($property == "new-products") $Shopp->Category = new NewProducts($options);
			case "featured-products":
				if ($property == "featured-products") $Shopp->Category = new FeaturedProducts($options);
			case "onsale-products":
				if ($property == "onsale-products") $Shopp->Category = new OnSaleProducts($options);
			case "bestsellers-products":
				if ($property == "bestsellers-products") $Shopp->Category = new BestsellerProducts($options);
			case "bestseller-products":
				if ($property == "bestseller-products") $Shopp->Category = new BestsellerProducts($options);
			case "bestselling-products":
				if ($property == "bestselling-products") $Shopp->Category = new BestsellerProducts($options);
			case "random-products":
				if ($property == "random-products") $Shopp->Category = new RandomProducts($options);
			case "tag-products":
				if ($property == "tag-products") $Shopp->Category = new TagProducts($options);
			case "related-products":
				if ($property == "related-products") $Shopp->Category = new RelatedProducts($options);
			case "search-products":
				if ($property == "search-products") $Shopp->Category = new SearchResults($options);
			case "category":
				if ($property == "category") {
					if (isset($options['name'])) $Shopp->Category = new Category($options['name'],'name');
					else if (isset($options['slug'])) $Shopp->Category = new Category($options['slug'],'slug');
					else if (isset($options['id'])) $Shopp->Category = new Category($options['id']);
				}
				if (isset($options['reset']))
					return (get_class($Shopp->Requested) == "Category"?($Shopp->Category = $Shopp->Requested):false);
				if (isset($options['title'])) $Shopp->Category->name = $options['title'];
				if (isset($options['show'])) $Shopp->Category->loading['limit'] = $options['show'];
				if (isset($options['pagination'])) $Shopp->Category->loading['pagination'] = $options['pagination'];
				if (isset($options['order'])) $Shopp->Category->loading['order'] = $options['order'];

				if (isset($options['load'])) return true;
				if (isset($options['controls']) && !value_is_true($options['controls']))
					$Shopp->Category->controls = false;
				if (isset($options['view'])) {
					if ($options['view'] == "grid") $Shopp->Category->view = "grid";
					else $Shopp->Category->view = "list";
				}
				ob_start();
				if (isset($Shopp->Category->slug) &&
						file_exists(SHOPP_TEMPLATES."/category-{$Shopp->Category->slug}.php"))
					include(SHOPP_TEMPLATES."/category-{$Shopp->Category->slug}.php");
				elseif (isset($Shopp->Category->id) &&
					file_exists(SHOPP_TEMPLATES."/category-{$Shopp->Category->id}.php"))
					include(SHOPP_TEMPLATES."/category-{$Shopp->Category->id}.php");
				else include(SHOPP_TEMPLATES."/category.php");
				$content = ob_get_contents();
				ob_end_clean();
				$Shopp->Category = false; // Reset the current category

				if (isset($options['wrap']) && value_is_true($options['wrap'])) $content = shoppdiv($content);
				return $content;
				break;
			case "product":
				if (isset($options['name'])) $Shopp->Product = new Product($options['name'],'name');
				else if (isset($options['slug'])) $Shopp->Product = new Product($options['slug'],'slug');
				else if (isset($options['id'])) $Shopp->Product = new Product($options['id']);

				if (isset($options['reset']))
					return (get_class($Shopp->Requested) == "Product"?($Shopp->Product = $Shopp->Requested):false);

				if (isset($Shopp->Product->id) && isset($Shopp->Category->slug)) {
					$Category = clone($Shopp->Category);

					if (isset($options['load'])) {
						if ($options['load'] == "next") $Shopp->Product = $Category->adjacent_product(1);
						elseif ($options['load'] == "previous") $Shopp->Product = $Category->adjacent_product(-1);
					} else {
						if (isset($options['next']) && value_is_true($options['next']))
							$Shopp->Product = $Category->adjacent_product(1);
						elseif (isset($options['previous']) && value_is_true($options['previous']))
							$Shopp->Product = $Category->adjacent_product(-1);
					}
				}

				if (isset($options['load'])) return true;
				ob_start();
				if (file_exists(SHOPP_TEMPLATES."/product-{$Shopp->Product->id}.php"))
					include(SHOPP_TEMPLATES."/product-{$Shopp->Product->id}.php");
				else include(SHOPP_TEMPLATES."/product.php");
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
				break;
			case "sideproduct":
				$content = false;
				$source = isset($options['source'])?$options['source']:'product';
				if ($source == "product" && isset($options['product'])) {
					 // Save original requested product
					if ($Shopp->Product) $Requested = $Shopp->Product;
					$products = explode(",",$options['product']);
					if (!is_array($products)) $products = array($products);
					foreach ($products as $product) {
						$product = trim($product);
						if (empty($product)) continue;
						if (preg_match('/^\d+$/',$product))
							$Shopp->Product = new Product($product);
						else $Shopp->Product = new Product($product,'slug');

						if (empty($Shopp->Product->id)) continue;
						if (isset($options['load'])) return true;
						ob_start();
						if (file_exists(SHOPP_TEMPLATES."/sideproduct-{$Shopp->Product->id}.php"))
							include(SHOPP_TEMPLATES."/sideproduct-{$Shopp->Product->id}.php");
						else include(SHOPP_TEMPLATES."/sideproduct.php");
						$content .= ob_get_contents();
						ob_end_clean();
					}
					 // Restore original requested Product
					if (!empty($Requested)) $Shopp->Product = $Requested;
					else $Shopp->Product = false;
				}

				if ($source == "category" && isset($options['category'])) {
					 // Save original requested category
					if ($Shopp->Category) $Requested = $Shopp->Category;
					if ($Shopp->Product) $RequestedProduct = $Shopp->Product;
					if (empty($options['category'])) return false;
					$Shopp->Category = Catalog::load_category($options['category']);
					$Shopp->Category->load_products($options);
					if (isset($options['load'])) return true;
					foreach ($Shopp->Category->products as $product) {
						$Shopp->Product = $product;
						ob_start();
						if (file_exists(SHOPP_TEMPLATES."/sideproduct-{$Shopp->Product->id}.php"))
							include(SHOPP_TEMPLATES."/sideproduct-{$Shopp->Product->id}.php");
						else include(SHOPP_TEMPLATES."/sideproduct.php");
						$content .= ob_get_contents();
						ob_end_clean();
					}
					 // Restore original requested category
					if (!empty($Requested)) $Shopp->Category = $Requested;
					else $Shopp->Category = false;
					if (!empty($RequestedProduct)) $Shopp->Product = $RequestedProduct;
					else $Shopp->Product = false;
				}

				return $content;
				break;
		}
	}

} // END class Catalog

?>