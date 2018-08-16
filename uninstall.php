<?php
/*
 * Uninstall plugin
 */
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();



		global $wpdb;
        $table = $wpdb->prefix."visitor_details";
		$wpdb->query("DROP TABLE IF EXISTS $table");

/**
 * Delete plugin table when uninstalled
 *
 * @access public
 * @return void
 */
function plugin_uninstalled() {
	global $wpdb;
}