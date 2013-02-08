<?php


$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( 'shopp/Shopp.php' ),
);

define('SHOPP_UNSUPPORTED',false);

require 'lib/bootstrap.php';
require 'testcase.php';
