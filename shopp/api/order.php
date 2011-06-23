<?php
/**
 * Order API
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


function shopp_orders () {}
function shopp_order_count () {}
function shopp_customer_orders ($customer) {}
function shopp_recent_orders ($timeframe) {}
function shopp_recent_customer_orders ($customer,$timeframe) {}
function shopp_last_order () {}
function shopp_last_customer_order ($customer) {}

function shopp_order ($id) {}
function shopp_add_order ($data) {}
function shopp_rmv_order ($id) {}

function shopp_add_order_line ($order,$data) {}
function shopp_rmv_order_line ($order,$line) {}
function shopp_order_lines ($order) {}
function shopp_order_line_count ($order) {}

function shopp_add_order_line_download ($order,$line,$download) {}
function shopp_rmv_order_line_download ($order,$line) {}

function shopp_order_line_data_list ($order,$line) {}
function shopp_order_line_data_count ($order,$line) {}
function shopp_order_line_data ($order,$line,$name) {}
function shopp_add_order_line_data ($order,$line,$download) {}
function shopp_rmv_order_line_data ($order,$line,$download) {}

?>