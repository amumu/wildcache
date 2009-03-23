<?php

	function detect_logged_in_traffic(&$wildcache) {
		if ( $wildcache->force_bypass === true )
			return $wildcache;

		if ( isset($_COOKIE['wordpressloggedin']) )
			$wildcache->force_bypass = true;
		if ( isset($_SESSION['wordpressloggedin']) )
			$wildcache->force_bypass = true;
		
		return $wildcache;
	}

	function detect_posted_request(&$wildcache) {
		if ( $wildcache->force_bypass === true )
			return $wildcache;
		
		if ( count($_POST) > 0 )
			$wildcache->force_bypass = true;
		return $wildcache;
	}

	function detect_noncacheable_urls(&$wildcache) {
		if ( $wildcache->force_bypass === true )
			return $wildcache;
		
		if ( preg_match('/^\/remote-login.php|^\/wp-login.php/', $_SERVER['REQUEST_URI']) ) 
			$wildcache->force_bypass = true;
		return $wildcache;
	}

	register_plugin( 'request_init', 'detect_noncacheable_urls');
	register_plugin( 'request_init', 'detect_logged_in_traffic');
	register_plugin( 'request_init', 'detect_posted_request');

?>