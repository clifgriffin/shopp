<?php
/**
 * Collection classes
 *
 * Library product collection models
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, May  5, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package Shopp
 * @since 1.0
 * @subpackage Collection
 **/

class ProductCollection implements Iterator {

	var $paged = false;
	var $pagination = false;
	var $products = array();
	var $resum = array();
	var $total = 0;

	private $_keys = array();
	private $_position = array();

	function load ($options=array()) {
		$Storefront =& ShoppStorefront();
		$Settings =& ShoppSettings();
		$Processing = new Product();
		$summary_table = DatabaseObject::tablename(ProductSummary::$table);

		$defaults = array(
			'columns' => false,		// Include extra columns (string) 'c.col1,c.col2…'
			'useindex' => false,	// FORCE INDEX to be used on the product table (string) 'indexname'
			'joins' => array(),		// JOIN tables array('INNER JOIN table AS t ON p.id=t.column')
			'where' => array(),		// WHERE query conditions array('x=y OR x=z','a!=b'…) (array elements are joined by AND
			'groupby' => false,		// GROUP BY column (string) 'column'
			'having' => array(),	// HAVING filters
			'limit' => false,		// Limit
			'order' => false,		// ORDER BY columns or named methods (string)
									// 'bestselling','highprice','lowprice','newest','oldest','random','chaos','title'

			'nostock' => true,		// Override to show products that are out of stock (string) 'on','off','yes','no'…
			'pagination' => true,	// Enable alpha pagination (string) 'alpha'
			'published' => true,	// Load published or unpublished products (string) 'on','off','yes','no'…
			'adjacent' => false,	//
			'product' => false,		//
			'load' => array(),		// Product data to load
		);
		$loading = array_merge($defaults,$options);
		extract($loading);

		$this->paged = false;
		$this->pagination = $Settings->get('catalog_pagination');
		$paged = get_query_var('paged');
		$this->page = ((int)$paged > 0 || !is_numeric($paged))?$paged:1;

		// Hard product limit per category to keep resources "reasonable"
		$hardlimit = apply_filters('shopp_category_products_hardlimit',1000);

		if ($published) $where[] = "p.post_status='publish'";

		// Sort Order
		$defaultOrder = $Settings->get('default_product_order');
		if (empty($defaultOrder)) $defaultOrder = '';
		$ordering = isset($Storefront->browsing['sortorder'])?
						$Storefront->browsing['sortorder']:$defaultOrder;
		if ($order !== false) $ordering = $order;
		switch ($ordering) {
			case 'bestselling': $order = "s.sold DESC,p.post_title ASC"; break;
			case 'highprice': $order = "maxprice DESC,p.post_title ASC"; break;
			case 'lowprice': $order = "minprice ASC,p.post_title ASC"; /* $useindex = "lowprice"; */ break;
			case 'newest': $order = "p.post_date DESC,p.post_title ASC"; break;
			case 'oldest': $order = "p.post_date ASC,p.post_title ASC"; /* $useindex = "oldest";	*/ break;
			case 'random': $order = "RAND(".crc32($Shopp->Shopping->session).")"; break;
			case 'chaos': $order = "RAND(".time().")"; break;
			case 'title': $order = "p.post_title ASC"; /* $useindex = "name"; */ break;
			case 'recommended':
			default:
				// Need to add the catalog table for access to category-product priorities
				// if (!isset($this->smart)) {
				// 	$joins[$catalogtable] = "INNER JOIN $catalogtable AS c ON c.product=p.id AND c.parent='$this->id'";
				// 	$order = "c.priority ASC,p.name ASC";
				// } else $order = "p.name ASC";
				$order = "p.post_title ASC";
				break;
		}
		$orderby = false;
		if (!empty($order)) $orderby = $order;

		// Pagination
		if (empty($limit)) {
			if ($this->pagination > 0 && is_numeric($this->page) && value_is_true($pagination)) {
				if( !$this->pagination || $this->pagination < 0 )
					$this->pagination = $hardlimit;
				$start = ($this->pagination * ($this->page-1));

				$limit = "$start,$this->pagination";
			} else $limit = $hardlimit;
		}

		// Core query components

		// Load core product data and product summary columns
		$cols = array(	'p.ID','p.post_title','p.post_name','p.post_excerpt','p.post_status','p.post_date_gmt','p.post_modified',
						's.id AS sumid','s.modified AS summed','s.sold','s.maxprice','s.minprice','s.stock','s.inventory','s.featured','s.variants','s.addons','s.sale');

		$columns = "SQL_CALC_FOUND_ROWS ".join(',',$cols).($columns !== false?','.$columns:'');
		$table = "$Processing->_table AS p";
		$where[] = "p.post_type='$Processing->_post_type'";
		$joins[$stats_table] = "LEFT OUTER JOIN $summary_table AS s ON s.product=p.ID";

		$options = compact('columns','useindex','table','joins','where','groupby','having','limit','orderby');
		$query = DB::select($options);

		$this->products = DB::query($query,'array',array($this,'loader'));
		$this->total = DB::query("SELECT FOUND_ROWS() as total",'auto','col','total');

		if ($this->pagination > 0 && $this->total > $this->pagination) {
			$this->pages = ceil($this->total / $this->pagination);
			if ($this->pages > 1) $this->paged = true;
		}

		$Processing->load_data($load,$this->index);

		// If products are missing summary data, resum them
		if (!empty($this->resum)) {
			$Processing->load_data(array('prices'),$this->resum);
		}

		return (count($this->products) > 0);
	}

	function loader (&$records,$record) {
		$Product = new Product();
		$Product->populate($record);
		$Product->summary($records,$record);
		$records[] = &$Product;
		$this->index[$Product->id] = &$Product;

		// Resum the product pricing data if there is no summation data,
		// or if the summation data hasn't yet been updated today
		if ( empty($record->summed) || mktimestamp($record->summed) < mktime(0,0,0)) {
			$Product->resum();
			$this->resum[$Product->id] = &$Product;
		}


	}

	function pagelink ($page) {
		$type = isset($this->tag)?'tag':'category';
		$alpha = preg_match('/([a-z]|0\-9)/',$page);
		$prettyurl = "$type/$this->uri".($page > 1 || $alpha?"/page/$page":"");
		if ('catalog' == $this->uri) $prettyurl = ($page > 1 || $alpha?"page/$page":"");
		$queryvars = array("shopp_$type"=>$this->uri);
		if ($page > 1 || $alpha) $queryvars['paged'] = $page;

		return apply_filters('shopp_paged_link',shoppurl(SHOPP_PRETTYURLS?$prettyurl:$queryvars));
	}

	function workflow () {
		return array_keys($this->index);
	}

	/** Iterator implementation **/

	function current () {
		return $this->products[$this->_position];
	}

	function key () {
		return $this->_position;
	}

	function next () {
		++$this->_position;
	}

	function rewind () {
		$this->_position = 0;
	}

	function valid () {
		return isset($this->products[$this->_position]);
	}

	/**
	 * shopp('category','...') tags
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 * @see http://docs.shopplugin.net/Category_Tags
	 *
	 * @param string $property The property to handle
	 * @param array $options (optional) The tag options to process
	 * @return mixed
	 **/
	function tag ($property,$options=array()) {
		global $Shopp;
		$db = DB::get();

		switch ($property) {
			case 'link':
			case 'url':
				return shoppurl(SHOPP_PRETTYURLS?'category/'.$this->uri:array('s_cat'=>$this->id));
				break;
			case 'feed-url':
			case 'feedurl':
				$uri = 'category/'.$this->uri;
				if ($this->slug == "tag") $uri = $this->slug.'/'.$this->tag;
				return shoppurl(SHOPP_PRETTYURLS?"$uri/feed":array('s_cat'=>urldecode($this->uri),'src'=>'category_rss'));
			case 'id': return $this->id; break;
			case 'parent': return $this->parent; break;
			case 'name': return $this->name; break;
			case 'slug': return urldecode($this->slug); break;
			case 'description': return wpautop($this->description); break;
			case 'total': return $this->loaded?$this->total:false; break;
			case 'has-products':
			case 'loadproducts':
			case 'load-products':
			case 'hasproducts':
				if (empty($this->id) && empty($this->slug)) return false;
				if (isset($options['load'])) {
					$dataset = explode(",",$options['load']);
					$options['load'] = array();
					foreach ($dataset as $name) $options['load'][] = trim($name);
				 } else {
					$options['load'] = array('prices');
				}
				if (!$this->loaded) $this->load($options);
				if (count($this->products) > 0) return true; else return false; break;
			case 'products':
				if (!isset($this->_product_loop)) {
					reset($this->products);
					$Shopp->Product = current($this->products);
					$this->_pindex = 0;
					$this->_rindex = false;
					$this->_product_loop = true;
				} else {
					$Shopp->Product = next($this->products);
					$this->_pindex++;
				}

				if (current($this->products) !== false) return true;
				else {
					unset($this->_product_loop);
					$this->_pindex = 0;
					return false;
				}
				break;
			case 'row':
				if (!isset($this->_rindex) || $this->_rindex === false) $this->_rindex = 0;
				else $this->_rindex++;
				if (empty($options['products'])) $options['products'] = $Shopp->Settings->get('row_products');
				if (isset($this->_rindex) && $this->_rindex > 0 && $this->_rindex % $options['products'] == 0) return true;
				else return false;
				break;
			case 'has-categories':
			case 'hascategories':
				if (empty($this->children)) $this->load_children();
				return (!empty($this->children));
				break;
			case 'is-subcategory':
			case 'issubcategory':
				return ($this->parent != 0);
				break;
			case 'subcategories':
				if (!isset($this->_children_loop)) {
					reset($this->children);
					$this->child = current($this->children);
					$this->_cindex = 0;
					$this->_children_loop = true;
				} else {
					$this->child = next($this->children);
					$this->_cindex++;
				}

				if ($this->child !== false) return true;
				else {
					unset($this->_children_loop);
					$this->_cindex = 0;
					$this->child = false;
					return false;
				}
				break;
			case 'subcategory-list': return true; // @todo Handle sub-category listing in ShoppCategory
				if (isset($Shopp->Category->controls)) return false;

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

				if (!$this->children) $this->load_children(array('orderby'=>$orderby,'order'=>$order));
				if (empty($this->children)) return false;

				$string = "";
				$depthlimit = $depth;
				$depth = 0;
				$exclude = explode(",",$exclude);
				$classes = ' class="shopp_categories'.(empty($class)?'':' '.$class).'"';
				$wraplist = value_is_true($wraplist);

				if (value_is_true($dropdown)) {
					$count = 0;
					$string .= $title;
					$string .= '<select name="shopp_cats" id="shopp-'.$this->slug.'-subcategories-menu" class="shopp-categories-menu">';
					$string .= '<option value="">'.__('Select a sub-category&hellip;','Shopp').'</option>';
					foreach ($this->children as &$category) {
						if (!empty($show) && $count+1 > $show) break;
						if (value_is_true($hierarchy) && $depthlimit && $category->depth >= $depthlimit) continue;
						if ($category->products == 0) continue; // Only show categories with products
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = '/'.$parent->slug;
						}
						$padding = str_repeat("&nbsp;",$category->depth*3);

						$category_uri = empty($category->id)?$category->uri:$category->id;
						$link = SHOPP_PRETTYURLS?shoppurl("category/$category->uri"):shoppurl(array('s_cat'=>$category_uri));

						$total = '';
						if (value_is_true($products)) $total = '&nbsp;&nbsp;('.$category->products.')';

						$string .= '<option value="'.htmlentities($link).'">'.$padding.$category->name.$total.'</option>';
						$previous = &$category;
						$depth = $category->depth;
						$count++;
					}
					$string .= '</select>';
				} else {
					if (!empty($class)) $classes = ' class="'.$class.'"';
					$string .= $title.'<ul'.$classes.'>';
					$count = 0;
					foreach ($this->children as &$category) {
						if (!isset($category->total)) $category->total = 0;
						if (!isset($category->depth)) $category->depth = 0;
						if (!empty($category->id) && in_array($category->id,$exclude)) continue; // Skip excluded categories
						if ($depthlimit && $category->depth >= $depthlimit) continue;
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = $parent->slug;
							$string = substr($string,0,-5); // Remove the previous </li>
							$active = '';

							if (isset($Shopp->Category) && !empty($parent->slug)
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
						$link = SHOPP_PRETTYURLS?
							shoppurl("category/$category->uri"):
							shoppurl(array('s_cat'=>$category_uri));

						$total = '';
						if (value_is_true($products) && $category->total > 0) $total = ' <span>('.$category->total.')</span>';

						$current = '';
						if (isset($Shopp->Category) && $Shopp->Category->slug == $category->slug)
							$current = ' class="current"';

						$listing = '';
						if ($category->total > 0 || isset($category->smart) || $linkall)
							$listing = '<a href="'.$link.'"'.$current.'>'.$category->name.($linkcount?$total:'').'</a>'.(!$linkcount?$total:'');
						else $listing = $category->name;

						if (value_is_true($showall) ||
							$category->total > 0 ||
							isset($category->smart) ||
							$category->children)
							$string .= '<li'.$current.'>'.$listing.'</li>';

						$previous = &$category;
						$depth = $category->depth;
						$count++;
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
			case 'section-list':
				if (empty($this->id)) return false;
				if (isset($Shopp->Category->controls)) return false;
				if (empty($Shopp->Catalog->categories))
					$Shopp->Catalog->load_categories(array("where"=>"(pd.status='publish' OR pd.id IS NULL)"));
				if (empty($Shopp->Catalog->categories)) return false;
				if (!$this->children) $this->load_children();

				$defaults = array(
					'title' => '',
					'before' => '',
					'after' => '',
					'class' => '',
					'classes' => '',
					'exclude' => '',
					'total' => '',
					'current' => '',
					'listing' => '',
					'depth' => 0,
					'parent' => false,
					'showall' => false,
					'linkall' => false,
					'dropdown' => false,
					'hierarchy' => false,
					'products' => false,
					'wraplist' => true
					);

				$options = array_merge($defaults,$options);
				extract($options, EXTR_SKIP);

				$string = "";
				$depthlimit = $depth;
				$depth = 0;
				$wraplist = value_is_true($wraplist);
				$exclude = explode(",",$exclude);
				$section = array();

				// Identify root parent
				if (empty($this->id)) return false;
				$parent = '_'.$this->id;
				while($parent != 0) {
					if (!isset($Shopp->Catalog->categories[$parent])) break;
					if ($Shopp->Catalog->categories[$parent]->parent == 0
						|| $Shopp->Catalog->categories[$parent]->parent == $parent) break;
					$parent = '_'.$Shopp->Catalog->categories[$parent]->parent;
				}
				$root = $Shopp->Catalog->categories[$parent];
				if ($this->id == $parent && empty($this->children)) return false;

				// Build the section
				$section[] = $root;
				$in = false;
				foreach ($Shopp->Catalog->categories as &$c) {
					if ($in && $c->depth == $root->depth) break; // Done
					if ($in) $section[] = $c;
					if (!$in && isset($c->id) && $c->id == $root->id) $in = true;
				}

				if (value_is_true($dropdown)) {
					$string .= $title;
					$string .= '<select name="shopp_cats" id="shopp-'.$this->slug.'-subcategories-menu" class="shopp-categories-menu">';
					$string .= '<option value="">'.__('Select a sub-category&hellip;','Shopp').'</option>';
					foreach ($section as &$category) {
						if (value_is_true($hierarchy) && $depthlimit && $category->depth >= $depthlimit) continue;
						if (in_array($category->id,$exclude)) continue; // Skip excluded categories
						if ($category->products == 0) continue; // Only show categories with products
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = '/'.$parent->slug;
						}
						$padding = str_repeat("&nbsp;",$category->depth*3);

						$category_uri = empty($category->id)?$category->uri:$category->id;
						$link = SHOPP_PRETTYURLS?shoppurl("category/$category->uri"):shoppurl(array('s_cat'=>$category_uri));

						$total = '';
						if (value_is_true($products)) $total = '&nbsp;&nbsp;('.$category->total.')';

						$string .= '<option value="'.htmlentities($link).'">'.$padding.$category->name.$total.'</option>';
						$previous = &$category;
						$depth = $category->depth;

					}
					$string .= '</select>';
				} else {
					if (!empty($class)) $classes = ' class="'.$class.'"';
					$string .= $title;
					if ($wraplist) $string .= '<ul'.$classes.'>';
					foreach ($section as &$category) {
						if (in_array($category->id,$exclude)) continue; // Skip excluded categories
						if (value_is_true($hierarchy) && $depthlimit &&
							$category->depth >= $depthlimit) continue;
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path) && isset($parent->slug)) $parent->path = $parent->slug;
							$string = substr($string,0,-5);
							$string .= '<ul class="children">';
						}
						if (value_is_true($hierarchy) && $category->depth < $depth) $string .= '</ul></li>';

						$category_uri = empty($category->id)?$category->uri:$category->id;
						$link = SHOPP_PRETTYURLS?shoppurl("category/$category->uri"):shoppurl(array('s_cat'=>$category_uri));

						if (value_is_true($products)) $total = ' <span>('.$category->total.')</span>';

						if ($category->total > 0 || isset($category->smart) || $linkall) $listing = '<a href="'.$link.'"'.$current.'>'.$category->name.$total.'</a>';
						else $listing = $category->name;

						if (value_is_true($showall) ||
							$category->total > 0 ||
							$category->children)
							$string .= '<li>'.$listing.'</li>';

						$previous = &$category;
						$depth = $category->depth;
					}
					if (value_is_true($hierarchy) && $depth > 0)
						for ($i = $depth; $i > 0; $i--) $string .= '</ul></li>';

					if ($wraplist) $string .= '</ul>';
				}
				return $string;
				break;
			case 'pagination':
				if (!$this->paged) return "";

