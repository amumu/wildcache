<?php

	$log_base = '/var/log/wpcp/';

	function generic_log($file, $message) {
		#global $log_base;
		#$logfile = $log_base . date('Y-m-d-H-') . $file . '.log';
		#$fp = fopen($logfile, 'a');
		#fputs($fp, 
		#	"'{$_SERVER['REMOTE_ADDR']}' [".date('r')."] ".trim($message)."\r\n");
		#fclose($fp);
		return true;
	}

	function log_get_bypass_cache(&$wpcp) {
		apc_bump_stat('wpcp_bypassed');
		return $wpcp;
	}
	
	function log_get_not_cached(&$wpcp) {
		apc_bump_stat('wpcp_noncached');
		return $wpcp;
	}

	function log_get_cached(&$wpcp) {
		apc_bump_stat('wpcp_cached');
		return $wpcp;
	}

	function log_head(&$wpcp) {
		apc_bump_stat('wpcp_heads');
		return $wpcp;
	}

	function log_delete(&$wpcp) {
		apc_bump_stat('wpcp_deletes');
		return $wpcp;
	}

	function log_put(&$wpcp) {
		apc_bump_stat('wpcp_puts');
		return $wpcp;
	}

	function log_session_length(&$wpcp) {
		if ( $wpcp->force_bypass === true )
			generic_log('speed', round(($wpcp->stop - $wpcp->start), 4)." B '{$wpcp->domain}' '{$wpcp->uri}'" );
		else
			generic_log('speed', round(($wpcp->stop - $wpcp->start), 4)." {$wpcp->cache_access} '{$wpcp->domain}' '{$wpcp->uri}'" );
	}

        function apc_bump_stat($stat_name) {
                if ( function_exists('apc_fetch') ) {
                        if ( ! $value = apc_fetch($stat_name) )
				$value = 0;
                        $value++;
                        apc_store($stat_name, $value);
                }
        }

register_plugin( 'terminate', 'log_session_length' );
register_plugin( 'wpcp_get_bypass_cache_exit', 'log_get_bypass_cache' );
register_plugin( 'wpcp_get_not_cached', 'log_get_not_cached' );
register_plugin( 'wpcp_get_cached', 'log_get_cached' );
register_plugin( 'wpcp_head', 'log_head' );
register_plugin( 'wpcp_delete', 'log_delete' );
register_plugin( 'wpcp_put', 'log_put' );

?>