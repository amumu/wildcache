<?php

	register_plugin( 'backend_list', 'config_backend_list' );
	function config_backend_list(&$wildcache) {
		$servers = array ( '12.12.12.12', '12.12.12.13', '12.12.12.14' );
		shuffle($servers);
		$wildcache->servers = $servers;
		return $wildcache;
	}

?>
