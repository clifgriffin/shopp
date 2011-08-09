<?php
/**
* ShoppCatalogThemeAPI - Provided theme api tags.
*
* @version 1.0
* @since 1.2
* @package shopp
* @subpackage ShoppCatalogThemeAPI
*
**/

class ShoppCatalogThemeAPI implements ShoppAPI {
	static $context = 'Catalog';
	static $register = array(
		'breadcrumb' => 'breadcrumb',
		'categories' => 'categories',
		'category' => 'category',
		'categorylist' => 'category_list',
		'display' => 'type',
		'type' => 'type',
		'hascategories' => 'has_categories',
		'isaccount' => 'is_account',
		'iscart' => 'is_cart',
		'iscategory' => 'is_category',
		'ischeckout' => 'is_checkout',
		'islanding' => 'is_catalog',
		'iscatalog' => 'is_catalog',
		'isproduct' => 'is_product',
		'orderbylist' => 'orderby_list',
		'product' => 'product',
		'search' => 'search',
		'searchform' => 'search_form',
		'sideproduct' => 'side_product',
		'tagproducts' => 'tag_products',
		'tagcloud' => 'tag_cloud',
		'url' => 'url',
		'views' => 'views',
		'zoomoptions' => 'zoom_options'
	);

	static function _apicontext () { return 'catalog'; }

	function breadcrumb ($result, $options, $O) {
		global $Shopp;

		$defaults = array(
			'separator' => '&nbsp;&raquo; ',
			'depth'		=> 7
		);
		$options = array_merge($defaults,$options);
		extract($options);
		return false; // @todo Fix CatalogAPI breadcrumb
		if (isset($Shopp->Category->controls)) return false;
		if (empty($O->categories)) $O->load_categories(array('outofstock' => true));

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
		$Category = Catalog::load_collection($category,$search);

		if (!empty($Category->uri)) {
			$type = "category";
			if (isset($Category->tag)) $type = "tag";

			$category_uri = isset($Category->smart)?$Category->slug:$Category->id;

			$link = SHOPP_PRETTYURLS?
				shoppurl("$type/$Category->uri") :
				shoppurl(array_merge($_GET,array('s_cat'=>$category_uri,'s_pid'=>null)));

			$filters = false;
			if (!empty($Shopp->Cart->data->Category[$Category->slug]))
				$filters = ' (<a href="?shopp_catfilters=cancel">'.__('Clear Filters','Shopp').'</a>)';

			if (!empty($Shopp->Product))
				$trail .= '<li><a href="'.$link.'">'.$Category->name.(!$trail?'':$separator).'</a></li>';
			elseif (!empty($Category->name))
				$trail .= '<li>'.$Category->name.$filters.(!$trail?'':$separator).'</li>';

			// Build category names path by going from the target category up the parent chain
			$parentkey = (!empty($Category->id)
				&& isset($O->categories['_'.$Category->id]->parent)?
					'_'.$O->categories['_'.$Category->id]->parent:'_0');

			while ($parentkey != '_0' && $depth-- > 0) {
				$tree_category = $O->categories[$parentkey];

				$link = SHOPP_PRETTYURLS?
					shoppurl("category/$tree_category->uri"):
					shoppurl(array_merge($_GET,array('s_cat'=>$tree_category->id,'s_pid'=>null)));

				$trail = '<li><a href="'.$link.'">'.$tree_category->name.'</a>'.
					(empty($trail)?'':$separator).'</li>'.$trail;

				$parentkey = '_'.$tree_category->parent;
			}
		}
		// @todo replace with storefront_pages setting?
		$pages = shopp_setting('pages');

		$trail = '<li><a href="'.shoppurl().'">'.$pages['catalog']['title'].'</a>'.(empty($trail)?'':$separator).'</li>'.$trail;
		return '<ul class="breadcrumb">'.$trail.'</ul>';
	}

	function categories ($result, $options, $O) {
		global $Shopp;
		if (!isset($O->_category_loop)) {
			reset($O->categories);
			$Shopp->Category = current($O->categories);
			$O->_category_loop = true;
		} else {
			$Shopp->Category = next($O->categories);
		}

		if (current($O->categories) !== false) return true;
		else {
			unset($O->_category_loop);
			reset($O->categories);
			return false;
		}
	}

	function category ($result, $options, $O) {
		global $Shopp;

		if (isset($options['name'])) $Shopp->Category = new ProductCategory($options['name'],'name');
		else if (isset($options['slug'])) $Shopp->Category = new ProductCategory($options['slug'],'slug');
		else if (isset($options['id'])) $Shopp->Category = new ProductCategory($options['id']);

		if (isset($options['reset']))
			return (get_class($Shopp->Requested) == "ProductCategory"?($Shopp->Category = $Shopp->Requested):false);
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
	}

