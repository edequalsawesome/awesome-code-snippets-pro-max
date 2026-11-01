<?php
/**
 * Plugin Name: Awesome Code Snippets Pro Max
 * Plugin URI: https://edequalsaweso.me/development
 * Description: Add code snippets and header/footer scripts without wondering when we'll ask you to upgrade. That's it. All features included. No upsells. No paywalls. No shenanigans.
 * Version: 2026.07.002
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
define( 'ACSPM_VERSION', '2026.07.002' );
define( 'ACSPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACSPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

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

/**
 * Whether this admin request activated URL safe mode.
 *
 * @return bool True for an authorized URL safe-mode request.
 */
function acspm_is_url_safe_mode() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return is_admin() && current_user_can( 'manage_options' ) && isset( $_GET['acspm-safe-mode'] ) && is_string( $_GET['acspm-safe-mode'] ) && '1' === $_GET['acspm-safe-mode'];
}

/**
 * Build an admin URL that retains an authorized URL safe-mode flag.
 *
 * @param string $path   Path relative to wp-admin.
 * @param string $scheme URL scheme.
 * @return string Admin URL.
 */
function acspm_admin_url( $path = '', $scheme = 'admin' ) {
	$url = admin_url( $path, $scheme );

	return acspm_is_url_safe_mode() ? add_query_arg( 'acspm-safe-mode', '1', $url ) : $url;
}

/**
 * Retain URL safe mode on this plugin's two Tools sidebar links.
 */
function acspm_preserve_safe_mode_sidebar_links() {
	if ( ! acspm_is_url_safe_mode() ) {
		return;
	}
	$admin_paths = array(
		wp_parse_url( admin_url( 'tools.php' ), PHP_URL_PATH ),
		wp_parse_url( admin_url( 'admin.php' ), PHP_URL_PATH ),
	);
	?>
	<script>
		document.querySelectorAll('#menu-tools a').forEach(function (link) {
			var url;
			try {
				url = new URL(link.href, window.location.origin);
			} catch (error) {
				return;
			}
			if (url.origin === window.location.origin && <?php echo wp_json_encode( $admin_paths, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>.indexOf(url.pathname) !== -1 && ['acspm-snippets', 'acspm-header-footer'].indexOf(url.searchParams.get('page')) !== -1) {
				url.searchParams.set('acspm-safe-mode', '1');
				link.href = url.href;
			}
		});
	</script>
	<?php
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
		add_action( 'admin_footer', 'acspm_preserve_safe_mode_sidebar_links' );
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
	// Safe mode has two independent activation paths. The dismiss link only
	// clears the URL parameter, so it must NOT be shown when safe mode came
	// from the wp-config constant — that would give a dead "exit" link that
	// reappears on every load.
	$via_constant = defined( 'ACSPM_SAFE_MODE' ) && ACSPM_SAFE_MODE;
	$review_url   = acspm_admin_url( 'tools.php?page=acspm-snippets' );
	?>
	<div class="notice notice-warning" role="alert">
		<p>
			<strong>Awesome Code Snippets Pro Max - Safe Mode Active</strong><br>
			<?php esc_html_e( 'All snippets and header/footer code are currently disabled.', 'awesome-code-snippets-pro-max' ); ?>
			<?php if ( $via_constant ) : ?>
				<?php
				printf(
					/* translators: %s: the wp-config.php constant definition */
					esc_html__( 'Safe mode is enabled in wp-config.php. Fix or deactivate any problematic code, then remove %s from wp-config.php to exit safe mode.', 'awesome-code-snippets-pro-max' ),
					'<code>define( \'ACSPM_SAFE_MODE\', true );</code>'
				);
				?>
				<a href="<?php echo esc_url( $review_url ); ?>"><?php esc_html_e( 'Review your snippets', 'awesome-code-snippets-pro-max' ); ?></a>.
			<?php else : ?>
				<a href="<?php echo esc_url( $review_url ); ?>"><?php esc_html_e( 'Review your snippets', 'awesome-code-snippets-pro-max' ); ?></a>
				<?php esc_html_e( 'and deactivate any problematic code, then', 'awesome-code-snippets-pro-max' ); ?>
				<a href="<?php echo esc_url( remove_query_arg( 'acspm-safe-mode' ) ); ?>"><?php esc_html_e( 'exit safe mode', 'awesome-code-snippets-pro-max' ); ?></a>.
			<?php endif; ?>
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

	// Stamp the current DB version so fresh installs skip the upgrade ladder —
	// the options above are already created correctly, nothing to migrate.
	update_option( 'acspm_db_version', '1.2' );
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

		// Use 'no' (not the WP 6.6-only 'off') so the value is understood by every
		// supported core from the 5.0 floor upward.
		$wpdb->update(
			$wpdb->options,
			array( 'autoload' => 'no' ),
			array( 'option_name' => 'acspm_header_code' )
		);
		$wpdb->update(
			$wpdb->options,
			array( 'autoload' => 'no' ),
			array( 'option_name' => 'acspm_footer_code' )
		);

		// A direct SQL write leaves the options object cache stale — on a
		// persistent cache (Redis/Memcached) the old autoload flag would keep
		// being served, defeating the migration. Flush the affected caches.
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'acspm_header_code', 'options' );
		wp_cache_delete( 'acspm_footer_code', 'options' );

		update_option( 'acspm_db_version', '1.1' );
	}

	if ( version_compare( $db_version, '1.2', '<' ) ) {
		// Move PHP cache from uploads/ (web-accessible) to wp-content/cache/ (not web-accessible).
		$upload_dir = wp_upload_dir();
		$old_cache  = $upload_dir['basedir'] . '/acspm-cache';

		if ( is_dir( $old_cache ) ) {
			$old_files = array_merge(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob
				glob( $old_cache . '/*' ) ?: array(),
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob
				glob( $old_cache . '/.*' ) ?: array()
			);
			foreach ( $old_files as $file ) {
				if ( ! in_array( basename( $file ), array( '.', '..' ), true ) && is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
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