				$defaults = array(
					'label' => __("Pages:","Shopp"),
					'next' => __("next","Shopp"),
					'previous' => __("previous","Shopp"),
					'jumpback' => '&laquo;',
					'jumpfwd' => '&raquo;',
					'show' => 1000,
					'before' => '<div>',
					'after' => '</div>'
				);
				$options = array_merge($defaults,$options);
				extract($options);

				$_ = array();
				if (isset($this->alpha) && $this->paged) {
					$_[] = $before.$label;
					$_[] = '<ul class="paging">';
					foreach ($this->alpha as $alpha) {
						$link = $this->pagelink($alpha->letter);
						if ($alpha->total > 0)
							$_[] = '<li><a href="'.$link.'">'.$alpha->letter.'</a></li>';
						else $_[] = '<li><span>'.$alpha->letter.'</span></li>';
					}
					$_[] = '</ul>';
					$_[] = $after;
					return join("\n",$_);
				}

				if ($this->pages > 1) {

					if ( $this->pages > $show ) $visible_pages = $show + 1;
					else $visible_pages = $this->pages + 1;
					$jumps = ceil($visible_pages/2);
					$_[] = $before.$label;

					$_[] = '<ul class="paging">';
					if ( $this->page <= floor(($show) / 2) ) {
						$i = 1;
					} else {
						$i = $this->page - floor(($show) / 2);
						$visible_pages = $this->page + floor(($show) / 2) + 1;
						if ($visible_pages > $this->pages) $visible_pages = $this->pages + 1;
						if ($i > 1) {
							$link = $this->pagelink(1);
							$_[] = '<li><a href="'.$link.'">1</a></li>';

							$pagenum = ($this->page - $jumps);
							if ($pagenum < 1) $pagenum = 1;
							$link = $this->pagelink($pagenum);
							$_[] = '<li><a href="'.$link.'">'.$jumpback.'</a></li>';
						}
					}

					// Add previous button
					if (!empty($previous) && $this->page > 1) {
						$prev = $this->page-1;
						$link = $this->pagelink($prev);
						$_[] = '<li class="previous"><a href="'.$link.'">'.$previous.'</a></li>';
					} else $_[] = '<li class="previous disabled">'.$previous.'</li>';
					// end previous button

					while ($i < $visible_pages) {
						$link = $this->pagelink($i);
						if ( $i == $this->page ) $_[] = '<li class="active">'.$i.'</li>';
						else $_[] = '<li><a href="'.$link.'">'.$i.'</a></li>';
						$i++;
					}
					if ($this->pages > $visible_pages) {
						$pagenum = ($this->page + $jumps);
						if ($pagenum > $this->pages) $pagenum = $this->pages;
						$link = $this->pagelink($pagenum);
						$_[] = '<li><a href="'.$link.'">'.$jumpfwd.'</a></li>';
						$link = $this->pagelink($this->pages);
						$_[] = '<li><a href="'.$link.'">'.$this->pages.'</a></li>';
					}

					// Add next button
					if (!empty($next) && $this->page < $this->pages) {
						$pagenum = $this->page+1;
						$link = $this->pagelink($pagenum);
						$_[] = '<li class="next"><a href="'.$link.'">'.$next.'</a></li>';
					} else $_[] = '<li class="next disabled">'.$next.'</li>';

					$_[] = '</ul>';
					$_[] = $after;
				}
				return join("\n",$_);
				break;

			case 'has-faceted-menu': return ($this->facetedmenus == "on"); break;
			case 'faceted-menu':
				if ($this->facetedmenus == "off") return;
				$output = "";
				$CategoryFilters =& $Shopp->Flow->Controller->browsing[$this->slug];
				$link = $_SERVER['REQUEST_URI'];
				if (!isset($options['cancel'])) $options['cancel'] = "X";
				if (strpos($_SERVER['REQUEST_URI'],"?") !== false)
					list($link,$query) = explode("?",$_SERVER['REQUEST_URI']);
				$query = $_GET;
				$query = http_build_query($query);
				$link = esc_url($link).'?'.$query;

				$list = "";
				if (is_array($CategoryFilters)) {
					foreach($CategoryFilters AS $facet => $filter) {
						$href = add_query_arg('shopp_catfilters['.urlencode($facet).']','',$link);
						if (preg_match('/^(.*?(\d+[\.\,\d]*).*?)\-(.*?(\d+[\.\,\d]*).*)$/',$filter,$matches)) {
							$label = $matches[1].' &mdash; '.$matches[3];
							if ($matches[2] == 0) $label = __('Under ','Shopp').$matches[3];
							if ($matches[4] == 0) $label = $matches[1].' '.__('and up','Shopp');
						} else $label = $filter;
						if (!empty($filter)) $list .= '<li><strong>'.$facet.'</strong>: '.stripslashes($label).' <a href="'.$href.'=" class="cancel">'.$options['cancel'].'</a></li>';
					}
					$output .= '<ul class="filters enabled">'.$list.'</ul>';
				}

				if ($this->pricerange == "auto" && empty($CategoryFilters['Price'])) {
					// if (!$this->loaded) $this->load_products();
					$list = "";
					$this->priceranges = auto_ranges($this->pricing['average'],$this->pricing['max'],$this->pricing['min']);
					foreach ($this->priceranges as $range) {
						$href = add_query_arg('shopp_catfilters[Price]',urlencode(money($range['min']).'-'.money($range['max'])),$link);
						$label = money($range['min']).' &mdash; '.money($range['max']-0.01);
						if ($range['min'] == 0) $label = __('Under ','Shopp').money($range['max']);
						elseif ($range['max'] == 0) $label = money($range['min']).' '.__('and up','Shopp');
						$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
					}
					if (!empty($this->priceranges)) $output .= '<h4>'.__('Price Range','Shopp').'</h4>';
					$output .= '<ul>'.$list.'</ul>';
				}

				$catalogtable = DatabaseObject::tablename(Catalog::$table);
				$producttable = DatabaseObject::tablename(Product::$table);
				$spectable = DatabaseObject::tablename(Spec::$table);

				$query = "SELECT spec.name,spec.value,
					IF(spec.numeral > 0,spec.name,spec.value) AS merge,
					count(*) AS total,avg(numeral) AS avg,max(numeral) AS max,min(numeral) AS min
					FROM $catalogtable AS cat
					LEFT JOIN $producttable AS p ON cat.product=p.id
					LEFT JOIN $spectable AS spec ON p.id=spec.parent AND spec.context='product' AND spec.type='spec'
					WHERE cat.parent=$this->id AND cat.taxonomy='$this->taxonomy' AND spec.value != '' AND spec.value != '0' GROUP BY merge ORDER BY spec.name,merge";

				$results = $db->query($query,AS_ARRAY);

				$specdata = array();
				foreach ($results as $data) {
					if (isset($specdata[$data->name])) {
						if (!is_array($specdata[$data->name]))
							$specdata[$data->name] = array($specdata[$data->name]);
						$specdata[$data->name][] = $data;
					} else $specdata[$data->name] = $data;
				}

				if (is_array($this->specs)) {
					foreach ($this->specs as $spec) {
						$list = "";
						if (!empty($CategoryFilters[$spec['name']])) continue;

						// For custom menu presets
						if ($spec['facetedmenu'] == "custom" && !empty($spec['options'])) {
							foreach ($spec['options'] as $option) {
								$href = add_query_arg('shopp_catfilters['.$spec['name'].']',urlencode($option['name']),$link);
								$list .= '<li><a href="'.$href.'">'.$option['name'].'</a></li>';
							}
							$output .= '<h4>'.$spec['name'].'</h4><ul>'.$list.'</ul>';

						// For preset ranges
						} elseif ($spec['facetedmenu'] == "ranges" && !empty($spec['options'])) {
							foreach ($spec['options'] as $i => $option) {
								$matches = array();
								$format = '%s-%s';
								$next = 0;
								if (isset($spec['options'][$i+1])) {
									if (preg_match('/(\d+[\.\,\d]*)/',$spec['options'][$i+1]['name'],$matches))
										$next = $matches[0];
								}
								$matches = array();
								$range = array("min" => 0,"max" => 0);
								if (preg_match('/^(.*?)(\d+[\.\,\d]*)(.*)$/',$option['name'],$matches)) {
									$base = $matches[2];
									$format = $matches[1].'%s'.$matches[3];
									if (!isset($spec['options'][$i+1])) $range['min'] = $base;
									else $range = array("min" => $base, "max" => ($next-1));
								}
								if ($i == 1) {
									$href = add_query_arg('shopp_catfilters['.$spec['name'].']', urlencode(sprintf($format,'0',$range['min'])),$link);
									$label = __('Under ','Shopp').sprintf($format,$range['min']);
									$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
								}

								$href = add_query_arg('shopp_catfilters['.$spec['name'].']', urlencode(sprintf($format,$range['min'],$range['max'])), $link);
								$label = sprintf($format,$range['min']).' &mdash; '.sprintf($format,$range['max']);
								if ($range['max'] == 0) $label = sprintf($format,$range['min']).' '.__('and up','Shopp');
								$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
							}
							$output .= '<h4>'.$spec['name'].'</h4><ul>'.$list.'</ul>';

						// For automatically building the menu options
						} elseif ($spec['facetedmenu'] == "auto" && isset($specdata[$spec['name']])) {

							if (is_array($specdata[$spec['name']])) { // Generate from text values
								foreach ($specdata[$spec['name']] as $option) {
									$href = add_query_arg('shopp_catfilters['.$spec['name'].']',urlencode($option->value),$link);
									$list .= '<li><a href="'.$href.'">'.$option->value.'</a></li>';
								}
								$output .= '<h4>'.$spec['name'].'</h4><ul>'.$list.'</ul>';
							} else { // Generate number ranges
								$format = '%s';
								if (preg_match('/^(.*?)(\d+[\.\,\d]*)(.*)$/',$specdata[$spec['name']]->content,$matches))
									$format = $matches[1].'%s'.$matches[3];

								$ranges = auto_ranges($specdata[$spec['name']]->avg,$specdata[$spec['name']]->max,$specdata[$spec['name']]->min);
								foreach ($ranges as $range) {
									$href = add_query_arg('shopp_catfilters['.$spec['name'].']', urlencode($range['min'].'-'.$range['max']), $link);
									$label = sprintf($format,$range['min']).' &mdash; '.sprintf($format,$range['max']);
									if ($range['min'] == 0) $label = __('Under ','Shopp').sprintf($format,$range['max']);
									elseif ($range['max'] == 0) $label = sprintf($format,$range['min']).' '.__('and up','Shopp');
									$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
								}
								if (!empty($list)) $output .= '<h4>'.$spec['name'].'</h4>';
								$output .= '<ul>'.$list.'</ul>';

							}
						}
					}
				}


				return $output;
				break;
			case 'hasimages':
			case 'has-images':
				if (empty($this->images)) $this->load_images();
				if (empty($this->images)) return false;
				return true;
				break;
			case 'images':
				if (!isset($this->_images_loop)) {
					reset($this->images);
					$this->_images_loop = true;
				} else next($this->images);

				if (current($this->images) !== false) return true;
				else {
					unset($this->_images_loop);
					return false;
				}
				break;
			case 'coverimage':
			case 'thumbnail': // deprecated
				// Force select the first loaded image
				unset($options['id']);
				$options['index'] = 0;
			case 'image':
				if (empty($this->images)) $this->load_images();
				if (!(count($this->images) > 0)) return "";

				// Compatibility defaults
				$_size = 96;
				$_width = $Shopp->Settings->get('gallery_thumbnail_width');
				$_height = $Shopp->Settings->get('gallery_thumbnail_height');
				if (!$_width) $_width = $_size;
				if (!$_height) $_height = $_size;

				$defaults = array(
					'img' => false,
					'id' => false,
					'index' => false,
					'class' => '',
					'width' => false,
					'height' => false,
					'width_a' => false,
					'height_a' => false,
					'size' => false,
					'fit' => false,
					'sharpen' => false,
					'quality' => false,
					'bg' => false,
					'alt' => '',
					'title' => '',
					'zoom' => '',
					'zoomfx' => 'shopp-zoom',
					'property' => false
				);
				$options = array_merge($defaults,$options);
				extract($options);

				// Select image by database id
				if ($id !== false) {
					for ($i = 0; $i < count($this->images); $i++) {
						if ($img->id == $id) {
							$img = $this->images[$i]; break;
						}
					}
					if (!$img) return "";
				}

				// Select image by index position in the list
				if ($index !== false && isset($this->images[$index]))
					$img = $this->images[$index];

				// Use the current image pointer by default
				if (!$img) $img = current($this->images);

				if ($size !== false) $width = $height = $size;
				if (!$width) $width = $_width;
				if (!$height) $height = $_height;

				$scale = $fit?array_search($fit,$img->_scaling):false;
				$sharpen = $sharpen?min($sharpen,$img->_sharpen):false;
				$quality = $quality?min($quality,$img->_quality):false;
				$fill = $bg?hexdec(ltrim($bg,'#')):false;

				list($width_a,$height_a) = array_values($img->scaled($width,$height,$scale));
				if ($size == "original") {
					$width_a = $img->width;
					$height_a = $img->height;
				}
				if ($width_a === false) $width_a = $width;
				if ($height_a === false) $height_a = $height;

				$alt = esc_attr(empty($alt)?(empty($img->alt)?$img->name:$img->alt):$alt);
				$title = empty($title)?$img->title:$title;
				$titleattr = empty($title)?'':' title="'.esc_attr($title).'"';
				$classes = empty($class)?'':' class="'.esc_attr($class).'"';

				$src = shoppurl($img->id,'images');
				if ($size != "original") {
					$src = add_query_string(
						$img->resizing($width,$height,$scale,$sharpen,$quality,$fill),
						trailingslashit(shoppurl($img->id,'images')).$img->filename
					);
				}

				switch (strtolower($property)) {
					case 'id': return $img->id; break;
					case 'url':
					case 'src': return $src; break;
					case 'title': return $title; break;
					case 'alt': return $alt; break;
					case 'width': return $width_a; break;
					case 'height': return $height_a; break;
					case 'class': return $class; break;
				}

				$imgtag = '<img src="'.$src.'"'.$titleattr.' alt="'.$alt.'" width="'.$width_a.'" height="'.$height_a.'" '.$classes.' />';

				if (value_is_true($zoom))
					return '<a href="'.shoppurl($img->id,'images').'/'.$img->filename.'" class="'.$zoomfx.'" rel="product-'.$this->id.'">'.$imgtag.'</a>';

