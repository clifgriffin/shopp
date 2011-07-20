<?php
/**
 * Collection API
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, February 25, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package Shopp
 * @since 1.2
 * @subpackage Collection
 **/

/**
 * Registers a smart collection of products
 *
 * @author Jonathan Davis
 * @since 1.2
 *
 * @param string $name Class name of the smart collection
 * @return void
 **/
function shopp_register_collection ( $name = '' ) {
	if ( empty($name) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Collection name required.", __FUNCTION__ ,SHOPP_DEBUG_ERR);
		return false;
	}

	global $Shopp;
	if (empty($Shopp)) return;
	$slug = get_class_property($name,'_slug');
	$Shopp->Collections[$slug] = $name;

	add_rewrite_tag("%shopp_collection%",'collection/([^/]+)');
	add_permastruct('shopp_collection', Storefront::slug()."/%shopp_collection%", true);

	$apicall = create_function ('$result, $options, $O',
		'global $Shopp; $Shopp->Category = new '.$name.'($options);
		return ShoppCatalogThemeAPI::category($result, $options, $O);'
	);

	$slugs = array($slug);
	$altslugs = get_class_property($name,'_altslugs');
	if (is_array($altslugs)) $slugs = $altslugs;

	foreach ($slugs as $collection) {
		// @deprecated Remove the catalog-products tag in favor of catalog-collection
		add_filter( 'shopp_themeapi_catalog_'.$collection.'products', $apicall, 10, 3 );
		add_filter( 'shopp_themeapi_catalog_'.$collection.'collection', $apicall, 10, 3 );
	}
}

/**
 * shopp_add_product_category - Add a product category
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param string $name (required) The category name.
 * @param string $description (optional) The category description.
 * @param int $parent (optional) Parent category id.
 * @return bool|int false on error, int category id on success.
 **/
