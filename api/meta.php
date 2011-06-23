<?php
/**
 * Meta API
 *
 * Description…
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, June 23, 2011
 * @license GNU GPL version 3 (or later) {@see license.txt}
 * @package shopp
 * @since 1.0
 * @subpackage shopp
 **/

function shopp_meta ($id,$context,$name) {}
function shopp_meta_exists ($name) {}

function shopp_set_meta ($id,$context,$name,$value) {}
function shopp_rmv_meta ($id,$context,$name) {}

function shopp_product_meta ($product,$name) {}
function shopp_product_has_meta ($product,$name) {}
function shopp_product_meta_list ($product) {}
function shopp_product_meta_count ($product) {}
function shopp_set_product_meta ($product,$name,$value) {}
function shopp_rmv_product_meta ($product,$name) {}


?>