<?php

if($_REQUEST['download_pkey'] && $_REQUEST['private']) {
		header("Content-type: application/octet-stream");
		header("Content-Disposition: attachment; filename=\"private.pem\""); 
//print_r(urldecode($_REQUEST['private']));
		$data = base64_encode(urldecode($_REQUEST['private']));
		echo "-----BEGIN SHOPP PRIVATE KEY-----\n";
		echo $data;
		echo "\n-----END SHOPP PRIVATE KEY-----";

}

?>