function shopp_add_product_category ( $name = '', $description = '', $parent = false ) {
	if ( empty($name) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Category name required.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$Category = new ProductCategory();
	$Category->name = $name;

	if ( $parent ) {
		if ( ! term_exists($parent, ProductCategory::$taxonomy) ) {
			if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Non-existent parent $parent.",__FUNCTION__,SHOPP_DEBUG_ERR);
			return false;
		}
		$Category->parent = $parent;
	}

	if ( $description ) $Category->description = $description;

	$Category->save();
	return $Category->id;
}

/**
 * shopp_rmv_product_category - remove a product category by id
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $id (required) The category id
 * @return bool true on success, false on failure
 **/
function shopp_rmv_product_category ( $id = false ) {
	if ( ! $id ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Category id required.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	$Category = new ProductCategory($id);

	if ( empty($Category->id) ) return false;

	shopp_rmv_meta ( $id, 'category' );
	return $Category->delete();
}

/**
 * shopp_add_product_tag - add a product tag term.  If the tag already exists, will return the id of that tag.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param string $tag (required) The tag term to add.
 * @return bool/int - The tag id, false on failure
 **/
function shopp_add_product_tag ( $tag = '' ) {
	if ( ! $tag  ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Tag name required.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	$Tag = new ProductTag( array('name'=>$tag) );

	if ( empty($Tag->id) ) $Tag->save();

	return $Tag->id;
}

/**
 * shopp_rmv_product_tag - remove a tag term by name or id
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param mixed $tag (required) int tag term_id or string tag name
 * @return bool true on success, false on failure
 **/
function shopp_rmv_product_tag ( $tag = '' ) {
	if ( ! $tag ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Tag name or id required.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	if ( is_numeric($tag) ) $id = $tag;
	else $name = $tag;

	if ($name) $Tag = new ProductTag( array('name'=>$name ) );
	else $Tag = new ProductTag($id);

	if ( empty($Tag->id) ) return false;

	$success = shopp_rmv_meta ( $id, 'tag' );
	return $success && $Tag->delete();
}

/**
 * shopp_add_product_term - Add a taxonomical term to a product.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param string $term (required) The term name to be added to the product.
 * @param string $taxonomy (optional default:shopp_category) The taxonomy name.  The taxonomy specified must be of the Shopp product object type.
 * @return int|bool term id on success, false on failure
 **/
function shopp_add_product_term ( $term = '', $taxonomy = 'shopp_category' ) {
	if ( ! in_array($taxonomy, get_object_taxonomies(Product::$posttype) ) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: $taxonomy not a shopp product taxonomy.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	if ( ! $term ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: term required.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	$term = wp_create_term( $term, $taxonomy );
	return ( is_array($term) && $term['term_id'] ? $term['term_id'] : false );
}

/**
 * shopp_rmv_product_term - remove a taxonomical term
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $term (required) The term id
 * @param string $taxonomy (optional default=shopp_category) The taxonomy name the term belongs to.  The taxonomy specified must be of the Shopp product object type.
 * @return bool true on success, false on failure
 **/
function shopp_rmv_product_term ( $term = '', $taxonomy = 'shopp_category' ) {
	if ( ! in_array($taxonomy, get_object_taxonomies(Product::$posttype) ) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: $taxonomy not a shopp product taxonomy.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	if ( ! $term ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: term required.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	return true === wp_delete_term( $term, $taxonomy );
}

/**
 * shopp_product_categories - get an array of all product categories
 *
 * @uses get_terms - defaults get=>all, hide_empty=>false
 * @author John Dillick
 * @since 1.2
 *
 * @param array $args get_terms parameters
 * @return array ProductCategoy objects
 **/
function shopp_product_categories ( $args = array() ) {
	$defaults = array( 'get' => 'all', 'hide_empty' => false );
	$args = wp_parse_args ( $args, $defaults );
	$args['fields'] = 'ids';

	$terms = get_terms( ProductCategory::$taxonomy, $args );
	if ( ! is_array($terms) ) return false;

	$categories = array();
	foreach ( $terms as $term ) {
		$categories[$term] = new ProductCategory($term);
	}
	return $categories;
}

/**
 * shopp_product_tags - get an array of all product tags
 *
 * @uses get_terms - defaults get=>all, hide_empty=>false
 * @author John Dillick
 * @since 1.2
 *
 * @param array $args get_terms parameters
 * @return array ProductTags objects
 **/
function shopp_product_tags ( $args = array() ) {
	$defaults = array( 'get' => 'all', 'hide_empty' => false );
	$args = wp_parse_args ( $args, $defaults );
	$args['fields'] = 'ids';

	$terms = get_terms( ProductTag::$taxonomy, $args );
	if ( ! is_array($terms) ) return false;

	$tags = array();
	foreach ( $terms as $term ) {
		$tags[$term] = new ProductTag($term);
	}

	return $tags;
}

/**
 * shopp_subcategories - get array of category objects that are children of specified category
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $category id of the category you wish to get subcategories for
 * @return array ProductCategory objects
 **/
function shopp_subcategories ( $category = 0, $args = array() ) {
	$defaults = array( 'get' => '', 'child_of' => $category );
	$args = wp_parse_args ( $args, $defaults );

	return shopp_product_categories ( $args );
}

/**
 * shopp_category_products - get a list of product objects for the given category
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $category (required) The category id
 * @param array $options loading options
 * @return array Product objects
 **/
function shopp_category_products ( $category = 0, $options = array() ) {
	$defaults = array(
		'limit' => 1000,
		'debug' => false
	);

	$options = wp_parse_args($options, $defaults);

	if ( ! term_exists( $category, ProductCategory::$taxonomy ) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: $category not a valid Shopp product category.",__FUNCTION,SHOPP_DEBUG_ERR);
		return false;
	}

	$Category = new ProductCategory( $category );
	$Category->load($options);
	return ! empty($Category->products) ? $Category->products : array();
}

/**
 * shopp_tag_products - get a list of product objects for the given tag
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int|string $tag (required) The product tag name/id
 * @param array $options loading options
 * @return array Product objects
 **/
function shopp_tag_products ( $tag = false, $options = array() ) {
	$defaults = array(
		'limit' => 1000,
		'debug' => false
	);

	$options = wp_parse_args($options, $defaults);

	if ( ! $term = term_exists( $tag, ProductTag::$taxonomy ) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: $tag not a valid Shopp product tag.",__FUNCTION,SHOPP_DEBUG_ERR);
		return false;
	}

	$Tag = new ProductTag( $term['term_id'] );
	$Tag->load( $options );

	return ! empty($Tag->products) ? $Tag->products : array();
}

/**
 * shopp_tag_products - get a list of product objects for the given term and taxonomy
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $term (required) The term id
 * @param string $taxonomy The taxonomy name
 * @param array $options loading options
 * @return array Product objects
 **/
function shopp_term_products ( $term = false, $taxonomy = 'shopp_category', $options = array() ) {
	$defaults = array(
		'limit' => 1000,
		'debug' => false
	);

	$options = wp_parse_args($options, $defaults);

	if ( ! taxonomy_exists( $taxonomy ) || ! in_array($taxonomy, get_object_taxonomies(Product::$posttype) ) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Invalid Shopp taxonomy $taxonomy.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}

	if ( ! $term = term_exists( $term, $taxonomy ) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: $term not a valid Shopp $taxonomy term.",__FUNCTION,SHOPP_DEBUG_ERR);
		return false;
	}

	$Tax = new ProductTaxonomy();
	$Tax->id = $term['term_id'];
	$Tax->taxonomy = $taxonomy;
	$Tax->load( $options );

	return ! empty($Tax->products) ? $Tax->products : array();
}

/**
 * shopp_catalog_count - get a count of all products in the catalog
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param string $status (optional default:publish) the product's publish status
 * @return int number of products
 **/
function shopp_catalog_count ( $status = 'publish' ) {
	$C = wp_count_posts( Product::$posttype );
	$counts = get_object_vars($C);

	if ( 'total' == $status ) {
		$total = 0;
		foreach ( $counts as $count ) $total += $count;
		return $total;
	}

	if ( isset($counts[$status]) ) return $counts[$status];

	return 0;
}

/**
 * shopp_category_count - get a count of all products in the category
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $category (required) the category id
 * @param bool $children (optional default:false) include the children in the count
 * @return int number of products in the category
 **/
function shopp_category_count (	$category = 0, $children = false ) {
	if ( ! term_exists( $category, ProductCategory::$taxonomy ) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: $category not a valid Shopp product category.",__FUNCTION,SHOPP_DEBUG_ERR);
		return false;
	}

	$args = array( 	'post_type' => Product::$posttype,
					'suppress_filters' => true,
					'tax_query' => array(
							array( 	'taxonomy' => ProductCategory::$taxonomy,
									'terms' => array($category),
									'include_children' => $children
									)
					)
				);
	$Q = new WP_Query( $args );

	return $Q->found_posts;

}

/**
 * shopp_subcategory_count - get count of sub categories in a product category
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $category (required) the category id
 * @return int count of subcategories
 **/
function shopp_subcategory_count ( $category = 0 ) {
	if ( ! term_exists( $category, ProductCategory::$taxonomy ) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: $category not a valid Shopp product category.",__FUNCTION,SHOPP_DEBUG_ERR);
		return false;
	}

	$children = get_term_children( $category, ProductCategory::$taxonomy );

	return count($children);
}

/**
 * shopp_product_categories_count - get count of categories associated with a product
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $product (required) the product id
 * @return int count of categories
 **/
function shopp_product_categories_count ( $product ) {
	$Product = new Product( $product );
	if ( empty($Product->id) ) {
		if(SHOPP_DEBUG) new ShoppError(__FUNCTION__." failed: Invalid product $product.",__FUNCTION__,SHOPP_DEBUG_ERR);
		return false;
	}
	$terms = wp_get_post_terms( $product, ProductCategory::$taxonomy, array());

	return count($terms);
}


?>