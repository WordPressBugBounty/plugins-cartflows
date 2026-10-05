<?php
/**
 * Frequently Bought Together — in-admin activation nudges.
 *
 * Owns the products-list contextual notice, the self-expiring NEW badge on the
 * FBT product tab, and the Free-tier callout inside the FBT panel.
 *
 * @package cartflows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Cartflows_Fbt_Nudges.
 *
 * @since x.x.x
 */
class Cartflows_Fbt_Nudges {

	/**
	 * Notice id for the products-list nudge — also the user-meta key BSF_Admin_Notices writes on dismissal.
	 *
	 * @var string
	 */
	const NOTICE_ID = 'cartflows-fbt-products-notice';

	/**
	 * Option holding the timestamp the NEW badge started showing.
	 *
	 * @var string
	 */
	const BADGE_SINCE_OPTION = 'cartflows_fbt_badge_since';

	/**
	 * User meta flag set once the Free panel callout is dismissed.
	 *
	 * @var string
	 */
	const CALLOUT_META_KEY = 'cartflows_fbt_callout_dismissed';

	/**
	 * Nonce action for the panel-callout dismissal.
	 *
	 * @var string
	 */
	const CALLOUT_NONCE_ACTION = 'cartflows_fbt_callout';

	/**
	 * How long the NEW badge stays on the tab after the feature first appears.
	 *
	 * @var int
	 */
	const BADGE_DURATION = 60 * DAY_IN_SECONDS;

	/**
	 * Transient holding the resolved best-selling product ID.
	 *
	 * @var string
	 */
	const BEST_SELLER_TRANSIENT = 'cartflows_fbt_best_seller';

	/**
	 * How long the resolved best seller is reused before requerying.
	 *
	 * @var int
	 */
	const BEST_SELLER_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Query arg that tells the product editor to open the FBT tab on load.
	 *
	 * @var string
	 */
	const FOCUS_QUERY_ARG = 'wcf_fbt_focus';

	/**
	 * How-to page behind the notice's secondary CTA.
	 *
	 * @var string
	 */
	const DOCS_URL = 'https://cartflows.com/frequently-bought-together/?utm_source=wp-admin&utm_medium=fbt-nudge';

	/**
	 * Pricing page used for the Free tier's secondary upgrade link.
	 *
	 * @var string
	 */
	const PRICING_URL = 'https://cartflows.com/pricing/?utm_source=wp-admin&utm_medium=fbt-nudge';

	/**
	 * Capability required to see any FBT nudge — matches the rest of the FBT admin.
	 *
	 * @var string
	 */
	const CAPABILITY = 'cartflows_manage_flows_steps';

	/**
	 * Product types the FBT tab renders for — mirrors the show_if_* classes on the tab.
	 *
	 * @var array<int, string>
	 */
	const TAB_PRODUCT_TYPES = array( 'simple', 'variable' );

	/**
	 * Instance.
	 *
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Initiator.
	 *
	 * @since x.x.x
	 * @return self
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers the notice, the badge stamp and the callout dismissal handler.
	 *
	 * @since x.x.x
	 */
	public function __construct() {
		// admin_head runs before the admin_notices hook BSF_Admin_Notices renders on.
		add_action( 'admin_head', array( $this, 'maybe_register_products_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_notice_styles' ) );
		add_action( 'admin_init', array( $this, 'maybe_stamp_badge_since' ) );
		add_action( 'wp_ajax_cartflows_fbt_dismiss_callout', array( $this, 'ajax_dismiss_callout' ) );

		// The library resolves the notice capability from notices registered in the dismiss request itself.
		add_action( 'wp_ajax_astra-notice-dismiss', array( $this, 'register_notice_for_dismissal' ), 1 );

		// Fired by wc_update_total_sales_counts() after every total_sales change; save_post_product would flush per CSV row.
		add_action( 'woocommerce_recorded_sales', array( $this, 'flush_best_seller_cache' ) );
	}

