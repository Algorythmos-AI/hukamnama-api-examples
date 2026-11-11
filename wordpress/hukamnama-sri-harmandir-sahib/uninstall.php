<?php
/**
 * Remove what the plugin stored: three cache entries.
 *
 * @package Hukamnama_SHS
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_transient( 'hukamnama_shs_fresh' );
delete_transient( 'hukamnama_shs_last' );
delete_transient( 'hukamnama_shs_failed' );