				return $imgtag;
				break;
			case 'slideshow':
				$options['load'] = array('images');
				if (!$this->loaded) $this->load_products($options);
				if (count($this->products) == 0) return false;

				$defaults = array(
					'width' => '440',
					'height' => '180',
					'fit' => 'crop',
					'fx' => 'fade',
					'duration' => 1000,
					'delay' => 7000,
					'order' => 'normal'
				);
				$options = array_merge($defaults,$options);
				extract($options, EXTR_SKIP);

				$href = shoppurl(SHOPP_PERMALINKS?trailingslashit('000'):'000','images');
				$imgsrc = add_query_string("$width,$height",$href);

				$string = '<ul class="slideshow '.$fx.'-fx '.$order.'-order duration-'.$duration.' delay-'.$delay.'">';
				$string .= '<li class="clear"><img src="'.$imgsrc.'" width="'.$width.'" height="'.$height.'" /></li>';
				foreach ($this->products as $Product) {
					if (empty($Product->images)) continue;
					$string .= '<li><a href="'.$Product->tag('url').'">';
					$string .= $Product->tag('image',array('width'=>$width,'height'=>$height,'fit'=>$fit));
					$string .= '</a></li>';
				}
				$string .= '</ul>';
				return $string;
				break;
			case 'carousel':
				$options['load'] = array('images');
				if (!$this->loaded) $this->load_products($options);
				if (count($this->products) == 0) return false;

				$defaults = array(
					'imagewidth' => '96',
					'imageheight' => '96',
					'fit' => 'all',
					'duration' => 500
				);
				$options = array_merge($defaults,$options);
				extract($options, EXTR_SKIP);

				$string = '<div class="carousel duration-'.$duration.'">';
				$string .= '<div class="frame">';
				$string .= '<ul>';
				foreach ($this->products as $Product) {
					if (empty($Product->images)) continue;
					$string .= '<li><a href="'.$Product->tag('url').'">';
					$string .= $Product->tag('image',array('width'=>$imagewidth,'height'=>$imageheight,'fit'=>$fit));
					$string .= '</a></li>';
				}
				$string .= '</ul></div>';
				$string .= '<button type="button" name="left" class="left">&nbsp;</button>';
				$string .= '<button type="button" name="right" class="right">&nbsp;</button>';
				$string .= '</div>';
				return $string;
				break;
		}
	}

}

// @todo Document ProductTaxonomy
class ProductTaxonomy extends ProductCollection {
	static $taxonomy = 'shopp_group';
	static $namespace = 'group';
	static $hierarchical = true;

	protected $context = 'group';

	var $id = false;
	var $meta = array();

	function __construct ($id=false,$key='id') {
		if (!$id) return;
		if ('slug' == $key) $this->loadby_slug($id);
		else $this->load_term($id);
	}

	static function register ($class) {
		$slug = SHOPP_NAMESPACE_TAXONOMIES ? SHOPP_CATALOG_SLUG.'/'.$class::$namespace : $class::$namespace;
		register_taxonomy($class::$taxonomy,array(Product::$posttype), array(
			'hierarchical' => $class::$hierarchical,
			'labels' => $class::labels(),
			'show_ui' => true,
			'query_var' => true,
			'rewrite' => array( 'slug' => $slug ),
		));
	}

	static function labels () {
		return array(
			'name' => __('Groups','Shopp'),
			'singular_name' => __('Group','Shopp'),
			'search_items' => __('Search Group','Shopp'),
			'popular_items' => __('Popular','Shopp'),
			'all_items' => __('Show All','Shopp'),
			'parent_item' => __('Parent Group','Shopp'),
			'parent_item_colon' => __('Parent Group:','Shopp'),
			'edit_item' => __('Edit Group','Shopp'),
			'update_item' => __('Update Group','Shopp'),
			'add_new_item' => __('New Group','Shopp'),
			'new_item_name' => __('New Group Name','Shopp'),
			'separate_items_with_commas' => __('Separate groups with commas','Shopp'),
			'add_or_remove_items' => __('Add or remove groups','Shopp'),
			'choose_from_most_used' => __('Choose from the most used groups','Shopp')
		);
	}

	function load ($options=array()) {
		global $wpdb;

		$options['joins'][$wpdb->term_relationships] = "INNER JOIN $wpdb->term_relationships AS tr ON (p.ID=tr.object_id)";
		$options['joins'][$wpdb->term_taxonomy] = "INNER JOIN $wpdb->term_taxonomy AS tt ON (tr.term_taxonomy_id=tt.term_taxonomy_id AND tt.term_id=$this->id)";

		parent::load($options);
	}

	function load_term ($id) {
		$term = get_term($id,$this->taxonomy);
		if (empty($term->term_id)) return false;
		$this->populate($term);
	}

	/**
	 * Load a taxonomy by slug name
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param string $slug The slug name to load
	 * @return boolean loaded successfully or not
	 **/
	function loadby_slug ($slug) {
		$term = get_term_by('slug',$slug,$this->taxonomy);
		if (empty($term->term_id)) return false;
		$this->populate($term);
	}

	function populate ($data) {
		foreach(get_object_vars($data) as $var => $value)
			$this->{$var} = $value;

		$this->id = $this->term_id;
	}

	function load_meta () {
		$meta = DatabaseObject::tablename(MetaObject::$table);
		DB::query("SELECT * FROM $meta WHERE parent=$this->id AND context='$this->context' AND type='meta'",'array',array($this,'metaloader'));
	}

	function metaloader (&$records,&$record) {
		$Meta = new MetaObject();
		$Meta->populate($record);
		$this->meta[$record->name] = $Meta;
		if (!isset($this->{$record->name}))
			$this->{$record->name} = &$Meta->value;
	}

	function save () {
		$properties = array('slug','description','parent');
		$updates = array_intersect_key(get_object_vars($this),$properties);

		if ($this->id) wp_update_term($this->name,$this->taxonomy,$updates);
		else wp_insert_term($this->name,$this->taxonomy,$updates);

		if (!$this->id) return false;

		// If the term successfully saves, save all meta data too
 		foreach ($this->meta as $Meta) {
			$Meta->parent = $this->id;
			$Meta->context = 'category';
			$Meta->save();
		}
	}

	function delete () {
		if (!$this->id) return false;

		// Remove WP taxonomy term
		$result = wp_delete_term($this->id,$this->taxonomy);

		// Remove meta data & images
		$meta = DatabaseObject::tablename(MetaObject::$table);
		DB::query("DELETE FROM $meta WHERE parent='$this->id' AND context='category'");

	}

	function tree ($taxonomy,$terms,&$children,&$count,&$results = array(),$page=1,$per_page=0,$parent=0,$level=0) {

		$start = ($page - 1) * $per_page;
		$end = $start + $per_page;

		foreach ($terms as $id => $term_parent) {
			if ( $end > $start && $count >= $end ) break;
			if ($term_parent != $parent ) continue;

			// Render parents when pagination starts in a branch
			if ( $count == $start && $term_parent > 0 ) {
				$parents = $parent_ids = array();
				$p = $term_parent;
				while ( $p ) {
					$terms_parent = get_term( $p, $taxonomy );
					$parents[] = $terms_parent;
					$p = $terms_parent->parent;

					if (in_array($p,$parent_ids)) break;

					$parent_ids[] = $p;
				}
				unset($parent_ids);

				$parent_count = count($parents);
				while ($terms_parent = array_pop($parents)) {
					$results[$terms_parent->term_id] = $terms_parent;
					$results[$terms_parent->term_id]->level = $level-$parent_count;
					$parent_count--;
				}
			}

			if ($count >= $start) {
				if (isset($results[$id])) continue;
				$results[$id] = get_term($id,$taxonomy);
				$results[$id]->level = $level;
			}
			++$count;
			unset($terms[$id]);

			if (isset($children[$id]))
				self::tree($taxonomy,$terms,$children,$count,$results,$page,$per_page,$id,$level+1);
		}
	}


}

class ProductCategory extends ProductTaxonomy {
	static $taxonomy = 'shopp_category';
	static $namespace = 'category';
	static $hierarchical = true;

	protected $context = 'category';

	function __construct ($id,$key='id') {
		$this->taxonomy = self::$taxonomy;
		parent::__construct($id,$key);
	}

	// static $table = "category";
	// var $loaded = false;
	// var $paged = false;
	// var $children = array();
	// var $child = false;
	// var $parent = 0;
	// var $total = 0;
	// var $description = "";
	// var $timestamp = false;
	// var $thumbnail = false;
	// var $products = array();
	// var $pricing = array();
	// var $filters = array();
	// var $loading = array();
	// var $images = array();
	// var $facetedmenus = "off";
	// var $published = true;
	// var $taxonomy = false;
	// var $depth = false;

	static function labels ($class) {
		return array(
			'name' => __('Categories','Shopp'),
			'singular_name' => __('Category','Shopp'),
			'search_items' => __('Search Categories','Shopp'),
			'popular_items' => __('Popular','Shopp'),
			'all_items' => __('Show All','Shopp'),
			'parent_item' => __('Parent Category','Shopp'),
			'parent_item_colon' => __('Parent Category:','Shopp'),
			'edit_item' => __('Edit Category','Shopp'),
			'update_item' => __('Update Category','Shopp'),
			'add_new_item' => __('New Category','Shopp'),
			'new_item_name' => __('New Category Name','Shopp')
		);
	}

	/**
	 * Load sub-categories
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $loading Query configuration array
	 * @return boolean successfully loaded or not
	 **/
	function load_children($loading=array()) {
		if (isset($this->smart)
			|| empty($this->id)
			|| empty($this->uri)) return false;

		$db = DB::get();
		$catalog_table = DatabaseObject::tablename(Catalog::$table);

		$defaults = array(
			'columns' => 'cat.*,count(sc.product) as total',
			'joins' => array("LEFT JOIN $catalog_table AS sc ON sc.parent=cat.id AND sc.taxonomy='$this->taxonomy'"),
			'where' => array("cat.uri like '%$this->uri%' AND cat.id <> $this->id"),
			'orderby' => 'name',
			'order' => 'ASC'
		);
		$loading = array_merge($defaults,$loading);
		extract($loading);

		switch(strtolower($orderby)) {
			case 'id': $orderby = "cat.id"; break;
			case 'slug': $orderby = "cat.slug"; break;
			case 'count': $orderby = "total"; break;
			default: $orderby = "cat.name";
		}

		switch(strtoupper($order)) {
			case 'DESC': $order = "DESC"; break;
			default: $order = "ASC";
		}

		$joins = join(' ',$joins);
		$where = join(' AND ',$where);
		$name_order = ($orderby !== "name")?",name ASC":"";

		$query = "SELECT $columns FROM $this->_table AS cat
					$joins
					WHERE $where
					GROUP BY cat.id
					ORDER BY cat.parent DESC,$orderby $order$name_order";
		$children = $db->query($query,AS_ARRAY);

		$children = sort_tree($children,$this->id);
		foreach ($children as &$child) {
			$this->children[$child->id] = new ProductCategory();
			$this->children[$child->id]->populate($child);
			$this->children[$child->id]->depth = $child->depth;
			$this->children[$child->id]->total = $child->total;
		}

		return (!empty($this->children));
	}

	/**
	 * Loads images assigned to this category
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @return boolean Successful load or not
	 **/
	function load_images () {
		$db = DB::get();
		$Settings =& ShoppSettings();

		$ordering = $Settings->get('product_image_order');
		$orderby = $Settings->get('product_image_orderby');

		if ($ordering == "RAND()") $orderby = $ordering;
		else $orderby .= ' '.$ordering;
		$table = DatabaseObject::tablename(CategoryImage::$table);
		if (empty($this->id)) return false;
		$records = $db->query("SELECT * FROM $table WHERE parent=$this->id AND context='category' AND type='image' ORDER BY $orderby",AS_ARRAY);

		foreach ($records as $r) {
			$image = new ProductCategoryImage();
			$image->copydata($r,false,array());
			$image->value = unserialize($image->value);
			$image->expopulate();
			$this->images[] = $image;
		}

		return true;
	}

