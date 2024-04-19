<?php

	/*
	Plugin Name: Tour Master - Telr Plugin
	Plugin URI: 
	Description: Payment Telr Plugin
	Version: 1.0
	Author: Viktor Serobaba
	Author URI: http://www.vikweb.net
	License: 
	*/
function telr_tourmaster_init() {
	include_once(__DIR__.'/include/telr/telr.php');
}

if(!function_exists('telr_tourmaster_list_network_plugins')) {
	function telr_tourmaster_list_network_plugins() {
		if (!is_multisite()) {
			return false;
		$sitewide_plugins = array_keys((array) get_site_option('active_sitewide_plugins'));
		}
		if (!is_array($sitewide_plugins)) {
			return false;
		}
		return $sitewide_plugins;
	}
}

// Add plugin to tourmaster/tourmaster
if ((in_array('tourmaster/tourmaster.php', (array)get_option('active_plugins'))) || (in_array('tourmaster/tourmaster.php', (array)telr_tourmaster_list_network_plugins()))) {
	add_action('plugins_loaded', 'telr_tourmaster_init', 100);
	
}
