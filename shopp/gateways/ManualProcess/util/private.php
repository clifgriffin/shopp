<?php
if($_REQUEST['private']) {
	//error_log("private: ".$_REQUEST['private']);
	header("Content-type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"private.pem\""); 
	$data = $_REQUEST['private'];
	echo "-----BEGIN SHOPP PRIVATE KEY-----\n";
	echo urlencode($data);
	echo "\n-----END SHOPP PRIVATE KEY-----";
}
?>