	/**
	 * Loads a list of products for the category
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.2
	 *
	 * @param array $loading Loading options for the category
	 * @return void
	 **/
	function load_products ($loading=false) {
		global $Shopp;
		$db = DB::get();
		$Storefront =& ShoppStorefront();
		$Settings =& ShoppSettings();

		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$producttable = DatabaseObject::tablename(Product::$table);
		$pricetable = DatabaseObject::tablename(Price::$table);

		$this->paged = false;
		$this->pagination = $Settings->get('catalog_pagination');
		$paged = get_query_var('paged');
		$this->page = ((int)$paged > 0 || !is_numeric($paged))?$paged:1;

		if (empty($this->page)) $this->page = 1;

		// Hard product limit per category to keep resources "reasonable"
		$hardlimit = apply_filters('shopp_category_products_hardlimit',1000);

		$options = array(
			'columns' => false,		// Include extra columns (string) 'c.col1,c.col2…'
			'useindex' => false,	// FORCE INDEX to be used on the product table (string) 'indexname'
			'joins' => array(),		// JOIN tables array('INNER JOIN table AS t ON p.id=t.column')
			'where' => array(),		// WHERE query conditions array('x=y OR x=z','a!=b'…) (array elements are joined by AND
			'groupby' => false,		// GROUP BY column (string) 'column'
			'having' => array(),	// HAVING filters
			'limit' => false,		// Limit
			'order' => false,		// ORDER BY columns or named methods (string)
									// 'bestselling','highprice','lowprice','newest','oldest','random','chaos','title'

			'nostock' => true,		// Override to show products that are out of stock (string) 'on','off','yes','no'…
			'pagination' => true,	// Enable alpha pagination (string) 'alpha'
			'published' => true,	// Load published or unpublished products (string) 'on','off','yes','no'…
			'adjacent' => false,	//
			'product' => false,		//
			'restat' => false		// Force recalculate product stats
		);

		$loading = array_merge($options,$this->loading,$loading);
		extract($loading);

		if (!empty($where) && is_string($where)) $where = array($where);
		if (!empty($joins) && is_string($joins)) $joins = array($joins);

		// Allow override for loading unpublished products
		if (!value_is_true($published)) $this->published = false;

		// Handle default WHERE clause matching this category id
		if (!empty($this->id))
			$joins[$catalogtable] = "INNER JOIN $catalogtable AS c ON p.id=c.product AND parent=$this->id AND taxonomy='$this->taxonomy'";

		if (!value_is_true($nostock) && $Settings->get('outofstock_catalog') == "off")
			$where[] = "((p.inventory='on' AND p.stock > 0) OR p.inventory='off')";

		// Faceted browsing
		if ($Storefront !== false && !empty($Storefront->browsing[$this->slug])) {
			$spectable = DatabaseObject::tablename(Spec::$table);

			$f = 1;
			$filters = array();
			foreach ($Storefront->browsing[$this->slug] as $facet => $value) {
				if (empty($value)) continue;
				$specalias = "spec".($f++);

				// Handle Number Range filtering
				if (!is_array($value) &&
						preg_match('/^.*?(\d+[\.\,\d]*).*?\-.*?(\d+[\.\,\d]*).*$/',$value,$matches)) {

					if ('price' == strtolower($facet)) { // Prices require complex matching on price line entries
						list(,$min,$max) = array_map('floatvalue',$matches);

						if ($min > 0) $filters[] = "((sale='on' AND (minprice >= $min OR maxprice >= $min))
													OR (sale='on' AND (minprice >= $min OR maxprice >= $min)))";
						if ($max > 0) $filters[] = "((sale='on' AND (minprice <= $max OR maxprice <= $max))
													OR (sale='on' AND (minprice <= $max OR maxprice <= $max)))";

						// Use HAVING clause for filtering by pricing information because of data aggregation
						// $having[] = $match;
						// continue;

					} else { // Spec-based numbers are somewhat more straightforward
						list(,$min,$max) = $matches;
						if ($min > 0) $filters[] = "$specalias.numeral >= $min";
						if ($max > 0) $filters[] = "$specalias.numeral <= $max";
					}
				} else $filters[] = "$specalias.value='$value'"; // No range, direct value match

				$joins[$specalias] = "LEFT JOIN $spectable AS $specalias
										ON $specalias.parent=p.id
										AND $specalias.context='product'
										AND $specalias.type='spec'
										AND $specalias.name='".$db->escape($facet)."'";
			}
			$where[] = join(' AND ',$filters);

		}

		// WP TZ setting based time - (timezone offset:[PHP UTC adjusted time - MySQL UTC adjusted time])
		$now = time()."-(".(time()-date("Z",time()))."-UNIX_TIMESTAMP(UTC_TIMESTAMP()))";

		if ($this->published) $where[] = "(p.status='publish' AND $now >= UNIX_TIMESTAMP(p.publish))";
		else $where[] = "(p.status!='publish' OR $now < UNIX_TIMESTAMP(p.publish))";

		$defaultOrder = $Settings->get('default_product_order');
		if (empty($defaultOrder)) $defaultOrder = '';
		$ordering = isset($Storefront->browsing['orderby'])?
						$Storefront->browsing['orderby']:$defaultOrder;
		if ($order !== false) $ordering = $order;
		switch ($ordering) {
			case 'bestselling': $order = "sold DESC,p.name ASC"; break;
			case 'highprice': $order = "maxprice DESC,p.name ASC"; break;
			case 'lowprice': $order = "minprice ASC,p.name ASC"; $useindex = "lowprice"; break;
			case 'newest': $order = "p.publish DESC,p.name ASC"; break;
			case 'oldest': $order = "p.publish ASC,p.name ASC"; $useindex = "oldest";	break;
			case 'random': $order = "RAND(".crc32($Shopp->Shopping->session).")"; break;
			case 'chaos': $order = "RAND(".time().")"; break;
			case 'title': $order = "p.name ASC"; $useindex = "name"; break;
			case 'recommended':
			default:
				// Need to add the catalog table for access to category-product priorities
				if (!isset($this->smart)) {
					$joins[$catalogtable] = "INNER JOIN $catalogtable AS c ON c.product=p.id AND c.parent='$this->id'";
					$order = "c.priority ASC,p.name ASC";
				} else $order = "p.name ASC";
				break;
		}

		// Handle adjacent product navigation
		if ($adjacent && $product) {

			$field = substr($order,0,strpos($order,' '));
			$op = $adjacent != "next"?'<':'>';

			// Flip the sort order for previous
			$c = array('ASC','DESC'); $r = array('__A__','__D__');
			if ($op == '<') $order = str_replace($r,array_reverse($c),str_replace($c,$r,$order));

			switch ($field) {
				case 'sold':
					if ($product->sold() == 0) {
						$field = 'p.name';
						$target = "'".$db->escape($product->name)."'";
					} else $target = $product->sold();
					$where[] = "$field $op $target";
					break;
				case 'highprice':
					if (empty($product->prices)) $product->load_data(array('prices'));
					$target = !empty($product->max['saleprice'])?$product->max['saleprice']:$product->max['price'];
					$where[] = "$target $op IF (pd.sale='on' OR pr.discount>0,pd.saleprice,pd.price) AND p.id != $product->id";
					break;
				case 'lowprice':
					if (empty($product->prices)) $product->load_data(array('prices'));
					$target = !empty($product->max['saleprice'])?$product->max['saleprice']:$product->max['price'];
					$where[] = "$target $op= IF (pd.sale='on' OR pr.discount>0,pd.saleprice,pd.price) AND p.id != $product->id";
					break;
				case 'p.name': $where[] = "$field $op '".$db->escape($product->name)."'"; break;
				default:
					if ($product->priority == 0) {
						$field = 'p.name';
						$target = "'".$db->escape($product->name)."'";
					} else $target = $product->priority;
					$where[] = "$field $op $target";
					break;
			}

		}

		// Handle alphabetic page requests
		if ($Shopp->Category->controls !== false
				&& ('alpha' === $pagination || !is_numeric($this->page))) {

			$this->alphapages(array(
				'useindex' => $useindex,
				'joins' => $joins,
				'where' => $where
			));

			$this->paged = true;
			if (!is_numeric($this->page)) {
				$where[] = $this->page == "0-9" ?
					"1 = (LEFT(p.name,1) REGEXP '[0-9]')":
					"'$this->page' = IF(LEFT(p.name,1) REGEXP '[0-9]',LEFT(p.name,1),LEFT(SOUNDEX(p.name),1))";
			}

		}

		if (!empty($columns)) {
			if (is_string($columns)) {
				if (strpos($columns,',') !== false) $columns = explode(',',$columns);
				else $columns = array($columns);
			}
		} else $columns = array();

		$columns = array_map('trim',$columns);
		array_unshift($columns,'p.*');
		$columns = join(',', $columns);

 		if (!empty($useindex)) $useindex = "FORCE INDEX($useindex)";
		if (!empty($groupby)) $groupby = "GROUP BY $groupby";

		if (!empty($having)) $having = "HAVING ".join(" AND ",$having);
		else $having = '';

		$joins = join(' ',$joins);
		$where = join(' AND ',$where);

		if (empty($limit)) {
			if ($this->pagination > 0 && is_numeric($this->page) && value_is_true($pagination)) {
				if( !$this->pagination || $this->pagination < 0 )
					$this->pagination = $hardlimit;
				$start = ($this->pagination * ($this->page-1));

				$limit = "$start,$this->pagination";
			} else $limit = $hardlimit;
		}

		$query =   "SELECT SQL_CALC_FOUND_ROWS $columns
					FROM $producttable AS p $useindex
					$joins
					WHERE $where
					$groupby $having
					ORDER BY $order
					LIMIT $limit";

		// Execute the main category products query
		$products = $db->query($query,AS_ARRAY);

		$total = $db->query("SELECT FOUND_ROWS() as count");
		$this->total = $total->count;

		if ($this->pagination > 0 && $this->total > $this->pagination) {
			$this->pages = ceil($this->total / $this->pagination);
			if ($this->pages > 1) $this->paged = true;
		}

		$this->pricing['min'] = 0;
		$this->pricing['max'] = 0;

		$prices = array();
		foreach ($products as $i => &$product) {
			$this->pricing['max'] = max($this->pricing['max'],$product->maxprice);
			$this->pricing['min'] = min($this->pricing['min'],$product->minprice);

			$this->products[$product->id] = new Product();
			$this->products[$product->id]->populate($product);

			if (isset($product->score))
				$this->products[$product->id]->score = $product->score;

			// Special property for Bestseller category
			if (isset($product->sold) && $product->sold)
				$this->products[$product->id]->sold = $product->sold;

			// Special property Promotions
			if (isset($product->promos))
				$this->products[$product->id]->promos = $product->promos;

		}

		$this->pricing['average'] = 0;
		if (count($prices) > 0) $this->pricing['average'] = array_sum($prices)/count($prices);

		if (!isset($loading['load'])) $loading['load'] = array('prices');

		if (count($this->products) > 0) {
			$Processing = new Product();
			$Processing->load_data($loading['load'],$this->products);
		}

		$this->loaded = true;

	}

	function alphapages ($loading=array()) {
		$db =& DB::get();

		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$producttable = DatabaseObject::tablename(Product::$table);
		$pricetable = DatabaseObject::tablename(Price::$table);

		$alphanav = range('A','Z');

		$ac =   "SELECT count(*) AS total,
						IF(LEFT(p.name,1) REGEXP '[0-9]',LEFT(p.name,1),LEFT(SOUNDEX(p.name),1)) AS letter,
						AVG((p.maxprice+p.minprice)/2) as avgprice
					FROM $producttable AS p {$loading['useindex']}
					{$loading['joins']}
					WHERE {$loading['where']}
					GROUP BY letter";

		$alpha = $db->query($ac,AS_ARRAY);

		$entry = new stdClass();
		$entry->letter = false;
		$entry->total = $entry->avg = 0;

		$existing = current($alpha);
		if (!isset($this->alpha['0-9'])) {
			$this->alpha['0-9'] = clone $entry;
			$this->alpha['0-9']->letter = '0-9';
		}

		while (is_numeric($existing->letter)) {
			$this->alpha['0-9']->total += $existing->total;
			$this->alpha['0-9']->avg = ($this->alpha['0-9']->avg+$existing->avg)/2;
			$this->alpha['0-9']->letter = '0-9';
			$existing = next($alpha);
		}

		foreach ($alphanav as $letter) {
			if ($existing->letter == $letter) {
				$this->alpha[$letter] = $existing;
				$existing = next($alpha);
			} else {
				$this->alpha[$letter] = clone $entry;
				$this->alpha[$letter]->letter = $letter;
			}
		}

	}

	/**
	 * Returns the product adjacent to the requested product in the category
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param int $next (optional) Which product to get (-1 for previous, defaults to 1 for next)
	 * @return object The Product object
	 **/
	function adjacent_product($next=1) {
		global $Shopp;

		if ($next < 0) $this->loading['adjacent'] = "previous";
		else $this->loading['adjacent'] = "next";

		$this->loading['limit'] = '1';
		$this->loading['product'] = $Shopp->Requested;
		$this->load_products();

		if (!$this->loaded) return false;

		reset($this->products);
		$product = key($this->products);
		return new Product($product);
	}

	/**
	 * Updates the sort order of category image assets
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $ordering List of image ids in order
	 * @return boolean true on success
	 **/
	function save_imageorder ($ordering) {
		$db = DB::get();
		$table = DatabaseObject::tablename(CategoryImage::$table);
		foreach ($ordering as $i => $id)
			$db->query("UPDATE LOW_PRIORITY $table SET sortorder='$i' WHERE (id='$id' AND parent='$this->id' AND context='category' AND type='image')");
		return true;
	}

	/**
	 * Updates the assigned parent id of images to link them to the category
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $images List of image ids
	 * @return boolean true on successful update
	 **/
	function link_images ($images) {
		if (empty($images) || !is_array($images)) return false;

		$db = DB::get();
		$table = DatabaseObject::tablename(CategoryImage::$table);
		$set = "id=".join(' OR id=',$images);
		$query = "UPDATE $table SET parent='$this->id',context='category' WHERE ".$set;
		$db->query($query);

		return true;
	}

	/**
	 * Deletes image assignments to the category and metadata (not the binary data)
	 *
	 * Removes the meta table record that assigns the image to the category and all
	 * cached image metadata built from the original image. Does NOT delete binary
	 * data.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $images List of image ids to delete
	 * @return boolean true on success
	 **/
	function delete_images ($images) {
		$db = &DB::get();
		$imagetable = DatabaseObject::tablename(CategoryImage::$table);
		$imagesets = "";
		foreach ($images as $image) {
			$imagesets .= (!empty($imagesets)?" OR ":"");
			$imagesets .= "((context='category' AND parent='$this->id' AND id='$image') OR (context='image' AND parent='$image'))";
		}
		$db->query("DELETE LOW_PRIORITY FROM $imagetable WHERE type='image' AND ($imagesets)");
		return true;
	}

	/**
	 * Generates an RSS feed of products for this category
	 *
	 * NOTE: To modify the output of the RSS generator, use
	 * the filter hooks provided in a separate plugin or
	 * in the theme functions.php file.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @return string The final RSS markup
	 **/
	function rss () {
		global $Shopp;
		$db = DB::get();

		add_filter('shopp_rss_description','wptexturize');
		add_filter('shopp_rss_description','convert_chars');
		add_filter('shopp_rss_description','make_clickable',9);
		add_filter('shopp_rss_description','force_balance_tags', 25);
		add_filter('shopp_rss_description','convert_smilies',20);
		add_filter('shopp_rss_description','wpautop',30);

		do_action_ref_array('shopp_category_rss',array(&$this));

		if (!$this->products) $this->load_products(array('limit'=>500,'load'=>array('images','prices')));

		$rss = array('title' => get_bloginfo('name')." ".$this->name,
			 			'link' => $this->tag('feed-url'),
					 	'description' => $this->description,
						'sitename' => get_bloginfo('name').' ('.get_bloginfo('url').')',
						'xmlns' => array('shopp'=>'http://shopplugin.net/xmlns',
							'g'=>'http://base.google.com/ns/1.0',
							'atom'=>'http://www.w3.org/2005/Atom',
							'content'=>'http://purl.org/rss/1.0/modules/content/')
						);
		$rss = apply_filters('shopp_rss_meta',$rss);

		$items = array();
		foreach ($this->products as $product) {
			$item = array();
			$item['guid'] = $product->tag('url','return=1');
			$item['title'] = $product->name;
			$item['link'] =  $product->tag('url','return=1');

			// Item Description
			$item['description'] = '';

			$Image = current($product->images);
			if (!empty($Image)) {
				$item['description'] .= '<a href="'.$item['link'].'" title="'.$product->name.'">';
				$item['description'] .= '<img src="'.esc_attr(add_query_string($Image->resizing(96,96,0),shoppurl($Image->id,'images'))).'" alt="'.$product->name.'" width="96" height="96" style="float: left; margin: 0 10px 0 0;" />';
				$item['description'] .= '</a>';
			}

			$pricing = "";
			if ($product->onsale) {
				if ($product->min['saleprice'] != $product->max['saleprice'])
					$pricing .= "from ";
				$pricing .= money($product->min['saleprice']);
			} else {
				if ($product->min['price'] != $product->max['price'])
					$pricing .= "from ";
				$pricing .= money($product->min['price']);
			}
			$item['description'] .= "<p><big><strong>$pricing</strong></big></p>";

			$item['description'] .= $product->description;
			$item['description'] =
			 	'<![CDATA['.apply_filters('shopp_rss_description',($item['description']),$product).']]>';

			// Google Base Namespace
			if ($Image) $item['g:image_link'] = add_query_string($Image->resizing(400,400,0),shoppurl($Image->id,'images'));
			$item['g:condition'] = "new";

			$price = floatvalue($product->onsale?$product->min['saleprice']:$product->min['price']);
			if (!empty($price))	{
				$item['g:price'] = $price;
				$item['g:price_type'] = "starting";
			}

			$item = apply_filters('shopp_rss_item',$item,$product);
			$items[] = $item;
		}
		$rss['items'] = $items;

		return $rss;
	}


	/**
	 * A functional list of support category sort options
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array The list of supported sort methods
	 **/
	function sortoptions () {
		return apply_filters('shopp_category_sortoptions', array(
			"title" => __('Title','Shopp'),
			"custom" => __('Recommended','Shopp'),
			"bestselling" => __('Bestselling','Shopp'),
			"highprice" => __('Price High to Low','Shopp'),
			"lowprice" => __('Price Low to High','Shopp'),
			"newest" => __('Newest to Oldest','Shopp'),
			"oldest" => __('Oldest to Newest','Shopp'),
			"random" => __('Random','Shopp')
		));
	}

	function pagelink ($page) {

		$categoryurl = get_term_link($this->name,$this->taxonomy);
		$alpha = preg_match('/([a-z]|0\-9)/',$page);
		$prettyurl = $categoryurl.($page > 1 || $alpha?"page/$page":"");
		if ($page > 1 || $alpha) $queryvars['paged'] = $page;

		$url = SHOPP_PRETTYURLS?$prettyurl:add_query_args($queryvars,$categoryurl);

		return apply_filters('shopp_paged_link',$url);
	}

} // END class ProductCategory



class __Category extends DatabaseObject {
	static $table = "category";
	var $loaded = false;
	var $paged = false;
	var $children = array();
	var $child = false;
	var $parent = 0;
	var $total = 0;
	var $description = "";
	var $timestamp = false;
	var $thumbnail = false;
	var $products = array();
	var $pricing = array();
	var $filters = array();
	var $loading = array();
	var $images = array();
	var $facetedmenus = "off";
	var $published = true;
	var $taxonomy = false;
	var $depth = false;

	function __construct ($id=false,$key=false) {
		$this->init(self::$table);
		$this->taxonomy = get_catalog_taxonomy_id('category');

		if (!$id) return;
		if ($this->load($id,$key)) return true;
		return false;
	}

	/**
	 * Load a single record by slug name
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param string $slug The slug name to load
	 * @return boolean loaded successfully or not
	 **/
	function loadby_slug ($slug) {
		$db = DB::get();

		$r = $db->query("SELECT * FROM $this->_table WHERE slug='$slug'");
		$this->populate($r);

		if (!empty($this->id)) return true;
		return false;
	}

