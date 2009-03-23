<?php

	// Invalidation order preferences
	$servers_dc = array(
		'dc1' => array (
			'1.1.1.1',
		),
		'dc2' => array (
			'2.2.2.2',
		),
		'dc3' => array (
			'3.3.3.3',
		),
	);

	// ALL Servers list
	$servers = array (
		'1.1.1.1',	// dc1
		'2.2.2.2',	// dc2
		'3.3.3.3',	// dc3
	);

	register_plugin( 'whereami', 'config_whereami' );
	function config_whereami(&$wildcache) {
		// expects (datacenter).(domain).(tld) to be the hostname
		list(, , $dc) = array_reverse(explode('.', php_uname('n')));
		return $dc;
	}
	
	register_plugin( 'backend_list', 'config_backend_list' );
	function config_backend_list(&$wildcache) {
		$servers = array (
			'dc1' => array(
				'192.168.1.1',
				'192.168.1.2',
				'192.168.1.3',
			),
			'dc2' => array(
				'192.168.2.1',
				'192.168.2.2',
				'192.168.2.3',
			),
			'dc3' => array(
				'192.168.0.1',
				'192.168.0.2',
				'192.168.0.3',
			),
		);
		shuffle($servers[$wildcache->dc]);
		$wildcache->servers = $servers[$wildcache->dc];
		return $wildcache;
	}
?>