<?php

$plugins=array();

ini_set('allow_call_time_pass_reference', true);

function register_plugin($hook, $function) {
	global $plugins;
	if ( !isset($plugins[$hook]) )
		$plugins[$hook]=array();
	$plugins[$hook][]=$function;
	return true;
}

function execute_plugins_for($hook, &$data) {
	global $plugins;
	if ( !isset($plugins[$hook]) )
		return $data;
	foreach ( $plugins[$hook] as $function ) {
		$data = $function($data);
	}
	return $data;
}

?>