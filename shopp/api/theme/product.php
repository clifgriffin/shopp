<?php
/**
* ShoppProductThemeAPI - Provided theme api tags.
*
* @version 1.0
* @since 1.2
* @package shopp
* @subpackage ShoppProductThemeAPI
*
**/

/**
 * Provides shopp('product') template API functionality
 *
 * @author Jonathan Davis, John Dillick
 * @since 1.2
 *
 **/
class ShoppProductThemeAPI implements ShoppAPI {
	static $context = 'Product';
	static $register = array(
		'addons' => 'addons',
		'addtocart' => 'add_to_cart',
		'buynow' => 'buy_now',
		'categories' => 'categories',
		'category' => 'category',
		'coverimage' => 'coverimage',
		'description' => 'description',
		'donation' => 'quantity',
		'amount' => 'quantity',
		'quantity' => 'quantity',
		'found' => 'found',
		'freeshipping' => 'free_shipping',
		'gallery' => 'gallery',
		'hasaddons' => 'has_addons',
		'hascategories' => 'has_categories',
		'hassavings' => 'has_savings',
		'hasvariations' => 'has_variations',
		'hasimages' => 'has_images',
		'hasspecs' => 'has_specs',
		'hastags' => 'has_tags',
		'id' => 'id',
		'image' => 'image',
		'thumbnail' => 'image',
		'images' => 'images',
		'incategory' => 'in_category',
		'input' => 'input',
		'isfeatured' => 'is_featured',
		'link' => 'url',
		'url' => 'url',
		'name' => 'name',
		'onsale' => 'on_sale',
		'outofstock' => 'out_of_stock',
		'price' => 'price',
		'saleprice' => 'price',
		'relevance' => 'relevance',
		'savings' => 'savings',
		'slug' => 'slug',
		'spec' => 'spec',
		'specs' => 'specs',
		'summary' => 'summary',
		'tag' => 'tag',
		'tagged' => 'tagged',
		'tags' => 'tags',
		'taxrate' => 'taxrate',
		'variation' => 'variation',
		'variations' => 'variations',
		'weight' => 'weight'
	);

	static function _apicontext () { return "product"; }

	function addons ($result, $options, $O) {
		global $Shopp;
		$string = "";

		if (!isset($options['mode'])) {
			if (!$O->priceloop) {
				reset($O->prices);
				$O->priceloop = true;
			} else next($O->prices);
			$Oprice = current($O->prices);

			if ($Oprice && $Oprice->type == "N/A")
				next($O->prices);

			if ($Oprice && $Oprice->context != "addon")
				next($O->prices);

			if (current($O->prices) !== false) return true;
			else {
				$O->priceloop = false;
				return false;
			}
			return true;
		}

		if ($O->outofstock) return false; // Completely out of stock, hide menus
		if (!isset($options['taxes'])) $options['taxes'] = null;

		$defaults = array(
			'defaults' => '',
			'disabled' => 'show',
			'before_menu' => '',
			'after_menu' => ''
			);

		$options = array_merge($defaults,$options);

		if (!isset($options['label'])) $options['label'] = "on";
		if (!isset($options['required'])) $options['required'] = __('You must select the options for this item before you can add it to your shopping cart.','Shopp');
		if ($options['mode'] == "single") {
			if (!empty($options['before_menu'])) $string .= $options['before_menu']."\n";
			if (value_is_true($options['label'])) $string .= '<label for="product-options'.$O->id.'">'. __('Options').': </label> '."\n";

			$string .= '<select name="products['.$O->id.'][price]" id="product-options'.$O->id.'">';
			if (!empty($options['defaults'])) $string .= '<option value="">'.$options['defaults'].'</option>'."\n";

			foreach ($O->prices as $pricetag) {
				if ($pricetag->context != "addon") continue;

				if (isset($options['taxes']))
					$taxrate = shopp_taxrate(value_is_true($options['taxes']),$pricetag->tax,$O);
				else $taxrate = shopp_taxrate(null,$pricetag->tax,$O);
				$currently = ($pricetag->sale == "on")?$pricetag->promoprice:$pricetag->price;
				$disabled = ($pricetag->inventory == "on" && $pricetag->stock == 0)?' disabled="disabled"':'';

				$price = '  ('.money($currently).')';
				if ($pricetag->type != "N/A")
					$string .= '<option value="'.$pricetag->id.'"'.$disabled.'>'.$pricetag->label.$price.'</option>'."\n";
			}

			$string .= '</select>';
			if (!empty($options['after_menu'])) $string .= $options['after_menu']."\n";

		} else {
			if (!isset($O->options['a'])) return;

			$taxrate = shopp_taxrate($options['taxes'],true,$O);

			// Index addon prices by option
			$pricing = array();
			foreach ($O->prices as $pricetag) {
				if ($pricetag->context != "addon") continue;
				$pricing[$pricetag->options] = $pricetag;
			}

			foreach ($O->options['a'] as $id => $menu) {
				if (!empty($options['before_menu'])) $string .= $options['before_menu']."\n";
				if (value_is_true($options['label'])) $string .= '<label for="options-'.$menu['id'].'">'.$menu['name'].'</label> '."\n";
				$category_class = isset($Shopp->Category->slug)?'category-'.$Shopp->Category->slug:'';
				$string .= '<select name="products['.$O->id.'][addons][]" class="'.$category_class.' product'.$O->id.' addons" id="addons-'.$menu['id'].'">';
				if (!empty($options['defaults'])) $string .= '<option value="">'.$options['defaults'].'</option>'."\n";
				foreach ($menu['options'] as $key => $option) {

					$pricetag = $pricing[$option['id']];

					if (isset($options['taxes']))
						$taxrate = shopp_taxrate(value_is_true($options['taxes']),$pricetag->tax,$O);
					else $taxrate = shopp_taxrate(null,$pricetag->tax,$O);

					$currently = ($pricetag->sale == "on")?$pricetag->promoprice:$pricetag->price;
					if ($taxrate > 0) $currently = $currently+($currently*$taxrate);
					$string .= '<option value="'.$option['id'].'">'.$option['name'].' (+'.money($currently).')</option>'."\n";
				}

				$string .= '</select>';
			}
			if (!empty($options['after_menu'])) $string .= $options['after_menu']."\n";

		}

		return $string;
	}

