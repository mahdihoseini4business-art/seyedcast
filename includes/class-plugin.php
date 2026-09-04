<?php
/**
 * Main plugin bootstrap.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Plugin
 */
class Seyedcast_Plugin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		load_plugin_textdomain( 'seyedcast', false, dirname( SEYEDCAST_BASENAME ) . '/languages' );

		new Seyedcast_Post_Types();
		new Seyedcast_Meta();
		new Seyedcast_Settings();
		new Seyedcast_Rewrite();
		new Seyedcast_Seo();
		new Seyedcast_Assets();
		new Seyedcast_Templates();
		new Seyedcast_Stats();
		new Seyedcast_Listen_Stats();
		new Seyedcast_Notify_Leads();
		new Seyedcast_App();
		new Seyedcast_Pwa();
		new Seyedcast_Shortcode();

		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 20 );
	}

	/**
	 * One-shot upgrade tasks (rewrite flush for PWA endpoints, etc.).
	 */
	public static function maybe_upgrade() {
		$stored = get_option( 'seyedcast_db_version', '' );
		if ( version_compare( (string) $stored, SEYEDCAST_VERSION, '>=' ) ) {
			return;
		}
		Seyedcast_Rewrite::add_rules();
		add_rewrite_rule( '^seyedcast-manifest\.webmanifest$', 'index.php?seyedcast_manifest=1', 'top' );
		add_rewrite_rule( '^seyedcast-sw\.js$', 'index.php?seyedcast_sw=1', 'top' );
		flush_rewrite_rules( false );
		update_option( 'seyedcast_db_version', SEYEDCAST_VERSION, true );
	}

	/**
	 * Activate plugin.
	 */
	public static function activate() {
		Seyedcast_Post_Types::register();
		Seyedcast_Rewrite::add_rules();
		add_rewrite_rule( '^seyedcast-manifest\.webmanifest$', 'index.php?seyedcast_manifest=1', 'top' );
		add_rewrite_rule( '^seyedcast-sw\.js$', 'index.php?seyedcast_sw=1', 'top' );
		flush_rewrite_rules();

		Seyedcast_Stats::ensure_table();
		Seyedcast_Listen_Stats::ensure_table();
		Seyedcast_Notify_Leads::ensure_table();

		Seyedcast_App::ensure_comments_board();

		$defaults = Seyedcast_Settings::defaults();
		if ( false === get_option( 'seyedcast_settings' ) ) {
			add_option( 'seyedcast_settings', $defaults );
		}
	}

	/**
	 * Deactivate plugin.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
