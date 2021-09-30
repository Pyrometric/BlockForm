<?php
/**
 * Core class for BlockForm.
 *
 * @package blockform
 */

namespace BlockForm;

/**
 * BlockForm Class.
 */
class BlockForm {

	/**
	 * The single instance of the class.
	 *
	 * @var BlockForm
	 */
	protected static $instance = null;

	/**
	 * Holds the version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Holds the plugin name.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Holds the plugin config.
	 *
	 * @var array
	 */
	protected $config = array();

	/**
	 * Holds the plugin slug.
	 *
	 * @var string
	 */
	public static $slug;

	/**
	 * Hold the record of the plugins current version for upgrade.
	 *
	 * @var string
	 */
	const VERSION_KEY = '_blockform_version';

	/**
	 * Hold the config storage key.
	 *
	 * @var string
	 */
	const CONFIG_KEY = '_blockform_config';

	/**
	 * Initiate the blockform object.
	 */
	public function __construct() {

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin            = get_plugin_data( BLOCKFORM_CORE );
		$this->plugin_name = $plugin['Name'];
		$this->version     = $plugin['Version'];
		self::$slug        = $plugin['TextDomain'];

		spl_autoload_register( array( $this, 'autoload_class' ), true, false );

		// Start hooks.
		$this->setup_hooks();

		// Wire up the REST api.
		new Rest( $this );
	}

	/**
	 * Setup and register WordPress hooks.
	 */
	protected function setup_hooks() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_text_domain' ) );
		add_action( 'init', array( $this, 'blockform_init' ), PHP_INT_MAX ); // Always the last thing to init.
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Autoloader by Locating and finding classes via folder structure.
	 *
	 * @param string $class class name to be checked and autoloaded.
	 */

	function autoload_class( $class ) {

		$class_location = self::locate_class_file( $class );
		if ( $class_location ) {
			include_once $class_location;
		}
	}

	/**
	 * Locates the path to a requested class name.
	 *
	 * @param string $class The class name to locate.
	 *
	 * @return string|null
	 */
	static public function locate_class_file( $class ) {

		$return = null;
		$parts  = explode( '\\', strtolower( str_replace( '_', '-', $class ) ) );
		$core   = array_shift( $parts );
		$self   = strtolower( str_replace( '_', '-', __CLASS__ ) );
		if ( $core === self::$slug ) {
			$name    = 'class-' . strtolower( array_pop( $parts ) ) . '.php';
			$parts[] = $name;
			$path    = BLOCKFORM_PATH . 'classes/' . implode( '/', $parts );
			if ( file_exists( $path ) ) {
				$return = $path;
			}
		}

		return $return;
	}

	/**
	 * Get the plugin version
	 */
	public function version() {

		return $this->version;
	}

	/**
	 * Check blockform version to allow 3rd party implementations to update or upgrade.
	 */
	protected function check_version() {

		$previous_version = get_option( self::VERSION_KEY, 0.0 );
		$new_version      = $this->version();
		if ( version_compare( $previous_version, $new_version, '<' ) ) {
			// Allow for updating.
			do_action( "_blockform_version_upgrade", $previous_version, $new_version );
			// Update version.
			update_option( self::VERSION_KEY, $new_version, true );
		}
	}

	/**
	 * Initialise blockform.
	 */
	public function blockform_init() {

		// Check version.
		$this->check_version();

		// Load config.
		$config = $this->load_config();
		$this->set_config( $config );

		/**
		 * Init the settings system
		 *
		 * @param BlockForm ${slug} The core object.
		 */
		do_action( 'blockform_init' );
	}

	/**
	 * Hook into admin_init.
	 */
	public function admin_init() {

		$asset = include BLOCKFORM_PATH . 'js/' . self::$slug . '.asset.php';
		wp_register_script( self::$slug, BLOCKFORM_URL . 'js/' . self::$slug . '.js', $asset['dependencies'], $asset['version'], true );
		wp_register_style( self::$slug, BLOCKFORM_URL . 'css/' . self::$slug . '.css', array(), $asset['version'] );
	}

