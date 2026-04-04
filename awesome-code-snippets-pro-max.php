<?php
/**
 * Plugin Name: Awesome Code Snippets Pro Max
 * Plugin URI: https://edequalsaweso.me/development
 * Description: Add code snippets and header/footer scripts without wondering when we'll ask you to upgrade. That's it. All features included. No upsells. No paywalls. No shenanigans.
 * Version: 2026.03.10
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
define( 'ACSPM_VERSION', '2026.03.10' );
define( 'ACSPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACSPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACSPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if safe mode is active
 *
 * Safe mode disables all snippet execution, allowing admins to recover
 * from a broken snippet that crashed the site.
 *
 * Activate via:
 * - URL parameter: ?acspm-safe-mode=1
 * - wp-config.php: define( 'ACSPM_SAFE_MODE', true );
 *
 * @return bool True if safe mode is active.
 */
function acspm_is_safe_mode() {
	// Check wp-config constant first (always applies)
	if ( defined( 'ACSPM_SAFE_MODE' ) && ACSPM_SAFE_MODE ) {
		return true;
	}

	// Check URL parameter (only for logged-in admins)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['acspm-safe-mode'] ) && '1' === $_GET['acspm-safe-mode'] ) {
		// Must be logged in as admin for URL param to work
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
	}

	return false;
}

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

	// Show admin notice when safe mode is active (deferred to admin_init
	// so current_user_can() is reliable — it may not be at plugins_loaded)
	add_action( 'admin_init', function () {
		if ( acspm_is_safe_mode() ) {
			add_action( 'admin_notices', 'acspm_safe_mode_notice' );
		}
	} );
}
add_action( 'plugins_loaded', 'acspm_init' );

/**
 * Display admin notice when safe mode is active
 */
function acspm_safe_mode_notice() {
	$dismiss_url = remove_query_arg( 'acspm-safe-mode' );
	?>
	<div class="notice notice-warning" role="alert">
		<p>
			<strong>Awesome Code Snippets Pro Max - Safe Mode Active</strong><br>
			All snippets and header/footer code are currently disabled.
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=acspm-snippets' ) ); ?>">Review your snippets</a> and deactivate any problematic code, then
			<a href="<?php echo esc_url( $dismiss_url ); ?>">exit safe mode</a>.
		</p>
	</div>
	<?php
}

/**
 * Activation hook - register custom post type and flush rewrite rules
 */
function acspm_activate() {
	ACSPM_Snippets::get_instance()->register_post_type();
	flush_rewrite_rules();

	// Create header/footer options with autoload=false (only needed on frontend + one admin page)
	if ( false === get_option( 'acspm_header_code' ) ) {
		add_option( 'acspm_header_code', '', '', false );
	}
	if ( false === get_option( 'acspm_footer_code' ) ) {
		add_option( 'acspm_footer_code', '', '', false );
	}
}
register_activation_hook( __FILE__, 'acspm_activate' );

/**
 * Upgrade path for existing installations.
 *
 * The activation hook only runs on fresh installs, not updates. This migration
 * fixes autoload for header/footer options that were added before we set autoload=false.
 */
function acspm_maybe_upgrade() {
	$db_version = get_option( 'acspm_db_version', '0' );

	if ( version_compare( $db_version, '1.1', '<' ) ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->options,
			array( 'autoload' => 'off' ),
			array( 'option_name' => 'acspm_header_code' )
		);
		$wpdb->update(
			$wpdb->options,
			array( 'autoload' => 'off' ),
			array( 'option_name' => 'acspm_footer_code' )
		);

		update_option( 'acspm_db_version', '1.1' );
	}

	if ( version_compare( $db_version, '1.2', '<' ) ) {
		// Move PHP cache from uploads/ (web-accessible) to wp-content/cache/ (not web-accessible).
		$upload_dir = wp_upload_dir();
		$old_cache  = $upload_dir['basedir'] . '/acspm-cache';

		if ( is_dir( $old_cache ) ) {
			$old_files = array_merge(
				glob( $old_cache . '/*' ) ?: array(),
				glob( $old_cache . '/.*' ) ?: array()
			);
			foreach ( $old_files as $file ) {
				if ( ! in_array( basename( $file ), array( '.', '..' ), true ) && is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
			rmdir( $old_cache );
		}

		update_option( 'acspm_db_version', '1.2' );
	}
}
add_action( 'plugins_loaded', 'acspm_maybe_upgrade' );

/**
 * Deactivation hook - flush rewrite rules
 */
function acspm_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'acspm_deactivate' );
