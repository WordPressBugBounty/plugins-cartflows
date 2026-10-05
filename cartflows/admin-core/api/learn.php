<?php
/**
 * CartFlows Learn Data Query.
 *
 * @package CartFlows
 */

namespace CartflowsAdmin\AdminCore\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CartflowsAdmin\AdminCore\Api\ApiBase;

/**
 * Class Learn.
 */
class Learn extends ApiBase {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '/admin/learn/';

	/**
	 * Instance
	 *
	 * @access private
	 * @var object Class object.
	 * @since 2.2.2
	 */
	private static $instance;

	/**
	 * Initiator
	 *
	 * @since 2.2.2
	 * @return object initialized object of class.
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Init Hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {

		$namespace = $this->get_api_namespace();

		register_rest_route(
			$namespace,
			$this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_learn_sections' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(),
				),
			)
		);
	}


	/**
	 * Get learn sections.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response
	 */
	public function get_learn_sections( $request ) {

		// Resolved completion state — manual checks plus auto-derived state.
		$learn_progress = \Cartflows_Learn_Progress::get_instance();

		$sections = array(
			array(
				'id'          => 'funnel-basics',
				'title'       => __( 'Funnel Basics', 'cartflows' ),
				'description' => __( 'Build a solid foundation for your first funnel.', 'cartflows' ),
				'modules'     => array(
					array(
						'id'            => 'install-woocommerce',
						'title'         => __( 'Install WooCommerce', 'cartflows' ),
						'description'   => __( 'Install WooCommerce to enable products, checkout, and payments.', 'cartflows' ),
						'cta'           => __( 'WooCommerce', 'cartflows' ),
						'action'        => 'Installs WooCommerce Instantly',
						'action_type'   => 'install_plugin',
						'action_data'   => array(
							'plugin_slug' => 'woocommerce',
							'plugin_init' => 'woocommerce/woocommerce.php',
						),
						'learn_how'     => false,
						'is_pro'        => false,
						'completed'     => $learn_progress->is_completed( 'install-woocommerce' ),
						'plugin_status' => \Cartflows_Helper::get_plugin_status( 'woocommerce/woocommerce.php', true ),
					),
					array(
						'id'          => 'create-your-first-funnel',
						'title'       => __( 'Create Your First Funnel', 'cartflows' ),
						'description' => __( 'Start by creating a funnel and selecting a ready-made template.', 'cartflows' ),
						'cta'         => __( 'Create Funnel', 'cartflows' ),
						'action'      => 'Redirects to Funnel Templates Library',
						'action_type' => 'redirect',
						'action_data' => array(
							'url' => admin_url( 'admin.php?page=cartflows&path=flows' ),
						),
						'learn_how'   => esc_url( \Cartflows_Helper::get_kb_doc_link( 'https://cartflows.com/docs/how-to-create-your-first-cartflows-funnel/', array( 'utm_campaign' => 'learn_how' ) ) ),
						'is_pro'      => false,
						'completed'   => $learn_progress->is_completed( 'create-your-first-funnel' ),
					),
				),
			),
			array(
				'id'          => 'design-customize-pages',
				'title'       => __( 'Design & Customize Pages', 'cartflows' ),
				'description' => __( 'Make your funnel pages match your brand and goals.', 'cartflows' ),
				'modules'     => array(
					array(
						'id'          => 'edit-design-funnel-pages-steps',
						'title'       => __( 'Edit & Design Funnel Pages/Steps', 'cartflows' ),
						'description' => __( 'Customize your landing, checkout, and thank you pages using your preferred page builder.', 'cartflows' ),
						'cta'         => __( 'Edit Steps', 'cartflows' ),
						'action'      => 'Redirects to Funnel Editor',
						'action_type' => 'redirect',
						'action_data' => array(
							'url' => admin_url( 'admin.php?page=cartflows&path=flows' ),
						),
						'learn_how'   => esc_url( \Cartflows_Helper::get_kb_doc_link( 'https://cartflows.com/docs/editing-and-customising-funnel-steps/', array( 'utm_campaign' => 'learn_how' ) ) ),
						'is_pro'      => false,
						'completed'   => $learn_progress->is_completed( 'edit-design-funnel-pages-steps' ),
					),
				),
			),
			array(
				'id'          => 'setup-products',
				'title'       => __( 'Setup Products', 'cartflows' ),
				'description' => __( 'Add and manage the products you\'ll sell through your funnels.', 'cartflows' ),
				'modules'     => array(
					array(
						'id'          => 'add-products',
						'title'       => __( 'Add Products', 'cartflows' ),
						'description' => __( 'Create or import products to use in your CartFlows funnel.', 'cartflows' ),
						'cta'         => __( 'Add Products', 'cartflows' ),
						'action'      => 'Redirects to WooCommerce > Products',
						'action_type' => 'redirect',
						'action_data' => array(
							'url' => admin_url( 'edit.php?post_type=product' ),
						),
						'learn_how'   => esc_url( \Cartflows_Helper::get_kb_doc_link( 'https://cartflows.com/docs/how-to-add-products-in-woocommerce/', array( 'utm_campaign' => 'learn_how' ) ) ),
						'is_pro'      => false,
						'completed'   => $learn_progress->is_completed( 'add-products' ),
					),
					array(
						'id'          => 'assign-products-to-checkout',
						'title'       => __( 'Assign Products to Checkout', 'cartflows' ),
						'description' => __( 'Attach products to your checkout step and control pricing & quantity.', 'cartflows' ),
						'cta'         => __( 'Select Products', 'cartflows' ),
						'action'      => 'Redirects to Funnel > Checkout Step > Products',
						'action_type' => 'redirect',
						'action_data' => array(
							'url' => admin_url( 'admin.php?page=cartflows&path=flows' ),
						),
						'learn_how'   => esc_url( \Cartflows_Helper::get_kb_doc_link( 'https://cartflows.com/docs/how-to-add-assign-products-to-a-checkout-step-in-cartflows/', array( 'utm_campaign' => 'learn_how' ) ) ),
						'is_pro'      => false,
						'completed'   => $learn_progress->is_completed( 'assign-products-to-checkout' ),
					),
				),
			),
			array(
				'id'          => 'setup-payments',
				'title'       => __( 'Setup Payments', 'cartflows' ),
				'description' => __( 'Accept payments securely with your preferred gateways.', 'cartflows' ),
				'modules'     => array(
					array(
						'id'          => 'connect-payment-gateway',
						'title'       => __( 'Connect Payment Gateway', 'cartflows' ),
						'description' => __( 'Set up Stripe, PayPal, or other WooCommerce-supported payment methods.', 'cartflows' ),
						'cta'         => __( 'Setup Payments', 'cartflows' ),
						'action'      => 'Redirects to WooCommerce > Payments',
						'action_type' => 'redirect',
						'action_data' => array(
							'url' => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
						),
						'learn_how'   => false,
						'is_pro'      => false,
						'completed'   => $learn_progress->is_completed( 'connect-payment-gateway' ),
					),
				),
			),
			array(
				'id'          => 'setup-cart-abandonment-recovery',
				'title'       => __( 'Setup Cart Abandonment Recovery', 'cartflows' ),
				'description' => __( 'Recover lost revenue from incomplete checkouts.', 'cartflows' ),
				'modules'     => array(
					array(
						'id'            => 'enable-cart-abandonment-tracking',
						'title'         => __( 'Enable Cart Abandonment Tracking', 'cartflows' ),
						'description'   => __( 'Start tracking abandoned carts automatically.', 'cartflows' ),
						'cta'           => __( 'Cart Abandonment Recovery', 'cartflows' ),
						'action'        => 'Installs CAR Free Instantly',
						'action_type'   => 'install_plugin',
						'action_data'   => array(
							'plugin_slug' => 'woo-cart-abandonment-recovery',
							'plugin_init' => 'woo-cart-abandonment-recovery/woo-cart-abandonment-recovery.php',
						),
						'learn_how'     => false,
						'is_pro'        => false,
						'completed'     => $learn_progress->is_completed( 'enable-cart-abandonment-tracking' ),
						'plugin_status' => \Cartflows_Helper::get_plugin_status( 'woo-cart-abandonment-recovery/woo-cart-abandonment-recovery.php', true ),
					),
					array(
						'id'          => 'setup-recovery-emails',
						'title'       => __( 'Setup Recovery Emails', 'cartflows' ),
						'description' => __( 'Edit and setup recovery emails to start recovering lost sales automatically.', 'cartflows' ),
						'cta'         => __( 'Setup Emails', 'cartflows' ),
						'action'      => 'Redirects to CAR > Follow Ups',
						'action_type' => 'redirect',
						'action_data' => array(
							'url' => admin_url( 'admin.php?page=woo-cart-abandonment-recovery&path=follow-up-templates' ),
						),
						'learn_how'   => false,
						'is_pro'      => false,
						'completed'   => $learn_progress->is_completed( 'setup-recovery-emails' ),
					),
				),
			),
			array(
				'id'          => 'modernise-your-cart',
				'title'       => __( 'Modernise Your Cart', 'cartflows' ),
				'description' => __( 'Deliver a faster, cleaner, and more conversion-focused cart experience.', 'cartflows' ),
				'modules'     => array(
					array(
						'id'            => 'enable-modern-cart',
						'title'         => __( 'Enable Modern Cart', 'cartflows' ),
						'description'   => __( 'Switch to the modern cart layout for better UX and performance.', 'cartflows' ),
						'cta'           => __( 'Modern Cart', 'cartflows' ),
						'action'        => 'Installs MCW Free Instantly',
						'action_type'   => 'install_plugin',
						'action_data'   => array(
							'plugin_slug' => 'modern-cart',
							'plugin_init' => 'modern-cart/modern-cart.php',
						),
						'learn_how'     => false,
						'is_pro'        => false,
						'completed'     => $learn_progress->is_completed( 'enable-modern-cart' ),
						'plugin_status' => \Cartflows_Helper::get_plugin_status( 'modern-cart/modern-cart.php', true ),
					),
					array(
						'id'          => 'setup-your-cart',
						'title'       => __( 'Setup Your Cart', 'cartflows' ),
						'description' => __( 'Set up and customize your cart settings to match your brand style.', 'cartflows' ),
						'cta'         => __( 'Setup Cart', 'cartflows' ),
						'action'      => 'Redirects to MCW > Settings',
						'action_type' => 'redirect',
						'action_data' => array(
							'url' => admin_url( 'admin.php?page=moderncart_settings' ),
						),
						'learn_how'   => false,
						'is_pro'      => false,
						'completed'   => $learn_progress->is_completed( 'setup-your-cart' ),
					),
				),
			),
			array(
				'id'          => 'setup-offers',
				'title'       => __( 'Setup Offers', 'cartflows' ),
				'description' => __( 'Increase order value with smart one-click offers.', 'cartflows' ),
				'modules'     => array(
					array(
						'id'          => 'add-order-bump',
						'title'       => __( 'Add Order Bump', 'cartflows' ),
						'description' => __( 'Offer complementary products directly on the checkout page.', 'cartflows' ),
						'cta'         => __( 'Add Order Bump', 'cartflows' ),
						'action'      => 'Redirects to Funnel > Checkout Step > Order Bump',
						'action_type' => 'redirect',
						'action_data' => array(
							'url' => admin_url( 'admin.php?page=cartflows&path=flows' ),
						),
						'learn_how'   => esc_url( \Cartflows_Helper::get_kb_doc_link( 'https://cartflows.com/docs/how-to-add-order-bumps-to-woocommerce-sales-funnel/', array( 'utm_campaign' => 'learn_how' ) ) ),
						'is_pro'      => ! _is_cartflows_pro(),
						'completed'   => $learn_progress->is_completed( 'add-order-bump' ),
					),
					array(
						'id'          => 'setup-upsell-downsell-offers',
						'title'       => __( 'Setup Upsell / Downsell Offers', 'cartflows' ),
						'description' => __( 'Create one-click post-checkout offers to boost revenue.', 'cartflows' ),
						'cta'         => __( 'Add Offer Step', 'cartflows' ),
						'action'      => 'Redirects to Funnel > Checkout Step > Order Bump',
						'action_type' => 'redirect',
						'action_data' => array(
							'url' => admin_url( 'admin.php?page=cartflows&path=flows' ),
						),
						'learn_how'   => esc_url( \Cartflows_Helper::get_kb_doc_link( 'https://cartflows.com/docs/how-to-create-one-click-upsell-and-downsell-offers-in-cartflows/', array( 'utm_campaign' => 'learn_how' ) ) ),
						'is_pro'      => ! _is_cartflows_pro(),
						'completed'   => $learn_progress->is_completed( 'setup-upsell-downsell-offers' ),
					),
				),
			),
		);

		$response = new \WP_REST_Response( $sections );
		$response->set_status( 200 );

		return $response;
	}

	/**
	 * Check whether a given request has permission to read notes.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return \WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {

		if ( ! current_user_can( 'cartflows_manage_settings' ) ) {
			return new \WP_Error( 'cartflows_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'cartflows' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}
}
