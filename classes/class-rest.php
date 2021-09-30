<?php
/**
 * REST API class for BlockForm
 *
 * @package blockform
 */

namespace BlockForm;

/**
 * Rest Class.
 */
class Rest {

	/**
	 * The single instance of the class.
	 *
	 * @var BlockForm
	 */
	protected $blockform = null;

	/**
	 * Initiate the bulk_actions object.
	 *
	 * @param BlockForm $blockform The instance of the main plugin.
	 */
	public function __construct( $blockform ) {

		$this->blockform = $blockform;
		// Start hooks.
		$this->setup_hooks();
	}

	/**
	 * Setup and register WordPress hooks.
	 */
	protected function setup_hooks() {

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST Endpoint for saving config.
	 */
	public function register_routes() {

		register_rest_route(
			BlockForm::$slug,
			'save',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'args'                => array(),
				'callback'            => array( $this, 'rest_save_config' ),
				'permission_callback' => function( \WP_REST_Request $request ) {

					if ( is_multisite() ) {
						$data = $request->get_json_params();
						$can  = current_user_can_for_blog( $data['siteID'], 'manage_options' );
					} else {
						$can = current_user_can( 'manage_options' );
					}

					return $can;
				},
			)
		);

		register_rest_route(
			BlockForm::$slug,
			'load',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'args'                => array(),
				'callback'            => array( $this, 'rest_load_config' ),
				'permission_callback' => function( \WP_REST_Request $request ) {

					$id = $request->get_param( 'siteID' );

					return current_user_can_for_blog( $id, 'manage_options' );
				},
			)
		);
	}

	/**
	 * Load a config for a specific site.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function rest_load_config( \WP_REST_Request $request ) {

		$id   = $request->get_param( 'siteID' );
		$json = $this->blockform->build_config_object( $id );

		return rest_ensure_response( json_decode( $json ) );
	}

	/**
	 * Save endpoint.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function rest_save_config( \WP_REST_Request $request ) {

		$data    = $request->get_json_params();
		$site_id = get_current_blog_id();
		if ( is_multisite() ) {
			if ( ! empty( $data['siteID'] ) ) {
				$site_id = $data['siteID'];
				unset( $data['siteID'] );
			}
			if ( ! empty( $data['sitesEnabled'] ) ) {
				// Ensure we have the same types.
				$data['sitesEnabled'] = array_map( 'intval', $data['sitesEnabled'] );
			}
		}

		$config = wp_parse_args( $data, $this->blockform->load_config( $site_id ) );
		$this->blockform->set_config( $config );
		$success = $this->blockform->save_config( $site_id );

		return rest_ensure_response( array( 'success' => $success ) );
	}
}