	function add_to_cart ($result, $options, $O) {
		global $Shopp;
		if (!isset($options['class'])) $options['class'] = "addtocart";
		else $options['class'] .= " addtocart";
		if (!isset($options['value'])) $options['value'] = __("Add to Cart","Shopp");
		$string = "";

		if ($O->outofstock) {
			$string .= '<span class="outofstock">'.$Shopp->Settings->get('outofstock_text').'</span>';
			return $string;
		}
		if (isset($options['redirect']) && !isset($options['ajax']))
			$string .= '<input type="hidden" name="redirect" value="'.$options['redirect'].'" />';

		$string .= '<input type="hidden" name="products['.$O->id.'][product]" value="'.$O->id.'" />';

		if (!empty($O->prices[0]) && $O->prices[0]->type != "N/A")
			$string .= '<input type="hidden" name="products['.$O->id.'][price]" value="'.$O->prices[0]->id.'" />';

		if (!empty($Shopp->Category)) {
			if (SHOPP_PRETTYURLS)
				$string .= '<input type="hidden" name="products['.$O->id.'][category]" value="'.$Shopp->Category->slug.'" />';
			else
				$string .= '<input type="hidden" name="products['.$O->id.'][category]" value="'.((!empty($Shopp->Category->id))?$Shopp->Category->id:$Shopp->Category->slug).'" />';
		}

		$string .= '<input type="hidden" name="cart" value="add" />';
		if (isset($options['ajax'])) {
			if ($options['ajax'] == "html") $options['class'] .= ' ajax-html';
			else $options['class'] .= " ajax";
			$string .= '<input type="hidden" name="ajax" value="true" />';
			$string .= '<input type="button" name="addtocart" '.inputattrs($options).' />';
		} else {
			$string .= '<input type="submit" name="addtocart" '.inputattrs($options).' />';
		}

		return $string;
	}

	function buy_now ($result, $options, $O) {
		if (!isset($options['value'])) $options['value'] = __("Buy Now","Shopp");
		return self::addtocart($result, $options, $O);
	}

	function categories ($result, $options, $O) {
		if (!isset($O->_categories_loop)) {
			reset($O->categories);
			$O->_categories_loop = true;
		} else next($O->categories);

		if (current($O->categories) !== false) return true;
		else {
			unset($O->_categories_loop);
			return false;
		}
	}

	function category ($result, $options, $O) {
		$category = current($O->categories);
		if (isset($options['show'])) {
			if ($options['show'] == "id") return $category->id;
			if ($options['show'] == "slug") return $category->slug;
		}
		return $category->name;
	}

	function coverimage ($result, $options, $O) {
		// Force select the first loaded image
		unset($options['id']);
		$options['index'] = 0;
		return self::image($result, $options, $O);
	}

	function description ($result, $options, $O) { return apply_filters('shopp_product_description',$O->description); }

	function found ($result, $options, $O) {
		if (empty($O->id)) return false;
		$load = array('prices','images','specs','tags','categories');
		if (isset($options['load'])) $load = explode(",",$options['load']);
		$O->load_data($load);
		return true;
	}

