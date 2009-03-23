<?php

	function remote_deletes(&$wildcache) {
		global $servers;
		
		if ( in_array($_SERVER["REMOTE_ADDR"], $servers) )
			return $wildcache;

		foreach ( $servers as $server ) {
			if ( $server != $_SERVER["SERVER_ADDR"] ) {
				generic_log('remote-delete', "{$server} {$wildcache->domain}\t{$wildcache->uri}");
				delete($server, $wildcache->domain, $wildcache->uri);
			}
		}
		return $wildcache;
	}

	function delete($server, $host, $uri, $timeout=0.5, $port=80) {
		$sock = @fsockopen($server, $port, $errno, $errst, $timeout);
		if ( $sock === false )
			return false;
		fputs($sock, "DELETE {$uri} HTTP/1.1\r\nHost: {$host}\r\nConnection: Close\r\n\r\n");
		$reply="";
		while ( !feof($sock) ) {
			$reply.=fgets($sock, 4096);
		}
		fclose($sock);
		$reply = explode(chr(10), $reply);
		if ( preg_match('/HTTP\/1\.1 2.. OK/', $reply[0]) )
			return true;
		return false;
	}

	register_plugin( 'delete', 'remote_deletes');
	
?>