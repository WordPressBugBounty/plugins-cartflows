<?php
/**
 * CartFlows Loader.
 *
 * @package CartFlows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Cartflows_Nps_Survey' ) ) :

	/**
	 * Cartflows Nps Survey Loader
	 *
	 * @since 2.1.3
	 */
	class Cartflows_Nps_Survey {
		/**
		 * Instance
		 *
		 * @since 2.1.3
		 * @var (Object) Cartflows_Nps_Survey
		 */
		private static $instance = null;

		/**
		 * Get Instance
		 *
		 * @since 2.1.3
		 *
		 * @return object Class object.
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor.
		 *
		 * @since 2.1.3
		 */
		private function __construct() {
			$this->version_check();
			add_action( 'init', array( $this, 'load' ), 999 );
			add_filter( 'nps_survey_api_endpoint', array( $this, 'update_webhook_url' ), 10, 2 );
			add_filter( 'nps_survey_post_data', array( $this, 'mark_nps_dismissed_on_submit' ), 10, 1 );
			add_filter( 'nps_survey_required_capability', array( $this, 'require_flow_manager_capability' ), 10, 2 );
			add_action( 'admin_footer', array( $this, 'alias_script_data_globals' ), 1000 );
			add_filter( 'rest_post_dispatch', array( $this, 'add_legacy_success_flag' ), 10, 3 );
		}

		/**
		 * Version Check
		 *
		 * @since 2.1.3
		 * @return void
		 */
		public function version_check() {

			$file = realpath( dirname( __FILE__ ) . '/nps-survey/version.json' );

			// Is file exist?
			if ( is_file( $file ) ) {

				$file_data = json_decode( file_get_contents( $file ), true );

				global $nps_survey_version, $nps_survey_init;

				$path = realpath( dirname( __FILE__ ) . '/nps-survey/nps-survey.php' );

				$version = isset( $file_data['nps-survey'] ) ? $file_data['nps-survey'] : 0;

				if ( null === $nps_survey_version ) {
					$nps_survey_version = '1.0.0';
				}

				// Compare versions.
				if ( version_compare( $version, $nps_survey_version, '>=' ) ) {
					$nps_survey_version = $version;
					$nps_survey_init    = $path;
				}
			}
		}

		/**
		 * Load latest plugin
		 *
		 * @since 2.1.3
		 * @return void
		 */
		public function load() {

			global $nps_survey_version, $nps_survey_init;
			if ( is_file( realpath( $nps_survey_init ) ) ) {
				include_once realpath( $nps_survey_init );
			}
		}

		/**
		 * Update the NPS survey webhook URL.
		 *
		 * @param string       $url Webhook URL.
		 * @param array<mixed> $post_data NPS survey post data.
		 *
		 * @return string
		 */
		public function update_webhook_url( $url, $post_data ) {
			if ( isset( $post_data['plugin_slug'] ) && 'cartflows' === $post_data['plugin_slug'] ) {
				// Return the NPS webhook URL if the plugin slug is CartFlows.
				return CARTFLOWS_NPS_WEBHOOK_URL;
			}

			return $url;
		}

		/**
		 * Lock the NPS dismissal state as soon as a submission is initiated.
		 * The bundled library only persists `dismiss_permanently` on an HTTP 200
		 * response from the webhook. Ottokit can return 202/204, leaving the
		 * notice re-displayable and causing the same user to resubmit repeatedly.
		 *
		 * @param array<mixed> $post_data NPS survey post data.
		 * @return array<mixed>
		 */
		public function mark_nps_dismissed_on_submit( $post_data ) {
			if ( ! isset( $post_data['plugin_slug'] ) || 'cartflows' !== $post_data['plugin_slug'] ) {
				return $post_data;
			}

			update_option(
				'nps-survey-cartflows',
				array(
					'dismiss_count'       => 0,
					'dismiss_permanently' => true,
					'dismiss_step'        => 'submitted',
				),
				false
			);

			return $post_data;
		}

		/**
		 * Let a user with `cartflows_manage_flows_steps` interact with the NPS survey.
		 * The bundled library defaults to `manage_options`; this scopes CartFlows' own narrower capability to CartFlows' own requests only.
		 *
		 * @since x.x.x
		 * @param string $capability Default capability.
		 * @param object $request WP_REST_Request carrying the `plugin_slug` of the survey being checked.
		 * @return string
		 */
		public function require_flow_manager_capability( $capability, $request ) {

			if ( ! ( $request instanceof \WP_REST_Request ) || 'cartflows' !== $request->get_param( 'plugin_slug' ) ) {
				return $capability;
			}

			return 'cartflows_manage_flows_steps';
		}

		/**
		 * Expose the survey script data as both `npsSurvey` (library <= 1.0.19) and `nps_survey_data` (>= 1.0.20).
		 * Astra serves its own bundled survey script regardless of which library PHP loaded, so the two can disagree on the name.
		 *
		 * @since x.x.x
		 * @return void
		 */
		public function alias_script_data_globals() {

			if ( ! wp_script_is( 'nps-survey-script', 'enqueued' ) ) {
				return;
			}

			wp_add_inline_script(
				'nps-survey-script',
				'window.npsSurvey = window.npsSurvey || window.nps_survey_data; window.nps_survey_data = window.nps_survey_data || window.npsSurvey;',
				'before'
			);
		}

		/**
		 * Add the `success` flag that library <= 1.0.22 sent, so an older survey script still closes the popup.
		 * Newer library versions only return `status`, which the older script ignores.
		 *
		 * @since x.x.x
		 * @param mixed            $response Result to send to the client.
		 * @param \WP_REST_Server  $server   Server instance.
		 * @param \WP_REST_Request $request  Request used to generate the response.
		 * @return mixed
		 */
		public function add_legacy_success_flag( $response, $server, $request ) {

			if ( ! ( $response instanceof \WP_REST_Response ) || ! ( $request instanceof \WP_REST_Request ) || 0 !== strpos( $request->get_route(), '/nps-survey/v1/' ) ) {
				return $response;
			}

			$data = $response->get_data();

			if ( is_array( $data ) && isset( $data['status'] ) && true === $data['status'] ) {
				$data['success'] = true;
				$response->set_data( $data );
			}

			return $response;
		}

	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Cartflows_Nps_Survey::get_instance();

endif;
