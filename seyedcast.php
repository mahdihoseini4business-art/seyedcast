<?php
/**
 * Plugin Name: Seyedcast
 * Plugin URI:  https://seyedcast.local
 * Description: Educational podcasts with shows, sticky player, design themes, and PWA install.
 * Version:     1.2.0
 * Author:      Seyedcast
 * Text Domain: seyedcast
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEYEDCAST_VERSION', '1.2.0' );
define( 'SEYEDCAST_FILE', __FILE__ );
define( 'SEYEDCAST_PATH', plugin_dir_path( __FILE__ ) );
define( 'SEYEDCAST_URL', plugin_dir_url( __FILE__ ) );
define( 'SEYEDCAST_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load plugin PHP classes.
 */
function seyedcast_load_files() {
	static $loaded = false;
	if ( $loaded ) {
		return;
	}
	$loaded = true;

	$files = array(
		'includes/class-plugin.php',
		'includes/class-post-types.php',
		'includes/class-meta.php',
		'includes/class-settings.php',
		'includes/class-rewrite.php',
		'includes/class-seo.php',
		'includes/class-assets.php',
		'includes/class-templates.php',
		'includes/class-app.php',
		'includes/class-pwa.php',
		'includes/class-shortcode.php',
	);

	foreach ( $files as $relative ) {
		$path = SEYEDCAST_PATH . $relative;
		if ( ! is_readable( $path ) ) {
			return;
		}
		require_once $path;
	}
}

seyedcast_load_files();

/**
 * Bootstrap plugin.
 *
 * @return Seyedcast_Plugin|null
 */
function seyedcast() {
	if ( ! class_exists( 'Seyedcast_Plugin', false ) ) {
		seyedcast_load_files();
	}
	if ( ! class_exists( 'Seyedcast_Plugin', false ) ) {
		return null;
	}

	static $instance = null;
	if ( null === $instance ) {
		$instance = new Seyedcast_Plugin();
	}
	return $instance;
}

/**
 * Activation wrapper (avoids missing-class fatals).
 */
function seyedcast_activate() {
	seyedcast_load_files();
	if ( class_exists( 'Seyedcast_Plugin', false ) ) {
		Seyedcast_Plugin::activate();
	}
}

/**
 * Deactivation wrapper.
 */
function seyedcast_deactivate() {
	seyedcast_load_files();
	if ( class_exists( 'Seyedcast_Plugin', false ) ) {
		Seyedcast_Plugin::deactivate();
	}
}

register_activation_hook( __FILE__, 'seyedcast_activate' );
register_deactivation_hook( __FILE__, 'seyedcast_deactivate' );

add_action( 'plugins_loaded', 'seyedcast' );
