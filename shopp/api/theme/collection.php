<?php
/**
* ShoppCollectionThemeAPI - Provided theme api tags.
*
* @version 1.0
* @since 1.2
* @package shopp
* @subpackage ShoppCollectionThemeAPI
*
**/

add_filter('shopp_themeapi_context_name', array('ShoppCollectionThemeAPI', '_context_name'));

/**
 * shopp('category','...') tags
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.0
 * @version 1.1
 * @see http://docs.shopplugin.net/Category_Tags
 *
 **/
class ShoppCollectionThemeAPI implements ShoppAPI {
	static $context = 'Category'; // TODO transition to Collection
	static $register = array(
		'carousel' => 'carousel',
		'coverimage' => 'cover_image',
		'description' => 'description',
		'facetedmenu' => 'faceted_menu',
		'feedurl' => 'feed_url',
		'hascategories' => 'has_categories',
		'hasfacetedmenu' => 'has_faceted_menu',
		'hasimages' => 'has_images',
		'hasproducts' => 'load_products',
		'loadproducts' => 'load_products',
		'id' => 'id',
		'image' => 'image',
		'images' => 'images',
		'issubcategory' => 'is_subcategory',
		'link' => 'url',
		'name' => 'name',
		'pagination' => 'pagination',
		'parent' => 'parent',
		'products' => 'products',
		'row' => 'row',
		'sectionlist' => 'section_list',
		'slideshow' => 'slideshow',
		'slug' => 'slug',
		'subcategories' => 'subcategories',
		'subcategorylist' => 'subcategory_list',
		'total' => 'total',
		'url' => 'url'
	);

	static function _context_name ( $name ) {
		switch ( $name ) {
			case "collection":
			case "category":
			case "subcategory":
			return "category";
			break;
		}
		return $name;
	}

	static function _apicontext () { return "category"; }

