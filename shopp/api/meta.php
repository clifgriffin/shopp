<?php
/**
 * Meta API
 *
 * plugin api for getting and setting Shopp object meta data
 *
 * @author Jonathan Davis, John Dillick
 * @version 1.0
 * @copyright Ingenesis Limited, June 23, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage shopp
 **/

/**
 * shopp_meta - Returns meta data assigned to an object.
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int (optional) $id of the meta entry, or object id of the object the Shopp meta is attached to
 * @param string (optional) $context the object type that the object id refers to.
 * @param string (optional) $name the name of the meta data
 * @param string (optional) $type the data type of meta data (examples meta, spec, download, image, yourdatatype )
 * @return array of stdClass Object meta values, with parent, type, name, and value properties
 *
 * Usage Examples:
 * shopp_meta(<id>) - meta record by id
 * shopp_meta([id], [context], [type], [name]) - pick one or more, id is the id of the parent contextual object if context is specified
 *
 * shopp_meta(1) loads meta record 1
 * shopp_meta(5,'product','spec','Producer') loads spec named Producer of product id 5
 * shopp_meta(false, 'product','downloads') loads all product downloads
 * shopp_meta(false, 'price') loads all meta data associated with variants
 *
 **/
function shopp_meta ( $id = false, $context = false, $name = false, $type = 'meta' ) {
	$values = array();

	if ( ! ( $id || $context || $name ) ) {
		if(SHOPP_DEBUG) new ShoppError('shopp_meta failed: No parameters specified.', 'shopp_meta',SHOPP_DEBUG_ERR);
		return;
	}

	// Load meta by id
	if ( $id && false === $context ) {
		$meta = new MetaObject();
		$meta->load($id);

		if ( empty($meta->id) ) {
			if(SHOPP_DEBUG) new ShoppError("shopp_meta load failed: No such meta with id $id or missing context.",'shopp_meta',SHOPP_DEBUG_ERR);
		}

		return $meta->value;
	}

	// Load one or more meta
	$loading = array();
	if ( $id && $context ) $loading['parent'] = $id; // if context is specified, id will always be parent object
	if ( $context ) $loading['context'] = $context;
	if ( $type ) $loading['type'] = $type;
	if ( $name ) $loading['name'] = $name;

	$Meta = new ObjectMeta();
	$Meta->load( $loading );

	if ( empty($Meta->meta) ) return;

	foreach ( $Meta->meta as $meta ) {
		if( ! isset($values[$meta->id]) ) $values[$meta->id] = new stdClass;
		$values[$meta->id]->parent = $meta->parent;
		$values[$meta->id]->type = $meta->type;
		$values[$meta->id]->name = $meta->name;
		if ( empty($meta->value) && $meta->numeral > 0 ) $meta->value = $meta->numeral;
		$values[$meta->id]->value = $meta->value;
	}

	if ( $id && $context && $type && $name and 1 == count($values)) {
		return reset($values)->value;
	}

	return $values;
}

/**
 * shopp_meta_exists - Determine if one or more meta records exist based on some combination of context, and/or type, and/or name of the metadata
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param string $name (optional) name of the meta entry
 * @param string $context (optional) object context of the meta entry
 * @param string $type (default: meta) (optional) type of the meta entry
 * @return bool true if one or more meta entries exist
 *
 * One or more of the parameters must be specified.
 *
 **/
function shopp_meta_exists ( $name = false, $context = false, $type = 'meta' ) {
	if ( ! ( $name || $context ) ) return false;
	$meta = shopp_meta(false, $context, $name, $type);

	return ( ! empty($meta) );
}

/**
 * shopp_set_meta - create or update a new meta record
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int (required on creation/update) $id id of an existing meta entry, or with context the parent object of a new meta entry
 * @param string (required on update) $context the parent object type of the meta entry (example product, price, and more)
 * @param string (required on update) $name the name of the meta entry, more specific than type
 * @param mixed (optional default: false) $value the value stored to the meta entry
 * @param string (optional default: meta) $type the type or classification of the meta data
 * @param string (optional) $valuetype (default: 'value') 'numeral' or 'value', if the value is numeric, 'numeric' will store in numeric field.
 * @return bool true on successful save or update, fail on failure
 **/
function shopp_set_meta ( $id = false, $context = false, $name = false, $value = false, $type = 'meta', $valuetype = 'value' ) {
	if ( ! ( $id || $id && $context ) ) {
		if(SHOPP_DEBUG) new ShoppError("shopp_set_meta failed: Must specify at least a meta id or parent id and context.",'shopp_set_meta',SHOPP_DEBUG_ERR);
		return false;
	}

	$record = array();
	if ( $context ) $record['context'] = $context;
	if ( $type ) $record['type'] = $type;
	if ( $name ) $record['name'] = $name;

	$valuefield = array();
	$valuefield[( 'numeral' == $valuetype && is_numeric($value) ? 'numeral' : 'value' )]  = $value;

	// save existing meta record by meta id
	if ( $id && ! $context ) {
		$meta = new MetaObject();
		$meta->load($id);
		if ( ! empty($meta->id) ) {
			$meta->updates( array_merge($record, $valuefield) );
			$meta->save();
			return true;
		} else {
			if(SHOPP_DEBUG) new ShoppError("shopp_set_meta failed: No metadata with id $id.",'shopp_set_meta',SHOPP_DEBUG_ERR);
			return false;
		}
	}

	// fully spec'd meta entry
	if ( $id && $context && $type && $name ) {
		$meta = new MetaObject();
		$meta->load( array_merge( $record, array( 'parent'=>$id, 'context'=>$context ) ) );
		$meta->updates( array_merge(array( 'parent'=>$id, 'context'=>$context ), $record, $valuefield) );
		$meta->save();
		return true;
	}
	if(SHOPP_DEBUG) new ShoppError('shopp_set_meta failed: id, context, type, and name are required parameters for this context.','shopp_set_meta',SHOPP_DEBUG_ERR);
	return false;
}

