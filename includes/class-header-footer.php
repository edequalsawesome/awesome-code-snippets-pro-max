<?php
/**
 * Header/Footer injection functionality
 *
 * @package Awesome_Code_Snippets_Pro_Max
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Header/Footer management class
 */
class ACSPM_Header_Footer {

	/**
	 * Singleton instance
	 *
	 * @var ACSPM_Header_Footer
	 */
	private static $instance = null;

	/**
	 * Option name for header code
	 *
	 * @var string
	 */
	const OPTION_HEADER = 'acspm_header_code';

	/**
	 * Option name for footer code
	 *
	 * @var string
	 */
	const OPTION_FOOTER = 'acspm_footer_code';

	/**
	 * Get singleton instance
	 *
	 * @return ACSPM_Header_Footer
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
		add_action( 'wp_head', array( $this, 'output_header_code' ), 1 );
		add_action( 'wp_footer', array( $this, 'output_footer_code' ), 999 );
	}

	/**
	 * Get header code
	 *
	 * @return string Header code.
	 */
	public function get_header_code() {
		return get_option( self::OPTION_HEADER, '' );
	}

	/**
	 * Get footer code
	 *
	 * @return string Footer code.
	 */
	public function get_footer_code() {
		return get_option( self::OPTION_FOOTER, '' );
	}

	/**
	 * Save header code
	 *
	 * @param string $code Header code to save.
	 * @return bool True on success, false on failure.
	 */
	public function save_header_code( $code ) {
		return update_option( self::OPTION_HEADER, wp_unslash( $code ) );
	}

	/**
	 * Save footer code
	 *
	 * @param string $code Footer code to save.
	 * @return bool True on success, false on failure.
	 */
	public function save_footer_code( $code ) {
		return update_option( self::OPTION_FOOTER, wp_unslash( $code ) );
	}

	/**
	 * Output header code in wp_head
	 */
	public function output_header_code() {
		$code = $this->get_header_code();

		if ( ! empty( $code ) ) {
			echo "\n<!-- Awesome Code Snippets Pro Max - Header -->\n";
			echo $code;
			echo "\n<!-- /Awesome Code Snippets Pro Max - Header -->\n";
		}
	}

	/**
	 * Output footer code in wp_footer
	 */
	public function output_footer_code() {
		$code = $this->get_footer_code();

		if ( ! empty( $code ) ) {
			echo "\n<!-- Awesome Code Snippets Pro Max - Footer -->\n";
			echo $code;
			echo "\n<!-- /Awesome Code Snippets Pro Max - Footer -->\n";
		}
	}
}