	/**
	 * Hook into the admin_menu.
	 */
	public function admin_menu() {

		if ( ! $this->network_active() || $this->site_enabled() || is_main_site() ) {
			add_menu_page( __( 'BlockForm', self::$slug ), __( 'BlockForm', self::$slug ), 'manage_options', 'blockform', array( $this, 'render_admin' ), 'dashicons-editor-table' );
		}
	}

	/**
	 * Check if the plugin is network activated.
	 *
	 * @return bool
	 */
	protected function network_active() {

		return is_plugin_active_for_network( BLOCKFORM_SLUG );
	}

	/**
	 * Check to see if the site is allowed to use this.
	 *
	 * @return bool
	 */
	protected function site_enabled() {

		$site_id     = get_current_blog_id();
		$main_config = get_network_option( get_main_site_id(), self::CONFIG_KEY, $this->get_default_config() );

		return in_array( $site_id, $main_config['sitesEnabled'], true );
	}

	/**
	 * Enqueue assets where needed.
	 */
	public function enqueue_assets() {

		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );
		if ( $page && self::$slug === $page ) {
			wp_enqueue_script( self::$slug );
			wp_enqueue_style( self::$slug );

			$this->prep_config();
		}
	}

	/**
	 * Prepare the config data for output to the admin UI.
	 */
	protected function prep_config() {

		$data = $this->build_config_object();

		// Add config data.
		wp_add_inline_script( self::$slug, 'var blkData = ' . $data, 'before' );
	}

	/**
	 * Build the json config object.
	 *
	 * @param int|null $site_id The site to get config for, ir null for current.
	 *
	 * @return string|false
	 */
	public function build_config_object( $site_id = null ) {

		if ( null === $site_id ) {
			$data = $this->config;
		} else {
			$data = $this->load_config( $site_id );
		}
		// Prep config data.
		$data['saveURL']   = rest_url( self::$slug . '/save' );
		$data['restNonce'] = wp_create_nonce( 'wp_rest' );
		// Multisite.
		if ( $this->network_active() && ( is_network_admin() || defined( 'REST_REQUEST' ) && true === REST_REQUEST ) ) {
			$data['loadURL']  = rest_url( self::$slug . '/load' );
			$data['sites']    = get_sites();
			$data['mainSite'] = get_main_site_id();
			if ( ! in_array( $data['mainSite'], $data['sitesEnabled'], true ) ) {
				$data['sitesEnabled'][] = get_main_site_id();
			}
		}

		return wp_json_encode( $data );
	}

	/**
	 * Get the default config.
	 *
	 * @return array
	 */
	protected function get_default_config() {

		return array(
			'sitesEnabled' => array(), // Used for multisite.
		);
	}

	/**
	 * Load the UI config.
	 *
	 * @param int|null $site_id The site ID to load. Null for current site.
	 *
	 * @return array;
	 */
	public function load_config( $site_id = null ) {

		// Load the config.
		if ( is_multisite() ) {
			if ( ! $site_id ) {
				$site_id = get_current_blog_id();
			}
			$config           = get_network_option( $site_id, self::CONFIG_KEY, $this->get_default_config() );
			$config['siteID'] = (int) $site_id;
		} else {
			$config = get_option( self::CONFIG_KEY, $this->get_default_config() );
		}
		$config['pluginName'] = $this->plugin_name;
		$config['version']    = $this->version;
		$config['slug']       = self::$slug;

		return $config;
	}

	/**
	 * Set the config.
	 *
	 * @param array $config The config to set.
	 */
	public function set_config( $config ) {

		$this->config = $config;
	}

	/**
	 * Save the current config.
	 *
	 * @param null|int $site_id The site ID to save for.
	 *
	 * @return bool
	 */
	public function save_config( $site_id = null ) {

		if ( is_multisite() ) {
			if ( null === $site_id ) {
				$site_id = get_current_blog_id();
			}
			$success = update_network_option( $site_id, self::CONFIG_KEY, $this->config );
		} else {
			$success = update_option( self::CONFIG_KEY, $this->config );
		}

		return $success;
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin() {

		include BLOCKFORM_PATH . 'includes/main.php';
	}

	/**
	 * Get the instance of the class.
	 *
	 * @return BlockForm
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
