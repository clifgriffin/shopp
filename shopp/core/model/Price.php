<?php
/**
 * Price.php
 *
 * Product price objects
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 28 March, 2008
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage products
 **/
class Price extends DatabaseObject {

	static $table = "price";

	function __construct ($id=false,$key=false) {
		$this->init(self::$table);
		if ($this->load($id,$key)) {
			$this->load_download();
			$this->load_settings();
		}

		// Recalculate promo price from applied promotional discounts
		add_action('shopp_price_updates',array(&$this,'discounts'));
	}

	function metaloader (&$records,&$record,$id='id',$property=false,$collate=false,$merge=false) {

		if (isset($this->prices) && !empty($this->prices)) $prices = &$this->prices;
		else $prices = array();

		$metamap = array(
			'download' => 'download',
			'settings' => 'settings'
		);
		$metaclass = array(
			'meta' => 'MetaObject'
		);

		if ('metatype' == $property)
			$property = isset($metamap[$record->type])?$metamap[$record->type]:'meta';

		if (isset($metaclass[$record->type])) {
			$ObjectClass = $metaclass[$record->type];
			$Object = new $ObjectClass();
			$Object->populate($record);
			if (method_exists($Object,'expopulate'))
				$Object->expopulate();
			$record = $Object;
		}

		if ('download' == $record->type) {
			$collate = false;
			$data = unserialize($record->value);
			foreach (get_object_vars($data) as $prop => $val) $record->{$prop} = $val;
			$clean = array('context','type','numeral','sortorder','created','modified','value');
			foreach ($clean as $prop) unset($record->{$prop});
		}

		if ( 'settings' == $record->name ) {
			$data = $record->value;
			if (is_array($prices) && isset($prices[$record->{$id}])) {
				$target = $prices[$record->{$id}];
			} elseif (isset($this)) {
				$target = $this;
			}
			foreach ( $data as $prop => $setting ) {
				$target->{$prop} = $setting;
			}
		}

		parent::metaloader($records,$record,$prices,$id,$property,$collate,$merge);
	}

	/**
	 * Loads a product download attached to the price object
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return boolean
	 **/
	function load_download () {
		if(SHOPP_DEBUG) new ShoppError("loading download for price ".$this->id,false,SHOPP_DEBUG_ERR);
		if ($this->type != "Download") return false;
		$this->download = new ProductDownload();
		$this->download->load(array(
			'parent' => $this->id,
			'context' => 'price',
			'type' => 'download'
			));

		if (empty($this->download->id)) return false;
		return true;
	}

	function load_settings () {
		$settings = shopp_meta ( $this->id, 'price', 'settings');
		if ( ! is_array( $settings ) ) {
			foreach ( $settings as $property => $setting ) {
				$this->{$property} = $setting;
			}
		}
	}

	/**
	 * Attaches a product download asset to the price object
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	function attach_download ($id) {
		if (!$id) return false;

		$Download = new ProductDownload($id);
		$Download->parent = $this->id;
		$Download->save();

		do_action('attach_product_download',$id,$this->id);

		return true;
	}

	/**
	 * Updates price record with provided data
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $data An associative array of key/value data
	 * @param array $ignores A list of properties to ignore updating
	 * @return void
	 **/
	function updates($data,$ignores = array()) {
		parent::updates($data,$ignores);
		do_action('shopp_price_updates');
	}

	/**
	 * Calculates promotional discounts applied to the price record
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function discounts () {

		if ('on' == $this->sale) $this->promoprice = floatvalue($this->saleprice);
		else $this->promoprice = floatvalue($this->price);
		if (empty($this->discounts)) return;

		$db =& DB::get();
		$promo_table = DatabaseObject::tablename(Promotion::$table);
		$query = "SELECT type,SUM(discount) AS amount FROM $promo_table WHERE 0 < FIND_IN_SET(id,'$this->discounts') AND discount > 0 AND status='enabled' GROUP BY type ORDER BY type DESC";
		$discounts = $db->query($query,AS_ARRAY);
		if (empty($discounts)) return;

		// Apply discounts
		$a = $p = 0;
		foreach ($discounts as $discount) {
			switch ($discount->type) {
				case 'Amount Off': $a += $discount->amount; break;
				case 'Percentage Off': $p += $discount->amount; break;
			}
		}

		if ($a > 0) $this->promoprice -= $a; // Take amounts off first (to reduce merchant percentage discount burden)
		if ($p > 0)	$this->promoprice -= ($this->promoprice * ($p/100));
	}

	/**
	 * Returns structured product price line type values and labels
	 *
	 * Used for building selection UIs in the editors
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return array
	 **/
	static function types () {
		 return array(
			array('value'=>'Shipped','label'=>__('Shipped','Shopp')),
			array('value'=>'Virtual','label'=>__('Virtual','Shopp')),
			array('value'=>'Download','label'=>__('Download','Shopp')),
			array('value'=>'Donation','label'=>__('Donation','Shopp')),
			array('value'=>'Subscription','label'=>__('Subscription','Shopp')),
			array('value'=>'N/A','label'=>__('Disabled','Shopp')),
		);
	}

	/**
	 * Returns structured subscription period values and labels
	 *
	 * Used for building selector UIs in the editors. The structure
	 * is organized with plural labels first array[0] and singular
	 * labels are second array[1].
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return array
	 **/
	static function periods () {
		return array(
			array(
				array('value'=>'d','label'=>__('days','Shopp')),
				array('value'=>'w','label'=>__('weeks','Shopp')),
				array('value'=>'m','label'=>__('months','Shopp')),
				array('value'=>'y','label'=>__('years','Shopp')),

			),
			array(
				array('value'=>'d','label'=>__('day','Shopp')),
				array('value'=>'w','label'=>__('week','Shopp')),
				array('value'=>'m','label'=>__('month','Shopp')),
				array('value'=>'y','label'=>__('year','Shopp')),
			)
		);
	}

} // END class Price

?>