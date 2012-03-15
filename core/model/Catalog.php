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
 * @package Shopp
 * @subpackage Catalog
 **/

require("Product.php");
require("Collection.php");

class Catalog {
	static $table = "catalog";

	var $categories = array();
	var $outofstock = false;
	var $type = false; 			// @deprecated

	function __construct () {
		$this->outofstock = (shopp_setting('outofstock_catalog') == "on");
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

		$terms = get_terms(ProductCategory::$taxon);
		foreach ($terms as $term)
			$this->categories[] = new ProductCategory($term);

		if ($showsmart == "before" || $showsmart == "after")
			$this->collections($showsmart);

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
	function collections ($method="after") {
		global $Shopp;
		foreach ($Shopp->Collections as $Collection) {
			$auto = get_class_property($Collection,'_auto');
			if (!$auto) continue;
			$category = new $Collection(array("noload" => true));
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

		$query = "SELECT t.*,count(sc.product) AS products FROM $this->_table AS sc LEFT JOIN $tagtable AS t ON sc.parent=t.id WHERE sc.taxonomy='$taxonomy' GROUP BY t.id ORDER BY t.name ASC$limit";
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
	static function load_collection ($slug,$options=array()) {
		global $Shopp;
		foreach ($Shopp->Collections as $Collection) {
			$Collection_slug = get_class_property($Collection,'_slug');
			if ($slug == $Collection_slug)
				return new $Collection($options);
		}

		$key = "id";
		if (!preg_match("/^\d+$/",$slug)) $key = "slug";
		return new ProductCategory($slug,$key);

	}

	/**
	 * shopp('catalog','...') tags
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 * @version 1.2
	 * @see api/theme/catalog.php
	 * @deprecated Retained for compatibilty
	 *
	 * @param string $property The property to handle
	 * @param array $options (optional) The tag options to process
	 * @return mixed
	 **/
	function tag ($property,$options=array()) {
		$options = array_merge( array('return' => true),shopp_parse_options($options) );
		return shopp($this,$property,$options);
	}

} // END class Catalog

?>