	function free_shipping ($result, $options, $O) {
		if (empty($O->prices)) $O->load_data(array('prices'));
		return $O->freeshipping;
	}

	function gallery ($result, $options, $O) {
		global $Shopp;
		if (empty($O->images)) $O->load_data(array('images'));
		if (empty($O->images)) return false;
		$styles = '';
		$_size = 240;
		$_width = $Shopp->Settings->get('gallery_small_width');
		$_height = $Shopp->Settings->get('gallery_small_height');

		if (!$_width) $_width = $_size;
		if (!$_height) $_height = $_size;

		$defaults = array(

			// Layout settings
			'margins' => 20,
			'rowthumbs' => false,
			// 'thumbpos' => 'after',

			// Preview image settings
			'p.size' => false,
			'p.width' => false,
			'p.height' => false,
			'p.fit' => false,
			'p.sharpen' => false,
			'p.quality' => false,
			'p.bg' => false,
			'p.link' => true,
			'rel' => '',

			// Thumbnail image settings
			'thumbsize' => false,
			'thumbwidth' => false,
			'thumbheight' => false,
			'thumbfit' => false,
			'thumbsharpen' => false,
			'thumbquality' => false,
			'thumbbg' => false,

			// Effects settings
			'zoomfx' => 'shopp-zoom',
			'preview' => 'click',
			'colorbox' => '{}'


		);
		$optionset = array_merge($defaults,$options);

		// Translate dot names
		$options = array();
		$keys = array_keys($optionset);
		foreach ($keys as $key)
			$options[str_replace('.','_',$key)] = $optionset[$key];
		extract($options);

		if ($p_size > 0)
			$_width = $_height = $p_size;

		$width = $p_width > 0?$p_width:$_width;
		$height = $p_height > 0?$p_height:$_height;

		$preview_width = $width;

		$previews = '<ul class="previews">';
		$firstPreview = true;

		// Find the max dimensions to use for the preview spacing image
		$maxwidth = $maxheight = 0;
		foreach ($O->images as $img) {
			$scale = $p_fit?false:array_search($p_fit,$img->_scaling);
			$scaled = $img->scaled($width,$height,$scale);
			$maxwidth = max($maxwidth,$scaled['width']);
			$maxheight = max($maxheight,$scaled['height']);
		}

		if ($maxwidth == 0) $maxwidth = $width;
		if ($maxheight == 0) $maxheight = $height;

		$p_link = value_is_true($p_link);

		foreach ($O->images as $img) {

			$scale = $p_fit?array_search($p_fit,$img->_scaling):false;
			$sharpen = $p_sharpen?min($p_sharpen,$img->_sharpen):false;
			$quality = $p_quality?min($p_quality,$img->_quality):false;
			$fill = $p_bg?hexdec(ltrim($p_bg,'#')):false;
			$scaled = $img->scaled($width,$height,$scale);

			if ($firstPreview) { // Adds "filler" image to reserve the dimensions in the DOM
				$href = shoppurl(SHOPP_PERMALINKS?trailingslashit('000'):'000','images');
				$previews .= '<li id="preview-fill"'.(($firstPreview)?' class="fill"':'').'>';
				$previews .= '<img src="'.add_query_string("$maxwidth,$maxheight",$href).'" alt=" " width="'.$maxwidth.'" height="'.$maxheight.'" />';
				$previews .= '</li>';
			}
			$title = !empty($img->title)?' title="'.esc_attr($img->title).'"':'';
			$alt = esc_attr(!empty($img->alt)?$img->alt:$img->filename);

			$previews .= '<li id="preview-'.$img->id.'"'.(($firstPreview)?' class="active"':'').'>';

			$href = shoppurl(SHOPP_PERMALINKS?trailingslashit($img->id).$img->filename:$img->id,'images');
			if ($p_link) $previews .= '<a href="'.$href.'" class="gallery product_'.$O->id.' '.$options['zoomfx'].'"'.(!empty($rel)?' rel="'.$rel.'"':'').'>';
			// else $previews .= '<a name="preview-'.$img->id.'">'; // If links are turned off, leave the <a> so we don't break layout
			$previews .= '<img src="'.add_query_string($img->resizing($width,$height,$scale,$sharpen,$quality,$fill),shoppurl($img->id,'images')).'"'.$title.' alt="'.$alt.'" width="'.$scaled['width'].'" height="'.$scaled['height'].'" />';
			if ($p_link) $previews .= '</a>';
			$previews .= '</li>';
			$firstPreview = false;
		}
		$previews .= '</ul>';

		$thumbs = "";
		$twidth = $preview_width+$margins;

		if (count($O->images) > 1) {
			$default_size = 64;
			$_thumbwidth = $Shopp->Settings->get('gallery_thumbnail_width');
			$_thumbheight = $Shopp->Settings->get('gallery_thumbnail_height');
			if (!$_thumbwidth) $_thumbwidth = $default_size;
			if (!$_thumbheight) $_thumbheight = $default_size;

			if ($thumbsize > 0) $thumbwidth = $thumbheight = $thumbsize;

			$width = $thumbwidth > 0?$thumbwidth:$_thumbwidth;
			$height = $thumbheight > 0?$thumbheight:$_thumbheight;

			$firstThumb = true;
			$thumbs = '<ul class="thumbnails">';
			foreach ($O->images as $img) {
				$scale = $thumbfit?array_search($thumbfit,$img->_scaling):false;
				$sharpen = $thumbsharpen?min($thumbsharpen,$img->_sharpen):false;
				$quality = $thumbquality?min($thumbquality,$img->_quality):false;
				$fill = $thumbbg?hexdec(ltrim($thumbbg,'#')):false;
				$scaled = $img->scaled($width,$height,$scale);

				$title = !empty($img->title)?' title="'.esc_attr($img->title).'"':'';
				$alt = esc_attr(!empty($img->alt)?$img->alt:$img->name);

				$thumbs .= '<li id="thumbnail-'.$img->id.'" class="preview-'.$img->id.(($firstThumb)?' first':'').'">';
				$thumbs .= '<img src="'.add_query_string($img->resizing($width,$height,$scale,$sharpen,$quality,$fill),shoppurl($img->id,'images')).'"'.$title.' alt="'.$alt.'" width="'.$scaled['width'].'" height="'.$scaled['height'].'" />';
				$thumbs .= '</li>'."\n";
				$firstThumb = false;
			}
			$thumbs .= '</ul>';

		}
		if ($rowthumbs > 0) $twidth = ($width+$margins+2)*(int)$rowthumbs;

		$result = '<div id="gallery-'.$O->id.'" class="gallery">'.$previews.$thumbs.'</div>';
		$script = "\t".'ShoppGallery("#gallery-'.$O->id.'","'.$preview.'"'.($twidth?",$twidth":"").');';
		add_storefrontjs($script);

		return $result;
	}

