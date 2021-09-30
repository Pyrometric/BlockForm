<?php
/**
 * Plugin Name: BlockForm
 * Plugin URI: https://cramer.co.za
 * Description: Block based form builder.
 * Version: 1.0.0
 * Author: David Cramer
 * Author URI: https://cramer.co.za
 * Text Domain: blockform
 * Requires at least: 5.3
 * Requires PHP: 7.0
 * License: GPL2+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Constants.
define( 'BLOCKFORM_PATH', plugin_dir_path( __FILE__ ) );
define( 'BLOCKFORM_CORE', __FILE__ );
define( 'BLOCKFORM_URL', plugin_dir_url( __FILE__ ) );
define( 'BLOCKFORM_SLUG', basename( __DIR__ ) . '/' . basename( __FILE__ ) );

if ( ! version_compare( PHP_VERSION, '7.0', '>=' ) ) {
	if ( is_admin() ) {
		add_action( 'admin_notices', 'blockform_php_ver' );
	}
} else {
	// Includes BlockForm and starts instance.
	include_once BLOCKFORM_PATH . 'bootstrap.php';
}

function blockform_php_ver() {

	$message = __( 'BlockForm requires PHP version 7.0 or later. We strongly recommend PHP 7.0 or later for security and performance reasons.', 'blockform' );
	echo sprintf( '<div id="blockform_error" class="error notice notice-error"><p>%s</p></div>', esc_html( $message ) );
}