	/**
	 * Load sub-categories
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $loading Query configuration array
	 * @return boolean successfully loaded or not
	 **/
	function load_children($loading=array()) {
		if (isset($this->smart)
			|| empty($this->id)
			|| empty($this->uri)) return false;

		$db = DB::get();
		$catalog_table = DatabaseObject::tablename(Catalog::$table);

		$defaults = array(
			'columns' => 'cat.*,count(sc.product) as total',
			'joins' => array("LEFT JOIN $catalog_table AS sc ON sc.parent=cat.id AND sc.taxonomy='$this->taxonomy'"),
			'where' => array("cat.uri like '%$this->uri%' AND cat.id <> $this->id"),
			'orderby' => 'name',
			'order' => 'ASC'
		);
		$loading = array_merge($defaults,$loading);
		extract($loading);

		switch(strtolower($orderby)) {
			case 'id': $orderby = "cat.id"; break;
			case 'slug': $orderby = "cat.slug"; break;
			case 'count': $orderby = "total"; break;
			default: $orderby = "cat.name";
		}

		switch(strtoupper($order)) {
			case 'DESC': $order = "DESC"; break;
			default: $order = "ASC";
		}

		$joins = join(' ',$joins);
		$where = join(' AND ',$where);
		$name_order = ($orderby !== "name")?",name ASC":"";

		$query = "SELECT $columns FROM $this->_table AS cat
					$joins
					WHERE $where
					GROUP BY cat.id
					ORDER BY cat.parent DESC,$orderby $order$name_order";
		$children = $db->query($query,AS_ARRAY);

		$children = sort_tree($children,$this->id);
		foreach ($children as &$child) {
			$this->children[$child->id] = new ProductCategory();
			$this->children[$child->id]->populate($child);
			$this->children[$child->id]->depth = $child->depth;
			$this->children[$child->id]->total = $child->total;
		}

		return (!empty($this->children));
	}

	/**
	 * Loads images assigned to this category
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @return boolean Successful load or not
	 **/
	function load_images () {
		$db = DB::get();
		$Settings =& ShoppSettings();

		$ordering = $Settings->get('product_image_order');
		$orderby = $Settings->get('product_image_orderby');

		if ($ordering == "RAND()") $orderby = $ordering;
		else $orderby .= ' '.$ordering;
		$table = DatabaseObject::tablename(CategoryImage::$table);
		if (empty($this->id)) return false;
		$records = $db->query("SELECT * FROM $table WHERE parent=$this->id AND context='category' AND type='image' ORDER BY $orderby",AS_ARRAY);

		foreach ($records as $r) {
			$image = new ProductCategoryImage();
			$image->copydata($r,false,array());
			$image->value = unserialize($image->value);
			$image->expopulate();
			$this->images[] = $image;
		}

		return true;
	}

