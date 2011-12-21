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
	static $context = 'Category'; // @todo transition to Collection
	static $register = array(
		'carousel' => 'carousel',
		'coverimage' => 'coverimage',
		'description' => 'description',
		'feedurl' => 'feed_url',
		'hascategories' => 'has_categories',
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
		'url' => 'url',

		// Faceted menu tags
		'hasfacetedmenu' => 'has_faceted_menu',
		'facetedmenu' => 'faceted_menu',
		'isfacetfiltered' => 'is_facet_filtered',
		'facetfilters' => 'facet_filters',
		'facetfilter' => 'facet_filter',
		'facetfiltered' => 'facet_filtered',
		'facetmenus' => 'facet_menus',
		'facetname' => 'facet_name',
		'facetslug' => 'facet_slug',
		'facetlink' => 'facet_link',
		'facetmenuhasoptions' => 'facet_menu_has_options',
		'facetoptions' => 'facet_options',
		'facetoptionlink' => 'facet_option_link',
		'facetoptionlabel' => 'facet_option_label',
		'facetoptioninput' => 'facet_option_input',
		'facetoptionvalue' => 'facet_option_value',
		'facetoptioncount' => 'facet_option_count'
	);

	static function _context_name ( $name ) {
		switch ( $name ) {
			case 'collection':
			case 'category':
			case 'subcategory':
			return 'category';
			break;
		}
		return $name;
	}

	static function _setobject ($Object, $context) {
		if( is_object($Object) && is_a($Object, 'ProductCollection') ) return $Object;

		switch ( $context ) {
			case 'collection':
			case 'category':
				return ShoppCollection();
				break;
			case 'subcategory':
				if (isset(ShoppCollection()->child))
					return ShoppCollection()->child;
				break;
		}
		return $Object;
	}

	static function _apicontext () { return "category"; }

	static function carousel ($result, $options, $O) {
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

	static function coverimage ($result, $options, $O) {
		// Force select the first loaded image
		unset($options['id']);
		$options['index'] = 0;
		return self::image($result, $options, $O);
	}

	static function description ($result, $options, $O) { return wpautop($O->description);  }


	static function is_facet_filtered ($result, $options, $O) {
		return (count($O->filters) > 0);
	}

	static function facet_filters ($result, $options, $O) {
		if (!isset($O->_filters_loop)) {
			reset($O->filters);
			$O->_filters_loop = true;
		} else next($O->filters);

		$slug = key($O->filters);
		if (isset($O->facets[ $slug ]))
			$O->facet = $O->facets[ $slug ];

		if (current($O->filters) !== false) return true;
		else {
			unset($O->_filters_loop,$O->facet);
			return false;
		}

	}

	static function facet_filter ($result, $options, $O) {
		if (!isset($O->_filters_loop)) return false;
		return $O->facet->selected;
	}

	static function facet_menus ($result, $options, $O) {
		if (!isset($O->_facets_loop)) {
			reset($O->facets);
			$O->_facets_loop = true;
		} else next($O->facets);

		if (current($O->facets) !== false) return true;
		else {
			unset($O->_facets_loop);
			return false;
		}
	}

	static function facet_name ($result, $options, $O) {
		if (isset($O->_filters_loop)) $facet = $O->facet;
		else $facet = current($O->facets);
		return $facet->name;
	}

	static function facet_slug ($result, $options, $O) {
		$facet = current($O->facets);
		return $facet->slug;
	}

	static function facet_link ($result, $options, $O) {
		if (isset($O->_filters_loop)) $facet = $O->facet;
		else $facet = current($O->facets);
		return $facet->link;
	}

	static function facet_filtered ($result, $options, $O) {
		if (isset($O->_filters_loop)) $facet = $O->facet;
		else $facet = current($O->facets);
		return !empty($facet->selected);
	}

	static function facet_menu_has_options ($result, $options, $O) {
		$facet = current($O->facets);
		return (count($facet->filters) > 0);
	}

	static function facet_options   ($result, $options, $O) {
		$facet = current($O->facets);

		if (!isset($O->_facetoptions_loop)) {
			reset($facet->filters);
			$O->_facetoptions_loop = true;
		} else next($facet->filters);

		if (current($facet->filters) !== false) return true;
		else {
			unset($O->_facetoptions_loop);
			return false;
		}

	}

	static function facet_option_link  ($result, $options, $O) {
		$facet = current($O->facets);
		$option = current($facet->filters);
		return add_query_arg($facet->slug,$option->param,$facet->link);
	}

	static function facet_option_label  ($result, $options, $O) {
		$facet = current($O->facets);
		$option = current($facet->filters);
		return $option->label;
	}

	static function facet_option_value  ($result, $options, $O) {
		$facet = current($O->facets);
		$option = current($facet->filters);
		return $option->param;
	}

	static function facet_option_count  ($result, $options, $O) {
		$facet = current($O->facets);
		$option = current($facet->filters);
		return $option->count;
	}

	static function facet_option_input  ($result, $options, $O) {
		$facet = current($O->facets);
		$option = current($facet->filters);

		$defaults = array(
			'type' => 'checkbox',
			'label' => $option->label,
			'value' => $option->param,
			'class' => 'click-submit'
		);
		if (isset($options['class'])) $options['class'] = trim($defaults['class'].' '.$options['class']);
		$options = array_merge($defaults,$options);
		extract($options);
		if ($option->param == $facet->selected) $options['checked'] = 'checked';

		$_ = array();
		$_[] = '<form action="'.self::url(false,false,$O).'" method="get"><input type="hidden" name="s_ff" value="on" /><input type="hidden" name="'.$facet->slug.'" value="" />';
		$_[] = '<label><input type="'.$type.'" name="'.$facet->slug.'" value="'.$value.'"'.inputattrs($options).' />'.(!empty($label)?'&nbsp;'.$label:'').'</label>';
		$_[] = '</form>';
		return join('',$_);
	}

	static function faceted_menu ($result, $options, $O) {
		$_ = array();

		// Use a template if available
		$template = locate_shopp_template(array('facetedmenu-'.$O->slug.'.php','facetedmenu.php'));
		if ($template) {
			ob_start();
			include($template);
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}

		if (self::is_facet_filtered('',false,$O)) {
			$_[] = '<ul>';
			while(self::facet_filters(false,false,$O)) {
				$_[] = '<li>';
				$_[] = '<strong>'.self::facet_name(false,false,$O).':</strong> ';
				$_[] = self::facet_filter(false,false,$O);
				$_[] = sprintf(' <a href="%s" class="cancel">%s</a>',self::facet_link(false,false,$O),'X');
				$_[] = '</li>';
			}
			$_[] = '</ul>';
		}

		$_[] = '<ul class="faceted-menu">';
		while(self::facet_menus(false,false,$O)) {
			if (self::facet_filtered(false,false,$O)) continue;
			if (!self::facet_menu_has_options(false,false,$O)) continue;
			$_[] = '<li>';
			$_[] = '<h4>'.self::facet_name(false,false,$O).'</h4>';
			$_[] = '<ul class="facet-option '.self::facet_slug(false,false,$O).'">';
			while(self::facet_options(false,false,$O)) {
				$_[] = '<li>';
				$_[] = sprintf('<a href="%s">%s</a>',self::facet_option_link(false,false,$O),self::facet_option_label(false,false,$O));
				$_[] = ' <span class="count">'.self::facet_option_count(false,false,$O).'</span>';
				$_[] = '</li>';
			}
			$_[] = '</ul>';

			$_[] = '</li>';

		}
		$_[] = '</ul>';

		return join('',$_);
/*
		global $Shopp;
		if ('off' == $O->facetedmenus) return;
		$output = "";

		$Storefront = ShoppStorefront();
		if (!$Storefront) return;
		$CategoryFilters =& $Storefront->browsing[$O->slug];


		$link = self::url('', array('echo'=>false), $O);

		$link = add_query_arg('s_ff','on',$link);

		if (!isset($options['cancel'])) $options['cancel'] = "X";

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

		$Filtered = new ProductCategory($O->id);
		$filtering = array_merge($Filtered->facetsql(),array('ids'=>true,'limit'=>1000));
		$Filtered->load($filtering);
		$ids = join(',',$Filtered->worklist());

		if ($O->pricerange == "auto" && empty($CategoryFilters['price'])) {
			if (!$O->loaded) $O->load();
			$list = "";
			$O->priceranges = auto_ranges($O->pricing->average,$O->pricing->max,$O->pricing->min);
			$pricing = $O->pricing;

			$casewhen = '';
			foreach ($O->priceranges as $index => $r)
				$casewhen .= " WHEN (minprice >= {$r['min']} AND minprice <= {$r['max']}) THEN $index";

			$sumtable = DatabaseObject::tablename(ProductSummary::$table);
			$query = "SELECT count(*) AS total, CASE $casewhen END AS rangeid
				FROM $sumtable
				WHERE product IN ($ids) GROUP BY rangeid";
			$pricecounts = DB::query($query,'array','col','total','rangeid');

			foreach ($O->priceranges as $id => $range) {
				if ($pricecounts[$id] < 1) continue;
				$href = add_query_arg('price',urlencode(money($range['min']).'-'.money($range['max'])),$link);
				$label = money($range['min']).' &mdash; '.money($range['max']-0.01);
				if ($range['min'] == 0) $label = __('Under ','Shopp').money($range['max']);
				elseif ($range['max'] == 0) $label = money($range['min']).' '.__('and up','Shopp');
				$list .= '<li><a href="'.$href.'">'.$label.'</a> ('.$pricecounts[$id].')</li>';
			}
			if (!empty($O->priceranges)) $output .= '<h4>'.__('Price Range','Shopp').'</h4>';
			$output .= '<ul>'.$list.'</ul>';
		}

		$spectable = DatabaseObject::tablename(Spec::$table);
		$query = "SELECT spec.name,spec.value,
			IF(spec.numeral > 0,spec.name,spec.value) AS merge,
			count(*) AS total,avg(numeral) AS avg,max(numeral) AS max,min(numeral) AS min
			FROM $spectable AS spec
			WHERE spec.parent IN ($ids) AND spec.context='product' AND spec.type='spec' AND (spec.value != '' OR spec.numeral > 0) GROUP BY merge";

		$specdata = DB::query($query,'array','index','name',true);

		if (!is_array($O->specs)) return $output;

		foreach ($O->specs as $spec) {
			$slug = sanitize_title_with_dashes($spec['name']);
			if (!empty($CategoryFilters[$slug])) continue;
			$list = "";

			// For custom menu presets
			if ($spec['facetedmenu'] == "custom" && !empty($spec['options'])) {
				$data = $specdata[ $spec['name'] ];
				$totals = array();
				foreach ($data as $d) $totals[$d->value] = $d->total;

				foreach ($spec['options'] as $option) {
					if ($totals[ $option['name'] ] < 1) continue;
					$href = add_query_arg($slug,urlencode($option['name']),$link);
					$list .= '<li><a href="'.$href.'">'.$option['name'].'</a> ('.$totals[$option['name']].')</li>';
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
						$list .= '<li><a href="'.$href.'">'.$option->value.'</a> ('.$option->total.')</li>';
					}
					$output .= '<h4>'.$spec['name'].'</h4><ul>'.$list.'</ul>';
				} else { // Generate number ranges
					$specd = $specdata[$spec['name']][0];
					$format = '%s';
					if (preg_match('/^(.*?)(\d+[\.\,\d]*)(.*)$/',$specd->content,$matches))
						$format = $matches[1].'%s'.$matches[3];

					$ranges = auto_ranges($specd->avg,$specd->max,$specd->min);

					$casewhen = '';
					foreach ($ranges as $index => $r)
						$casewhen .= " WHEN (spec.numeral >= {$r['min']} AND spec.numeral <= {$r['max']}) THEN $index";

					$query = "SELECT count(*) AS total, CASE $casewhen END AS rangeid
						FROM $spectable AS spec
						WHERE spec.parent IN ($ids) AND spec.context='product' AND spec.type='spec' AND spec.numeral > 0 GROUP BY rangeid";
					$count = DB::query($query,'array','col','total','rangeid');

					foreach ($ranges as $id => $range) {
						if ($count[$id] < 1) continue;
						$href = add_query_arg($slug, urlencode($range['min'].'-'.$range['max']), $link);
						$label = sprintf($format,$range['min']).' &mdash; '.sprintf($format,$range['max']);
						if ($range['min'] == 0) $label = __('Under ','Shopp').sprintf($format,$range['max']);
						elseif ($range['max'] == 0) $label = sprintf($format,$range['min']).' '.__('and up','Shopp');
						$list .= '<li><a href="'.$href.'">'.$label.'</a> ('.$count[$id].')</li>';
					}
					if (!empty($list)) $output .= '<h4>'.$spec['name'].'</h4>';
					$output .= '<ul>'.$list.'</ul>';

				}
			}
		}


		return $output;
		*/
	}

	static function feed_url ($result, $options, $O) {
		$url = self::url($result,$options,$O);
		if ( '' == get_option('permalink_structure') ) return add_query_arg(array('src'=>'category_rss'),$url);

		$query = false;
		if (strpos($url,'?') !== false) list($url,$query) = explode('?',$url);
		$url = trailingslashit($url)."feed";
		if ($query) $url = "$url?$query";
			return $url;
	}

	static function has_categories ($result, $options, $O) {
		if (empty($O->children) && method_exists($O, 'load_children')) $O->load_children();
		return (!empty($O->children));
	}

	static function has_faceted_menu ($result, $options, $O) {
		if (empty($O->meta)) $O->load_meta();
		if ('on' == $O->facetedmenus) {
			$O->load_facets();
			return true;
		}
		return false;
	}

	static function has_images ($result, $options, $O) {
		if (empty($O->images)) $O->load_images();
		if (empty($O->images)) return false;
		return true;
	}

	static function id ($result, $options, $O) {
		if ( isset($O->term_id)) return $O->term_id;
		return false;
	}

	/**
	 * Renders a custom category image
	 *
	 * @see the image() method from theme/catalog.php
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return string
	 **/
	static function image ($result, $options, $O) {
		if (!self::has_images($result, $options, $O)) return '';
		return ShoppCatalogThemeAPI::image($result, $options, $O);
	}

	static function images ($result, $options, $O) {
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

	static function is_subcategory ($result, $options, $O) {
		return ($O->parent != 0);
	}

	static function load_products ($result, $options, $O) {
		if (empty($O->id) && empty($O->slug)) return false;
		if (isset($options['load'])) {
			$dataset = explode(",",$options['load']);
			$options['load'] = array();
			foreach ($dataset as $name) {
				if ( 'description' == trim(strtolower($name)) )
					$options['columns'] = 'p.post_content';
				$options['load'][] = trim($name);
			}
		 } else {
			$options['load'] = array('prices');
		}
		if (!$O->loaded) $O->load($options);
		if (count($O->products) > 0) return true; else return false;
	}

	static function name ($result, $options, $O) { return $O->name; }

	static function pagination ($result, $options, $O) {
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
			foreach ($O->alpha as $letter => $products) {
				$link = $O->pagelink($letter);
				if ($products > 0) $_[] = '<li><a href="'.esc_url($link).'">'.$letter.'</a></li>';
				else $_[] = '<li><span>'.$letter.'</span></li>';
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
					$_[] = '<li><a href="'.esc_url($link).'">1</a></li>';

					$pagenum = ($O->page - $jumps);
					if ($pagenum < 1) $pagenum = 1;
					$link = $O->pagelink($pagenum);
					$_[] = '<li><a href="'.esc_url($link).'">'.$jumpback.'</a></li>';
				}
			}

			// Add previous button
			if (!empty($previous) && $O->page > 1) {
				$prev = $O->page-1;
				$link = $O->pagelink($prev);
				$_[] = '<li class="previous"><a href="'.esc_url($link).'">'.$previous.'</a></li>';
			} else $_[] = '<li class="previous disabled">'.$previous.'</li>';
			// end previous button

			while ($i < $visible_pages) {
				$link = $O->pagelink($i);
				if ( $i == $O->page ) $_[] = '<li class="active">'.$i.'</li>';
				else $_[] = '<li><a href="'.esc_url($link).'">'.$i.'</a></li>';
				$i++;
			}
			if ($O->pages > $visible_pages) {
				$pagenum = ($O->page + $jumps);
				if ($pagenum > $O->pages) $pagenum = $O->pages;
				$link = $O->pagelink($pagenum);
				$_[] = '<li><a href="'.esc_url($link).'">'.$jumpfwd.'</a></li>';
				$link = $O->pagelink($O->pages);
				$_[] = '<li><a href="'.esc_url($link).'">'.$O->pages.'</a></li>';
			}

			// Add next button
			if (!empty($next) && $O->page < $O->pages) {
				$pagenum = $O->page+1;
				$link = $O->pagelink($pagenum);
				$_[] = '<li class="next"><a href="'.esc_url($link).'">'.$next.'</a></li>';
			} else $_[] = '<li class="next disabled">'.$next.'</li>';

			$_[] = '</ul>';
			$_[] = $after;
		}
		return join("\n",$_);
	}

	static function parent ($result, $options, $O) { return $O->parent;  }

	static function products ($result, $options, $O) {
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

	static function row ($result, $options, $O) {
		global $Shopp;
		if (!isset($O->_rindex) || $O->_rindex === false) $O->_rindex = 0;
		else $O->_rindex++;
		if (empty($options['products'])) $options['products'] = shopp_setting('row_products');
		if (isset($O->_rindex) && $O->_rindex > 0 && $O->_rindex % $options['products'] == 0) return true;
		else return false;
	}

	static function section_list ($result, $options, $O) {
		if (!isset($O->id) || empty($O->id)) return false;
		$options['section'] = true;
		return ShoppCatalogThemeAPI::category_list($result, $options, $O);
	}

	static function slideshow ($result, $options, $O) {
		$options['load'] = array('images');
		if (!$O->loaded) $O->load($options);
		if (count($O->products) == 0) return false;

		$defaults = array(
			'fx' => 'fade',
			'duration' => 1000,
			'delay' => 7000,
			'order' => 'normal'
		);
		$imgdefaults = array(
			'setting' => false,
			'width' => '580',
			'height' => '200',
			'size' => false,
			'fit' => 'crop',
			'sharpen' => false,
			'quality' => false,
			'bg' => false,
		);

		$options = array_merge($defaults,$imgdefaults,$options);
		extract($options, EXTR_SKIP);

		$href = shoppurl('' != get_option('permalink_structure')?trailingslashit('000'):'000','images');
		$imgsrc = add_query_string("$width,$height",$href);

		$string = '<ul class="slideshow '.$fx.'-fx '.$order.'-order duration-'.$duration.' delay-'.$delay.'">';
		$string .= '<li class="clear"><img src="'.$imgsrc.'" width="'.$width.'" height="'.$height.'" /></li>';
		foreach ($O->products as $Product) {
			if (empty($Product->images)) continue;
			$string .= '<li><a href="'.$Product->tag('url').'">';
			$imgoptions = array_filter(array_intersect_key($options,$imgdefaults));
			$string .= shopp($Product,'get-image',$imgoptions);
			$string .= '</a></li>';
		}
		$string .= '</ul>';
		return $string;
	}

	static function slug ($result, $options, $O) {
		if (isset($O->slug)) return urldecode($O->slug);
		return false;
	}

	static function subcategories ($result, $options, $O) {
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

	static function subcategory_list ($result, $options, $O) {
		if (!isset($O->id) || empty($O->id)) return false;
		$options['childof'] = $O->id;
		$options['default'] = __('Select a sub-category&hellip;','Shopp');
		return ShoppCatalogThemeAPI::category_list($result, $options, $O);
	}

	static function total ($result, $options, $O) { return $O->loaded?$O->total:false; }

	static function url ($result, $options, $O) {
		$namespace = get_class_property( get_class($O) ,'namespace');
		$prettyurls = ( '' != get_option('permalink_structure') );

		$url = shoppurl( $prettyurls ? "$namespace/$O->slug" : array($O->taxonomy=>$O->slug),false );
		if (isset($options['page'])) $url = $O->pagelink((int)$options['page']);
		return $url;
	}

}

?>