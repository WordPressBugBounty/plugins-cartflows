<?php
/**
 * CartFlows Learn ajax actions.
 *
 * @package CartFlows
 */

namespace CartflowsAdmin\AdminCore\Ajax;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CartflowsAdmin\AdminCore\Ajax\AjaxBase;

/**
 * Class Learn.
 */
class Learn extends AjaxBase {

	/**
	 * Instance
	 *
	 * @access private
	 * @var object Class object.
	 * @since 1.0.0
	 */
	private static $instance;

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return object initialized object of class.
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register_ajax_events.
	 *
	 * @return void
	 */
	public function register_ajax_events() {

		if ( current_user_can( 'cartflows_manage_settings' ) ) {

			$ajax_events = array(
				'save_learn_completed',
				'track_learn_event',
			);
			$this->init_ajax_events( $ajax_events );
		}
	}

	/**
	 * Localize nonce for ajax call — Learn handlers require the settings capability, not the flows one.
	 *
	 * @since x.x.x
	 * @param string $action Action name.
	 * @return void
	 */
	public function localize_ajax_action_nonce( $action ) {

		if ( ! current_user_can( 'cartflows_manage_settings' ) ) {
			return;
		}

		add_filter(
			'cartflows_admin_localized_vars',
			function( $localize ) use ( $action ) {

				$localize[ $action . '_nonce' ] = wp_create_nonce( 'cartflows_' . $action );
				return $localize;
			}
		);
	}

	/**
	 * AJAX handler – persist completed module IDs to option.
	 *
	 * @return void
	 */
	public function save_learn_completed() {
		check_ajax_referer( 'cartflows_save_learn_completed', 'nonce' );

		$module_ids = isset( $_POST['module_ids'] )
			? json_decode( sanitize_text_field( wp_unslash( $_POST['module_ids'] ) ), true )
			: array();

		if ( ! is_array( $module_ids ) ) {
			wp_send_json_error();
		}

		$module_ids = array_map( 'sanitize_text_field', $module_ids );

		// Store manual ticks only — auto-completed IDs would otherwise stick after the store stops satisfying them.
		$module_ids = \Cartflows_Learn_Progress::get_instance()->filter_manual_ids( $module_ids );

		update_option( 'wcf_learn_data', $module_ids );

		wp_send_json_success();
	}

	/**
	 * AJAX handler – record a browser-side Learn event via BSF Analytics.
	 *
	 * Every value is whitelisted so this endpoint cannot become an arbitrary-event writer.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function track_learn_event() {
		check_ajax_referer( 'cartflows_track_learn_event', 'nonce' );

		if ( ! current_user_can( 'cartflows_manage_settings' ) ) {
			wp_send_json_error();
		}

		$event = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( $_POST['event'] ) ) : '';

		if ( ! in_array( $event, \Cartflows_Analytics::get_trackable_learn_events(), true ) ) {
			wp_send_json_error();
		}

		$analytics = \Cartflows_Analytics::get_instance();

		if ( 'learn_page_viewed' === $event ) {
			$analytics->track_learn_page_view();
			wp_send_json_success();
		}

		if ( 'learn_video_opened' === $event ) {
			$analytics->track_learn_video_opened();
			wp_send_json_success();
		}

		$module_id = isset( $_POST['module_id'] ) ? sanitize_key( wp_unslash( $_POST['module_id'] ) ) : '';

		if ( ! in_array( $module_id, \Cartflows_Learn_Progress::get_module_ids(), true ) ) {
			wp_send_json_error();
		}

		if ( 'learn_action_failed' === $event ) {
			$reason = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';

			if ( ! in_array( $reason, \Cartflows_Analytics::get_learn_failure_reasons(), true ) ) {
				wp_send_json_error();
			}

			$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_key( wp_unslash( $_POST['plugin_slug'] ) ) : '';
			$plugin_slug = in_array( $plugin_slug, \Cartflows_Analytics::get_learn_plugin_slugs(), true ) ? $plugin_slug : '';

			$analytics->track_learn_action_failed( $module_id, $reason, $plugin_slug );
			wp_send_json_success();
		}

		// Fail closed: a newly whitelisted event must be routed explicitly, never treated as a CTA click.
		if ( 'learn_module_action' !== $event ) {
			wp_send_json_error();
		}

		$action_type = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : '';

		if ( ! in_array( $action_type, \Cartflows_Analytics::get_learn_action_types(), true ) ) {
			wp_send_json_error();
		}

		$is_pro = isset( $_POST['is_pro'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['is_pro'] ) );

		$analytics->track_learn_module_action( $module_id, $action_type, $is_pro );
		wp_send_json_success();
	}
}