	function carousel ($result, $options, $O) {
		$options['load'] = array('images');
		if (!$O->loaded) $O->load_products($options);
		if (count($O->products) == 0) return false;

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
		foreach ($O->products as $Product) {
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
	}

	function cover_image ($result, $options, $O) {
		// Force select the first loaded image
		unset($options['id']);
		$options['index'] = 0;
		return self::image($result, $options, $O);
	}

	function description ($result, $options, $O) { return wpautop($O->description);  }

	function faceted_menu ($result, $options, $O) {
		global $Shopp;
		if ($O->facetedmenus == "off") return;
		$output = "";

		$Storefront = ShoppStorefront();
		if (!$Storefront) return;
		$CategoryFilters =& $Storefront->browsing[$O->slug];

		$link = self::url('', array('echo'=>false), $O);

		$link = add_query_arg('s_ff','on',$link);

		if (!isset($options['cancel'])) $options['cancel'] = "X";
		// if (strpos($_SERVER['REQUEST_URI'],"?") !== false)
		// 	list($link,$query) = explode("?",$_SERVER['REQUEST_URI']);
		// $query = $_GET;
		// $query = http_build_query($query);
		// $link = esc_url($link).'?'.$query;

		$list = "";
		if (is_array($CategoryFilters)) {
			foreach($CategoryFilters AS $facet => $filter) {
				$facetname = $O->facets[$facet];
				$href = add_query_arg($facet,'',$link);
				if (preg_match('/^(.*?(\d+[\.\,\d]*).*?)\-(.*?(\d+[\.\,\d]*).*)$/',$filter,$matches)) {
					$label = $matches[1].' &mdash; '.$matches[3];
					if ($matches[2] == 0) $label = __('Under ','Shopp').$matches[3];
					if ($matches[4] == 0) $label = $matches[1].' '.__('and up','Shopp');
				} else $label = $filter;
				if (!empty($filter)) $list .= '<li><strong>'.$facetname.'</strong>: '.stripslashes($label).' <a href="'.$href.'=" class="cancel">'.$options['cancel'].'</a></li>';
			}
			$output .= '<ul class="filters enabled">'.$list.'</ul>';
		}

		if ($O->pricerange == "auto" && empty($CategoryFilters['price'])) {
			if (!$O->loaded) $O->load();
			$list = "";
			$O->priceranges = auto_ranges($O->pricing->average,$O->pricing->max,$O->pricing->min);

			foreach ($O->priceranges as $range) {
				$href = add_query_arg('price',urlencode(money($range['min']).'-'.money($range['max'])),$link);
				$label = money($range['min']).' &mdash; '.money($range['max']-0.01);
				if ($range['min'] == 0) $label = __('Under ','Shopp').money($range['max']);
				elseif ($range['max'] == 0) $label = money($range['min']).' '.__('and up','Shopp');
				$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
			}
			if (!empty($O->priceranges)) $output .= '<h4>'.__('Price Range','Shopp').'</h4>';
			$output .= '<ul>'.$list.'</ul>';
		}

		global $wpdb;
		$tr = $wpdb->term_relationships;
		$tt = $wpdb->term_taxonomy;
		$spectable = DatabaseObject::tablename(Spec::$table);

		$query = "SELECT spec.name,spec.value,
			IF(spec.numeral > 0,spec.name,spec.value) AS merge,
			count(*) AS total,avg(numeral) AS avg,max(numeral) AS max,min(numeral) AS min
			FROM $spectable AS spec
			INNER JOIN $tr AS tr ON tr.object_id=spec.parent AND spec.context='product' AND spec.type='spec'
			INNER JOIN $tt AS tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
			WHERE tt.term_id='$O->id' AND spec.value != '' AND spec.value != '0' GROUP BY merge ORDER BY spec.name,merge";

		$specdata = DB::query($query,'array','index','name',true);

		// print_r($specdata);
		// $specdata = array();
		// foreach ($results as $data) {
		// 	if (isset($specdata[$data->name])) {
		// 		if (!is_array($specdata[$data->name]))
		// 			$specdata[$data->name] = array($specdata[$data->name]);
		// 		$specdata[$data->name][] = $data;
		// 	} else $specdata[$data->name] = $data;
		// }

		if (!is_array($O->specs)) return $output;

		foreach ($O->specs as $spec) {
			$slug = sanitize_title_with_dashes($spec['name']);
			if (!empty($CategoryFilters[$slug])) continue;
			$list = "";

			// For custom menu presets
			if ($spec['facetedmenu'] == "custom" && !empty($spec['options'])) {
				foreach ($spec['options'] as $option) {
					$href = add_query_arg($slug,urlencode($option['name']),$link);
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
						$href = add_query_arg($slug, urlencode(sprintf($format,'0',$range['min'])),$link);
						$label = __('Under ','Shopp').sprintf($format,$range['min']);
						$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
					}

					$href = add_query_arg($slug, urlencode(sprintf($format,$range['min'],$range['max'])), $link);
					$label = sprintf($format,$range['min']).' &mdash; '.sprintf($format,$range['max']);
					if ($range['max'] == 0) $label = sprintf($format,$range['min']).' '.__('and up','Shopp');
					$list .= '<li><a href="'.$href.'">'.$label.'</a></li>';
				}
				$output .= '<h4>'.$spec['name'].'</h4><ul>'.$list.'</ul>';

			// For automatically building the menu options
			} elseif ($spec['facetedmenu'] == "auto" && isset($specdata[$spec['name']])) {

				if (count($specdata[$spec['name']]) > 1) { // Generate from text values
					foreach ($specdata[$spec['name']] as $option) {
						$href = add_query_arg($slug,urlencode($option->value),$link);
						$list .= '<li><a href="'.$href.'">'.$option->value.'</a></li>';
					}
					$output .= '<h4>'.$spec['name'].'</h4><ul>'.$list.'</ul>';
				} else { // Generate number ranges
					$specd = $specdata[$spec['name']][0];
					$format = '%s';
					if (preg_match('/^(.*?)(\d+[\.\,\d]*)(.*)$/',$specd->content,$matches))
						$format = $matches[1].'%s'.$matches[3];

					$ranges = auto_ranges($specd->avg,$specd->max,$specd->min);
					foreach ($ranges as $range) {
						$href = add_query_arg($slug, urlencode($range['min'].'-'.$range['max']), $link);
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


		return $output;
	}

	function feed_url ($result, $options, $O) {
		$url = self::url($result,$options,$O);
		if (!SHOPP_PRETTYURLS) return add_query_arg(array('src'=>'category_rss'),$url);

		$query = false;
		if (strpos($url,'?') !== false) list($url,$query) = explode('?',$url);
		$url = trailingslashit($url)."feed";
		if ($query) $url = "$url?$query";
			return $url;
	}

	function has_categories ($result, $options, $O) {
		if (empty($O->children)) $O->load_children();
		return (!empty($O->children));
	}

	function has_faceted_menu ($result, $options, $O) { if (empty($O->meta)) $O->load_meta(); return ('on' == $O->facetedmenus); }

	function has_images ($result, $options, $O) {
		if (empty($O->images)) $O->load_images();
		if (empty($O->images)) return false;
		return true;
	}

	function id ($result, $options, $O) { return $O->id; }

	function image ($result, $options, $O) {
		// @todo Implement collection 'image' ThemeAPI tag
	}

	function images ($result, $options, $O) {
		if (!isset($O->_images_loop)) {
			reset($O->images);
			$O->_images_loop = true;
		} else next($O->images);

		if (current($O->images) !== false) return true;
		else {
			unset($O->_images_loop);
			return false;
		}
	}

	function is_subcategory ($result, $options, $O) {
		return ($O->parent != 0);
	}

	function load_products ($result, $options, $O) {
		if (empty($O->id) && empty($O->slug)) return false;
		if (isset($options['load'])) {
			$dataset = explode(",",$options['load']);
			$options['load'] = array();
			foreach ($dataset as $name) $options['load'][] = trim($name);
		 } else {
			$options['load'] = array('prices');
		}
		if (!$O->loaded) $O->load($options);
		if (count($O->products) > 0) return true; else return false;
	}

	function name ($result, $options, $O) { return $O->name; }

	function pagination ($result, $options, $O) {
		if (!$O->paged) return "";

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
		if (isset($O->alpha) && $O->paged) {
			$_[] = $before.$label;
			$_[] = '<ul class="paging">';
			foreach ($O->alpha as $alpha) {
				$link = $O->pagelink($alpha->letter);
				if ($alpha->total > 0)
					$_[] = '<li><a href="'.$link.'">'.$alpha->letter.'</a></li>';
				else $_[] = '<li><span>'.$alpha->letter.'</span></li>';
			}
			$_[] = '</ul>';
			$_[] = $after;
			return join("\n",$_);
		}

		if ($O->pages > 1) {

			if ( $O->pages > $show ) $visible_pages = $show + 1;
			else $visible_pages = $O->pages + 1;
			$jumps = ceil($visible_pages/2);
			$_[] = $before.$label;

			$_[] = '<ul class="paging">';
			if ( $O->page <= floor(($show) / 2) ) {
				$i = 1;
			} else {
				$i = $O->page - floor(($show) / 2);
				$visible_pages = $O->page + floor(($show) / 2) + 1;
				if ($visible_pages > $O->pages) $visible_pages = $O->pages + 1;
				if ($i > 1) {
					$link = $O->pagelink(1);
					$_[] = '<li><a href="'.$link.'">1</a></li>';

					$pagenum = ($O->page - $jumps);
					if ($pagenum < 1) $pagenum = 1;
					$link = $O->pagelink($pagenum);
					$_[] = '<li><a href="'.$link.'">'.$jumpback.'</a></li>';
				}
			}

			// Add previous button
			if (!empty($previous) && $O->page > 1) {
				$prev = $O->page-1;
				$link = $O->pagelink($prev);
				$_[] = '<li class="previous"><a href="'.$link.'">'.$previous.'</a></li>';
			} else $_[] = '<li class="previous disabled">'.$previous.'</li>';
			// end previous button

			while ($i < $visible_pages) {
				$link = $O->pagelink($i);
				if ( $i == $O->page ) $_[] = '<li class="active">'.$i.'</li>';
				else $_[] = '<li><a href="'.$link.'">'.$i.'</a></li>';
				$i++;
			}
			if ($O->pages > $visible_pages) {
				$pagenum = ($O->page + $jumps);
				if ($pagenum > $O->pages) $pagenum = $O->pages;
				$link = $O->pagelink($pagenum);
				$_[] = '<li><a href="'.$link.'">'.$jumpfwd.'</a></li>';
				$link = $O->pagelink($O->pages);
				$_[] = '<li><a href="'.$link.'">'.$O->pages.'</a></li>';
			}

			// Add next button
			if (!empty($next) && $O->page < $O->pages) {
				$pagenum = $O->page+1;
				$link = $O->pagelink($pagenum);
				$_[] = '<li class="next"><a href="'.$link.'">'.$next.'</a></li>';
			} else $_[] = '<li class="next disabled">'.$next.'</li>';

			$_[] = '</ul>';
			$_[] = $after;
		}
		return join("\n",$_);
	}

	function parent ($result, $options, $O) { return $O->parent;  }

	function products ($result, $options, $O) {
		global $Shopp;
		if (!isset($O->_product_loop)) {
			reset($O->products);
			$Shopp->Product = current($O->products);
			$O->_pindex = 0;
			$O->_rindex = false;
			$O->_product_loop = true;
		} else {
			$Shopp->Product = next($O->products);
			$O->_pindex++;
		}

		if (current($O->products) !== false) return true;
		else {
			unset($O->_product_loop);
			$O->_pindex = 0;
			return false;
		}
	}

	function row ($result, $options, $O) {
		global $Shopp;
		if (!isset($O->_rindex) || $O->_rindex === false) $O->_rindex = 0;
		else $O->_rindex++;
		if (empty($options['products'])) $options['products'] = shopp_setting('row_products');
		if (isset($O->_rindex) && $O->_rindex > 0 && $O->_rindex % $options['products'] == 0) return true;
		else return false;
	}

	function section_list ($result, $options, $O) {
		return true; // @todo Handle section-list listing in ShoppCategory
		global $Shopp;
		if (empty($O->id)) return false;
		if (isset($Shopp->Category->controls)) return false;
		if (empty($Shopp->Catalog->categories))
			$Shopp->Catalog->load_categories(array("where"=>"(pd.status='publish' OR pd.id IS NULL)"));
		if (empty($Shopp->Catalog->categories)) return false;
		if (!$O->children) $O->load_children();

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
		if (empty($O->id)) return false;
		$parent = '_'.$O->id;
		while($parent != 0) {
			if (!isset($Shopp->Catalog->categories[$parent])) break;
			if ($Shopp->Catalog->categories[$parent]->parent == 0
				|| $Shopp->Catalog->categories[$parent]->parent == $parent) break;
			$parent = '_'.$Shopp->Catalog->categories[$parent]->parent;
		}
		$root = $Shopp->Catalog->categories[$parent];
		if ($O->id == $parent && empty($O->children)) return false;

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
			$string .= '<select name="shopp_cats" id="shopp-'.$O->slug.'-subcategories-menu" class="shopp-categories-menu">';
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
	}

	function slideshow ($result, $options, $O) {
		$options['load'] = array('images');
		if (!$O->loaded) $O->load_products($options);
		if (count($O->products) == 0) return false;

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
		foreach ($O->products as $Product) {
			if (empty($Product->images)) continue;
			$string .= '<li><a href="'.$Product->tag('url').'">';
			$string .= $Product->tag('image',array('width'=>$width,'height'=>$height,'fit'=>$fit));
			$string .= '</a></li>';
		}
		$string .= '</ul>';
		return $string;
	}

	function slug ($result, $options, $O) { return urldecode($O->slug); }

	function subcategories ($result, $options, $O) {
		if (!isset($O->_children_loop)) {
			reset($O->children);
			$O->child = current($O->children);
			$O->_cindex = 0;
			$O->_children_loop = true;
		} else {
			$O->child = next($O->children);
			$O->_cindex++;
		}

		if ($O->child !== false) return true;
		else {
			unset($O->_children_loop);
			$O->_cindex = 0;
			$O->child = false;
			return false;
		}
	}

	function subcategory_list ($result, $options, $O) {
		return true; // @todo Handle sub-category listing in ShoppCategory
		global $Shopp;
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

		if (!$O->children) $O->load_children(array('orderby'=>$orderby,'order'=>$order));
		if (empty($O->children)) return false;

		$string = "";
		$depthlimit = $depth;
		$depth = 0;
		$exclude = explode(",",$exclude);
		$classes = ' class="shopp_categories'.(empty($class)?'':' '.$class).'"';
		$wraplist = value_is_true($wraplist);

		if (value_is_true($dropdown)) {
			$count = 0;
			$string .= $title;
			$string .= '<select name="shopp_cats" id="shopp-'.$O->slug.'-subcategories-menu" class="shopp-categories-menu">';
			$string .= '<option value="">'.__('Select a sub-category&hellip;','Shopp').'</option>';
			foreach ($O->children as &$category) {
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
			foreach ($O->children as &$category) {
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
	}

	function total ($result, $options, $O) { return $O->loaded?$O->total:false; }

	function url ($result, $options, $O) {
		$class = get_class($O);
		$namespace = get_class_property($class,'namespace');
		return shoppurl( SHOPP_PRETTYURLS ? "$namespace/$O->slug" : array('s_cat'=>$O->id) );
	}

}

?>