	function has_addons ($result, $options, $O) { return ($O->addons == "on" && !empty($O->options['a'])); }

	function has_categories ($result, $options, $O) {
		if (empty($O->categories)) $O->load_data(array('categories'));
		if (count($O->categories) > 0) return true; else return false;
	}

	function has_images ($result, $options, $O) {
		if (empty($O->images)) $O->load_data(array('images'));
		return (!empty($O->images));
	}

	function has_savings ($result, $options, $O) { return ($O->onsale && $O->min['saved'] > 0); }

	function has_specs ($result, $options, $O) {
		if (empty($O->specs)) $O->load_data(array('specs'));
		if (count($O->specs) > 0) {
			$O->merge_specs();
			return true;
		} else return false;
	}

	function has_tags ($result, $options, $O) {
		if (empty($O->tags)) $O->load_data(array('tags'));
		if (count($O->tags) > 0) return true; else return false;
	}

	function has_variations ($result, $options, $O) { return ($O->variations == "on" && (!empty($O->options['v']) || !empty($O->options))); }

	function id ($result, $options, $O) { return $O->id; }

	function image ($result, $options, $O) {
		global $Shopp;
		if (empty($O->images)) $O->load_data(array('images'));
		if (!(count($O->images) > 0)) return "";

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
			for ($i = 0; $i < count($O->images); $i++) {
				if ($img->id == $id) {
					$img = $O->images[$i]; //break;
				}
			}
			if (!$img) return "";
		}

		// Select image by index position in the list
		if ($index !== false && isset($O->images[$index]))
			$img = $O->images[$index];

		// Use the current image pointer by default
		if (!$img) $img = current($O->images);

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
		if (SHOPP_PERMALINKS) $src = trailingslashit($src).$img->filename;

		if ($size != "original")
			$src = add_query_string($img->resizing($width,$height,$scale,$sharpen,$quality,$fill),$src);

		switch (strtolower($property)) {
			case "id": return $img->id; break;
			case "url":
			case "src": return $src; break;
			case "title": return $title; break;
			case "alt": return $alt; break;
			case "width": return $width_a; break;
			case "height": return $height_a; break;
			case "class": return $class; break;
		}

		$imgtag = '<img src="'.$src.'"'.$titleattr.' alt="'.$alt.'" width="'.$width_a.'" height="'.$height_a.'" '.$classes.' />';

		if (value_is_true($zoom))
			return '<a href="'.shoppurl($img->id,'images').'/'.$img->filename.'" class="'.$zoomfx.'" rel="product-'.$O->id.'">'.$imgtag.'</a>';