	function category_list ($result, $options, $O) {
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
		$taxonomy = 'shopp_category';

		$categories = array(); $count = 0;
		$terms = get_terms( $taxonomy, array('hide_empty' => 0,'fields'=>'id=>parent') );
		$children = _get_term_hierarchy($taxonomy);
		ProductCategory::tree($taxonomy,$terms,$children,$count,$categories);

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
			foreach ($O->categories as &$category) {
				// If the parent of this category was excluded, add this to the excludes and skip
				if (!empty($category->parent) && in_array($category->parent,$exclude)) {
					$exclude[] = $category->id;
					continue;
				}
				if (!empty($category->id) && in_array($category->id,$exclude)) continue; // Skip excluded categories
				if ($category->count == 0 && !isset($category->smart) && !$category->_children) continue; // Only show categories with products
				if ($depthlimit && $category->depth >= $depthlimit) continue;

				if (value_is_true($hierarchy) && $category->depth > $depth) {
					$parent = &$previous;
					if (!isset($parent->path)) $parent->path = '/'.$parent->slug;
				}

				if (value_is_true($hierarchy))
					$padding = str_repeat("&nbsp;",$category->depth*3);

				$category_uri = empty($category->id)?$category->uri:$category->id;
				$link = SHOPP_PRETTYURLS?shoppurl("category/$category->uri"):shoppurl(array('s_cat'=>$category_uri));

				$total = '';
				if (value_is_true($products) && $category->count > 0) $total = ' ('.$category->count.')';

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
			foreach ($categories as &$category) {
				if (!isset($category->count)) $category->count = 0;
				if (!isset($category->level)) $category->level = 0;

				// If the parent of this category was excluded, add this to the excludes and skip
				if (!empty($category->parent) && in_array($category->parent,$exclude)) {
					$exclude[] = $category->id;
					continue;
				}

				if (!empty($category->id) && in_array($category->id,$exclude)) continue; // Skip excluded categories
			if ($depthlimit && $category->level >= $depthlimit) continue;
				if (value_is_true($hierarchy) && $category->level > $depth) {
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

				if (value_is_true($hierarchy) && $category->level < $depth) {
					for ($i = $depth; $i > $category->level; $i--) {
						if (substr($string,strlen($subcategories)*-1) == $subcategories) {
							// If the child menu is empty, remove the <ul> to avoid breaking standards
							$string = substr($string,0,strlen($subcategories)*-1).'</li>';
						} else $string .= '</ul></li>';
					}
				}

				// $category_uri = empty($category->id)?$category->uri:$category->id;
				// $link = SHOPP_PRETTYURLS?shoppurl("category/$category->uri"):shoppurl(array('s_cat'=>$category_uri));
				$link = get_term_link($category->name,$category->taxonomy);
				if (is_wp_error($link)) $link = '';
				$total = '';
				if (value_is_true($products) && $category->count > 0) $total = ' <span>('.$category->count.')</span>';

				$current = '';
				if (isset($Shopp->Category->slug) && $Shopp->Category->slug == $category->slug)
					$current = ' class="current"';

				$listing = '';

				if (!empty($link) && ($category->count > 0 || isset($category->smart) || $linkall))
					$listing = '<a href="'.$link.'"'.$current.'>'.$category->name.($linkcount?$total:'').'</a>'.(!$linkcount?$total:'');
				else $listing = $category->name;

				if (value_is_true($showall) ||
					$category->count > 0 ||
					isset($category->smart) ||
					$category->_children)
					$string .= '<li'.$current.'>'.$listing.'</li>';

				$previous = &$category;
				$depth = $category->level;
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
	}

	function type ($result, $options, $O) { return $O->type; }

	function has_categories ($result, $options, $O) {
		$showsmart = isset($options['showsmart'])?$options['showsmart']:false;
		if (empty($O->categories)) $O->load_categories(array('where'=>'true'),$showsmart);
		if (count($O->categories) > 0) return true; else return false;
	}

	function is_account ($result, $options, $O) { return (is_shopp_page('account')); }

	function is_cart ($result, $options, $O) { return (is_shopp_page('cart')); }

	function is_category ($result, $options, $O) { return (is_shopp_page('catalog') && $O->type == "category"); }

	function is_checkout ($result, $options, $O) { return (is_shopp_page('checkout')); }

	function is_catalog ($result, $options, $O) { return (is_shopp_page('catalog') && $O->type == "catalog"); }

	function is_product ($result, $options, $O) { return (is_shopp_page('catalog') && $O->type == "product"); }

	function orderby_list ($result, $options, $O) {
		global $Shopp;

		if (isset($Shopp->Category->controls)) return false;
		if (isset($Shopp->Category->loading['order']) || isset($Shopp->Category->loading['sortorder'])) return false;

		$menuoptions = ProductCategory::sortoptions();
		// Don't show custom product order for smart categories
		if (isset($Shopp->Category->smart)) unset($menuoptions['custom']);

		$title = "";
		$string = "";
		$dropdown = isset($options['dropdown'])?$options['dropdown']:true;
		$default = shopp_setting('default_product_order');
		if (empty($default)) $default = "title";

		if (isset($options['default'])) $default = $options['default'];
		if (isset($options['title'])) $title = $options['title'];

		if (value_is_true($dropdown)) {
			$Storefront = ShoppStorefront();
			if (isset($Storefront->browsing['sortorder']))
				$default = $Storefront->browsing['sortorder'];
			$string .= $title;
			$string .= '<form action="'.esc_url($_SERVER['REQUEST_URI']).'" method="get" id="shopp-'.$Shopp->Category->slug.'-orderby-menu">';
			if (!SHOPP_PRETTYURLS) {
				foreach ($_GET as $key => $value)
					if ($key != 's_ob') $string .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
			}
			$string .= '<select name="s_so" class="shopp-orderby-menu">';
			$string .= menuoptions($menuoptions,$default,true);
			$string .= '</select>';
			$string .= '</form>';
		} else {
			$link = "";
			$query = "";
			if (strpos($_SERVER['REQUEST_URI'],"?") !== false)
				list($link,$query) = explode("\?",$_SERVER['REQUEST_URI']);
			$query = $_GET;
			unset($query['s_ob']);
			$query = http_build_query($query);
			if (!empty($query)) $query .= '&';

			foreach($menuoptions as $value => $option) {
				$label = $option;
				$href = esc_url(add_query_arg(array('s_so' => $value),$link));
				$string .= '<li><a href="'.$href.'">'.$label.'</a></li>';
			}

		}
		return $string;
	}

	function product ($result, $options, $O) {
		global $Shopp;
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
	}

	function recent_shoppers ($result, $options, $O) {
		$defaults = array(
			'abbr' => 'firstname',
			'city' => true,
			'state' => true,
			'avatar' => true,
			'size' => 48,
			'show' => 5
		);
		$options = array_merge($defaults,$options);
		extract($options);

		$pt = DatabaseObject::tablename(Purchase::$table);
		$shoppers = DB::query("SELECT firstname,lastname,email,city,state FROM $pt AS pt ORDER BY created DESC LIMIT 5",'array');

		$_ = array();
		$_[] = '<ul>';
		foreach ($shoppers as $shopper) {
			if ('lastname' == $abbr) $name = "$shopper->firstname ".$shopper->lastname{0}.".";
			else $name = $shopper->firstname{0}.". $shopper->lastname";

			$img = '';
			if ($avatar) $img = get_avatar($shopper->email,$size,'',$name);

			$loc = '';
			if ($state || $province) $loc = $shopper->state;
			if ($city) $loc = "$shopper->city, $loc";

			$_[] = "<li><div>$img</div>$name <em>$loc</em></li>";
		}
		$_[] = '</ul>';

		return join('',$_);
	}

	function search ($result, $options, $O) {
		$Storefront =& ShoppStorefront();
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

		$allowed = array('accesskey','alt','checked','class','disabled','format', 'id',
			'minlength','maxlength','readonly','required','size','src','tabindex','title','value');

		$options['value'] = ($option == 'shopp');

		// Reset the checked option
		unset($options['checked']);

		// If searching the blog, check the non-store search option
		if ($searching && !$shopsearch && $option != 'shopp') $options['checked'] = 'checked';

		// If searching the storefront, mark the store search option
		if ($shopsearch && $option == 'shopp') $options['checked'] = 'checked';

		// Override any other settings with the supplied default 'checked' option
		if (!$searching && $checked) $options['checked'] = $checked;

		switch ($type) {
			case 'checkbox':
				$input =  '<input type="checkbox" name="s_cs"'.inputattrs($options,$allowed).' />';
				break;
			case 'radio':
				$input =  '<input type="radio" name="s_cs"'.inputattrs($options,$allowed).' />';
				break;
			case 'menu':
				$allowed = array('accesskey','alt','class','disabled','format', 'id',
					'readonly','required','size','tabindex','title');

				$input = '<select name="s_cs"'.inputattrs($options,$allowed).'>';
				$input .= '<option value="">'.$blog_option.'</option>';
				$input .= '<option value="1"'.($shopsearch || (!$searching && $option == 'shopp')?' selected="selected"':'').'>'.$shop_option.'</option>';
				$input .= '</select>';
				break;
			default:
				$allowed = array('alt','class','disabled','format','id','readonly','title','value');
				$input =  '<input type="hidden" name="s_cs"'.inputattrs($options,$allowed).' />';
				break;
		}

		$before = (!empty($label_before))?'<label>'.$label_before:'<label>';
		$after = (!empty($label_after))?$label_after.'</label>':'</label>';
		return $before.$input.$after;
	}

	function search_form ($result, $options, $O) {
		ob_start();
		get_search_form();
		$content = ob_get_contents();
		ob_end_clean();

		preg_match('/^(.*?<form[^>]*>)(.*?)(<\/form>.*?)$/is',$content,$_);
		list($all,$open,$content,$close) = $_;

		$markup = array(
			$open,
			$content,
			'<div><input type="hidden" name="s_cs" value="true" /></div>',
			$close
		);

		return join('',$markup);
	}

	function side_product ($result, $options, $O) {
		global $Shopp;
		$content = false;
		$source = isset($options['source'])?$options['source']:'product';
		if ($source == 'product' && isset($options['product'])) {
			 // Save original requested product
			if ($Shopp->Product) $Requested = $Shopp->Product;
			$products = explode(',',$options['product']);
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

		if ($source == 'category' && isset($options['category'])) {
			 // Save original requested category
			if ($Shopp->Category) $Requested = $Shopp->Category;
			if ($Shopp->Product) $RequestedProduct = $Shopp->Product;
			if (empty($options['category'])) return false;
			$Shopp->Category = new ProductCategory($options['category'],'name');
			$Shopp->Category->load($options);
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
	}

	function tag_products ($result, $options, $O) {
		global $Shopp;
		$Shopp->Category = new TagProducts($options);
		return self::category($result, $options, $O);
	}

	function tag_cloud ($result, $options, $O) {
		$defaults = array(
			'orderby' => 'name',
			'order' => false,
			'number' => 45,
			'levels' => 7,
			'format' => 'list',
			'link' => 'view'
		);
		$options = array_merge($defaults,$options);
		extract($options);

		$tags = get_terms( ProductTag::$taxonomy, array( 'orderby' => 'count', 'order' => 'DESC', 'number' => $number) );

		if (empty($tags)) return false;

		$min = $max = false;
		foreach ($tags as &$tag) {
			$min = !$min?$tag->count:min($min,$tag->count);
			$max = !$max?$tag->count:max($max,$tag->count);

			$link_function = ('edit' == $link?'get_edit_tag_link':'get_term_link');
			$tag->link = $link_function(intval($tag->term_id),ProductTag::$taxonomy);
		}

		// Sorting
		$sorted = apply_filters( 'tag_cloud_sort', $tags, $options );
		if ( $sorted != $tags  ) $tags = &$sorted;
		else {
			if ( 'RAND' == $order ) shuffle($tags);
			else {
				if ( 'name' == $orderby )
					uasort( $tags, create_function('$a, $b', 'return strnatcasecmp($a->name, $b->name);') );
				else
					uasort( $tags, create_function('$a, $b', 'return ($a->count > $b->count);') );

				if ( 'DESC' == $order ) $tags = array_reverse( $tags, true );
			}
		}

		// Markup
		if ('inline' == $format) $markup = '<div class="shopp tagcloud">';
		if ('list' == $format) $markup = '<ul class="shopp tagcloud">';
		foreach ((array)$tags as $tag) {
			$level = floor((1-$tag->count/$max)*$levels)+1;
			if ('list' == $format) $markup .= '<li class="level-'.$level.'">';
			$markup .= '<a href="'.esc_url($tag->link).'" rel="tag">'.$tag->name.'</a>';
			if ('list' == $format) $markup .= '</li> ';
		}
		if ('list' == $format) $markup .= '</ul>';
		if ('inline' == $format) $markup .= '</div>';

		return $markup;
	}

	function url ($result, $options, $O) { return shoppurl(false,'catalog'); }

	function views ($result, $options, $O) {
		global $Shopp;
		if (isset($Shopp->Category->controls)) return false;
		$string = "";
		$string .= '<ul class="views">';
		if (isset($options['label'])) $string .= '<li>'.$options['label'].'</li>';
		$string .= '<li><button type="button" class="grid"></button></li>';
		$string .= '<li><button type="button" class="list"></button></li>';
		$string .= '</ul>';
		return $string;
	}

	function zoom_options ($result, $options, $O) {
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
	}

}



?>