	/**
	 * Loads a list of products for the category
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.2
	 *
	 * @param array $loading Loading options for the category
	 * @return void
	 **/
	function load_products ($loading=false) {
		global $Shopp;
		$db = DB::get();
		$Storefront =& ShoppStorefront();
		$Settings =& ShoppSettings();

		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$producttable = DatabaseObject::tablename(Product::$table);
		$pricetable = DatabaseObject::tablename(Price::$table);

		$this->paged = false;
		$this->pagination = $Settings->get('catalog_pagination');
		$paged = get_query_var('paged');
		$this->page = ((int)$paged > 0 || !is_numeric($paged))?$paged:1;

		if (empty($this->page)) $this->page = 1;

		// Hard product limit per category to keep resources "reasonable"
		$hardlimit = apply_filters('shopp_category_products_hardlimit',1000);

		$options = array(
			'columns' => false,		// Include extra columns (string) 'c.col1,c.col2…'
			'useindex' => false,	// FORCE INDEX to be used on the product table (string) 'indexname'
			'joins' => array(),		// JOIN tables array('INNER JOIN table AS t ON p.id=t.column')
			'where' => array(),		// WHERE query conditions array('x=y OR x=z','a!=b'…) (array elements are joined by AND
			'groupby' => false,		// GROUP BY column (string) 'column'
			'having' => array(),	// HAVING filters
			'limit' => false,		// Limit
			'order' => false,		// ORDER BY columns or named methods (string)
									// 'bestselling','highprice','lowprice','newest','oldest','random','chaos','title'

			'nostock' => true,		// Override to show products that are out of stock (string) 'on','off','yes','no'…
			'pagination' => true,	// Enable alpha pagination (string) 'alpha'
			'published' => true,	// Load published or unpublished products (string) 'on','off','yes','no'…
			'adjacent' => false,	//
			'product' => false,		//
			'restat' => false		// Force recalculate product stats
		);

		$loading = array_merge($options,$this->loading,$loading);
		extract($loading);

		if (!empty($where) && is_string($where)) $where = array($where);
		if (!empty($joins) && is_string($joins)) $joins = array($joins);

		// Allow override for loading unpublished products
		if (!value_is_true($published)) $this->published = false;

		// Handle default WHERE clause matching this category id
		if (!empty($this->id))
			$joins[$catalogtable] = "INNER JOIN $catalogtable AS c ON p.id=c.product AND parent=$this->id AND taxonomy='$this->taxonomy'";

		if (!value_is_true($nostock) && $Settings->get('outofstock_catalog') == "off")
			$where[] = "((p.inventory='on' AND p.stock > 0) OR p.inventory='off')";

		// Faceted browsing
		if ($Storefront !== false && !empty($Storefront->browsing[$this->slug])) {
			$spectable = DatabaseObject::tablename(Spec::$table);

			$f = 1;
			$filters = array();
			foreach ($Storefront->browsing[$this->slug] as $facet => $value) {
				if (empty($value)) continue;
				$specalias = "spec".($f++);

				// Handle Number Range filtering
				if (!is_array($value) &&
						preg_match('/^.*?(\d+[\.\,\d]*).*?\-.*?(\d+[\.\,\d]*).*$/',$value,$matches)) {

					if ('price' == strtolower($facet)) { // Prices require complex matching on price line entries
						list(,$min,$max) = array_map('floatvalue',$matches);

						if ($min > 0) $filters[] = "((sale='on' AND (minprice >= $min OR maxprice >= $min))
													OR (sale='on' AND (minprice >= $min OR maxprice >= $min)))";
						if ($max > 0) $filters[] = "((sale='on' AND (minprice <= $max OR maxprice <= $max))
													OR (sale='on' AND (minprice <= $max OR maxprice <= $max)))";

						// Use HAVING clause for filtering by pricing information because of data aggregation
						// $having[] = $match;
						// continue;

					} else { // Spec-based numbers are somewhat more straightforward
						list(,$min,$max) = $matches;
						if ($min > 0) $filters[] = "$specalias.numeral >= $min";
						if ($max > 0) $filters[] = "$specalias.numeral <= $max";
					}
				} else $filters[] = "$specalias.value='$value'"; // No range, direct value match

				$joins[$specalias] = "LEFT JOIN $spectable AS $specalias
										ON $specalias.parent=p.id
										AND $specalias.context='product'
										AND $specalias.type='spec'
										AND $specalias.name='".$db->escape($facet)."'";
			}
			$where[] = join(' AND ',$filters);

		}

		// WP TZ setting based time - (timezone offset:[PHP UTC adjusted time - MySQL UTC adjusted time])
		$now = time()."-(".(time()-date("Z",time()))."-UNIX_TIMESTAMP(UTC_TIMESTAMP()))";

		if ($this->published) $where[] = "(p.status='publish' AND $now >= UNIX_TIMESTAMP(p.publish))";
		else $where[] = "(p.status!='publish' OR $now < UNIX_TIMESTAMP(p.publish))";

		$defaultOrder = $Settings->get('default_product_order');
		if (empty($defaultOrder)) $defaultOrder = '';
		$ordering = isset($Storefront->browsing['orderby'])?
						$Storefront->browsing['orderby']:$defaultOrder;
		if ($order !== false) $ordering = $order;
		switch ($ordering) {
			case 'bestselling': $order = "sold DESC,p.name ASC"; break;
			case 'highprice': $order = "maxprice DESC,p.name ASC"; break;
			case 'lowprice': $order = "minprice ASC,p.name ASC"; $useindex = "lowprice"; break;
			case 'newest': $order = "p.publish DESC,p.name ASC"; break;
			case 'oldest': $order = "p.publish ASC,p.name ASC"; $useindex = "oldest";	break;
			case 'random': $order = "RAND(".crc32($Shopp->Shopping->session).")"; break;
			case 'chaos': $order = "RAND(".time().")"; break;
			case 'title': $order = "p.name ASC"; $useindex = "name"; break;
			case 'recommended':
			default:
				// Need to add the catalog table for access to category-product priorities
				if (!isset($this->smart)) {
					$joins[$catalogtable] = "INNER JOIN $catalogtable AS c ON c.product=p.id AND c.parent='$this->id'";
					$order = "c.priority ASC,p.name ASC";
				} else $order = "p.name ASC";
				break;
		}

		// Handle adjacent product navigation
		if ($adjacent && $product) {

			$field = substr($order,0,strpos($order,' '));
			$op = $adjacent != "next"?'<':'>';

			// Flip the sort order for previous
			$c = array('ASC','DESC'); $r = array('__A__','__D__');
			if ($op == '<') $order = str_replace($r,array_reverse($c),str_replace($c,$r,$order));

			switch ($field) {
				case 'sold':
					if ($product->sold() == 0) {
						$field = 'p.name';
						$target = "'".$db->escape($product->name)."'";
					} else $target = $product->sold();
					$where[] = "$field $op $target";
					break;
				case 'highprice':
					if (empty($product->prices)) $product->load_data(array('prices'));
					$target = !empty($product->max['saleprice'])?$product->max['saleprice']:$product->max['price'];
					$where[] = "$target $op IF (pd.sale='on' OR pr.discount>0,pd.saleprice,pd.price) AND p.id != $product->id";
					break;
				case 'lowprice':
					if (empty($product->prices)) $product->load_data(array('prices'));
					$target = !empty($product->max['saleprice'])?$product->max['saleprice']:$product->max['price'];
					$where[] = "$target $op= IF (pd.sale='on' OR pr.discount>0,pd.saleprice,pd.price) AND p.id != $product->id";
					break;
				case 'p.name': $where[] = "$field $op '".$db->escape($product->name)."'"; break;
				default:
					if ($product->priority == 0) {
						$field = 'p.name';
						$target = "'".$db->escape($product->name)."'";
					} else $target = $product->priority;
					$where[] = "$field $op $target";
					break;
			}

		}

		// Handle alphabetic page requests
		if ($Shopp->Category->controls !== false
				&& ('alpha' === $pagination || !is_numeric($this->page))) {

			$this->alphapages(array(
				'useindex' => $useindex,
				'joins' => $joins,
				'where' => $where
			));

			$this->paged = true;
			if (!is_numeric($this->page)) {
				$where[] = $this->page == "0-9" ?
					"1 = (LEFT(p.name,1) REGEXP '[0-9]')":
					"'$this->page' = IF(LEFT(p.name,1) REGEXP '[0-9]',LEFT(p.name,1),LEFT(SOUNDEX(p.name),1))";
			}

		}

		if (!empty($columns)) {
			if (is_string($columns)) {
				if (strpos($columns,',') !== false) $columns = explode(',',$columns);
				else $columns = array($columns);
			}
		} else $columns = array();

		$columns = array_map('trim',$columns);
		array_unshift($columns,'p.*');
		$columns = join(',', $columns);

 		if (!empty($useindex)) $useindex = "FORCE INDEX($useindex)";
		if (!empty($groupby)) $groupby = "GROUP BY $groupby";

		if (!empty($having)) $having = "HAVING ".join(" AND ",$having);
		else $having = '';

		$joins = join(' ',$joins);
		$where = join(' AND ',$where);

		if (empty($limit)) {
			if ($this->pagination > 0 && is_numeric($this->page) && value_is_true($pagination)) {
				if( !$this->pagination || $this->pagination < 0 )
					$this->pagination = $hardlimit;
				$start = ($this->pagination * ($this->page-1));

				$limit = "$start,$this->pagination";
			} else $limit = $hardlimit;
		}

		$query =   "SELECT SQL_CALC_FOUND_ROWS $columns
					FROM $producttable AS p $useindex
					$joins
					WHERE $where
					$groupby $having
					ORDER BY $order
					LIMIT $limit";

		// Execute the main category products query
		$products = $db->query($query,AS_ARRAY);

		$total = $db->query("SELECT FOUND_ROWS() as count");
		$this->total = $total->count;

		if ($this->pagination > 0 && $this->total > $this->pagination) {
			$this->pages = ceil($this->total / $this->pagination);
			if ($this->pages > 1) $this->paged = true;
		}

		$this->pricing['min'] = 0;
		$this->pricing['max'] = 0;

		$prices = array();
		foreach ($products as $i => &$product) {
			$this->pricing['max'] = max($this->pricing['max'],$product->maxprice);
			$this->pricing['min'] = min($this->pricing['min'],$product->minprice);

			$this->products[$product->id] = new Product();
			$this->products[$product->id]->populate($product);

			if (isset($product->score))
				$this->products[$product->id]->score = $product->score;

			// Special property for Bestseller category
			if (isset($product->sold) && $product->sold)
				$this->products[$product->id]->sold = $product->sold;

			// Special property Promotions
			if (isset($product->promos))
				$this->products[$product->id]->promos = $product->promos;

		}

		$this->pricing['average'] = 0;
		if (count($prices) > 0) $this->pricing['average'] = array_sum($prices)/count($prices);

		if (!isset($loading['load'])) $loading['load'] = array('prices');

		if (count($this->products) > 0) {
			$Processing = new Product();
			$Processing->load_data($loading['load'],$this->products);
		}

		$this->loaded = true;

	}

	function alphapages ($loading=array()) {
		$db =& DB::get();

		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$producttable = DatabaseObject::tablename(Product::$table);
		$pricetable = DatabaseObject::tablename(Price::$table);

		$alphanav = range('A','Z');

		$ac =   "SELECT count(*) AS total,
						IF(LEFT(p.name,1) REGEXP '[0-9]',LEFT(p.name,1),LEFT(SOUNDEX(p.name),1)) AS letter,
						AVG((p.maxprice+p.minprice)/2) as avgprice
					FROM $producttable AS p {$loading['useindex']}
					{$loading['joins']}
					WHERE {$loading['where']}
					GROUP BY letter";

		$alpha = $db->query($ac,AS_ARRAY);

		$entry = new stdClass();
		$entry->letter = false;
		$entry->total = $entry->avg = 0;

		$existing = current($alpha);
		if (!isset($this->alpha['0-9'])) {
			$this->alpha['0-9'] = clone $entry;
			$this->alpha['0-9']->letter = '0-9';
		}

		while (is_numeric($existing->letter)) {
			$this->alpha['0-9']->total += $existing->total;
			$this->alpha['0-9']->avg = ($this->alpha['0-9']->avg+$existing->avg)/2;
			$this->alpha['0-9']->letter = '0-9';
			$existing = next($alpha);
		}

		foreach ($alphanav as $letter) {
			if ($existing->letter == $letter) {
				$this->alpha[$letter] = $existing;
				$existing = next($alpha);
			} else {
				$this->alpha[$letter] = clone $entry;
				$this->alpha[$letter]->letter = $letter;
			}
		}

	}

	/**
	 * Returns the product adjacent to the requested product in the category
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param int $next (optional) Which product to get (-1 for previous, defaults to 1 for next)
	 * @return object The Product object
	 **/
	function adjacent_product($next=1) {
		global $Shopp;

		if ($next < 0) $this->loading['adjacent'] = "previous";
		else $this->loading['adjacent'] = "next";

		$this->loading['limit'] = '1';
		$this->loading['product'] = $Shopp->Requested;
		$this->load_products();

		if (!$this->loaded) return false;

		reset($this->products);
		$product = key($this->products);
		return new Product($product);
	}

	/**
	 * Updates category slug and rebuilds changed URIs
	 *
	 * Generates the slug if empty. Checks for duplicate slugs
	 * and adds a numeric suffix to ensure a unique slug.
	 *
	 * If the slug changes, the category uri is rebuilt and
	 * and all descendant category uri's are rebuilt and updated.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean successfully updated
	 **/
	function update_slug () {
		$db = DB::get();

		if (empty($this->slug)) {
			$name = !empty($_POST['name'])?$_POST['name']:$this->name;
			$this->slug = sanitize_title_with_dashes($name);
		}

		if (empty($this->slug)) return false; // No slug for this category, bail

		$uri = $this->uri;
		$parent = !empty($_POST['parent'])?$_POST['parent']:$this->parent;
		if ($parent > 0) {

			$Catalog = new Catalog();
			$Catalog->load_categories(array(
				'columns' => "cat.id,cat.parent,cat.name,cat.description,cat.uri,cat.slug",
				'where' => array(),
				'joins' => array(),
				'orderby' => false,
				'order' => false,
				'outofstock' => true
			));

			$paths = array();
			if (!empty($this->slug)) $paths = array($this->slug);  // Include self

			$parentkey = -1;
			// If we're saving a new category, lookup the parent
			if ($parent > 0) {
				array_unshift($paths,$Catalog->categories['_'.$parent]->slug);
				$parentkey = $Catalog->categories['_'.$parent]->parent;
			}

			while (isset($Catalog->categories['_'.$parentkey])
					&& $category_tree = $Catalog->categories['_'.$parentkey]) {
				array_unshift($paths,$category_tree->slug);
				$parentkey = '_'.$category_tree->parent;
			}
			if (count($paths) > 1) $this->uri = join("/",$paths);
			else $this->uri = $paths[0];
		} else $this->uri = $this->slug; // end if ($parent > 0)

		// Check for an existing category uri
		$exclude_category = !empty($this->id)?"AND id != $this->id":"";
		$existing = $db->query("SELECT uri FROM $this->_table WHERE uri='$this->uri' $exclude_category LIMIT 1");
		if ($existing) {
			$suffix = 2;
			while($existing) {
				$altslug = preg_replace('/\-\d+$/','',$this->slug)."-".$suffix++;
				$uris = explode('/',$this->uri);
				array_splice($uris,-1,1,$altslug);
				$alturi = join('/',$uris);
				$existing = $db->query("SELECT uri FROM $this->_table WHERE uri='$alturi' $exclude_category LIMIT 1");
			}
			$this->slug = $altslug;
			$this->uri = $alturi;
		}

		if ($uri == $this->uri) return true;

		// Update children uris
		$this->load_children(array(
			'columns' 	=> 'cat.id,cat.parent,cat.uri',
			'where' 	=> array("(cat.uri like '%$uri%' OR cat.parent='$this->id')","cat.id <> '$this->id'")
		));
		if (empty($this->children)) return true;

		$categoryuri = explode('/',$this->uri);
		foreach ($this->children as $child) {
			$childuri = explode('/',$child->uri);
			$changed = reset(array_diff($childuri,$categoryuri));
			array_splice($childuri,array_search($changed,$childuri),1,end($categoryuri));
			$updateduri = join('/',$childuri);
			$db->query("UPDATE $this->_table SET uri='$updateduri' WHERE id='$child->id' LIMIT 1");
		}

	}

	/**
	 * Updates the sort order of category image assets
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $ordering List of image ids in order
	 * @return boolean true on success
	 **/
	function save_imageorder ($ordering) {
		$db = DB::get();
		$table = DatabaseObject::tablename(CategoryImage::$table);
		foreach ($ordering as $i => $id)
			$db->query("UPDATE LOW_PRIORITY $table SET sortorder='$i' WHERE (id='$id' AND parent='$this->id' AND context='category' AND type='image')");
		return true;
	}

	/**
	 * Updates the assigned parent id of images to link them to the category
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $images List of image ids
	 * @return boolean true on successful update
	 **/
	function link_images ($images) {
		if (empty($images) || !is_array($images)) return false;

		$db = DB::get();
		$table = DatabaseObject::tablename(CategoryImage::$table);
		$set = "id=".join(' OR id=',$images);
		$query = "UPDATE $table SET parent='$this->id',context='category' WHERE ".$set;
		$db->query($query);

		return true;
	}

	/**
	 * Deletes image assignments to the category and metadata (not the binary data)
	 *
	 * Removes the meta table record that assigns the image to the category and all
	 * cached image metadata built from the original image. Does NOT delete binary
	 * data.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param array $images List of image ids to delete
	 * @return boolean true on success
	 **/
	function delete_images ($images) {
		$db = &DB::get();
		$imagetable = DatabaseObject::tablename(CategoryImage::$table);
		$imagesets = "";
		foreach ($images as $image) {
			$imagesets .= (!empty($imagesets)?" OR ":"");
			$imagesets .= "((context='category' AND parent='$this->id' AND id='$image') OR (context='image' AND parent='$image'))";
		}
		$db->query("DELETE LOW_PRIORITY FROM $imagetable WHERE type='image' AND ($imagesets)");
		return true;
	}

	/**
	 * Generates an RSS feed of products for this category
	 *
	 * NOTE: To modify the output of the RSS generator, use
	 * the filter hooks provided in a separate plugin or
	 * in the theme functions.php file.
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 *
	 * @return string The final RSS markup
	 **/
	function rss () {
		global $Shopp;
		$db = DB::get();

		add_filter('shopp_rss_description','wptexturize');
		add_filter('shopp_rss_description','convert_chars');
		add_filter('shopp_rss_description','make_clickable',9);
		add_filter('shopp_rss_description','force_balance_tags', 25);
		add_filter('shopp_rss_description','convert_smilies',20);
		add_filter('shopp_rss_description','wpautop',30);

		do_action_ref_array('shopp_category_rss',array(&$this));

		if (!$this->products) $this->load_products(array('limit'=>500,'load'=>array('images','prices')));

		$rss = array('title' => get_bloginfo('name')." ".$this->name,
			 			'link' => $this->tag('feed-url'),
					 	'description' => $this->description,
						'sitename' => get_bloginfo('name').' ('.get_bloginfo('url').')',
						'xmlns' => array('shopp'=>'http://shopplugin.net/xmlns',
							'g'=>'http://base.google.com/ns/1.0',
							'atom'=>'http://www.w3.org/2005/Atom',
							'content'=>'http://purl.org/rss/1.0/modules/content/')
						);
		$rss = apply_filters('shopp_rss_meta',$rss);

		$items = array();
		foreach ($this->products as $product) {
			$item = array();
			$item['guid'] = $product->tag('url','return=1');
			$item['title'] = $product->name;
			$item['link'] =  $product->tag('url','return=1');

			// Item Description
			$item['description'] = '';

			$Image = current($product->images);
			if (!empty($Image)) {
				$item['description'] .= '<a href="'.$item['link'].'" title="'.$product->name.'">';
				$item['description'] .= '<img src="'.esc_attr(add_query_string($Image->resizing(96,96,0),shoppurl($Image->id,'images'))).'" alt="'.$product->name.'" width="96" height="96" style="float: left; margin: 0 10px 0 0;" />';
				$item['description'] .= '</a>';
			}

			$pricing = "";
			if ($product->onsale) {
				if ($product->min['saleprice'] != $product->max['saleprice'])
					$pricing .= "from ";
				$pricing .= money($product->min['saleprice']);
			} else {
				if ($product->min['price'] != $product->max['price'])
					$pricing .= "from ";
				$pricing .= money($product->min['price']);
			}
			$item['description'] .= "<p><big><strong>$pricing</strong></big></p>";

			$item['description'] .= $product->description;
			$item['description'] =
			 	'<![CDATA['.apply_filters('shopp_rss_description',($item['description']),$product).']]>';

			// Google Base Namespace
			if ($Image) $item['g:image_link'] = add_query_string($Image->resizing(400,400,0),shoppurl($Image->id,'images'));
			$item['g:condition'] = "new";

			$price = floatvalue($product->onsale?$product->min['saleprice']:$product->min['price']);
			if (!empty($price))	{
				$item['g:price'] = $price;
				$item['g:price_type'] = "starting";
			}

			$item = apply_filters('shopp_rss_item',$item,$product);
			$items[] = $item;
		}
		$rss['items'] = $items;

		return $rss;
	}


	/**
	 * A functional list of support category sort options
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return array The list of supported sort methods
	 **/
	function sortoptions () {
		return apply_filters('shopp_category_sortoptions', array(
			"title" => __('Title','Shopp'),
			"custom" => __('Recommended','Shopp'),
			"bestselling" => __('Bestselling','Shopp'),
			"highprice" => __('Price High to Low','Shopp'),
			"lowprice" => __('Price Low to High','Shopp'),
			"newest" => __('Newest to Oldest','Shopp'),
			"oldest" => __('Oldest to Newest','Shopp'),
			"random" => __('Random','Shopp')
		));
	}

	function pagelink ($page) {
		$type = isset($this->tag)?'tag':'category';
		$alpha = preg_match('/\w/',$page);
		$prettyurl = "$type/$this->uri".($page > 1 || $alpha?"/page/$page":"");
		$queryvars = array("shopp_$type"=>$this->uri);
		if ($page > 1 || $alpha) $queryvars['paged'] = $page;

		return apply_filters('shopp_paged_link',shoppurl(SHOPP_PRETTYURLS?$prettyurl:$queryvars));
	}

	/**
	 * shopp('category','...') tags
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.1
	 * @see http://docs.shopplugin.net/Category_Tags
	 *
	 * @param string $property The property to handle
	 * @param array $options (optional) The tag options to process
	 * @return mixed
	 **/
	function tag ($property,$options=array()) {
		global $Shopp;
		$db = DB::get();

		switch ($property) {
			case 'link':
			case 'url':
				return get_term_link($this->name,$this->taxonomy);
				break;
			case 'feed-url':
			case 'feedurl':
				$uri = 'category/'.$this->uri;
				if ($this->slug == "tag") $uri = $this->slug.'/'.$this->tag;
				return shoppurl(SHOPP_PRETTYURLS?"$uri/feed":array('s_cat'=>urldecode($this->uri),'src'=>'category_rss'));
			case 'id': return $this->id; break;
			case 'parent': return $this->parent; break;
			case 'name': return $this->name; break;
			case 'slug': return urldecode($this->slug); break;
			case 'description': return wpautop($this->description); break;
			case 'total': return $this->loaded?$this->total:false; break;
			case 'has-products':
			case 'loadproducts':
			case 'load-products':
			case 'hasproducts':
				if (empty($this->id) && empty($this->slug)) return false;
				if (isset($options['load'])) {
					$dataset = explode(",",$options['load']);
					$options['load'] = array();
					foreach ($dataset as $name) $options['load'][] = trim($name);
				 } else {
					$options['load'] = array('prices');
				}
				if (!$this->loaded) $this->load_products($options);
				if (count($this->products) > 0) return true; else return false; break;
			case 'products':
				if (!isset($this->_product_loop)) {
					reset($this->products);
					$Shopp->Product = current($this->products);
					$this->_pindex = 0;
					$this->_rindex = false;
					$this->_product_loop = true;
				} else {
					$Shopp->Product = next($this->products);
					$this->_pindex++;
				}

				if (current($this->products) !== false) return true;
				else {
					unset($this->_product_loop);
					$this->_pindex = 0;
					return false;
				}
				break;
			case 'row':
				if (!isset($this->_rindex) || $this->_rindex === false) $this->_rindex = 0;
				else $this->_rindex++;
				if (empty($options['products'])) $options['products'] = $Shopp->Settings->get('row_products');
				if (isset($this->_rindex) && $this->_rindex > 0 && $this->_rindex % $options['products'] == 0) return true;
				else return false;
				break;
			case 'has-categories':
			case 'hascategories':
				if (empty($this->children)) $this->load_children();
				return (!empty($this->children));
				break;
			case 'is-subcategory':
			case 'issubcategory':
				return ($this->parent != 0);
				break;
			case 'subcategories':
				if (!isset($this->_children_loop)) {
					reset($this->children);
					$this->child = current($this->children);
					$this->_cindex = 0;
					$this->_children_loop = true;
				} else {
					$this->child = next($this->children);
					$this->_cindex++;
				}

				if ($this->child !== false) return true;
				else {
					unset($this->_children_loop);
					$this->_cindex = 0;
					$this->child = false;
					return false;
				}
				break;
			case 'subcategory-list':
				if (isset($Shopp->Category->controls)) return false;

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

				if (!$this->children) $this->load_children(array('orderby'=>$orderby,'order'=>$order));
				if (empty($this->children)) return false;

				$string = "";
				$depthlimit = $depth;
				$depth = 0;
				$exclude = explode(",",$exclude);
				$classes = ' class="shopp_categories'.(empty($class)?'':' '.$class).'"';
				$wraplist = value_is_true($wraplist);

				if (value_is_true($dropdown)) {
					$count = 0;
					$string .= $title;
					$string .= '<select name="shopp_cats" id="shopp-'.$this->slug.'-subcategories-menu" class="shopp-categories-menu">';
					$string .= '<option value="">'.__('Select a sub-category&hellip;','Shopp').'</option>';
					foreach ($this->children as &$category) {
						if (!empty($show) && $count+1 > $show) break;
						if (value_is_true($hierarchy) && $depthlimit && $category->depth >= $depthlimit) continue;
						if ($category->products == 0) continue; // Only show categories with products
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = '/'.$parent->slug;
						}
						$padding = str_repeat("&nbsp;",$category->depth*3);

						$category_uri = empty($category->id)?$category->uri:$category->id;
						$link = SHOPP_PRETTYURLS?shoppurl("category/$category->uri"):shoppurl(array('s_cat'=>$category_uri));

						$total = '';
						if (value_is_true($products)) $total = '&nbsp;&nbsp;('.$category->products.')';

						$string .= '<option value="'.htmlentities($link).'">'.$padding.$category->name.$total.'</option>';
						$previous = &$category;
						$depth = $category->depth;
						$count++;
					}
					$string .= '</select>';
				} else {
					if (!empty($class)) $classes = ' class="'.$class.'"';
					$string .= $title.'<ul'.$classes.'>';
					$count = 0;
					foreach ($this->children as &$category) {
						if (!isset($category->total)) $category->total = 0;
						if (!isset($category->depth)) $category->depth = 0;
						if (!empty($category->id) && in_array($category->id,$exclude)) continue; // Skip excluded categories
						if ($depthlimit && $category->depth >= $depthlimit) continue;
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = $parent->slug;
							$string = substr($string,0,-5); // Remove the previous </li>
							$active = '';

							if (isset($Shopp->Category) && !empty($parent->slug)
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
						$link = SHOPP_PRETTYURLS?
							shoppurl("category/$category->uri"):
							shoppurl(array('s_cat'=>$category_uri));

						$total = '';
						if (value_is_true($products) && $category->total > 0) $total = ' <span>('.$category->total.')</span>';

						$current = '';
						if (isset($Shopp->Category) && $Shopp->Category->slug == $category->slug)
							$current = ' class="current"';

						$listing = '';
						if ($category->total > 0 || isset($category->smart) || $linkall)
							$listing = '<a href="'.$link.'"'.$current.'>'.$category->name.($linkcount?$total:'').'</a>'.(!$linkcount?$total:'');
						else $listing = $category->name;

						if (value_is_true($showall) ||
							$category->total > 0 ||
							isset($category->smart) ||
							$category->children)
							$string .= '<li'.$current.'>'.$listing.'</li>';

						$previous = &$category;
						$depth = $category->depth;
						$count++;
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
			case 'section-list':
				if (empty($this->id)) return false;
				if (isset($Shopp->Category->controls)) return false;
				if (empty($Shopp->Catalog->categories))
					$Shopp->Catalog->load_categories(array("where"=>"(pd.status='publish' OR pd.id IS NULL)"));
				if (empty($Shopp->Catalog->categories)) return false;
				if (!$this->children) $this->load_children();

				$defaults = array(
					'title' => '',
					'before' => '',
					'after' => '',
					'class' => '',
					'classes' => '',
					'exclude' => '',
					'total' => '',
					'current' => '',
					'listing' => '',
					'depth' => 0,
					'parent' => false,
					'showall' => false,
					'linkall' => false,
					'dropdown' => false,
					'hierarchy' => false,
					'products' => false,
					'wraplist' => true
					);

				$options = array_merge($defaults,$options);
				extract($options, EXTR_SKIP);

				$string = "";
				$depthlimit = $depth;
				$depth = 0;
				$wraplist = value_is_true($wraplist);
				$exclude = explode(",",$exclude);
				$section = array();

				// Identify root parent
				if (empty($this->id)) return false;
				$parent = '_'.$this->id;
				while($parent != 0) {
					if (!isset($Shopp->Catalog->categories[$parent])) break;
					if ($Shopp->Catalog->categories[$parent]->parent == 0
						|| $Shopp->Catalog->categories[$parent]->parent == $parent) break;
					$parent = '_'.$Shopp->Catalog->categories[$parent]->parent;
				}
				$root = $Shopp->Catalog->categories[$parent];
				if ($this->id == $parent && empty($this->children)) return false;

				// Build the section
				$section[] = $root;
				$in = false;
				foreach ($Shopp->Catalog->categories as &$c) {
					if ($in && $c->depth == $root->depth) break; // Done
					if ($in) $section[] = $c;
					if (!$in && isset($c->id) && $c->id == $root->id) $in = true;
				}

				if (value_is_true($dropdown)) {
					$string .= $title;
					$string .= '<select name="shopp_cats" id="shopp-'.$this->slug.'-subcategories-menu" class="shopp-categories-menu">';
					$string .= '<option value="">'.__('Select a sub-category&hellip;','Shopp').'</option>';
					foreach ($section as &$category) {
						if (value_is_true($hierarchy) && $depthlimit && $category->depth >= $depthlimit) continue;
						if (in_array($category->id,$exclude)) continue; // Skip excluded categories
						if ($category->products == 0) continue; // Only show categories with products
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path)) $parent->path = '/'.$parent->slug;
						}
						$padding = str_repeat("&nbsp;",$category->depth*3);

						$category_uri = empty($category->id)?$category->uri:$category->id;
						$link = SHOPP_PRETTYURLS?shoppurl("category/$category->uri"):shoppurl(array('s_cat'=>$category_uri));

						$total = '';
						if (value_is_true($products)) $total = '&nbsp;&nbsp;('.$category->total.')';

						$string .= '<option value="'.htmlentities($link).'">'.$padding.$category->name.$total.'</option>';
						$previous = &$category;
						$depth = $category->depth;

					}
					$string .= '</select>';
				} else {
					if (!empty($class)) $classes = ' class="'.$class.'"';
					$string .= $title;
					if ($wraplist) $string .= '<ul'.$classes.'>';
					foreach ($section as &$category) {
						if (in_array($category->id,$exclude)) continue; // Skip excluded categories
						if (value_is_true($hierarchy) && $depthlimit &&
							$category->depth >= $depthlimit) continue;
						if (value_is_true($hierarchy) && $category->depth > $depth) {
							$parent = &$previous;
							if (!isset($parent->path) && isset($parent->slug)) $parent->path = $parent->slug;
							$string = substr($string,0,-5);
							$string .= '<ul class="children">';
						}
						if (value_is_true($hierarchy) && $category->depth < $depth) $string .= '</ul></li>';

						$category_uri = empty($category->id)?$category->uri:$category->id;
						$link = SHOPP_PRETTYURLS?shoppurl("category/$category->uri"):shoppurl(array('s_cat'=>$category_uri));

						if (value_is_true($products)) $total = ' <span>('.$category->total.')</span>';

						if ($category->total > 0 || isset($category->smart) || $linkall) $listing = '<a href="'.$link.'"'.$current.'>'.$category->name.$total.'</a>';
						else $listing = $category->name;

						if (value_is_true($showall) ||
							$category->total > 0 ||
							$category->children)
							$string .= '<li>'.$listing.'</li>';

						$previous = &$category;
						$depth = $category->depth;
					}
					if (value_is_true($hierarchy) && $depth > 0)
						for ($i = $depth; $i > 0; $i--) $string .= '</ul></li>';

					if ($wraplist) $string .= '</ul>';
				}
				return $string;
				break;
			case 'pagination':
				if (!$this->paged) return "";

				$defaults = array(
					'label' => __("Pages:","Shopp"),
					'next' => __("next","Shopp"),
					'previous' => __("previous","Shopp"),
					'jumpback' => '&laquo;',
					'jumpfwd' => '&raquo;',
					'show' => 1000,
					'before' => '<div>',
					'after' => '</div>'
				);
				$options = array_merge($defaults,$options);
				extract($options);

				$_ = array();
				if (isset($this->alpha) && $this->paged) {
					$_[] = $before.$label;
					$_[] = '<ul class="paging">';
					foreach ($this->alpha as $alpha) {
						$link = $this->pagelink($alpha->letter);
						if ($alpha->total > 0)
							$_[] = '<li><a href="'.$link.'">'.$alpha->letter.'</a></li>';
						else $_[] = '<li><span>'.$alpha->letter.'</span></li>';
					}
					$_[] = '</ul>';
					$_[] = $after;
					return join("\n",$_);
				}

				if ($this->pages > 1) {

					if ( $this->pages > $show ) $visible_pages = $show + 1;
					else $visible_pages = $this->pages + 1;
					$jumps = ceil($visible_pages/2);
					$_[] = $before.$label;

					$_[] = '<ul class="paging">';
					if ( $this->page <= floor(($show) / 2) ) {
						$i = 1;
					} else {
						$i = $this->page - floor(($show) / 2);
						$visible_pages = $this->page + floor(($show) / 2) + 1;
						if ($visible_pages > $this->pages) $visible_pages = $this->pages + 1;
						if ($i > 1) {
							$link = $this->pagelink(1);
							$_[] = '<li><a href="'.$link.'">1</a></li>';

							$pagenum = ($this->page - $jumps);
							if ($pagenum < 1) $pagenum = 1;
							$link = $this->pagelink($pagenum);
							$_[] = '<li><a href="'.$link.'">'.$jumpback.'</a></li>';
						}
					}

					// Add previous button
					if (!empty($previous) && $this->page > 1) {
						$prev = $this->page-1;
						$link = $this->pagelink($prev);
						$_[] = '<li class="previous"><a href="'.$link.'">'.$previous.'</a></li>';
					} else $_[] = '<li class="previous disabled">'.$previous.'</li>';
					// end previous button

					while ($i < $visible_pages) {
						$link = $this->pagelink($i);
						if ( $i == $this->page ) $_[] = '<li class="active">'.$i.'</li>';
						else $_[] = '<li><a href="'.$link.'">'.$i.'</a></li>';
						$i++;
					}
					if ($this->pages > $visible_pages) {
						$pagenum = ($this->page + $jumps);
						if ($pagenum > $this->pages) $pagenum = $this->pages;
						$link = $this->pagelink($pagenum);
						$_[] = '<li><a href="'.$link.'">'.$jumpfwd.'</a></li>';
						$link = $this->pagelink($this->pages);
						$_[] = '<li><a href="'.$link.'">'.$this->pages.'</a></li>';
					}

					// Add next button
					if (!empty($next) && $this->page < $this->pages) {
						$pagenum = $this->page+1;
						$link = $this->pagelink($pagenum);
						$_[] = '<li class="next"><a href="'.$link.'">'.$next.'</a></li>';
					} else $_[] = '<li class="next disabled">'.$next.'</li>';

					$_[] = '</ul>';
					$_[] = $after;
				}
				return join("\n",$_);
				break;

			case 'has-faceted-menu': return ($this->facetedmenus == "on"); break;
			case 'faceted-menu':
				if ($this->facetedmenus == "off") return;
				$output = "";
				$CategoryFilters =& $Shopp->Flow->Controller->browsing[$this->slug];
				$link = $_SERVER['REQUEST_URI'];
				if (!isset($options['cancel'])) $options['cancel'] = "X";
				if (strpos($_SERVER['REQUEST_URI'],"?") !== false)
					list($link,$query) = explode("?",$_SERVER['REQUEST_URI']);
				$query = $_GET;
				$query = http_build_query($query);
				$link = esc_url($link).'?'.$query;

				$list = "";
				if (is_array($CategoryFilters)) {
					foreach($CategoryFilters AS $facet => $filter) {
						$href = add_query_arg('shopp_catfilters['.urlencode($facet).']','',$link);
						if (preg_match('/^(.*?(\d+[\.\,\d]*).*?)\-(.*?(\d+[\.\,\d]*).*)$/',$filter,$matches)) {
							$label = $matches[1].' &mdash; '.$matches[3];
							if ($matches[2] == 0) $label = __('Under ','Shopp').$matches[3];
							if ($matches[4] == 0) $label = $matches[1].' '.__('and up','Shopp');
						} else $label = $filter;
						if (!empty($filter)) $list .= '<li><strong>'.$facet.'</strong>: '.stripslashes($label).' <a href="'.$href.'=" class="cancel">'.$options['cancel'].'</a></li>';
					}
					$output .= '<ul class="filters enabled">'.$list.'</ul>';
				}

				if ($this->pricerange == "auto" && empty($CategoryFilters['Price'])) {
					// if (!$this->loaded) $this->load_products();
					$list = "";
					$this->priceranges = auto_ranges($this->pricing['average'],$this->pricing['max'],$this->pricing['min']);
					foreach ($this->priceranges as $range) {
						$href = add_query_arg('shopp_catfilters[Price]',urlencode(money($range['min']).'-'.money($range['max'])),$link);
						$label = money($range['min']).' &mdash; '.money($range['max']-0.01);
						if ($range['min'] == 0) $label = __('Under ','Shopp').money($range['max']);
						elseif ($range['max'] == 0) $label = money($range['min']).' '.__('and up','Shopp');
						$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
					}
					if (!empty($this->priceranges)) $output .= '<h4>'.__('Price Range','Shopp').'</h4>';
					$output .= '<ul>'.$list.'</ul>';
				}

				$catalogtable = DatabaseObject::tablename(Catalog::$table);
				$producttable = DatabaseObject::tablename(Product::$table);
				$spectable = DatabaseObject::tablename(Spec::$table);

				$query = "SELECT spec.name,spec.value,
					IF(spec.numeral > 0,spec.name,spec.value) AS merge,
					count(*) AS total,avg(numeral) AS avg,max(numeral) AS max,min(numeral) AS min
					FROM $catalogtable AS cat
					LEFT JOIN $producttable AS p ON cat.product=p.id
					LEFT JOIN $spectable AS spec ON p.id=spec.parent AND spec.context='product' AND spec.type='spec'
					WHERE cat.parent=$this->id AND cat.taxonomy='$this->taxonomy' AND spec.value != '' AND spec.value != '0' GROUP BY merge ORDER BY spec.name,merge";

				$results = $db->query($query,AS_ARRAY);

				$specdata = array();
				foreach ($results as $data) {
					if (isset($specdata[$data->name])) {
						if (!is_array($specdata[$data->name]))
							$specdata[$data->name] = array($specdata[$data->name]);
						$specdata[$data->name][] = $data;
					} else $specdata[$data->name] = $data;
				}

				if (is_array($this->specs)) {
					foreach ($this->specs as $spec) {
						$list = "";
						if (!empty($CategoryFilters[$spec['name']])) continue;

						// For custom menu presets
						if ($spec['facetedmenu'] == "custom" && !empty($spec['options'])) {
							foreach ($spec['options'] as $option) {
								$href = add_query_arg('shopp_catfilters['.$spec['name'].']',urlencode($option['name']),$link);
								$list .= '<li><a href="'.$href.'">'.$option['name'].'</a></li>';
							}
							$output .= '<h4>'.$spec['name'].'</h4><ul>'.$list.'</ul>';

						// For preset ranges
						} elseif ($spec['facetedmenu'] == "ranges" && !empty($spec['options'])) {
							foreach ($spec['options'] as $i => $option) {
								$matches = array();
								$format = '%s-%s';
								$next = 0;
								if (isset($spec['options'][$i+1])) {
									if (preg_match('/(\d+[\.\,\d]*)/',$spec['options'][$i+1]['name'],$matches))
										$next = $matches[0];
								}
								$matches = array();
								$range = array("min" => 0,"max" => 0);
								if (preg_match('/^(.*?)(\d+[\.\,\d]*)(.*)$/',$option['name'],$matches)) {
									$base = $matches[2];
									$format = $matches[1].'%s'.$matches[3];
									if (!isset($spec['options'][$i+1])) $range['min'] = $base;
									else $range = array("min" => $base, "max" => ($next-1));
								}
								if ($i == 1) {
									$href = add_query_arg('shopp_catfilters['.$spec['name'].']', urlencode(sprintf($format,'0',$range['min'])),$link);
									$label = __('Under ','Shopp').sprintf($format,$range['min']);
									$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
								}

								$href = add_query_arg('shopp_catfilters['.$spec['name'].']', urlencode(sprintf($format,$range['min'],$range['max'])), $link);
								$label = sprintf($format,$range['min']).' &mdash; '.sprintf($format,$range['max']);
								if ($range['max'] == 0) $label = sprintf($format,$range['min']).' '.__('and up','Shopp');
								$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
							}
							$output .= '<h4>'.$spec['name'].'</h4><ul>'.$list.'</ul>';

						// For automatically building the menu options
						} elseif ($spec['facetedmenu'] == "auto" && isset($specdata[$spec['name']])) {

							if (is_array($specdata[$spec['name']])) { // Generate from text values
								foreach ($specdata[$spec['name']] as $option) {
									$href = add_query_arg('shopp_catfilters['.$spec['name'].']',urlencode($option->value),$link);
									$list .= '<li><a href="'.$href.'">'.$option->value.'</a></li>';
								}
								$output .= '<h4>'.$spec['name'].'</h4><ul>'.$list.'</ul>';
							} else { // Generate number ranges
								$format = '%s';
								if (preg_match('/^(.*?)(\d+[\.\,\d]*)(.*)$/',$specdata[$spec['name']]->content,$matches))
									$format = $matches[1].'%s'.$matches[3];

								$ranges = auto_ranges($specdata[$spec['name']]->avg,$specdata[$spec['name']]->max,$specdata[$spec['name']]->min);
								foreach ($ranges as $range) {
									$href = add_query_arg('shopp_catfilters['.$spec['name'].']', urlencode($range['min'].'-'.$range['max']), $link);
									$label = sprintf($format,$range['min']).' &mdash; '.sprintf($format,$range['max']);
									if ($range['min'] == 0) $label = __('Under ','Shopp').sprintf($format,$range['max']);
									elseif ($range['max'] == 0) $label = sprintf($format,$range['min']).' '.__('and up','Shopp');
									$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
								}
								if (!empty($list)) $output .= '<h4>'.$spec['name'].'</h4>';
								$output .= '<ul>'.$list.'</ul>';

							}
						}
					}
				}


				return $output;
				break;
			case 'hasimages':
			case 'has-images':
				if (empty($this->images)) $this->load_images();
				if (empty($this->images)) return false;
				return true;
				break;
			case 'images':
				if (!isset($this->_images_loop)) {
					reset($this->images);
					$this->_images_loop = true;
				} else next($this->images);

				if (current($this->images) !== false) return true;
				else {
					unset($this->_images_loop);
					return false;
				}
				break;
			case 'coverimage':
			case 'thumbnail': // deprecated
				// Force select the first loaded image
				unset($options['id']);
				$options['index'] = 0;
			case 'image':
				if (empty($this->images)) $this->load_images();
				if (!(count($this->images) > 0)) return "";

				// Compatibility defaults
				$_size = 96;
				$_width = $Shopp->Settings->get('gallery_thumbnail_width');
				$_height = $Shopp->Settings->get('gallery_thumbnail_height');
				if (!$_width) $_width = $_size;
				if (!$_height) $_height = $_size;

				$defaults = array(
					'img' => false,
					'id' => false,
					'index' => false,
					'class' => '',
					'width' => false,
					'height' => false,
					'width_a' => false,
					'height_a' => false,
					'size' => false,
					'fit' => false,
					'sharpen' => false,
					'quality' => false,
					'bg' => false,
					'alt' => '',
					'title' => '',
					'zoom' => '',
					'zoomfx' => 'shopp-zoom',
					'property' => false
				);
				$options = array_merge($defaults,$options);
				extract($options);

				// Select image by database id
				if ($id !== false) {
					for ($i = 0; $i < count($this->images); $i++) {
						if ($img->id == $id) {
							$img = $this->images[$i]; break;
						}
					}
					if (!$img) return "";
				}

				// Select image by index position in the list
				if ($index !== false && isset($this->images[$index]))
					$img = $this->images[$index];

				// Use the current image pointer by default
				if (!$img) $img = current($this->images);

				if ($size !== false) $width = $height = $size;
				if (!$width) $width = $_width;
				if (!$height) $height = $_height;

				$scale = $fit?array_search($fit,$img->_scaling):false;
				$sharpen = $sharpen?min($sharpen,$img->_sharpen):false;
				$quality = $quality?min($quality,$img->_quality):false;
				$fill = $bg?hexdec(ltrim($bg,'#')):false;

				list($width_a,$height_a) = array_values($img->scaled($width,$height,$scale));
				if ($size == "original") {
					$width_a = $img->width;
					$height_a = $img->height;
				}
				if ($width_a === false) $width_a = $width;
				if ($height_a === false) $height_a = $height;

				$alt = esc_attr(empty($alt)?(empty($img->alt)?$img->name:$img->alt):$alt);
				$title = empty($title)?$img->title:$title;
				$titleattr = empty($title)?'':' title="'.esc_attr($title).'"';
				$classes = empty($class)?'':' class="'.esc_attr($class).'"';

				$src = shoppurl($img->id,'images');
				if ($size != "original") {
					$src = add_query_string(
						$img->resizing($width,$height,$scale,$sharpen,$quality,$fill),
						trailingslashit(shoppurl($img->id,'images')).$img->filename
					);
				}

				switch (strtolower($property)) {
					case 'id': return $img->id; break;
					case 'url':
					case 'src': return $src; break;
					case 'title': return $title; break;
					case 'alt': return $alt; break;
					case 'width': return $width_a; break;
					case 'height': return $height_a; break;
					case 'class': return $class; break;
				}

				$imgtag = '<img src="'.$src.'"'.$titleattr.' alt="'.$alt.'" width="'.$width_a.'" height="'.$height_a.'" '.$classes.' />';

				if (value_is_true($zoom))
					return '<a href="'.shoppurl($img->id,'images').'/'.$img->filename.'" class="'.$zoomfx.'" rel="product-'.$this->id.'">'.$imgtag.'</a>';

				return $imgtag;
				break;
			case 'slideshow':
				$options['load'] = array('images');
				if (!$this->loaded) $this->load_products($options);
				if (count($this->products) == 0) return false;

				$defaults = array(
					'width' => '440',
					'height' => '180',
					'fit' => 'crop',
					'fx' => 'fade',
					'duration' => 1000,
					'delay' => 7000,
					'order' => 'normal'
				);
				$options = array_merge($defaults,$options);
				extract($options, EXTR_SKIP);

				$href = shoppurl(SHOPP_PERMALINKS?trailingslashit('000'):'000','images');
				$imgsrc = add_query_string("$width,$height",$href);

				$string = '<ul class="slideshow '.$fx.'-fx '.$order.'-order duration-'.$duration.' delay-'.$delay.'">';
				$string .= '<li class="clear"><img src="'.$imgsrc.'" width="'.$width.'" height="'.$height.'" /></li>';
				foreach ($this->products as $Product) {
					if (empty($Product->images)) continue;
					$string .= '<li><a href="'.$Product->tag('url').'">';
					$string .= $Product->tag('image',array('width'=>$width,'height'=>$height,'fit'=>$fit));
					$string .= '</a></li>';
				}
				$string .= '</ul>';
				return $string;
				break;
			case 'carousel':
				$options['load'] = array('images');
				if (!$this->loaded) $this->load_products($options);
				if (count($this->products) == 0) return false;

				$defaults = array(
					'imagewidth' => '96',
					'imageheight' => '96',
					'fit' => 'all',
					'duration' => 500
				);
				$options = array_merge($defaults,$options);
				extract($options, EXTR_SKIP);

				$string = '<div class="carousel duration-'.$duration.'">';
				$string .= '<div class="frame">';
				$string .= '<ul>';
				foreach ($this->products as $Product) {
					if (empty($Product->images)) continue;
					$string .= '<li><a href="'.$Product->tag('url').'">';
					$string .= $Product->tag('image',array('width'=>$imagewidth,'height'=>$imageheight,'fit'=>$fit));
					$string .= '</a></li>';
				}
				$string .= '</ul></div>';
				$string .= '<button type="button" name="left" class="left">&nbsp;</button>';
				$string .= '<button type="button" name="right" class="right">&nbsp;</button>';
				$string .= '</div>';
				return $string;
				break;
		}
	}

} // END class Category