	/**
	 * Drops the cached best seller so the next notice resolves it again.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function flush_best_seller_cache() {
		delete_transient( self::BEST_SELLER_TRANSIENT );
	}

	/**
	 * Registers the products-list notice when the screen and the store qualify.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function maybe_register_products_notice() {

		if ( ! $this->is_products_screen() || ! $this->store_can_use_fbt() || $this->is_notice_dismissed() ) {
			return;
		}

		if ( ! class_exists( 'Astra_Notices' ) ) {
			return;
		}

		Astra_Notices::add_notice(
			array(
				'id'                  => self::NOTICE_ID,
				'type'                => 'info',
				'class'               => 'cartflows-fbt-notice cartflows-admin-notice',
				'show_if'             => true,
				'is_dismissible'      => true,
				'capability'          => self::CAPABILITY,
				'priority'            => 5,
				'message'             => $this->get_notice_markup(),
				// Empty means BSF_Admin_Notices stores a permanent dismissal instead of a transient.
				'repeat-notice-after' => '',
			)
		);
	}

	/**
	 * Re-registers the notice inside the library's dismiss request, since admin_head never fires there.
	 * Without it BSF_Admin_Notices checks manage_options and shop managers cannot dismiss what they saw.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function register_notice_for_dismissal() {

		// Runs before the library verifies the nonce, so gate the option write on the capability here.
		if ( ! current_user_can( self::CAPABILITY ) || ! class_exists( 'Astra_Notices' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read only to skip other plugins' dismissals; the library verifies the nonce at priority 10.
		$requested = isset( $_POST['notice_id'] ) ? sanitize_key( wp_unslash( $_POST['notice_id'] ) ) : '';

		// Every other notice's dismissal would otherwise re-register ours pre-nonce.
		if ( self::NOTICE_ID !== $requested ) {
			return;
		}

		Astra_Notices::add_notice(
			array(
				'id'         => self::NOTICE_ID,
				'capability' => self::CAPABILITY,
				'priority'   => 5,
			)
		);
	}

	/**
	 * Loads the shared CartFlows notice stylesheet on the products list.
	 *
	 * Cartflows_Admin_Notices enqueues it only on the dashboard and plugins
	 * screens, so without this the notice renders unstyled here.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function maybe_enqueue_notice_styles() {

		if ( ! $this->is_products_screen() || ! $this->store_can_use_fbt() || $this->is_notice_dismissed() ) {
			return;
		}

		wp_enqueue_style( 'cartflows-custom-notices', CARTFLOWS_URL . 'admin/assets/css/notices.css', array(), CARTFLOWS_VER );

		// Depends on the shared sheet so these scoped overrides land after it.
		wp_enqueue_style(
			'wcf-fbt-nudges',
			CARTFLOWS_FBT_URL . 'assets/css/fbt-nudges.css',
			array( 'cartflows-custom-notices' ),
			CARTFLOWS_VER
		);
	}

	/**
	 * Whether this user already dismissed the products-list notice.
	 *
	 * The notices library only checks this when it renders, so without this gate
	 * the markup, the best-seller query and the stylesheets are all built for
	 * people who dismissed the notice long ago.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public function is_notice_dismissed() {

		return 'notice-dismissed' === get_user_meta( get_current_user_id(), self::NOTICE_ID, true );
	}

	/**
	 * Whether the current screen is the WooCommerce products list.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	private function is_products_screen() {

		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen instanceof WP_Screen && 'edit-product' === $screen->id;
	}

	/**
	 * Whether the store is in a state where FBT can actually be set up.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	private function store_can_use_fbt() {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return false;
		}

		// Same test the loader uses to set wcf()->is_woo_active, without the untyped property.
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		// A plain product count would nudge grouped-only stores the CTA cannot land on.
		return 0 !== $this->get_best_seller_id();
	}

	/**
	 * Resolves which copy variant the current site should see.
	 *
	 * @since x.x.x
	 * @return string One of pro_connected, pro_unconnected, free.
	 */
	public function get_tier_state() {

		if ( ! $this->has_active_pro_license() ) {
			return 'free';
		}

		if ( ! $this->has_connected_account() ) {
			return 'pro_unconnected';
		}

		return 'pro_connected';
	}

