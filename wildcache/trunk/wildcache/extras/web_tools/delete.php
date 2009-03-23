<?php

function wildcache_delete($server, $host, $uri, $timeout=0.5, $port=80) {
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

if ( isset($_GET['hostname']) ) {
	if ( !isset($_GET['uri']) )
		wildcache_delete('localhost', $_GET['hostname'], '/.*/');
	else
		wildcache_delete('localhost', $_GET['hostname'], $_GET['uri']);
}

?>