<?php
/**
 * Nota setup
 * 
 * @package NotaPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Nota class.
 */
class Nota {
	/**
	 * The single instance of the class
	 * 
	 * @var Nota
	 */
	protected static $_instance = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Settings class
	 * 
	 * @var Nota_Settings
	 */
	public $settings;

	/**
	 * API class
	 * 
	 * @var Nota_API
	 */
	public $api;

	/**
	 * WP Rest class
	 * 
	 * @var Nota_WP_Rest
	 */
	public $wp_rest;

	/**
	 * WP Post tools class
	 * 
	 * @var Nota_Post_Tools
	 */
	public $post_tools;

	/**
	 * Main Nota Instance.
	 * Ensures only one instance of Nota is loaded or can be loaded.
	 * 
	 * @return Nota - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Nota constructor
	 */
	public function __construct() {
		$this->define_constants();  
		$this->includes();
	}

	/**
	 * Defines constants
	 */
	private function define_constants() {
		define( 'NOTA_PLUGIN_ABSPATH', dirname( NOTA_PLUGIN_FILE ) . '/' );
		define( 'NOTA_PLUGIN_URL', plugins_url( '/', NOTA_PLUGIN_FILE ) );
		define( 'NOTA_PLUGIN_NONCE', 'NOTA_PLUGIN_NONCE' );
	}

	/**
	 * Includes files
	 */
	private function includes() {
		include_once NOTA_PLUGIN_ABSPATH . 'includes/class-nota-logger.php';
		include_once NOTA_PLUGIN_ABSPATH . 'includes/class-nota-settings.php';
		include_once NOTA_PLUGIN_ABSPATH . 'includes/class-nota-api.php';
		include_once NOTA_PLUGIN_ABSPATH . 'includes/class-nota-wp-rest.php';
		include_once NOTA_PLUGIN_ABSPATH . 'includes/class-nota-post-tools.php';
		include_once NOTA_PLUGIN_ABSPATH . 'includes/class-nota-debug.php';

		$this->settings   = new Nota_Settings();
		$this->api        = new Nota_Api( $this->settings );
		$this->wp_rest    = new Nota_WP_Rest( $this->api );
		$this->post_tools = new Nota_Post_Tools( $this->settings );
		new Nota_Debug();
	}

	/**
	 * Is the site in Nota debug mode?
	 */
	public static function is_debug_mode() {
		return get_option( 'nota_debug', false );
	}
}
Nota::instance();
