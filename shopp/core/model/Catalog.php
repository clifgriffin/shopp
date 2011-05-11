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

class Catalog extends DatabaseObject {
	static $table = "catalog";
	var $api = 'catalog';

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
		$ct_id = get_catalog_taxonomy_id('category');

		$defaults = array(
			'columns' => "cat.id,cat.parent,cat.name,cat.description,cat.uri,cat.slug,count(DISTINCT pd.id) AS total,IF(SUM(IF(pd.inventory='off',1,0) OR pd.inventory IS NULL)>0,'off','on') AS inventory, SUM(pd.stock) AS stock",
			'where' => array(),
			'joins' => array(
				"LEFT OUTER JOIN $this->_table AS sc FORCE INDEX(assignment) ON sc.parent=cat.id AND sc.taxonomy='$ct_id'",
				"LEFT OUTER JOIN $product_table AS pd ON sc.product=pd.id"
			),
			'limit' => false,
			'orderby' => 'name',
			'order' => 'ASC',
			'parent' => false,
			'ancestry' => false,
			'outofstock' => $this->outofstock,
		);
		$options = array_merge($defaults,$loading);
		extract($options);

		if (!is_array($where)) $where = array($where);

		// if (!$outofstock) $where[] = "(pt.inventory='off' OR (pt.inventory='on' AND pt.stock > 0))";

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
			$this->collections($showsmart);

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
	function collections ($method="after") {
		global $Shopp;
		foreach ($Shopp->Collections as $Collection) {
			if (!isset($Collection::$_auto)) continue;
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
		$taxonomy = get_catalog_taxonomy_id('tag');

		if ($limits) $limit = " LIMIT {$limits[0]},{$limits[1]}";
		else $limit = "";

		$tagtable = DatabaseObject::tablename(CatalogTag::$table);
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
	function load_category ($category,$options=array()) {
		global $Shopp;
		foreach ($Shopp->Collections as $Collection) {
			$Collection_slug = get_class_property($Collection,'_slug');
			if ($category == $Collection_slug)
				return new $Collection($options);
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
	 * @deprecated 1.2
	 * @see http://docs.shopplugin.net/Catalog_Tags
	 *
	 * @param string $property The property to handle
	 * @param array $options (optional) The tag options to process
	 * @return mixed
	 **/
	function tag ($property,$options=array()) {
		if (is_array($options)) $options['return'] = 'on';
		else $options .= (!empty($options)?"&":"").'return=on';
		return shopp($this,$property,$options);
	}

} // END class Catalog

class CatalogTag extends MetaObject {

	function __construct ($id=false,$key=false) {
		$this->init(self::$table);
		$this->load($id,$key);
		$this->context = 'catalog';
		$this->type = 'tag';
	}

} // END class CatalogTag

/**
 * CatalogTaxonomy class
 *
 *
 *
 * @author Jonathan Davis
 * @since 1.2
 * @package shopp
 **/
class CatalogTaxonomy extends MetaObject {

	var $id;
	var $name;
	var $label;
	var $parent;
	var $hierarchical;
	var $rewrite;
	var $queryvar;
	var $public;
	var $capabilities;

	function __construct ($id=false,$key=false) {
		$this->init(self::$table);
		$this->load($id,$key);
		$this->context = 'taxonomy';
		$this->type = $taxonomy;
	}

} // END class CatalogTaxonomy

class RegistryManager implements Iterator {

	private $_list = array();
	private $_keys = array();
	private $_false = false;

	public function __construct() {
        $this->_position = 0;
	}

	public function add ($key,$entry) {
		$this->_list[$key] = $entry;
		$this->rekey();
	}

	public function update ($key,$entry) {
		if (!$this->exists($key)) return false;
		$entry = array_merge($this->_list[$key],$entry);
		$this->_list[$key] = $entry;
	}

	public function &get ($key) {
		if ($this->exists($key)) return $this->_list[$key];
		else return $_false;
	}

	public function exists ($key) {
		return array_key_exists($key,$this->_list);
	}

	public function remove ($key) {
		if (!$this->exists($key)) return false;
		unset($this->_list[$key]);
		$this->rekey();
	}

	private function rekey () {
		$this->_keys = array_keys($this->_list);
	}

	function current () {
		return $this->_list[ $this->keys[$this->_position] ];
	}

	function key () {
		return $this->keys[$this->_position];
	}

	function next () {
		++$this->_position;
	}

	function rewind () {
		$this->_position = 0;
	}

	function valid () {
		return (
			array_key_exists($this->_position,$this->_keys)
			&& array_key_exists($this->keys[$this->_position],$this->_list)
		);
	}

}

class CatalogTaxonomies extends RegistryManager {
	private $_table = "meta";
	private $nextid = false;
	private $ids = array();

	public function __construct () {
		$this->_table = DatabaseObject::tablename(CatalogTaxonomy::$table);

		$Settings =& ShoppSettings();
		$this->ids = $Settings->get('taxonomies');
		if (!$this->ids) $this->ids = array();
		$this->nextid = $Settings->get('next_taxonomy_id');
		if (!$this->nextid) $this->nextid = 0;
	}

	public function add ($name,$options) {
		$taxonomy = sanitize_title_with_dashes($name);
		if (isset($this->ids[$name])) $options['id'] = $this->ids[$name];
		else $options['id'] = $this->reserve($name);
		parent::add($taxonomy,$options);
	}

	public function reserve ($name) {
		$Settings =& ShoppSettings();
		$id = $this->nextid();
		$this->ids[$name] = $id;

		$Settings->save('next_taxonomy_id',$this->nextid());
		$Settings->save('taxonomies',$this->ids);
		return $id;
	}

	public function get_id ($name) {
		return $this->get_option($name,'id');
	}

	public function get_option ($name,$option = 'id') {
		$taxonomy = $this->get($name);
		if (isset($taxonomy[$option]))
			return $taxonomy[$option];
		return false;
	}

	private function nextid () {
		if (!$this->reserved($this->nextid))
			return $this->nextid;

		$this->nextid++;

		// Recursively check for existing id
		$this->nextid();
	}

	private function reserved ($id) {
		return (array_search($id,$this->ids) !== false);
	}

}

/**
 * Access the registered Taxonomies
 *
 * @author Jonathan Davis
 * @since 1.2
 *
 * @return void Description...
 **/
function &ShoppTaxonomies () {
	global $Shopp;
	return $Shopp->Taxonomies;
}

function init_shopp_taxonomies () {
	register_catalog_taxonomy('category',array(
		'_builtin' => true,
		'hierarchical' => true,
		'public' => true,
		'editor_ui' => true
	));

	register_catalog_taxonomy('tag',array(
		'_builtin' => true,
		'editor_ui' => true
	));

	register_catalog_taxonomy('promo',array(
		'_builtin' => true,
		'editor_ui' => false
	));

}
add_action( 'shopp_init', 'init_shopp_taxonomies', 0 ); // highest priority

?>