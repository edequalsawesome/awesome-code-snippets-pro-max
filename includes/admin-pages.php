<?php
/**
 * Admin pages for the plugin
 *
 * @package Awesome_Code_Snippets_Pro_Max
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin pages management class
 */
class ACSPM_Admin_Pages {

	/**
	 * Singleton instance
	 *
	 * @var ACSPM_Admin_Pages
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return ACSPM_Admin_Pages
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
	}

	/**
	 * Add menu pages under Tools
	 */
	public function add_menu_pages() {
		add_submenu_page(
			'tools.php',
			__( 'Code Snippets', 'awesome-code-snippets-pro-max' ),
			__( 'Code Snippets', 'awesome-code-snippets-pro-max' ),
			'manage_options',
			'acspm-snippets',
			array( $this, 'render_snippets_page' )
		);

		add_submenu_page(
			'tools.php',
			__( 'Header & Footer', 'awesome-code-snippets-pro-max' ),
			__( 'Header & Footer', 'awesome-code-snippets-pro-max' ),
			'manage_options',
			'acspm-header-footer',
			array( $this, 'render_header_footer_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our pages
		if ( ! in_array( $hook, array( 'tools_page_acspm-snippets', 'tools_page_acspm-header-footer' ), true ) ) {
			return;
		}

		// Enqueue admin CSS
		wp_enqueue_style(
			'acspm-admin',
			ACSPM_PLUGIN_URL . 'assets/admin.css',
			array(),
			ACSPM_VERSION
		);

		// Enqueue code editor
		$settings = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );

		if ( false !== $settings ) {
			wp_enqueue_script( 'wp-theme-plugin-editor' );
			wp_enqueue_style( 'wp-codemirror' );
		}
	}

	/**
	 * Handle form submissions
	 */
	public function handle_form_submissions() {
		// Handle snippet actions
		if ( isset( $_POST['acspm_snippet_action'] ) && check_admin_referer( 'acspm_snippet_action', 'acspm_nonce' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do this.', 'awesome-code-snippets-pro-max' ) );
			}

			$this->handle_snippet_action();
		}

		// Handle header/footer save
		if ( isset( $_POST['acspm_save_header_footer'] ) && check_admin_referer( 'acspm_header_footer', 'acspm_nonce' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do this.', 'awesome-code-snippets-pro-max' ) );
			}

			$this->handle_header_footer_save();
		}

		// Handle snippet toggle via GET
		if ( isset( $_GET['action'] ) && 'toggle' === $_GET['action'] && isset( $_GET['snippet_id'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'acspm_toggle_' . $_GET['snippet_id'] ) ) {
				wp_die( esc_html__( 'Security check failed.', 'awesome-code-snippets-pro-max' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do this.', 'awesome-code-snippets-pro-max' ) );
			}

			$snippets = ACSPM_Snippets::get_instance();
			$snippets->toggle_snippet( (int) $_GET['snippet_id'] );

			wp_safe_redirect( admin_url( 'tools.php?page=acspm-snippets&toggled=1' ) );
			exit;
		}

		// Handle snippet delete via GET
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['snippet_id'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'acspm_delete_' . $_GET['snippet_id'] ) ) {
				wp_die( esc_html__( 'Security check failed.', 'awesome-code-snippets-pro-max' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do this.', 'awesome-code-snippets-pro-max' ) );
			}

			$snippets = ACSPM_Snippets::get_instance();
			$snippets->delete_snippet( (int) $_GET['snippet_id'] );

			wp_safe_redirect( admin_url( 'tools.php?page=acspm-snippets&deleted=1' ) );
			exit;
		}
	}

	/**
	 * Handle snippet create/update actions
	 */
	private function handle_snippet_action() {
		$snippets = ACSPM_Snippets::get_instance();

		$data = array(
			'name'        => isset( $_POST['snippet_name'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_name'] ) ) : '',
			'code'        => isset( $_POST['snippet_code'] ) ? $_POST['snippet_code'] : '',
			'code_type'   => isset( $_POST['snippet_code_type'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_code_type'] ) ) : 'php',
			'location'    => isset( $_POST['snippet_location'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_location'] ) ) : 'wp_head',
			'custom_hook' => isset( $_POST['snippet_custom_hook'] ) ? sanitize_text_field( wp_unslash( $_POST['snippet_custom_hook'] ) ) : '',
			'priority'    => isset( $_POST['snippet_priority'] ) ? (int) $_POST['snippet_priority'] : 10,
			'active'      => ! empty( $_POST['snippet_active'] ),
		);

		if ( ! empty( $_POST['snippet_id'] ) ) {
			// Update existing snippet
			$snippets->update_snippet( (int) $_POST['snippet_id'], $data );
			$redirect_arg = 'updated=1';
		} else {
			// Create new snippet
			$snippets->create_snippet( $data );
			$redirect_arg = 'created=1';
		}

		wp_safe_redirect( admin_url( 'tools.php?page=acspm-snippets&' . $redirect_arg ) );
		exit;
	}

	/**
	 * Handle header/footer save
	 */
	private function handle_header_footer_save() {
		$header_footer = ACSPM_Header_Footer::get_instance();

		$header_code = isset( $_POST['header_code'] ) ? $_POST['header_code'] : '';
		$footer_code = isset( $_POST['footer_code'] ) ? $_POST['footer_code'] : '';

		$header_footer->save_header_code( $header_code );
		$header_footer->save_footer_code( $footer_code );

		wp_safe_redirect( admin_url( 'tools.php?page=acspm-header-footer&saved=1' ) );
		exit;
	}

	/**
	 * Render the plugin logo area
	 */
	private function render_logo() {
		?>
		<div class="acspm-plugin-logo">
			<!-- Drop your rad SVG logo here -->
		</div>
		<?php
	}

	/**
	 * Render the footer tagline
	 */
	private function render_footer() {
		?>
		<p class="acspm-footer-tagline">
			<?php esc_html_e( "You're using the complete version. That's it. That's all the versions. Do something rad with it.", 'awesome-code-snippets-pro-max' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the snippets admin page
	 */
	public function render_snippets_page() {
		$snippets_manager = ACSPM_Snippets::get_instance();
		$snippets         = $snippets_manager->get_snippets();
		$editing_snippet  = null;

		// Check if editing a snippet
		if ( isset( $_GET['edit'] ) ) {
			$editing_snippet = $snippets_manager->get_snippet( (int) $_GET['edit'] );
		}

		// Show add new form
		$show_form = isset( $_GET['add_new'] ) || $editing_snippet;

		?>
		<div class="wrap">
			<?php $this->render_logo(); ?>

			<h1 class="wp-heading-inline"><?php esc_html_e( 'Code Snippets', 'awesome-code-snippets-pro-max' ); ?></h1>

			<?php if ( ! $show_form ) : ?>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=acspm-snippets&add_new=1' ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Add New', 'awesome-code-snippets-pro-max' ); ?>
				</a>
			<?php endif; ?>

			<hr class="wp-header-end">

			<?php
			// Show admin notices
			if ( isset( $_GET['created'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Snippet created successfully.', 'awesome-code-snippets-pro-max' ) . '</p></div>';
			}
			if ( isset( $_GET['updated'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Snippet updated successfully.', 'awesome-code-snippets-pro-max' ) . '</p></div>';
			}
			if ( isset( $_GET['deleted'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Snippet deleted successfully.', 'awesome-code-snippets-pro-max' ) . '</p></div>';
			}
			if ( isset( $_GET['toggled'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Snippet status updated.', 'awesome-code-snippets-pro-max' ) . '</p></div>';
			}
			?>

			<?php if ( $show_form ) : ?>
				<?php $this->render_snippet_form( $editing_snippet ); ?>
			<?php else : ?>
				<?php $this->render_snippets_table( $snippets ); ?>
			<?php endif; ?>

			<?php $this->render_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Render the snippet add/edit form
	 *
	 * @param array|null $snippet Snippet data for editing, or null for new snippet.
	 */
	private function render_snippet_form( $snippet = null ) {
		$is_edit = ! empty( $snippet );
		?>
		<div class="card" style="max-width: 100%;">
			<h2><?php echo $is_edit ? esc_html__( 'Edit Snippet', 'awesome-code-snippets-pro-max' ) : esc_html__( 'Add New Snippet', 'awesome-code-snippets-pro-max' ); ?></h2>

			<form method="post" action="">
				<?php wp_nonce_field( 'acspm_snippet_action', 'acspm_nonce' ); ?>
				<input type="hidden" name="acspm_snippet_action" value="1">

				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="snippet_id" value="<?php echo esc_attr( $snippet['id'] ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="snippet_name"><?php esc_html_e( 'Name', 'awesome-code-snippets-pro-max' ); ?></label>
						</th>
						<td>
							<input type="text" name="snippet_name" id="snippet_name" class="regular-text" required
								value="<?php echo $is_edit ? esc_attr( $snippet['name'] ) : ''; ?>">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="snippet_code_type"><?php esc_html_e( 'Code Type', 'awesome-code-snippets-pro-max' ); ?></label>
						</th>
						<td>
							<select name="snippet_code_type" id="snippet_code_type">
								<option value="php" <?php selected( $is_edit ? $snippet['code_type'] : '', 'php' ); ?>>PHP</option>
								<option value="js" <?php selected( $is_edit ? $snippet['code_type'] : '', 'js' ); ?>>JavaScript</option>
								<option value="css" <?php selected( $is_edit ? $snippet['code_type'] : '', 'css' ); ?>>CSS</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'PHP code should NOT start with <?php tag. You CAN use ?> and <?php inside callbacks to output HTML.', 'awesome-code-snippets-pro-max' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="snippet_code"><?php esc_html_e( 'Code', 'awesome-code-snippets-pro-max' ); ?></label>
						</th>
						<td>
							<textarea name="snippet_code" id="snippet_code" rows="15" class="large-text code"><?php echo $is_edit ? esc_textarea( $snippet['code'] ) : ''; ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="snippet_location"><?php esc_html_e( 'Location', 'awesome-code-snippets-pro-max' ); ?></label>
						</th>
						<td>
							<select name="snippet_location" id="snippet_location">
								<option value="wp_head" <?php selected( $is_edit ? $snippet['location'] : '', 'wp_head' ); ?>><?php esc_html_e( 'Frontend Header (wp_head)', 'awesome-code-snippets-pro-max' ); ?></option>
								<option value="wp_footer" <?php selected( $is_edit ? $snippet['location'] : '', 'wp_footer' ); ?>><?php esc_html_e( 'Frontend Footer (wp_footer)', 'awesome-code-snippets-pro-max' ); ?></option>
								<option value="everywhere" <?php selected( $is_edit ? $snippet['location'] : '', 'everywhere' ); ?>><?php esc_html_e( 'Everywhere (frontend + admin)', 'awesome-code-snippets-pro-max' ); ?></option>
								<option value="custom" <?php selected( $is_edit ? $snippet['location'] : '', 'custom' ); ?>><?php esc_html_e( 'Custom Hook', 'awesome-code-snippets-pro-max' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'PHP runs on init. JS outputs to footer. CSS outputs to head.', 'awesome-code-snippets-pro-max' ); ?>
							</p>
						</td>
					</tr>

					<tr class="acspm-custom-hook-row" style="<?php echo ( $is_edit && 'custom' === $snippet['location'] ) ? '' : 'display: none;'; ?>">
						<th scope="row">
							<label for="snippet_custom_hook"><?php esc_html_e( 'Custom Hook Name', 'awesome-code-snippets-pro-max' ); ?></label>
						</th>
						<td>
							<input type="text" name="snippet_custom_hook" id="snippet_custom_hook" class="regular-text"
								value="<?php echo $is_edit ? esc_attr( $snippet['custom_hook'] ) : ''; ?>"
								placeholder="e.g., init, wp_loaded, woocommerce_before_cart">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="snippet_priority"><?php esc_html_e( 'Priority', 'awesome-code-snippets-pro-max' ); ?></label>
						</th>
						<td>
							<input type="number" name="snippet_priority" id="snippet_priority" class="small-text"
								value="<?php echo $is_edit ? esc_attr( $snippet['priority'] ) : '10'; ?>" min="1" max="999">
							<p class="description">
								<?php esc_html_e( 'Lower numbers run earlier. Default is 10.', 'awesome-code-snippets-pro-max' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Status', 'awesome-code-snippets-pro-max' ); ?>
						</th>
						<td>
							<label for="snippet_active">
								<input type="checkbox" name="snippet_active" id="snippet_active" value="1"
									<?php checked( $is_edit ? $snippet['active'] : true ); ?>>
								<?php esc_html_e( 'Active', 'awesome-code-snippets-pro-max' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php echo $is_edit ? esc_attr__( 'Update Snippet', 'awesome-code-snippets-pro-max' ) : esc_attr__( 'Save Snippet', 'awesome-code-snippets-pro-max' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'tools.php?page=acspm-snippets' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'awesome-code-snippets-pro-max' ); ?></a>
				</p>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Initialize CodeMirror
			if (typeof wp !== 'undefined' && wp.codeEditor) {
				var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
				editorSettings.codemirror = _.extend({}, editorSettings.codemirror, {
					mode: 'php',
					lineNumbers: true,
					lineWrapping: true,
					indentUnit: 4,
					tabSize: 4,
					indentWithTabs: true
				});

				var editor = wp.codeEditor.initialize($('#snippet_code'), editorSettings);

				// Update CodeMirror mode when code type changes
				$('#snippet_code_type').on('change', function() {
					var mode = 'php';
					switch($(this).val()) {
						case 'js':
							mode = 'javascript';
							break;
						case 'css':
							mode = 'css';
							break;
						case 'php':
						default:
							mode = 'php';
							break;
					}
					editor.codemirror.setOption('mode', mode);
				});

				// Trigger initial mode set
				$('#snippet_code_type').trigger('change');
			}

			// Show/hide custom hook field
			$('#snippet_location').on('change', function() {
				if ($(this).val() === 'custom') {
					$('.acspm-custom-hook-row').show();
				} else {
					$('.acspm-custom-hook-row').hide();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render the snippets table
	 *
	 * @param array $snippets Array of snippets.
	 */
	private function render_snippets_table( $snippets ) {
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="column-name"><?php esc_html_e( 'Name', 'awesome-code-snippets-pro-max' ); ?></th>
					<th scope="col" class="column-type"><?php esc_html_e( 'Type', 'awesome-code-snippets-pro-max' ); ?></th>
					<th scope="col" class="column-location"><?php esc_html_e( 'Location', 'awesome-code-snippets-pro-max' ); ?></th>
					<th scope="col" class="column-priority"><?php esc_html_e( 'Priority', 'awesome-code-snippets-pro-max' ); ?></th>
					<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'awesome-code-snippets-pro-max' ); ?></th>
					<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'awesome-code-snippets-pro-max' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $snippets ) ) : ?>
					<tr>
						<td colspan="6">
							<?php esc_html_e( 'No snippets yet. Add your first snippet to get started.', 'awesome-code-snippets-pro-max' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $snippets as $snippet ) : ?>
						<tr>
							<td class="column-name">
								<strong>
									<a href="<?php echo esc_url( admin_url( 'tools.php?page=acspm-snippets&edit=' . $snippet['id'] ) ); ?>">
										<?php echo esc_html( $snippet['name'] ); ?>
									</a>
								</strong>
							</td>
							<td class="column-type">
								<?php
								$types = array(
									'php' => 'PHP',
									'js'  => 'JavaScript',
									'css' => 'CSS',
								);
								echo esc_html( $types[ $snippet['code_type'] ] ?? $snippet['code_type'] );
								?>
							</td>
							<td class="column-location">
								<?php
								$locations = array(
									'wp_head'    => __( 'Frontend Header', 'awesome-code-snippets-pro-max' ),
									'wp_footer'  => __( 'Frontend Footer', 'awesome-code-snippets-pro-max' ),
									'everywhere' => __( 'Everywhere', 'awesome-code-snippets-pro-max' ),
									'custom'     => __( 'Custom', 'awesome-code-snippets-pro-max' ),
								);
								echo esc_html( $locations[ $snippet['location'] ] ?? $snippet['location'] );
								if ( 'custom' === $snippet['location'] && ! empty( $snippet['custom_hook'] ) ) {
									echo ' <code>' . esc_html( $snippet['custom_hook'] ) . '</code>';
								}
								?>
							</td>
							<td class="column-priority">
								<?php echo esc_html( $snippet['priority'] ); ?>
							</td>
							<td class="column-status">
								<?php
								$toggle_url = wp_nonce_url(
									admin_url( 'tools.php?page=acspm-snippets&action=toggle&snippet_id=' . $snippet['id'] ),
									'acspm_toggle_' . $snippet['id']
								);
								?>
								<a href="<?php echo esc_url( $toggle_url ); ?>" class="acspm-status-toggle">
									<?php if ( $snippet['active'] ) : ?>
										<span class="acspm-status-active"><?php esc_html_e( 'Active', 'awesome-code-snippets-pro-max' ); ?></span>
									<?php else : ?>
										<span class="acspm-status-inactive"><?php esc_html_e( 'Inactive', 'awesome-code-snippets-pro-max' ); ?></span>
									<?php endif; ?>
								</a>
							</td>
							<td class="column-actions">
								<a href="<?php echo esc_url( admin_url( 'tools.php?page=acspm-snippets&edit=' . $snippet['id'] ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Edit', 'awesome-code-snippets-pro-max' ); ?>
								</a>
								<?php
								$delete_url = wp_nonce_url(
									admin_url( 'tools.php?page=acspm-snippets&action=delete&snippet_id=' . $snippet['id'] ),
									'acspm_delete_' . $snippet['id']
								);
								?>
								<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this snippet?', 'awesome-code-snippets-pro-max' ) ); ?>');">
									<?php esc_html_e( 'Delete', 'awesome-code-snippets-pro-max' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the header/footer admin page
	 */
	public function render_header_footer_page() {
		$header_footer = ACSPM_Header_Footer::get_instance();
		$header_code   = $header_footer->get_header_code();
		$footer_code   = $header_footer->get_footer_code();

		?>
		<div class="wrap">
			<?php $this->render_logo(); ?>

			<h1><?php esc_html_e( 'Header & Footer Scripts', 'awesome-code-snippets-pro-max' ); ?></h1>

			<hr class="wp-header-end">

			<?php
			if ( isset( $_GET['saved'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'awesome-code-snippets-pro-max' ) . '</p></div>';
			}
			?>

			<form method="post" action="">
				<?php wp_nonce_field( 'acspm_header_footer', 'acspm_nonce' ); ?>
				<input type="hidden" name="acspm_save_header_footer" value="1">

				<div class="card" style="max-width: 100%;">
					<h2><?php esc_html_e( 'Code after <head>', 'awesome-code-snippets-pro-max' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'This code will be inserted right after the opening <head> tag. Great for analytics, meta tags, fonts, etc.', 'awesome-code-snippets-pro-max' ); ?>
					</p>
					<textarea name="header_code" id="acspm_header_code" rows="10" class="large-text code"><?php echo esc_textarea( $header_code ); ?></textarea>
				</div>

				<div class="card" style="max-width: 100%; margin-top: 20px;">
					<h2><?php esc_html_e( 'Code before </body>', 'awesome-code-snippets-pro-max' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'This code will be inserted right before the closing </body> tag. Good for tracking scripts, chat widgets, etc.', 'awesome-code-snippets-pro-max' ); ?>
					</p>
					<textarea name="footer_code" id="acspm_footer_code" rows="10" class="large-text code"><?php echo esc_textarea( $footer_code ); ?></textarea>
				</div>

				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'awesome-code-snippets-pro-max' ); ?>">
				</p>
			</form>

			<?php $this->render_footer(); ?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Initialize CodeMirror for both textareas
			if (typeof wp !== 'undefined' && wp.codeEditor) {
				var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
				editorSettings.codemirror = _.extend({}, editorSettings.codemirror, {
					mode: 'htmlmixed',
					lineNumbers: true,
					lineWrapping: true,
					indentUnit: 4,
					tabSize: 4,
					indentWithTabs: true
				});

				wp.codeEditor.initialize($('#acspm_header_code'), editorSettings);
				wp.codeEditor.initialize($('#acspm_footer_code'), editorSettings);
			}
		});
		</script>
		<?php
	}
}
