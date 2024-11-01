<?php
class Zenpost_Options {

	/**
	 * The settings page title.
	 *
	 * @const string
	 */
	const PAGE_TITLE = 'Zenpost';

	/**
	 * The menu option title.
	 *
	 * @const string
	 */
	const MENU_TITLE = 'Zenpost';

	/**
	 * The capability required for accessing this page.
	 *
	 * @const string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * The menu slug.
	 *
	 * @const string
	 */
	const MENU_SLUG = 'zenpost';

	/**
	 * The option group.
	 *
	 * @const string
	 */
	const OPTION_GROUP = 'zenpost-auth';

	/**
	 * The auth secret option name.
	 *
	 * @const string
	 */
	const OPTION_NAME = 'zenpost-auth-secret';

	/**
	 * The plugin URL.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Create the options page.
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_options_page' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			$this->plugin_url = plugin_dir_url( __FILE__ );
		}		
	}
	/**
	 * Add the options page to the settings menu.
	 */
	public function add_options_page() {
		add_options_page(
			self::PAGE_TITLE,
			self::MENU_TITLE,
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'display_options_page' )
		);
	}

	/**
	 * Render the options page.
	 */
	public function display_options_page() {
		$option_value = self::get_secret();

		if ( null === $option_value || 0 === strlen( $option_value ) ) {
			if ( self::set_initial_secret() ) {
				$option_value = self::get_secret();
			}
		}

		include( dirname( __FILE__ ) . '/admin-page.php' );
	}

	/**
	 * Register zenpost-post-secret option.
	 */
	public function register_settings() {
		register_setting( self::OPTION_GROUP, self::OPTION_NAME );
	}

	/**
	 * Enqueue options page JavaScript.
	 */
	public function enqueue_scripts() {
		$admin_js_url = $this->plugin_url . '/admin-page.js';
		wp_enqueue_script( 'admin-js', $admin_js_url, array( 'jquery' ) );
		$clipboard_url 	=$this->plugin_url . '/assets/clipboard.min.js';
		wp_enqueue_script( 'clipboard-js', $clipboard_url, array( 'jquery' ) );
	}

	/**
	 * Get the current secret.
	 *
	 * @return string the secret or null if not set
	 */
	public static function get_secret() {
		return get_option( self::OPTION_NAME, null );
	}

	/**
	 * Set an initial random secret.
	 *
	 * @return bool success
	 */
	public static function set_initial_secret() {
		$secret = Zenpost_HMAC_Auth::generate_secret();
		return update_option( self::OPTION_NAME, $secret, 'yes' );
	}
}