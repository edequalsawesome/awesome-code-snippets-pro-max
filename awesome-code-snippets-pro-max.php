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

	// Show admin notice when safe mode is active
	if ( acspm_is_safe_mode() && is_admin() ) {
		add_action( 'admin_notices', 'acspm_safe_mode_notice' );
	}
}
add_action( 'plugins_loaded', 'acspm_init' );

/**
 * Display admin notice when safe mode is active
 */
function acspm_safe_mode_notice() {
	$dismiss_url = remove_query_arg( 'acspm-safe-mode' );
	?>
	<div class="notice notice-warning">
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
}
register_activation_hook( __FILE__, 'acspm_activate' );

/**
 * Deactivation hook - flush rewrite rules
 */
function acspm_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'acspm_deactivate' );
