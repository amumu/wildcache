<?php

class wildcache {

	// Config Variables
	var $cache_base			= null;
	var $cache_ttl			= 86400;
	var $generic_404		= '404';
	var $generic_200		= '200';
	var $generic_ua			= 'Wildcache-v0.1';
	var $max_filename_length	= '128';	// this is safe for ext3
	var $domain_split_depth		= 2;
	var $uri_split_depth		= 0;

	// Empty Placeholders
	var $domain			= null;		// The Requested HOSTNAME
	var $uri			= null;		// The Requested URI
	var $method			= null;		// GET, PUT, DELETE, HEAD
	var $cache_path			= null;		// cached HTML
	var $cache_meta			= null;		// cached HEADERS
	var $cache_root			= null;		// The root of the cache for the given domain
	var $remote_url			= array();	// Url(s) to hit, if any, for data transfer
	var $force_bypass		= false;	// should we force a bypassing fo the cache?
	var $cache_access		= null;		// N (not already cached) or C (cached)

	// Timer
	var $start			= null;
	var $stop			= null;

	/**
	 * Forward compatibility
	 */
	function __construct($args=array()) {
		$this->wildcache($args);
	}

	/**
	 * Constructor
	 */
	function wildcache($args=array()) {
		// Timer start
		$this->start = $this->microtime_float();

		// where will we put stuff?
		$this->cache_base = dirname(__FILE__) . "/__cache";

		// Gather information
		$this->domain  = $_SERVER['HTTP_HOST'];
		$this->uri	   = $_SERVER['REQUEST_URI'];
		if ( get_magic_quotes_gpc() )
			$this->uri = stripslashes($this->uri);
		$this->method  = $_SERVER['REQUEST_METHOD'];
		$this->whereami();	// Crucial Server Information
		$this->backend_list();	// More Crucial Server Information

		// Process overrides passed via associative array: $args
		foreach ( $args as $idx => $val ) {
			$this->$idx=$val;
		}

		// Setup our cache path
		$this->cache_path = $this->cache_base;
		if ( !file_exists($this->cache_path) )
			mkdir($this->cache_path);
		$part = md5($this->domain);

		$sub = array ();
		for ( $i=0; $i < $this->domain_split_depth; $i++)
			$sub[]=substr($part, $i, 1);
		$sub[]=rawurlencode($this->domain);
		//urlencode($part),
		$this->cache_root = $this->cache_path . '/' . implode('/', $sub);
		if ( function_exists('str_split') ) {
			$uri_parts = str_split(rawurlencode($this->uri), $this->max_filename_length);
			$uri = array_pop($uri_parts);
		} else {
			$uri_parts = array();
			$str = rawurlencode($this->uri);
			while ( strlen($str) !== 0 ) {
				$uri_parts[] = substr($str, 0, $this->max_filename_length);
				$str = substr($str, $this->max_filename_length);
			}
			$uri = array_pop($uri_parts);
		}
		$part = md5($uri);
		for ( $i=0; $i < $this->uri_split_depth; $i++)
			$sub[] = substr($part, $i, 1);
		foreach ( array_merge($sub, $uri_parts) as $part ) {
			$this->cache_path .= "/{$part}";
			if ( !file_exists($this->cache_path) && $this->method !== "DELETE" )
				mkdir($this->cache_path);
		}
		$this->cache_path	.= "/" . $uri;
		$this->cache_meta	= $this->cache_path . '.meta';

		$this->apc_url_key	= md5("{$this->domain}{$this->uri}");
		$this->apc_host_key	= md5($this->domain);

		execute_plugins_for( 'request_init', $this );

		switch ( $this->method ) {
			case 'POST':
			case 'GET':
			$this->get();		// Returned proxied content
			break;
			case 'HEAD':
			$this->head();		// Return meta only for proxied content
			break;
			case 'DELETE':
			$this->delete();	// Invalidate Proxied content
			break;
			case 'PUT':
			$this->put();		// Inject content into a proxy
			break;
			default:
			break;
		}
	}