/**
 * shopp_rmv_meta - remove a meta entry by meta id, or parent id, context, type, and name
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $id (required) - meta entry id or, with context, the parent object id
 * @param int $context (required for parent object id) - the parent object context
 * @param string $name (required with parent object context) - the meta name
 * @param string $type  (optional default: meta) - the meta type
 * @return bool true if the meta entry was removed, false on failure
 **/
function shopp_rmv_meta ( $id = false, $context = false, $name = false, $type = 'meta' ) {
	if ( ! ( $id || $id && $context ) ) {
		if(SHOPP_DEBUG) new ShoppError("shopp_rmv_meta failed: Must specify at least a meta id or parent id and context.",'shopp_rmv_meta',SHOPP_DEBUG_ERR);
		return false;
	}

	// save existing meta record by meta id
	if ( $id && ! $context ) {
		$meta = new MetaObject();
		$meta->load($id);
		if ( ! empty($meta->id) ) {
			$meta->delete();
			return true;
		} else {
			if(SHOPP_DEBUG) new ShoppError("shopp_rmv_meta failed: No metadata with id $id.",'shopp_rmv_meta',SHOPP_DEBUG_ERR);
			return false;
		}
	}

	// fully spec'd meta entry
	if ( $id && $context && $type && $name ) {
		$meta = new MetaObject();
		$meta->load( array( 'parent'=>$id, 'context'=>$context, 'type' => $type, 'name' => $name ) );
		$meta->delete();
		return true;
	}
	if(SHOPP_DEBUG) new ShoppError('shopp_rmv_meta failed: id, context, type, and name are required parameters for this context.','shopp_rmv_meta',SHOPP_DEBUG_ERR);
	return false;
}

/**
 * shopp_product_meta - get a product meta entry by product id, type, and name
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $product product id
 * @param string $name the name of the meta data
 * @param string $type (default: meta) the meta data type
 * @return array of stdClass Object meta values, with parent, type, name, and value properties
 **/
function shopp_product_meta ( $product = false, $name = false, $type = 'meta' ) {
	return shopp_meta( $product, 'product', $name, $type );
}

/**
 * Full Description...
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int (required) $product the product id
 * @param string (required) $name the name of the meta entry
 * @param string (optional default: meta) $type the type of meta entry
 * @return bool returns true if meta data exists on the product, false if not
 **/
function shopp_product_has_meta ( $product = false, $name = false, $type = 'meta' ) {
	if ( ! $name || ! $product ) return false;
	$meta = shopp_meta($product, 'product', $name, $type);

	return ( ! empty($meta) );
}

/**
 * shopp_product_meta_list - get an array of meta values on the product
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param type $var Description...
 * @return array list of values keyed by name, false on failure
 **/
function shopp_product_meta_list ( $product = false, $type = 'meta' ) {
	if ( ! $product ) {
		if(SHOPP_DEBUG) new ShoppError('shopp_product_meta_list failed: product id required', 'shopp_product_meta_list', SHOPP_DEBUG_ERR);
		return false;
	}
	$metas = shopp_product_meta ( $product, false, $type );

	$results = array();
	foreach ( $metas as $id => $meta ) {
		if ( is_object($meta) ) {
			$results[$meta->name] = $meta->value;
		} else if ( ! empty($meta) ) {
			$results[$id] = $meta;
		}
	}
	return $results;
}

/**
 * shopp_product_meta_count - number of meta entries associated with a product
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int (required) $product the product id
 * @param type (optional default: meta) $type the meta type to count
 * @return int count of meta entries, false on failure
 **/
function shopp_product_meta_count ( $product = false, $type = 'meta' ) {
	if ( ! $product ) {
		if(SHOPP_DEBUG) new ShoppError('shopp_product_meta_count failed: product id required', 'shopp_product_meta_count', SHOPP_DEBUG_ERR);
		return false;
	}
	$meta = shopp_product_meta ( $product, false, $type );
	return count( $meta );
}

/**
 * shopp_set_product_meta - create or update a new product meta record
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int (required on creation/update) $product product object to create/update the meta record on
 * @param string (required on update) $name the name of the meta entry, more specific than type
 * @param mixed (optional default: false) $value the value stored to the meta entry
 * @param string (optional default: meta) $type the type or classification of the meta data
 * @param string (optional) $valuetype (default: 'value') 'numeral' or 'value', if the value is numeric, 'numeric' will store in numeric field.
 * @return bool true on successful save or update, fail on failure
 **/
function shopp_set_product_meta ( $product = false, $name = false, $value = false, $type = 'meta', $valuetype = 'value' ) {
	return shopp_set_meta ( $product, 'product', $name, $value, $type, $valuetype );
}

/**
 * shopp_rmv_product_meta - remove a meta entry by product id and name
 *
 * @author John Dillick
 * @since 1.2
 *
 * @param int $product (required) - product id of meta entry to remove
 * @param string $name (required with parent object context) - the meta name
 * @param string $type  (optional default: meta) - the meta type
 * @return bool true if the meta entry was removed, false on failure
 **/
function shopp_rmv_product_meta ( $product = false, $name = false, $type = 'meta') {
	if ( ! $product && ! $name ) {
		if(SHOPP_DEBUG) new ShoppError('shopp_rmv_product_meta failed: product and name parameters required.','shopp_rmv_product_meta',SHOPP_DEBUG_ERR);
		return false;
	}
	return shopp_rmv_meta ( $product, 'product', $name, $type );
}

?>