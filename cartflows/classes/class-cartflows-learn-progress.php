<?php
/**
 * CartFlows Learn progress — resolved completion state for the Learn checklist.
 *
 * Single source of truth for module completion (manually checked ∪ auto-derived),
 * shared by the Learn REST route and the BSF Analytics payload.
 *
 * @package CartFlows
 * @since x.x.x
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cartflows_Learn_Progress.
 *
 * @since x.x.x
 */
class Cartflows_Learn_Progress {

	/**
	 * Completed module state.
	 */
	const STATE_COMPLETED = 'completed';

	/**
	 * Pending module state.
	 */
	const STATE_PENDING = 'pending';

	/**
	 * Instance
	 *
	 * @access private
	 * @var Cartflows_Learn_Progress|null Class object.
	 * @since x.x.x
	 */
	private static $instance;

	/**
	 * Resolved module states, memoized per request.
	 *
	 * @var array<string, string>|null
	 * @since x.x.x
	 */
	private $states = null;

	/**
	 * Initiator
	 *
	 * @since x.x.x
	 * @return Cartflows_Learn_Progress initialized object of class.
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Module ID => the detector that resolves its auto-completion, in checklist order.
	 *
	 * Single declaration of the module set — get_module_ids() and the state map both read it.
	 *
	 * @since x.x.x
	 * @return array<string, string>
	 */
	private static function get_detector_map() {
		return array(
			'install-woocommerce'              => 'is_woocommerce_active',
			'create-your-first-funnel'         => 'has_published_flow',
			'edit-design-funnel-pages-steps'   => 'has_step_been_edited',
			'add-products'                     => 'has_published_product',
			'assign-products-to-checkout'      => 'is_checkout_product_assigned',
			'connect-payment-gateway'          => 'is_payment_gateway_available',
			'enable-cart-abandonment-tracking' => 'is_cart_abandonment_active',
			'setup-recovery-emails'            => 'has_recovery_emails',
			'enable-modern-cart'               => 'is_modern_cart_active',
			'setup-your-cart'                  => 'is_modern_cart_configured',
			'add-order-bump'                   => 'has_published_order_bump',
			'setup-upsell-downsell-offers'     => 'has_published_offer_step',
		);
	}

	/**
	 * Learn module identifiers, in checklist order.
	 *
	 * Static and query-free — the AJAX whitelist calls it on every recorded event.
	 *
	 * @since x.x.x
	 * @return array<int, string>
	 */
	public static function get_module_ids() {
		return array_keys( self::get_detector_map() );
	}

	/**
	 * Resolve every module to `completed` or `pending`.
	 *
	 * A module is completed when its detector says so or it is in the stored manual list.
	 *
	 * @since x.x.x
	 * @return array<string, string> Module ID => state.
	 */
	public function get_module_states() {

		if ( null !== $this->states ) {
			return $this->states;
		}

		$checked = get_option( 'wcf_learn_data', array() );
		$checked = is_array( $checked ) ? $checked : array();

		$states = array();

		foreach ( self::get_module_ids() as $module_id ) {
			// Manual check first so an already-ticked module skips its detector queries.
			$is_completed         = in_array( $module_id, $checked, true ) || $this->is_auto_completed( $module_id );
			$states[ $module_id ] = $is_completed ? self::STATE_COMPLETED : self::STATE_PENDING;
		}

		$this->states = $states;

		return $states;
	}

	/**
	 * Whether the store itself satisfies a module right now, ignoring the stored list.
	 *
	 * @since x.x.x
	 * @param string $module_id Module identifier.
	 * @return bool
	 */
	public function is_auto_completed( $module_id ) {
		$detectors = self::get_detector_map();

		if ( ! isset( $detectors[ $module_id ] ) ) {
			return false;
		}

		return (bool) $this->{$detectors[ $module_id ]}();
	}

	/**
	 * Reduce a list of completed IDs to the ones that must be stored as manual ticks.
	 *
	 * The UI posts every completed module, so auto-completed IDs are dropped here or they
	 * stay completed after the store stops satisfying them (a deactivated plugin, say).
	 *
	 * @since x.x.x
	 * @param array<int, string> $module_ids Completed module IDs as posted by the UI.
	 * @return array<int, string>
	 */
	public function filter_manual_ids( $module_ids ) {
		$known = self::get_module_ids();

		$manual = array_filter(
			$module_ids,
			function ( $module_id ) use ( $known ) {
				return in_array( $module_id, $known, true ) && ! $this->is_auto_completed( $module_id );
			}
		);

		return array_values( $manual );
	}

	/**
	 * Whether a single module is completed.
	 *
	 * @since x.x.x
	 * @param string $module_id Module identifier.
	 * @return bool
	 */
	public function is_completed( $module_id ) {
		$states = $this->get_module_states();
		return isset( $states[ $module_id ] ) && self::STATE_COMPLETED === $states[ $module_id ];
	}

	/**
	 * Count of completed modules.
	 *
	 * @since x.x.x
	 * @return int
	 */
	public function get_completed_count() {
		return count( array_keys( $this->get_module_states(), self::STATE_COMPLETED, true ) );
	}

	/**
	 * Total number of Learn modules.
	 *
	 * @since x.x.x
	 * @return int
	 */
	public function get_total_count() {
		return count( self::get_module_ids() );
	}

