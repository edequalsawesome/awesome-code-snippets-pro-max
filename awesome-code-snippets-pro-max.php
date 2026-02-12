<?php
/**
 * Plugin Name: Awesome Code Snippets Pro Max
 * Plugin URI: https://edequalsaweso.me/development
 * Description: Add code snippets and header/footer scripts without wondering when we'll ask you to upgrade. That's it. All features included. No upsells. No paywalls. No shenanigans.
 * Version: 2026.02.12
 * Author: eD Thomas
 * Author URI: https://edequalsaweso.me
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: awesome-code-snippets-pro-max
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'ACSPM_VERSION', '2026.02.12' );
define( 'ACSPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACSPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACSPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Load plugin classes
require_once ACSPM_PLUGIN_DIR . 'includes/class-snippets.php';
require_once ACSPM_PLUGIN_DIR . 'includes/class-header-footer.php';
require_once ACSPM_PLUGIN_DIR . 'includes/admin-pages.php';

/**
 * Initialize the plugin
 */
function acspm_init() {
	// Initialize snippets functionality
	ACSPM_Snippets::get_instance();

	// Initialize header/footer functionality
	ACSPM_Header_Footer::get_instance();

	// Initialize admin pages (only in admin)
	if ( is_admin() ) {
		ACSPM_Admin_Pages::get_instance();
	}
}
add_action( 'plugins_loaded', 'acspm_init' );

/**
 * Activation hook - register custom post type and flush rewrite rules
 */
function acspm_activate() {
	ACSPM_Snippets::get_instance()->register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'acspm_activate' );

/**
 * Deactivation hook - flush rewrite rules
 */
function acspm_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'acspm_deactivate' );