	/**
	 * Proxy a request, bypassing the cache.  This is done for dynamic requests, and requests
	 * made by a user who is logged in (and thus may see mutable content)
	 */
	function get_bypass_cache() {
		execute_plugins_for( 'get_bypass_cache_entrance', $this );
		if ( count($_POST) > 0 ) {
			$post_data = array();
			foreach ($_POST as $key => $val) {
				$post_data[] = "{$key}=".utf8_encode($val);
			}
			$post_data=implode('&', $post_data);
		} else {
			$post_data = false;
		}
		if ( count($_COOKIE) > 0 ) {
			$cookie_data = array();
			foreach($_COOKIE as $key => $val) {
				$cookie_data[] = $key."=".rawurlencode($val);
			}
			$cookie_data = implode(';', $cookie_data);
		} else {
			$cookie_data = false;
		}

		$cookie_data = execute_plugins_for( 'get_bypass_cache_cookie', $cookie_data );
		foreach ( $this->servers as $ip ) {
			if ( ! $this->check_tcp_responsiveness($ip, 80, 0.1) )
				continue;
			$ch = $ch = curl_init("http://{$ip}{$this->uri}");
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
			curl_setopt($ch, CURLOPT_COOKIEFILE,' /dev/null');
			curl_setopt($ch, CURLOPT_COOKIEJAR,' /dev/null');
			curl_setopt($ch, CURLOPT_COOKIESESSION, true);
			curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Host: ' . $this->domain));
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
			curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'direct_head_display'));
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this, 'direct_data_display'));
			curl_setopt($ch, CURLOPT_USERAGENT, $this->generic_ua);
			if ( $post_data ) {
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			}
			if ( $cookie_data )
				curl_setopt($ch, CURLOPT_COOKIE,  $cookie_data);
			$ch = execute_plugins_for( 'get_bypass_cache_request', $ch );
			$rval = curl_exec($ch);
			if ( $rval === false )
				continue;
			execute_plugins_for( 'get_bypass_cache_exit', $this );
			$this->terminate();
		}
	}

	/**
	 * Callback for non-cached requests
	 */
	function direct_data_display($ch, $data, $body=true) {
		execute_plugins_for( 'direct_data_display_init', $this );
		$data = execute_plugins_for( 'direct_data_display', $data );
		if ( $body )
			echo $data;
		return strlen($data);
	}

	/**
	 * Callback for non-cached requests
	 */
	function direct_data_ignore($ch, $data) {
		return $this-> direct_data_display($ch, $data, false);
	}
	/**
	 * Callback for non-cached requests
	 */
	function direct_head_display($ch, $header) {
		$header = execute_plugins_for( 'direct_head_display', $header );
		if ( ! ereg('^Date|^Server:|^HTTP|^Connection:|^Transfer-Encoding', $header) && trim($header) != '' ) {
			if ( ereg('^Set-Cookie:', $header) )
				header($header, false);
			else
				header($header, true);
		}
		return strlen($header);
	}

	/**
	 * Caching fetch
	 */
	function remote_fetch() {
		execute_plugins_for( 'remote_fetch', $this );
		$fp_file = fopen($this->cache_path, 'w');
		$fp_meta = fopen($this->cache_meta, 'w');
		foreach ( $this->servers as $ip ) {
			if ( ! $this->check_tcp_responsiveness($ip, 80, 0.1) )
				continue;
			$ch = curl_init("http://{$ip}{$this->uri}");
			// Initialize a curl session
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
			// The number of seconds to wait whilst trying to connect. Use 0 to wait indefinitely.
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Host: ' . $this->domain)); // Clever bit
			// An array of HTTP header fields to set.
			curl_setopt($ch, CURLOPT_FILE, $fp_file);
			// The file that the transfer should be written to. The default is STDOUT (the browser window).
			curl_setopt($ch, CURLOPT_WRITEHEADER, $fp_meta);
			// TRUE to automatically set the Referer: field in requests where it follows a Location: redirect.
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
			// TRUE to force the connection to explicitly close when it has finished processing, and not be pooled for reuse.
			curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
			curl_setopt($ch, CURLOPT_COOKIEFILE,' /dev/null');
			curl_setopt($ch, CURLOPT_COOKIEJAR,' /dev/null');
			curl_setopt($ch, CURLOPT_COOKIESESSION, true);
			curl_setopt($ch, CURLOPT_USERAGENT, $this->generic_ua);
			$ch = execute_plugins_for( 'get_cache_request', $ch );
			$rval = curl_exec($ch);
			if ( $rval === false )
				continue;
			curl_close($ch);
			fclose($fp_meta);
			return true;
		}
		return false;
	}

	/**
	 * Determine cache stagnation for the current url
	 */
	function check_cache() {
		execute_plugins_for( 'check_cache', $this );
		if ( file_exists($this->cache_path) && file_exists($this->cache_meta) ) {
			$mtime = filemtime($this->cache_meta);
			if ( $mtime + $this->cache_ttl > time() && filesize($this->cache_meta) > 0 )
				return true;
		}
		return false;
	}

	/**
	 * Read cached data from disk
	 */
	function read_cache($body = true) {
		execute_plugins_for( 'read_cache_entrance', $this );
		clearstatcache();
		if ( !is_file($this->cache_path) || !is_file($this->cache_meta) )
			$this->get_bypass_cache($body);
		$data = file_get_contents($this->cache_path);
		$meta = file($this->cache_meta);
		foreach ( $meta as $idx => $header ) {
			$header = execute_plugins_for( 'read_cache_header', $header ); 
			if ( ! ereg('^Date|^Server:|^HTTP|^Connection:|^Transfer-Encoding|^Set-Cookie', $header) && trim($header) != '' ) {
				header($header, true);
				$header = execute_plugins_for( 'sent_cache_header', $header );
			} else {
				$header = execute_plugins_for( 'skipped_cache_header', $header );
			}
		}
		$data = execute_plugins_for( 'read_cache_data', $data );
		echo $data;
		execute_plugins_for( 'read_cache_exit', $this );
		$this->terminate();
	}

	/**
	 * handle HTTP GET verbs
	 */
	function get($body = true) {
		// as long as we're not logged in, and arent posting data, proceed and cache
		// else bypass cache
		execute_plugins_for( 'get', $this );
		if ( $this->force_bypass !== true ) {
			if ( $this->check_cache() === false ) {
				if ( !$this->remote_fetch() ) {
					$this->error_404();
				}
				$this->cache_access = "N";
				execute_plugins_for( 'get_not_cached', $this );
				$this->read_cache($body);
			} else {
				$this->cache_access = "C";
				execute_plugins_for( 'get_cached', $this );
				$this->read_cache($body);
			}
		} else {
			$this->get_bypass_cache($body);
		}
	}

	/**
	 * Give headers, but not data for the uri for the host
	 */
	function head() {
		execute_plugins_for( 'head', $this );
		$this->get(false);
	}

	/**
	 * Force (re)caching for the uri for the host
	 */
	function put() {
		execute_plugins_for( 'put', $this );
		$this->delete();
		$this->head();
	}

	function recursive_dir_implode($path) {
		$rval = array();
		if ( !is_dir($path) )
			return $rval;
		$d = dir($path);
		while ( false !== ($entry = $d->read()) ) {
			if ( $entry == '.' || $entry == '..' )
				continue;
			$entry = "{$path}/{$entry}";
			if ( is_dir($entry) ) {
				$rval2 = $this->recursive_dir_implode($entry);
				$rval  = array_merge($rval, $rval2);
			} else {
				if ( ereg('\.meta$', $entry) )
					continue;
				$file = substr($entry, strlen($this->cache_root));
				$rval[$entry] = str_replace('/', '', $file);
			}
		}
		return $rval;
	}

	/**
	 * Invalidate the uri for the host
	 */
	function delete() {
		execute_plugins_for( 'delete', $this );

		if ( $this->uri == '/*' )
			$this->uri = '/.*/';
		if ( file_exists($this->cache_path) ) {
			@unlink($this->cache_path);
			@unlink($this->cache_meta);
		} else {
			if ( is_dir(dirname($this->cache_root)) ) {
				$files = $this->recursive_dir_implode($this->cache_root);
				foreach ( $files as $file => $uri ) {
					$fileuri=rawurldecode($uri);
					if ( preg_match($this->uri, $fileuri) ) {
						@unlink($file);
						@unlink("{$file}.meta");
					}
				}
			}
		}
		return true;
	}

	/**
	 * Terminate
	 */
	function terminate($message=false, $header=false) {
		$this->stop = $this->microtime_float();
		header('X-Wildcache-Time: '.($this->stop - $this->start), true);

		if ( $this->force_bypass === true ) 
			header('X-Wildcache-Skipped: true', true);
		else
			header('X-Wildcache-Skipped: false', true);

		execute_plugins_for( 'terminate', $this );

		if ( $header !== false )
			header($header);

		ob_end_flush(); 
		if ( $message === false )
			die();
		else
			die($message);
	}

	/**
	 * Generic 404 if necessary
	 */
	function error_404() {
		execute_plugins_for( 'http_404', $this );
		$this->terminate($this->generic_404, "HTTP/1.0 404 Not Found");
	}

	/**
	 * Generic 200 if necessary
	 */
	function http_200() {
		execute_plugins_for( 'http_200', $this );
		$this->terminate($this->generic_200, "HTTP/1.0 200 OK");
	}

	/**
	 * Helper function for timing
	 */
	function microtime_float() {
		list($usec, $sec) = explode(" ", microtime());
		return (float)$usec + (float)$sec;
	}

	/**
	 * Determine the responsiveness of our backend servers
	 */
	function check_tcp_responsiveness($host, $port, $float_timeout) {
		if ( function_exists('apc_store') ) {
			$use_apc = true;
			$apc_key = "{$host}{$port}";
			$apc_ttl = 10;
		} else {
			$use_apc = false;
		}
		if ( $use_apc ) {
			$cached_value=apc_fetch($apc_key);
			switch ( $cached_value ) {
				case 'up':
				return true;
				case 'down':
				return false;
			}
		}
		$socket = @fsockopen($host, $port, $errno, $errstr, $float_timeout);
		if ( $socket === false ) {
			if ( $use_apc )
				apc_store($apc_key, 'down', $apc_ttl);
			return false;
		}
		fclose($socket);
		if ( $use_apc )
			apc_store($apc_key, 'up', $apc_ttl);
		return true;
	}

	/**
	 * Determine which datacenter I'm in
	 */
	function whereami() {
		execute_plugins_for( 'whereami', $this );
	}

	/**
	 * Return a list of backend servers for the current datacenter
	 */
	function backend_list() {
		execute_plugins_for( 'backend_list', $this );
	}

}

// Setup
ini_set('session.use_only_cookies', true);
ob_start();

// Plugins
include(dirname(__FILE__) . '/__pluggable.php');
foreach ( glob( dirname(__FILE__) . '/plugins/*.php') as $file ) {
	include($file);
}

// Actually run wildcache for the request
$opts = array();
$cache = new wildcache($opts);

?>