		return $imgtag;
	}

	function images ($result, $options, $O) {
		if (!$O->images) return false;
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

	function in_category ($result, $options, $O) {
		if (empty($O->categories)) $O->load_data(array('categories'));
		if (isset($options['id'])) $field = "id";
		if (isset($options['name'])) $field = "name";
		if (isset($options['slug'])) $field = "slug";
		foreach ($O->categories as $category)
			if ($category->{$field} == $options[$field]) return true;
		return false;
	}

	function input ($result, $options, $O) {
		$select_attrs = array('title','required','class','disabled','required','size','tabindex','accesskey');
		$submit_attrs = array('title','class','value','disabled','tabindex','accesskey');

		if (!isset($options['type']) ||
			($options['type'] != "menu" && $options['type'] != "textarea" && !valid_input($options['type']))) $options['type'] = "text";
		if (!isset($options['name'])) return "";
		if ($options['type'] == "menu") {
			$result = '<select name="products['.$O->id.'][data]['.$options['name'].']" id="data-'.$options['name'].'-'.$O->id.'"'.inputattrs($options,$select_attrs).'>';
			if (isset($options['options']))
				$menuoptions = preg_split('/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/',$options['options']);
			if (is_array($menuoptions)) {
				foreach($menuoptions as $option) {
					$selected = "";
					$option = trim($option,'"');
					if (isset($options['default']) && $options['default'] == $option)
						$selected = ' selected="selected"';
					$result .= '<option value="'.$option.'"'.$selected.'>'.$option.'</option>';
				}
			}
			$result .= '</select>';
		} elseif ($options['type'] == "textarea") {
			if (isset($options['cols'])) $cols = ' cols="'.$options['cols'].'"';
			if (isset($options['rows'])) $rows = ' rows="'.$options['rows'].'"';
			$result .= '<textarea name="products['.$O->id.'][data]['.$options['name'].']" id="data-'.$options['name'].'-'.$O->id.'"'.$cols.$rows.inputattrs($options).'>'.$options['value'].'</textarea>';
		} else {
			$result = '<input type="'.$options['type'].'" name="products['.$O->id.'][data]['.$options['name'].']" id="data-'.$options['name'].'-'.$O->id.'"'.inputattrs($options).' />';
		}

		return $result;
	}

	function is_featured ($result, $options, $O) { return ($O->featured == "on"); }

	function name ($result, $options, $O) { return apply_filters('shopp_product_name',$O->name); }

	function on_sale ($result, $options, $O) {
		if (empty($O->prices)) $O->load_data(array('prices'));
		if (empty($O->prices)) return false;
		return $O->onsale;
	}

	function out_of_stock ($result, $options, $O) {
		global $Shopp;
		if ($O->outofstock) {
			$label = isset($options['label'])?$options['label']:$Shopp->Settings->get('outofstock_text');
			$string = '<span class="outofstock">'.$label.'</span>';
			return $string;
		} else return false;
	}

	function price ($result, $options, $O) {
		if (empty($O->prices)) $O->load_data(array('prices'));
		$defaults = array(
			'taxes' => null,
			'starting' => '',
			'property' => 'price'
		);
		$options = array_merge($defaults,$options);
		extract($options);

		if (!is_null($taxes)) $taxes = value_is_true($taxes);

		$min = $O->min[$property];
		$mintax = $O->min[$property.'_tax'];

		$max = $O->max[$property];
		$maxtax = $O->max[$property.'_tax'];

		$taxrate = shopp_taxrate($taxes,$O->prices[0]->tax,$O);

		if ('saleprice' == $property) $pricetag = $O->prices[0]->promoprice;
		else $pricetag = $O->prices[0]->price;

		if (count($O->prices) > 0) {
			$taxrate = shopp_taxrate($taxes,true,$O);
			$mintax = $mintax?$min*$taxrate:0;
			$maxtax = $maxtax?$max*$taxrate:0;

			if ($min == $max) return money($min+$mintax);
			else {
				if (!empty($starting)) return "$starting ".money($min+$mintax);
				return money($min+$mintax)." &mdash; ".money($max+$maxtax);
			}
		} else return money($pricetag+($pricetag*$taxrate));
	}

	function quantity ($result, $options, $O) {
		if ($O->outofstock) return false;

		$inputs = array('text','menu');
		$defaults = array(
			'value' => 1,
			'input' => 'text', // accepts text,menu
			'labelpos' => 'before',
			'label' => '',
			'options' => '1-15,20,25,30,40,50,75,100',
			'size' => 3
		);
		$options = array_merge($defaults,$options);
		$_options = $options;
		extract($options);

		unset($_options['label']); // Interferes with the text input value when passed to inputattrs()
		$labeling = '<label for="quantity-'.$O->id.'">'.$label.'</label>';

		if (!isset($O->_prices_loop)) reset($O->prices);
		$variation = current($O->prices);
		$_ = array();

		if ("before" == $labelpos) $_[] = $labeling;
		if ("menu" == $input) {
			if ($O->inventory && $O->max['stock'] == 0) return "";

			if (strpos($options,",") !== false) $options = explode(",",$options);
			else $options = array($options);

			$qtys = array();
			foreach ((array)$options as $v) {
				if (strpos($v,"-") !== false) {
					$v = explode("-",$v);
					if ($v[0] >= $v[1]) $qtys[] = $v[0];
					else for ($i = $v[0]; $i < $v[1]+1; $i++) $qtys[] = $i;
				} else $qtys[] = $v;
			}
			$_[] = '<select name="products['.$O->id.'][quantity]" id="quantity-'.$O->id.'">';
			foreach ($qtys as $qty) {
				$amount = $qty;
				$selection = (isset($O->quantity))?$O->quantity:1;
				if ($variation->type == "Donation" && $variation->donation['var'] == "on") {
					if ($variation->donation['min'] == "on" && $amount < $variation->price) continue;
					$amount = money($amount);
					$selection = $variation->price;
				} else {
					if ($O->inventory && $amount > $O->max['stock']) continue;
				}
				$selected = ($qty==$selection)?' selected="selected"':'';
				$_[] = '<option'.$selected.' value="'.$qty.'">'.$amount.'</option>';
			}
			$_[] = '</select>';
		} elseif (valid_input($input)) {
			if ($variation->type == "Donation" && $variation->donation['var'] == "on") {
				if ($variation->donation['min']) $_options['value'] = $variation->price;
				$_options['class'] .= " currency";
			}
			$_[] = '<input type="'.$input.'" name="products['.$O->id.'][quantity]" id="quantity-'.$O->id.'"'.inputattrs($_options).' />';
		}

		if ("after" == $labelpos) $_[] = $labeling;
		return join("\n",$_);
	}

	function relevance ($result, $options, $O) { return (string)$O->score; }

	function savings ($result, $options, $O) {
		if (empty($O->prices)) $O->load_data(array('prices'));
		if (!isset($options['taxes'])) $options['taxes'] = null;

		$taxrate = shopp_taxrate($options['taxes']);
		$range = false;

		if (!isset($options['show'])) $options['show'] = '';
		if ($options['show'] == "%" || $options['show'] == "percent") {
			if ($O->options > 1) {
				if (round($O->min['savings']) != round($O->max['savings'])) {
					$range = array($O->min['savings'],$O->max['savings']);
					sort($range);
				}
				if (!$range) return percentage($O->min['savings'],array('precision' => 0)); // No price range
				else return percentage($range[0],array('precision' => 0))." &mdash; ".percentage($range[1],array('precision' => 0));
			} else return percentage($O->max['savings'],array('precision' => 0));
		} else {
			if ($O->options > 1) {
				if (round($O->min['saved']) != round($O->max['saved'])) {
					$range = array($O->min['saved'],$O->max['saved']);
					sort($range);
				}
				if (!$range) return money($O->min['saved']+($O->min['saved']*$taxrate)); // No price range
				else return money($range[0]+($range[0]*$taxrate))." &mdash; ".money($range[1]+($range[1]*$taxrate));
			} else return money($O->max['saved']+($O->max['saved']*$taxrate));
		}
	}

	function slug ($result, $options, $O) { return $O->slug; }

	function spec ($result, $options, $O) {
		$string = "";
		$separator = ": ";
		$delimiter = ", ";
		if (isset($options['separator'])) $separator = $options['separator'];
		if (isset($options['delimiter'])) $separator = $options['delimiter'];

		$spec = current($O->specs);
		if (is_array($spec->value)) $spec->value = join($delimiter,$spec->value);

		if (isset($options['name'])
			&& !empty($options['name'])
			&& isset($O->specskey[$options['name']])) {
				$spec = $O->specskey[$options['name']];
				if (is_array($spec)) {
					if (isset($options['index'])) {
						foreach ($spec as $index => $entry)
							if ($index+1 == $options['index'])
								$content = $entry->value;
					} else {
						foreach ($spec as $entry) $contents[] = $entry->value;
						$content = join($delimiter,$contents);
					}
				} else $content = $spec->value;
			$string = apply_filters('shopp_product_spec',$content);
			return $string;
		}

		if (isset($options['name']) && isset($options['content']))
			$string = "{$spec->name}{$separator}".apply_filters('shopp_product_spec',$spec->value);
		elseif (isset($options['name'])) $string = $spec->name;
		elseif (isset($options['content'])) $string = apply_filters('shopp_product_spec',$spec->value);
		else $string = "{$spec->name}{$separator}".apply_filters('shopp_product_spec',$spec->value);
		return $string;
	}

	function specs ($result, $options, $O) {
		if (!isset($O->_specs_loop)) {
			reset($O->specs);
			$O->_specs_loop = true;
		} else next($O->specs);

		if (current($O->specs) !== false) return true;
		else {
			unset($O->_specs_loop);
			return false;
		}
	}

	function summary ($result, $options, $O) { return apply_filters('shopp_product_summary',$O->summary); }

	function tag ($result, $options, $O) {
		$tag = current($O->tags);
		if (isset($options['show'])) {
			if ($options['show'] == "id") return $tag->id;
		}
		return $tag->name;
	}

	function tagged ($result, $options, $O) {
		if (empty($O->tags)) $O->load_data(array('tags'));
		if (isset($options['id'])) $field = "id";
		if (isset($options['name'])) $field = "name";
		foreach ($O->tags as $tag)
			if ($tag->{$field} == $options[$field]) return true;
		return false;
	}

	function tags ($result, $options, $O) {
		if (!isset($O->_tags_loop)) {
			reset($O->tags);
			$O->_tags_loop = true;
		} else next($O->tags);

		if (current($O->tags) !== false) return true;
		else {
			unset($O->_tags_loop);
			return false;
		}
	}

	function taxrate ($result, $options, $O) { return shopp_taxrate(null,true,$O); }

	function url ($result, $options, $O) { return shoppurl(SHOPP_PRETTYURLS?$O->slug:array('s_pid'=>$O->id)); }

	function variation ($result, $options, $O) {
		global $Shopp;
		$variation = current($O->prices);

		if (!isset($options['taxes'])) $options['taxes'] = null;
		else $options['taxes'] = value_is_true($options['taxes']);
		$taxrate = shopp_taxrate($options['taxes'],$variation->tax,$O);

		$weightunit = (isset($options['units']) && !value_is_true($options['units']) ) ? false : $Shopp->Settings->get('weight_unit');

		$string = '';
		if (array_key_exists('id',$options)) $string .= $variation->id;
		if (array_key_exists('label',$options)) $string .= $variation->label;
		if (array_key_exists('type',$options)) $string .= $variation->type;
		if (array_key_exists('sku',$options)) $string .= $variation->sku;
		if (array_key_exists('price',$options)) $string .= money($variation->price+($variation->price*$taxrate));
		if (array_key_exists('saleprice',$options)) {
			if (isset($options['promos']) && !value_is_true($options['promos'])) {
				$string .= money($variation->saleprice+($variation->saleprice*$taxrate));
			} else $string .= money($variation->promoprice+($variation->promoprice*$taxrate));
		}
		if (array_key_exists('stock',$options)) $string .= $variation->stock;
		if (array_key_exists('weight',$options)) $string .= round($variation->weight, 3) . ($weightunit ? " $weightunit" : false);
		if (array_key_exists('shipfee',$options)) $string .= money(floatvalue($variation->shipfee));
		if (array_key_exists('sale',$options)) return ($variation->sale == "on");
		if (array_key_exists('shipping',$options)) return ($variation->shipping == "on");
		if (array_key_exists('tax',$options)) return ($variation->tax == "on");
		if (array_key_exists('inventory',$options)) return ($variation->inventory == "on");
		return $string;
	}

	function variations ($result, $options, $O) {
		global $Shopp;
		$string = "";

		if (!isset($options['mode'])) {
			if (!isset($O->_prices_loop)) {
				reset($O->prices);
				$O->_prices_loop = true;
			} else next($O->prices);
			$price = current($O->prices);

			if ($price && ($price->type == 'N/A' || $price->context != 'variation'))
				next($O->prices);

			if (current($O->prices) !== false) return true;
			else {
				unset($O->_prices_loop);
				return false;
			}
			return true;
		}

		if ($O->outofstock) return false; // Completely out of stock, hide menus
		if (!isset($options['taxes'])) $options['taxes'] = null;

		$defaults = array(
			'defaults' => '',
			'disabled' => 'show',
			'pricetags' => 'show',
			'before_menu' => '',
			'after_menu' => '',
			'label' => 'on',
			'required' => __('You must select the options for this item before you can add it to your shopping cart.','Shopp')
			);
		$options = array_merge($defaults,$options);

		if ($options['mode'] == "single") {
			if (!empty($options['before_menu'])) $string .= $options['before_menu']."\n";
			if (value_is_true($options['label'])) $string .= '<label for="product-options'.$O->id.'">'. __('Options').': </label> '."\n";

			$string .= '<select name="products['.$O->id.'][price]" id="product-options'.$O->id.'">';
			if (!empty($options['defaults'])) $string .= '<option value="">'.$options['defaults'].'</option>'."\n";

			foreach ($O->prices as $pricetag) {
				if ($pricetag->context != "variation") continue;

				if (!isset($options['taxes']))
					$taxrate = shopp_taxrate(null,$pricetag->tax);
				else $taxrate = shopp_taxrate(value_is_true($options['taxes']),$pricetag->tax);
				$currently = ($pricetag->sale == "on")?$pricetag->promoprice:$pricetag->price;
				$disabled = ($pricetag->inventory == "on" && $pricetag->stock == 0)?' disabled="disabled"':'';

				$price = '  ('.money($currently).')';
				if ($pricetag->type != "N/A")
					$string .= '<option value="'.$pricetag->id.'"'.$disabled.'>'.$pricetag->label.$price.'</option>'."\n";
			}
			$string .= '</select>';
			if (!empty($options['after_menu'])) $string .= $options['after_menu']."\n";

		} else {
			if (!isset($O->options)) return;

			$menuoptions = $O->options;
			if (!empty($O->options['v'])) $menuoptions = $O->options['v'];

			$baseop = $Shopp->Settings->get('base_operations');
			$precision = $baseop['currency']['format']['precision'];

			if (!isset($options['taxes']))
				$taxrate = shopp_taxrate(null,true,$O);
			else $taxrate = shopp_taxrate(value_is_true($options['taxes']),true,$O);

			$pricekeys = array();
			foreach ($O->pricekey as $key => $pricing) {
				$filter = array('');
				$_ = new StdClass();
				if ($pricing->type != "Donation")
					$_->p = ((isset($pricing->onsale)
								&& $pricing->onsale == "on")?
									(float)$pricing->promoprice:
									(float)$pricing->price);
				$_->i = ($pricing->inventory == "on");
				$_->s = ($pricing->inventory == "on")?$pricing->stock:false;
				$_->tax = ($pricing->tax == "on");
				$_->t = $pricing->type;
				$pricekeys[$key] = $_;
			}

			ob_start();
?><?php if (!empty($options['defaults'])): ?>
sjss.opdef = true;
<?php endif; ?>
<?php if (!empty($options['required'])): ?>
sjss.opreq = "<?php echo $options['required']; ?>";
<?php endif; ?>
pricetags[<?php echo $O->id; ?>] = <?php echo json_encode($pricekeys); ?>;
new ProductOptionsMenus('select<?php if (!empty($Shopp->Category->slug)) echo ".category-".$Shopp->Category->slug; ?>.product<?php echo $O->id; ?>.options',{<?php if ($options['disabled'] == "hide") echo "disabled:false,"; ?><?php if ($options['pricetags'] == "hide") echo "pricetags:false,"; ?><?php if (!empty($taxrate)) echo "taxrate:$taxrate,"?>prices:pricetags[<?php echo $O->id; ?>]});
<?php
			$script = ob_get_contents();
			ob_end_clean();

			add_storefrontjs($script);

			foreach ($menuoptions as $id => $menu) {
				if (!empty($options['before_menu'])) $string .= $options['before_menu']."\n";
				if (value_is_true($options['label'])) $string .= '<label for="options-'.$menu['id'].'">'.$menu['name'].'</label> '."\n";
				$category_class = isset($Shopp->Category->slug)?'category-'.$Shopp->Category->slug:'';
				$string .= '<select name="products['.$O->id.'][options][]" class="'.$category_class.' product'.$O->id.' options" id="options-'.$menu['id'].'">';
				if (!empty($options['defaults'])) $string .= '<option value="">'.$options['defaults'].'</option>'."\n";
				foreach ($menu['options'] as $key => $option)
					$string .= '<option value="'.$option['id'].'">'.$option['name'].'</option>'."\n";

				$string .= '</select>';
			}
			if (!empty($options['after_menu'])) $string .= $options['after_menu']."\n";
		}

		return $string;
	}

	function weight ($result, $options, $O) {
		global $Shopp;
		if(empty($O->prices)) $O->load_data(array('prices'));
		$defaults = array(
			'unit' => $Shopp->Settings->get('weight_unit'),
			'min' => $O->min['weight'],
			'max' => $O->max['weight'],
			'units' => true,
			'convert' => false
		);
		$options = array_merge($defaults,$options);
		extract($options);

		if(!isset($O->min['weight'])) return false;

		if ($convert !== false) {
			$min = convert_unit($min,$convert);
			$max = convert_unit($max,$convert);
			if (is_null($units)) $units = true;
			$unit = $convert;
		}

		$range = false;
		if ($min != $max) {
			$range = array($min,$max);
			sort($range);
		}

		$string = ($min == $max)?round($min,3):round($range[0],3)." - ".round($range[1],3);
		$string .= value_is_true($units) ? " $unit" : "";
		return $string;
	}

}

?>