	/**
	 * Roll the per-module states up to a single progress value.
	 *
	 * @since x.x.x
	 * @return string One of not_started, in_progress, completed.
	 */
	public function get_progress_value() {

		$completed = $this->get_completed_count();

		if ( 0 === $completed ) {
			return 'not_started';
		}

		return $completed >= $this->get_total_count() ? 'completed' : 'in_progress';
	}

	/**
	 * Discard memoized states so the next read recomputes.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function reset() {
		$this->states = null;
	}

	/**
	 * Whether a plugin is installed and active.
	 *
	 * @since x.x.x
	 * @param string $plugin_init Plugin init file, e.g. woocommerce/woocommerce.php.
	 * @return bool
	 */
	private function is_plugin_active( $plugin_init ) {
		return 'active' === Cartflows_Helper::get_plugin_status( $plugin_init, true );
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	protected function is_woocommerce_active() {
		return $this->is_plugin_active( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Whether Cart Abandonment Recovery is active.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	protected function is_cart_abandonment_active() {
		return $this->is_plugin_active( 'woo-cart-abandonment-recovery/woo-cart-abandonment-recovery.php' );
	}

	/**
	 * Whether Modern Cart is active.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	protected function is_modern_cart_active() {
		return $this->is_plugin_active( 'modern-cart/modern-cart.php' );
	}

	/**
	 * Whether at least one funnel is published.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	protected function has_published_flow() {
		return intval( wp_count_posts( CARTFLOWS_FLOW_POST_TYPE )->publish ) > 0;
	}

	/**
	 * Whether at least one product is published.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	protected function has_published_product() {
		// wp_count_posts() returns a bare stdClass when the type is unregistered, so guard the read.
		if ( ! post_type_exists( 'product' ) ) {
			return false;
		}

		return intval( wp_count_posts( 'product' )->publish ) > 0;
	}

	/**
	 * Whether any recent published checkout step has a product assigned.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	protected function is_checkout_product_assigned() {

		// Only the 10 most recently modified checkout steps are inspected, for performance.
		$steps = get_posts(
			array(
				'post_type'      => CARTFLOWS_STEP_POST_TYPE,
				'post_status'    => array( 'publish' ),
				'posts_per_page' => 10,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'meta_query'     => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'wcf-step-type',
						'value' => 'checkout',
					),
					array(
						'key'     => 'wcf-checkout-products',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( empty( $steps ) ) {
			return false;
		}

		foreach ( $steps as $step_id ) {
			$products = get_post_meta( intval( $step_id ), 'wcf-checkout-products', true );

			if ( ! is_array( $products ) || empty( $products ) || empty( $products[0]['product'] ) ) {
				continue;
			}

			$product_id = $products[0]['product'];

			if ( is_numeric( $product_id ) && (int) $product_id > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether at least one WooCommerce payment gateway is enabled and ready.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	protected function is_payment_gateway_available() {

		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		$payment_gateways = WC()->payment_gateways();

		if ( ! is_object( $payment_gateways ) || ! method_exists( $payment_gateways, 'get_available_payment_gateways' ) ) {
			return false;
		}

		$available_gateways = $payment_gateways->get_available_payment_gateways();

		return ! empty( $available_gateways ) && is_array( $available_gateways );
	}

	/**
	 * Whether at least one activated Cart Abandonment follow-up email exists.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	protected function has_recovery_emails() {

		if ( ! $this->is_cart_abandonment_active() ) {
			return false;
		}

		global $wpdb;

		$template_table = $wpdb->prefix . 'cartflows_ca_email_templates';

		$table_exists = $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $template_table )
		);

		if ( ! $table_exists ) {
			return false;
		}

		$active_count = (int) $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}cartflows_ca_email_templates WHERE is_activated = %d", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				1
			)
		);

		return $active_count > 0;
	}

	/**
	 * Whether Modern Cart is active and its settings have been saved at least once.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	protected function is_modern_cart_configured() {

		if ( ! $this->is_modern_cart_active() ) {
			return false;
		}

		$setting_keys = array( 'moderncart_setting', 'moderncart_cart', 'moderncart_floating', 'moderncart_appearance' );

		foreach ( $setting_keys as $key ) {
			if ( false !== get_option( $key, false ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether any published checkout step has an order bump configured.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	protected function has_published_order_bump() {

		$steps = get_posts(
			array(
				'post_type'      => CARTFLOWS_STEP_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'wcf-step-type',
						'value' => 'checkout',
					),
					array(
						'key'     => 'wcf-order-bumps',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return ! empty( $steps );
	}

	/**
	 * Whether any published upsell or downsell step exists.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	protected function has_published_offer_step() {

		$offer_steps = get_posts(
			array(
				'post_type'      => CARTFLOWS_STEP_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'wcf-step-type',
						'value'   => array( 'upsell', 'downsell' ),
						'compare' => 'IN',
					),
				),
			)
		);

		return ! empty( $offer_steps );
	}

	/**
	 * Whether any published CartFlows step exists.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	protected function has_step_been_edited() {

		$steps = get_posts(
			array(
				'post_type'      => CARTFLOWS_STEP_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return ! empty( $steps );
	}
}