	/**
	 * Whether CartFlows Pro is active with a genuinely activated licence.
	 *
	 * Free's own `_is_cartflows_pro_license_activated()` returns the raw status
	 * string, which stays truthy after deactivation — use Pro's strict check.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	private function has_active_pro_license() {

		if ( ! function_exists( 'cartflows_pro_is_active_license' ) ) {
			return false;
		}

		return (bool) cartflows_pro_is_active_license();
	}

	/**
	 * Whether the site has a connected CartFlows account for Auto Suggest.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	private function has_connected_account() {

		if ( ! class_exists( 'Cartflows_Ai_Auth' ) ) {
			return false;
		}

		return (bool) Cartflows_Ai_Auth::get_instance()->get_auth_status();
	}

	/**
	 * Resolves the store's best-selling published product the FBT tab can render for.
	 *
	 * Falls back to the newest published product when nothing has sold yet, so
	 * the CTA always has somewhere real to land.
	 *
	 * @since x.x.x
	 * @return int Product ID, or 0 when the store has no published product.
	 */
	public function get_best_seller_id() {

		$cached = get_transient( self::BEST_SELLER_TRANSIENT );
		if ( is_numeric( $cached ) ) {
			return absint( $cached );
		}

		global $wpdb;

		// Untyped products carry no product_type term and WC treats them as simple, so only a foreign type excludes.
		$type_placeholders = implode( ', ', array_fill( 0, count( self::TAB_PRODUCT_TYPES ), '%s' ) );

		// A LEFT JOIN keeps never-sold products in play, so the fallback needs no second query.
		//phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Result is cached in the transient below; the interpolation is a list of %s placeholders.
		$product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID
				FROM $wpdb->posts p
				LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = 'product'
					AND p.post_status = 'publish'
					AND NOT EXISTS (
						SELECT 1
						FROM $wpdb->term_relationships tr
						INNER JOIN $wpdb->term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
						INNER JOIN $wpdb->terms t ON t.term_id = tt.term_id
						WHERE tr.object_id = p.ID
							AND tt.taxonomy = 'product_type'
							AND t.slug NOT IN ( {$type_placeholders} )
					)
				ORDER BY CAST( COALESCE( pm.meta_value, 0 ) AS UNSIGNED ) DESC, p.post_date DESC, p.ID DESC
				LIMIT 1",
				array_merge( array( 'total_sales' ), self::TAB_PRODUCT_TYPES )
			)
		);
		//phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$product_id = is_numeric( $product_id ) ? absint( $product_id ) : 0;

		// Only a real winner is worth caching; caching 0 would outlast the store's first product.
		if ( $product_id > 0 ) {
			set_transient( self::BEST_SELLER_TRANSIENT, $product_id, self::BEST_SELLER_TTL );
		}

		return $product_id;
	}

	/**
	 * URL the notice's primary CTA points at.
	 *
	 * Opens the best seller's edit screen with the FBT tab focused. The products
	 * list is not a usable fallback: WooCommerce only sorts that list by price,
	 * sku, cogs_value and global_unique_id, so an orderby of total_sales is
	 * silently dropped.
	 *
	 * @since x.x.x
	 * @return string
	 */
	private function get_best_seller_url() {

		$product_id = $this->get_best_seller_id();

		if ( 0 === $product_id ) {
			return admin_url( 'edit.php?post_type=product' );
		}

		return add_query_arg(
			array(
				'post'                => $product_id,
				'action'              => 'edit',
				self::FOCUS_QUERY_ARG => 1,
			),
			admin_url( 'post.php' )
		);
	}

	/**
	 * Builds the notice markup for the resolved tier state.
	 *
	 * @since x.x.x
	 * @return string
	 */
	private function get_notice_markup() {

		$state = $this->get_tier_state();

		// Phrased as an action, not a claim, since nothing here checks whether companions are already set.
		$heading = __( 'Add companion products to your best seller', 'cartflows' );

		if ( 'pro_connected' === $state ) {
			$body      = __( 'Frequently Bought Together shows companion products on your product pages, so shoppers can add the whole set in one click. Auto Suggest picks them from your order history, and you approve what goes live.', 'cartflows' );
			$primary   = __( 'Try it on your best seller', 'cartflows' );
			$secondary = '';
		} elseif ( 'pro_unconnected' === $state ) {
			$body      = __( 'Frequently Bought Together shows companion products on your product pages. Connect your CartFlows account and Auto Suggest will pick them from your order history.', 'cartflows' );
			$primary   = __( 'Connect and set up', 'cartflows' );
			$secondary = '';
		} else {
			$body    = __( 'Frequently Bought Together shows companion products on your product pages, so shoppers can add the whole set in one click. Pick the companions for any product yourself.', 'cartflows' );
			$primary = __( 'Try it on your best seller', 'cartflows' );
			// Left as a question so the link that follows it reads as the answer.
			$secondary = __( 'Want them chosen automatically from your order history?', 'cartflows' );
		}

		// The upgrade sentence rides in the same paragraph so the two lines share one rhythm.
		$tail = '';
		if ( '' !== $secondary ) {
			$tail = sprintf(
				' %1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>',
				esc_html( $secondary ),
				esc_url( self::PRICING_URL ),
				esc_html__( 'See CartFlows Pro', 'cartflows' )
			);
		}

		return sprintf(
			'<div class="notice-image">
				<img src="%1$s" class="custom-logo" alt="" itemprop="logo"></div>
				<div class="notice-content">
					<div class="notice-heading">%2$s</div>
					<div class="notice-description">%3$s%4$s</div>
					<div class="astra-review-notice-container">
						<a href="%5$s" class="astra-review-notice button-primary">%6$s</a>
						<a href="%7$s" class="astra-review-notice" target="_blank" rel="noopener noreferrer">
							<span class="dashicons dashicons-external"></span>
							%8$s
						</a>
					</div>
				</div>',
			esc_url( CARTFLOWS_URL . 'assets/images/cartflows-logo-small.jpg' ),
			esc_html( $heading ),
			esc_html( $body ),
			$tail,
			esc_url( $this->get_best_seller_url() ),
			esc_html( $primary ),
			esc_url( self::DOCS_URL ),
			esc_html__( 'See how it works', 'cartflows' )
		);
	}

	/**
	 * Stamps the badge start date the first time an admin loads the dashboard after the update.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function maybe_stamp_badge_since() {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		if ( ! empty( get_option( self::BADGE_SINCE_OPTION, '' ) ) ) {
			return;
		}

		update_option( self::BADGE_SINCE_OPTION, time() );
	}

	/**
	 * Whether the NEW badge is still within its 60-day window.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public function is_badge_active() {

		$stored = get_option( self::BADGE_SINCE_OPTION, 0 );
		$since  = is_numeric( $stored ) ? absint( $stored ) : 0;

		if ( 0 === $since ) {
			return false;
		}

		return ( time() - $since ) < self::BADGE_DURATION;
	}

	/**
	 * Whether the Free-tier panel callout should render for the current user.
	 *
	 * Pro already ships a guided tour on this screen, so the callout is Free-only.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public function should_show_callout() {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return false;
		}

		if ( 'free' !== $this->get_tier_state() ) {
			return false;
		}

		return empty( get_user_meta( get_current_user_id(), self::CALLOUT_META_KEY, true ) );
	}

	/**
	 * Renders the Free-tier callout above the FBT panel body.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function render_panel_callout() {

		if ( ! $this->should_show_callout() ) {
			return;
		}
		?>
		<div class="wcf-fbt-callout" data-nonce="<?php echo esc_attr( wp_create_nonce( self::CALLOUT_NONCE_ACTION ) ); ?>">
			<div class="wcf-fbt-callout-body">
				<span class="wcf-fbt-badge wcf-fbt-badge-brand"><?php esc_html_e( 'New', 'cartflows' ); ?></span>
				<div class="wcf-fbt-callout-text">
					<strong><?php esc_html_e( 'Add companion products to this product', 'cartflows' ); ?></strong>
					<p>
						<?php esc_html_e( 'Pick the products customers often buy with this one and they show right on its product page.', 'cartflows' ); ?>
						<?php
						printf(
							/* translators: %s: linked "CartFlows Pro" label. */
							esc_html__( 'Auto Suggest picks them for you in %s.', 'cartflows' ),
							sprintf(
								'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
								esc_url( self::PRICING_URL ),
								esc_html__( 'CartFlows Pro', 'cartflows' )
							)
						);
						?>
					</p>
				</div>
			</div>
			<button type="button" class="button-link wcf-fbt-callout-dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'cartflows' ); ?>">
				<?php esc_html_e( 'Dismiss', 'cartflows' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Persists the panel-callout dismissal for the current user.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function ajax_dismiss_callout() {

		check_ajax_referer( self::CALLOUT_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized user.', 'cartflows' ) ), 403 );
		}

		$user_id = get_current_user_id();
		if ( 0 === $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized user.', 'cartflows' ) ), 403 );
		}

		update_user_meta( $user_id, self::CALLOUT_META_KEY, 1 );

		wp_send_json_success();
	}
}

Cartflows_Fbt_Nudges::get_instance();
