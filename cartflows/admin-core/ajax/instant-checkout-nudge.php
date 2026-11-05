<?php
/**
 * CartFlows Instant Checkout nudge ajax actions.
 *
 * @package CartFlows
 */

namespace CartflowsAdmin\AdminCore\Ajax;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CartflowsAdmin\AdminCore\Ajax\AjaxBase;
use CartflowsAdmin\AdminCore\Inc\InstantCheckoutNudge as NudgeState;

/**
 * Class InstantCheckoutNudge.
 *
 * @since x.x.x
 */
class InstantCheckoutNudge extends AjaxBase {

	/**
	 * Instance
	 *
	 * @access private
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Initiator
	 *
	 * @since x.x.x
	 * @return self initialized object of class.
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
	 * @since x.x.x
	 * @return void
	 */
	public function register_ajax_events() {

		if ( current_user_can( 'manage_options' ) ) {

			$ajax_events = array(
				'dismiss_icp_nudge',
				'icp_modal_visit',
				'icp_modal_close',
				'icp_nudge_click',
			);
			$this->init_ajax_events( $ajax_events );
		}
	}

	/**
	 * Localize nonce for ajax call. The nudges are for administrators only, not for flow managers.
	 *
	 * @since x.x.x
	 * @param string $action Action name.
	 * @return void
	 */
	public function localize_ajax_action_nonce( $action ) {

		if ( ! current_user_can( 'manage_options' ) ) {
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
	 * AJAX handler – hide one nudge placement for the current user.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function dismiss_icp_nudge() {

		check_ajax_referer( 'cartflows_dismiss_icp_nudge', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => $this->get_error_msg( 'permission' ) ) );
		}

		$placement = isset( $_POST['placement'] ) ? sanitize_key( wp_unslash( $_POST['placement'] ) ) : '';

		if ( ! NudgeState::get_instance()->dismiss( get_current_user_id(), $placement ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown placement.', 'cartflows' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX handler – record the first click on a nudge button for the opt-in usage report.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function icp_nudge_click() {

		check_ajax_referer( 'cartflows_icp_nudge_click', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => $this->get_error_msg( 'permission' ) ) );
		}

		$placement = isset( $_POST['placement'] ) ? sanitize_key( wp_unslash( $_POST['placement'] ) ) : '';

		if ( ! NudgeState::get_instance()->record_click( $placement ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown placement.', 'cartflows' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX handler – count an eligible visit and say whether the announcement modal opens now.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function icp_modal_visit() {

		check_ajax_referer( 'cartflows_icp_modal_visit', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => $this->get_error_msg( 'permission' ) ) );
		}

		$state   = NudgeState::get_instance();
		$current = $state->get_state();

		// The modal follows the same eligibility as the nudges, so a stale tab cannot open it.
		if ( empty( $current ) || empty( $current['modal'] ) ) {
			wp_send_json_success( array( 'show' => false ) );
		}

		wp_send_json_success( array( 'show' => $state->register_modal_visit( get_current_user_id() ) ) );
	}

	/**
	 * AJAX handler – record that the user closed the announcement modal.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function icp_modal_close() {

		check_ajax_referer( 'cartflows_icp_modal_close', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => $this->get_error_msg( 'permission' ) ) );
		}

		NudgeState::get_instance()->close_modal( get_current_user_id() );

		wp_send_json_success();
	}
}