class ProductTag extends ProductTaxonomy {
	static $taxonomy = 'shopp_tag';
	static $namespace = 'tag';
	static $hierarchical = false;

	protected $context = 'tag';

	function __construct ($id,$key='id') {
		$this->taxonomy = self::$taxonomy;
		parent::__construct($id,$key);
	}

	static function labels ($class) {
		return array(
			'name' => __('Tags','Shopp'),
			'singular_name' => __('Tag','Shopp'),
			'search_items' => __('Search Tag','Shopp'),
			'popular_items' => __('Popular','Shopp'),
			'all_items' => __('Show All','Shopp'),
			'edit_item' => __('Edit Tag','Shopp'),
			'update_item' => __('Update Tag','Shopp'),
			'add_new_item' => __('New Tag','Shopp'),
			'new_item_name' => __('New Tag Name','Shopp'),
			'separate_items_with_commas' => __('Separate tags with commas','Shopp'),
			'add_or_remove_items' => sprintf(__('Type a tag name and press tab %s to add it.','Shopp'),'<abbr title="'.__('tab key','Shopp').'">&#8677;</abbr>'),
			'choose_from_most_used' => __('Type to search, or wait for popular tags&hellip;','Shopp')
		);
	}

}

class SmartCollection extends ProductCollection {
	var $smart = true;
	var $slug = false;
	var $uri = false;
	var $name = false;
	var $loading = array();

