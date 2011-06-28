<?php
/**
 * Core
 *
 * Description…
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, February 25, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage shopp
 **/

function &ShoppProduct (&$Object=false) {
	global $Shopp; $false = false;
	if (empty($Shopp)) return $false;
	if ($Object !== false) $Shopp->Product = $Object;
	return $Shopp->Product;
}

function &ShoppCatalog (&$Object=false) {
	global $Shopp; $false = false;
	if (empty($Shopp)) return $false;
	if ($Object !== false) $Shopp->Catalog = $Object;
	return $Shopp->Catalog;
}

function &ShoppPurchase (&$Object=false) {
	global $Shopp; $false = false;
	if (empty($Shopp)) return $false;
	if ($Object !== false) $Shopp->Purchase = $Object;
	return $Shopp->Purchase;
}

?>