	function __construct ($options=array()) {
		global $Shopp;
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];
		if (isset($options['pagination'])) $this->loading['pagination'] = $options['pagination'];
		$this->smart($options);
	}

	function load () {
		parent::load($this->loading);
	}

	function register () {

		if ('' == get_option('permalink_structure') ) return;

		$args['rewrite'] = wp_parse_args($args['rewrite'], array(
			'slug' => sanitize_title_with_dashes($taxonomy),
			'with_front' => true,
		));
		add_rewrite_tag("%$taxonomy%", '([^/]+)', $args['query_var'] ? "{$args['query_var']}=" : "taxonomy=$taxonomy&term=");
		add_permastruct($taxonomy, "{$args['rewrite']['slug']}/%$taxonomy%", $args['rewrite']['with_front']);
	}

}

class CatalogProducts extends SmartCollection {
	static $_slug = "catalog";

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __('Catalog Products','Shopp');
		if (isset($options['order'])) $this->loading['order'] = $options['order'];
	}

}

class NewProducts extends SmartCollection {
	static $_slug = "new";
	static $_auto = true;

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __('New Products','Shopp');
		$this->loading = array('order'=>'newest');
		if (isset($options['columns'])) $this->loading['columns'] = $options['columns'];
	}

}

class FeaturedProducts extends SmartCollection {
	static $_slug = 'featured';
	static $_auto = true;

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __('Featured Products','Shopp');
		$this->loading = array('where'=>array("s.featured='on'"),'order'=>'newest');
	}

}

class OnSaleProducts extends SmartCollection {
	static $_slug = "onsale";
	static $_auto = true;

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __("On Sale","Shopp");
		$this->loading = array('where'=>"p.sale='on'",'order'=>'p.modified DESC');
	}

}

class BestsellerProducts extends SmartCollection {
	static $_slug = "bestsellers";
	static $_auto = true;

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __("Bestsellers","Shopp");
		$this->loading = array('order'=>'bestselling');
		if (isset($options['where'])) $this->loading['where'] = $options['where'];
	}

}

class SearchResults extends SmartCollection {
	static $_slug = "search-results";

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$options['search'] = empty($options['search'])?"":stripslashes($options['search']);

		// Load search engine components
		require_once(SHOPP_MODEL_PATH."/Search.php");
		new SearchParser();
		new BooleanParser();
		new ShortwordParser();

		// Sanitize the search string
		$search = $options['search'];

		// Price matching
		$prices = SearchParser::PriceMatching($search);
		if ($prices) {
			$pricematch = false;
			switch ($prices->op) {
				case '>': $pricematch = "((onsale=0 AND (minprice > $prices->target OR maxprice > $prices->target))
							OR (onsale=1 AND (minsaleprice > $prices->target OR maxsaleprice > $prices->target)))"; break;
				case '<': $pricematch = "((onsale=0 AND (minprice < $prices->target OR maxprice < $prices->target))
							OR (onsale=1 AND (minsaleprice < $prices->target OR maxsaleprice < $prices->target)))"; break;
				default: $pricematch = "((onsale=0 AND (minprice >= $prices->min AND maxprice <= $prices->max))
								OR (onsale=1 AND (minsaleprice >= $prices->min AND maxsaleprice <= $prices->max)))";
			}
		}

		// Boolean keyword search
		$boolean = apply_filters('shopp_boolean_search',$search);

		// Exact shortword search
		$shortwords = '';
		if (!(defined('SHOPP_DISABLE_SHORTWORD_SEARCH') && SHOPP_DISABLE_SHORTWORD_SEARCH))
			$shortwords = apply_filters('shopp_shortword_search',$search);

		// Natural language search for relevance
		$search = apply_filters('shopp_search_query',$search);

		if (strlen($options['search']) > 0 && empty($boolean)) $boolean = $options['search'];

		$score = "SUM(MATCH(terms) AGAINST ('$search'))";
		$where = "MATCH(terms) AGAINST ('$boolean' IN BOOLEAN MODE)";
		if (!empty($shortwords)) {
			$score = "SUM(MATCH(terms) AGAINST ('$search'))+SUM(terms REGEXP '[[:<:]](".str_replace(' ','|',$shortwords).")[[:>:]]')";
			$where = "($where OR terms REGEXP '[[:<:]](".str_replace(' ','|',$shortwords).")[[:>:]]')";
		}

		/*
			@todo Fix product id associations in product index
		*/
		$index = DatabaseObject::tablename(ContentIndex::$table);
		$this->loading = array(
			'joins'=>array($index => "INNER JOIN $index AS search ON search.product=p.id"),
			'columns'=> "$score AS score",
			'where'=> $where,
			'groupby'=>'p.id',
			'orderby'=>'score DESC');
		if (!empty($pricematch)) $this->loading['having'] = $pricematch;
		if (isset($options['show'])) $this->loading['limit'] = $options['show'];

		// No search
		if (empty($options['search'])) $options['search'] = __('(no search terms)','Shopp');
		$this->name = __("Search Results for","Shopp").": {$options['search']}";

	}
}

class TagProducts extends SmartCollection {
	static $_slug = "tag";

	function smart ($options=array()) {
		$this->slug = self::$_slug;
		$tagtable = DatabaseObject::tablename(CatalogTag::$table);
		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$this->taxonomy = get_catalog_taxonomy_id('tag');
		$this->tag = urldecode($options['tag']);
		$tagquery = "";
		if (strpos($options['tag'],',') !== false) {
			$tags = explode(",",$options['tag']);
			foreach ($tags as $tag)
				$tagquery .= empty($tagquery)?"tag.name='$tag'":" OR tag.name='$tag'";
		} else $tagquery = "tag.name='{$this->tag}'";

		$this->name = __("Products tagged","Shopp")." &quot;".stripslashes($this->tag)."&quot;";
		$this->uri = urlencode($this->tag);
		$this->loading = array('joins'=> array("INNER JOIN $catalogtable AS catalog ON p.id=catalog.product AND catalog.taxonomy='$this->taxonomy' JOIN $tagtable AS tag ON catalog.parent=tag.id"),'where' => $tagquery);

	}
}

class RelatedProducts extends SmartCollection {
	static $_slug = "related";
	var $product = false;

	function smart ($options=array()) {
		$this->slug = self::$_slug;

		global $Shopp;
		$Cart = $Shopp->Order->Cart;
		$tagtable = DatabaseObject::tablename(CatalogTag::$table);
		$catalogtable = DatabaseObject::tablename(Catalog::$table);
		$this->taxonomy = get_catalog_taxonomy_id('tag');

		// Use the current product if available
		if (!empty($Shopp->Product->id))
			$this->product = $Shopp->Product;

		// Or load a product specified
		if (isset($options['product'])) {
			if ($options['product'] == "recent-cartitem") 			// Use most recently added item in the cart
				$this->product = new Product($Cart->Added->product);
			elseif (preg_match('/^[\d+]$/',$options['product']) !== false) 	// Load by specified id
				$this->product = new Product($options['product']);
			else
				$this->product = new Product($options['product'],'slug'); // Load by specified slug
		}

		if (empty($this->product->id)) return false;

		// Load the product's tags if they are not available
		if (empty($this->product->tags))
			$this->product->load_data(array('tags'));

		if (empty($this->product->tags)) return false;

		$tagscope = "";
		if (isset($options['tagged'])) {
			$tagged = new CatalogTag($options['tagged'],'name');

			if (!empty($tagged->id)) {
				$tagscope .= (empty($tagscope)?"":" OR ")."catalog.parent=$tagged->id";
			}

		}

		foreach ($this->product->tags as $tag)
			if (!empty($tag->id))
				$tagscope .= (empty($tagscope)?"":" OR ")."catalog.parent=$tag->id";

		if (!empty($tagscope)) $tagscope = "($tagscope) AND catalog.taxonomy='$this->taxonomy'";

		$this->tag = "product-".$this->product->id;
		$this->name = __("Products related to","Shopp")." &quot;".stripslashes($this->product->name)."&quot;";
		$this->uri = urlencode($this->tag);
		$this->controls = false;

		$exclude = "";
		if (!empty($this->product->id)) $exclude = " AND p.id != {$this->product->id}";

		$this->loading = array(
			'columns'=>'count(DISTINCT catalog.id)+SUM(IF('.$tagscope.',100,0)) AS score',
			'joins'=>"LEFT JOIN $catalogtable AS catalog ON catalog.product=p.id LEFT JOIN $tagtable AS t ON t.id=catalog.parent AND catalog.product=p.id",
			'where'=>"($tagscope) $exclude",
			'orderby'=>'score DESC'
			);
		if (isset($options['order'])) $this->loading['order'] = $options['order'];
		if (isset($options['controls']) && value_is_true($options['controls']))
			unset($this->controls);
	}

}

class RandomProducts extends SmartCollection {
	static $_slug = "random";

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;
		$this->name = __("Random Products","Shopp");
		$this->loading = array('order'=>'random');
		if (isset($options['exclude'])) {
			$where = array();
			$excludes = explode(",",$options['exclude']);
			global $Shopp;
			if (in_array('current-product',$excludes) &&
				isset($Shopp->Product->id)) $where[] = '(p.id != $Shopp->Product->id)';
			if (in_array('featured',$excludes)) $where[] = "(p.featured='off')";
			if (in_array('onsale',$excludes)) $where[] = "(pd.sale='off' OR pr.discount=0)";
			$this->loading['where'] = $where;
		}
		if (isset($options['columns'])) $this->loading['columns'] = $options['columns'];
	}
}

class PromoProducts extends SmartCollection {
	static $_slug = "promo";

	function smart ($options=array()) {
		$this->slug = $this->uri = self::$_slug;

		$id = urldecode($options['id']);

		$Promo = new Promotion($id);
		$this->name = $Promo->name;

		$pricetable = DatabaseObject::tablename(Price::$table);
		$this->loading = array('where' => "p.id IN (SELECT product FROM $pricetable WHERE 0 < FIND_IN_SET($Promo->id,discounts))");
	}
}

?>