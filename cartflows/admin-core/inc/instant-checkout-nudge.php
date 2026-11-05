<?php
/**
 * CartFlows Instant Checkout nudge state.
 *
 * @package CartFlows
 */

namespace CartflowsAdmin\AdminCore\Inc;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides who sees the Instant Checkout (Pro Buy Now popup) nudges, not Instant Layout.
 *
 * @since x.x.x
 */
class InstantCheckoutNudge {

	/**
	 * Segment: CartFlows Pro is not active.
	 *
	 * @var string
	 */
	const SEGMENT_FREE = 'free';

	/**
	 * Segment: Pro is installed but its licence is not active.
	 *
	 * @var string
	 */
	const SEGMENT_ACTIVATE = 'activate_license';

	/**
	 * Segment: Pro is licensed but too old to ship Instant Checkout.
	 *
	 * @var string
	 */
	const SEGMENT_UPDATE = 'update_pro';

	/**
	 * Segment: Pro is licensed and the feature is off everywhere.
	 *
	 * @var string
	 */
	const SEGMENT_TURN_ON = 'turn_on';

	/**
	 * Dismissible placements, each stored as its own user meta key.
	 *
	 * @var array<int, string>
	 */
	const PLACEMENTS = array( 'dashboard', 'store_checkout' );

	/**
	 * User meta prefix holding the dismissal timestamp of one placement.
	 *
	 * @var string
	 */
	const DISMISS_META_PREFIX = 'cartflows_icp_nudge_';

	/**
	 * User meta counting how often the user closed the announcement modal.
	 *
	 * @var string
	 */
	const MODAL_CLOSES_META = 'cartflows_icp_modal_closes';

	/**
	 * User meta holding the timestamp of the latest close of the announcement modal.
	 *
	 * @var string
	 */
	const MODAL_CLOSED_AT_META = 'cartflows_icp_modal_closed_at';

	/**
	 * User meta counting eligible visits that did not open the modal.
	 *
	 * @var string
	 */
	const MODAL_VISITS_META = 'cartflows_icp_modal_visits';

	/**
	 * Places a nudge button can be clicked, each recorded as its own analytics flag.
	 *
	 * @var array<int, string>
	 */
	const CLICK_PLACEMENTS = array( 'dashboard', 'store_checkout', 'modal' );

	/**
	 * Days after install before any nudge shows.
	 *
	 * @var int
	 */
	const MIN_DAYS_SINCE_INSTALL = 3;

	/**
	 * Days the announcement modal stays hidden after its first close.
	 *
	 * @var int
	 */
	const MODAL_COOLDOWN_DAYS = 14;

	/**
	 * Closes after which the announcement modal never returns. One close only starts its cooldown.
	 *
	 * @var int
	 */
	const MODAL_MAX_CLOSES = 2;

	/**
	 * Eligible visit on which the modal opens even when the dice said no.
	 *
	 * @var int
	 */
	const MODAL_FORCED_VISIT = 3;

	/**
	 * CartFlows Pro release that introduced Instant Checkout, shown in the "new" badge.
	 *
	 * @var string
	 */
	const FEATURE_VERSION = '3.3';

	/**
	 * Days the badge names the release number, counted from the first admin request after the update.
	 *
	 * @var int
	 */
	const BADGE_VERSION_DAYS = 30;

	/**
	 * Option holding the timestamp the badge started showing the release number.
	 *
	 * @var string
	 */
	const BADGE_SINCE_OPTION = 'cartflows_icp_badge_since';

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
	 * Constructor
	 *
	 * @since x.x.x
	 */
	public function __construct() {
		add_filter( 'cartflows_admin_localized_vars', array( $this, 'localize' ) );
		add_action( 'admin_init', array( $this, 'maybe_stamp_badge_since' ) );
	}

	/**
	 * Records when the badge started showing the release number, once per site.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function maybe_stamp_badge_since() {

		if ( false === get_option( self::BADGE_SINCE_OPTION, false ) ) {
			add_option( self::BADGE_SINCE_OPTION, time() );
		}
	}

	/**
	 * The release number for the badge while it is still new, an empty string afterwards.
	 *
	 * @since x.x.x
	 * @return string
	 */
	public function get_badge_version() {

		$since = $this->to_int( get_option( self::BADGE_SINCE_OPTION, 0 ) );

		if ( $since > 0 && ( time() - $since ) >= self::BADGE_VERSION_DAYS * DAY_IN_SECONDS ) {
			return '';
		}

		return self::FEATURE_VERSION;
	}

	/**
	 * Adds the nudge state to the admin app variables.
	 *
	 * @since x.x.x
	 * @param array<string, mixed> $vars Localized admin variables.
	 * @return array<string, mixed>
	 */
	public function localize( $vars ) {

		$state = $this->get_state();

		if ( ! empty( $state ) ) {
			$vars['icp_nudge'] = $state;
		}

		return $vars;
	}

	/**
	 * Builds the nudge state for the current user, empty when nothing may show.
	 *
	 * @since x.x.x
	 * @return array<string, mixed>
	 */
	public function get_state() {

		if ( ! $this->is_admin_request() ) {
			return array();
		}

		$segment = $this->get_segment();

		if ( '' === $segment || ! $this->store_is_ready() ) {
			return array();
		}

		$user_id = get_current_user_id();
		$show    = array();

		foreach ( self::PLACEMENTS as $placement ) {
			$show[ $placement ] = ! $this->is_dismissed( $user_id, $placement );
		}

		$toggle_url = $this->get_toggle_url();

		// The turn-on message has no action without a Store Checkout, so the modal stays away.
		$has_action = self::SEGMENT_TURN_ON !== $segment || '' !== $toggle_url;

		return array(
			'segment'       => $segment,
			'competitor'    => $this->detect_competitor(),
			'show'          => $show,
			'modal'         => $has_action && ! $this->is_modal_hidden( $user_id ),
			'toggle_url'    => $toggle_url,
			'badge_version' => $this->get_badge_version(),
		);
	}

	/**
	 * Casts a stored value to a non-negative integer, zero when it is not numeric.
	 *
	 * @since x.x.x
	 * @param mixed $value Stored value.
	 * @return int
	 */
	private function to_int( $value ) {
		return is_numeric( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Whether this request may carry nudge data at all: wp-admin, administrators only.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public function is_admin_request() {
		return is_admin() && current_user_can( 'manage_options' );
	}

	/**
	 * Works out which message fits the site, or an empty string when none applies.
	 *
	 * @since x.x.x
	 * @return string
	 */
	public function get_segment() {

		if ( ! _is_cartflows_pro() ) {
			return self::SEGMENT_FREE;
		}

		// The free-side helper returns a raw string that stays truthy after the licence is deactivated.
		if ( 'Activated' !== _is_cartflows_pro_license_activated() ) {
			return self::SEGMENT_ACTIVATE;
		}

		if ( ! class_exists( 'Cartflows_Pro_Instant_Checkout' ) ) {
			return self::SEGMENT_UPDATE;
		}

		if ( \Cartflows_Pro_Instant_Checkout::has_active_flows() ) {
			return '';
		}

		return self::SEGMENT_TURN_ON;
	}

	/**
	 * Whether the store is past setup and old enough to be nudged.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public function store_is_ready() {

		if ( ! \Cartflows_Loader::get_instance()->is_woo_active || '1' !== get_option( 'wcf_setup_complete', false ) ) {
			return false;
		}

		$installed = $this->to_int( \Cartflows_Helper::get_analytics_flag( 'usage_installed_time', 0 ) );

		// An unknown install time is treated as an old install.
		if ( $installed > 0 && ( time() - $installed ) < self::MIN_DAYS_SINCE_INSTALL * DAY_IN_SECONDS ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the user already dismissed one placement.
	 *
	 * @since x.x.x
	 * @param int    $user_id   User ID.
	 * @param string $placement Placement key.
	 * @return bool
	 */
	public function is_dismissed( $user_id, $placement ) {
		return $this->to_int( get_user_meta( $user_id, self::DISMISS_META_PREFIX . $placement, true ) ) > 0;
	}

	/**
	 * Records a dismissal for one placement. Each placement is dismissed on its own and for good.
	 *
	 * @since x.x.x
	 * @param int    $user_id   User ID.
	 * @param string $placement Placement key.
	 * @return bool False when the placement is unknown.
	 */
	public function dismiss( $user_id, $placement ) {

		if ( ! in_array( $placement, self::PLACEMENTS, true ) ) {
			return false;
		}

		if ( $this->is_dismissed( $user_id, $placement ) ) {
			return true;
		}

		update_user_meta( $user_id, self::DISMISS_META_PREFIX . $placement, time() );

		\Cartflows_Helper::set_analytics_flag( 'icp_nudge_' . $placement . '_dismissed', true );

		return true;
	}

	/**
	 * Whether the announcement modal is gone for good, because the user closed it twice.
	 *
	 * @since x.x.x
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_modal_hidden( $user_id ) {
		return $this->to_int( get_user_meta( $user_id, self::MODAL_CLOSES_META, true ) ) >= self::MODAL_MAX_CLOSES;
	}

	/**
	 * Whether the modal waits out the days after its latest close.
	 *
	 * @since x.x.x
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function is_modal_cooling_down( $user_id ) {

		$closed_at = $this->to_int( get_user_meta( $user_id, self::MODAL_CLOSED_AT_META, true ) );

		return $closed_at > 0 && ( time() - $closed_at ) < self::MODAL_COOLDOWN_DAYS * DAY_IN_SECONDS;
	}

	/**
	 * Records that the user closed the announcement modal and starts its cooldown.
	 *
	 * @since x.x.x
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function close_modal( $user_id ) {

		update_user_meta( $user_id, self::MODAL_CLOSES_META, $this->to_int( get_user_meta( $user_id, self::MODAL_CLOSES_META, true ) ) + 1 );
		update_user_meta( $user_id, self::MODAL_CLOSED_AT_META, time() );

		\Cartflows_Helper::set_analytics_flag( 'icp_modal_closed', true );
	}

	/**
	 * Counts an eligible visit and says whether the modal opens now.
	 *
	 * @since x.x.x
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function register_modal_visit( $user_id ) {

		if ( $this->is_modal_hidden( $user_id ) || $this->is_modal_cooling_down( $user_id ) ) {
			return false;
		}

		$visits = $this->to_int( get_user_meta( $user_id, self::MODAL_VISITS_META, true ) ) + 1;

		// One chance in three per visit, and a sure thing on the third so it cannot be missed forever.
		$open = $visits >= self::MODAL_FORCED_VISIT || 1 === wp_rand( 1, 3 );

		if ( $open ) {
			delete_user_meta( $user_id, self::MODAL_VISITS_META );
			\Cartflows_Helper::set_analytics_flag( 'icp_modal_shown', true );
			return true;
		}

		update_user_meta( $user_id, self::MODAL_VISITS_META, $visits );

		return false;
	}

	/**
	 * Records the first click on a nudge button as an analytics flag.
	 *
	 * @since x.x.x
	 * @param string $placement Placement key: dashboard, store_checkout or modal.
	 * @return bool False when the placement is unknown.
	 */
	public function record_click( $placement ) {

		if ( ! in_array( $placement, self::CLICK_PLACEMENTS, true ) ) {
			return false;
		}

		$flag = 'modal' === $placement ? 'icp_modal_cta_clicked' : 'icp_nudge_' . $placement . '_clicked';

		\Cartflows_Helper::set_analytics_flag( $flag, true );

		return true;
	}

	/**
	 * Analytics flags that become events in the opt-in usage report.
	 *
	 * @since x.x.x
	 * @return array<int, string>
	 */
	public static function get_tracked_flags() {

		$flags = array( 'icp_modal_shown', 'icp_modal_closed', 'icp_modal_cta_clicked' );

		foreach ( self::PLACEMENTS as $placement ) {
			$flags[] = 'icp_nudge_' . $placement . '_clicked';
			$flags[] = 'icp_nudge_' . $placement . '_dismissed';
		}

		return $flags;
	}

	/**
	 * Detects an active Buy Now or direct-checkout plugin from local constants and classes only.
	 *
	 * @since x.x.x
	 * @return array<string, string>|null Slug and name of the first match, null when none.
	 */
	public static function detect_competitor() {

		foreach ( self::get_competitors() as $slug => $competitor ) {

			$type = $competitor['type'];
			$key  = $competitor['key'];

			$active = ( 'constant' === $type && defined( $key ) )
				|| ( 'class' === $type && class_exists( $key, false ) )
				|| ( 'function' === $type && function_exists( $key ) );

			if ( $active ) {
				return array(
					'slug' => $slug,
					'name' => $competitor['name'],
				);
			}
		}

		return null;
	}

	/**
	 * Buy Now and direct-checkout plugins that overlap Instant Checkout.
	 *
	 * @since x.x.x
	 * @return array<string, array<string, string>>
	 */
	public static function get_competitors() {

		$competitors = array(
			'direct-checkout-quadlayers' => array(
				'name' => 'Direct Checkout for WooCommerce',
				'type' => 'constant',
				'key'  => 'QLWCDC_PLUGIN_VERSION',
			),
			'quick-buy-now-button'       => array(
				'name' => 'Quick Buy Now Button for WooCommerce',
				'type' => 'constant',
				'key'  => 'WOO_BUY_NOW_BUTTON_PLUGIN_VERSION',
			),
			'wpc-buy-now-button'         => array(
				'name' => 'WPC Buy Now Button',
				'type' => 'constant',
				'key'  => 'WPCBN_VERSION',
			),
			'direct-checkout-pi'         => array(
				'name' => 'Add to Cart Redirect / Direct Checkout',
				'type' => 'constant',
				'key'  => 'PI_DCW_VERSION',
			),
			'buy-now-woo'                => array(
				'name' => 'Buy Now Button for WooCommerce',
				'type' => 'constant',
				'key'  => 'BUY_NOW_WOO_VERSION',
			),
			'buy-one-click'              => array(
				'name' => 'Buy one click for WooCommerce',
				'type' => 'constant',
				'key'  => 'CODERUN_ONECLICKWOO_PLUGIN_VERSION',
			),
			'quick-buy-woocommerce'      => array(
				'name' => 'Quick Buy For WooCommerce',
				'type' => 'constant',
				'key'  => 'WCQB_VERSION',
			),
			'grandplugins-buy-now'       => array(
				'name' => 'Direct Checkout – Quick View – Buy Now',
				'type' => 'class',
				'key'  => 'GPLS_ARCW_Quick_View_And_Buy_Now_For_WooCommerce',
			),
			'barn2-fast-cart'            => array(
				'name' => 'WooCommerce Fast Cart',
				'type' => 'function',
				'key'  => 'Barn2\Plugin\WC_Fast_Cart\wfc',
			),
		);

		/**
		 * Filters the Buy Now plugins detected by the Instant Checkout nudges.
		 *
		 * @since x.x.x
		 * @param array<string, array<string, string>> $competitors Slug => name, type (constant|class|function) and key.
		 */
		$competitors = apply_filters( 'cartflows_buy_now_conflict_plugins', $competitors );

		// Drop entries a filter callback left incomplete, so detection never reads a missing key.
		return array_filter(
			(array) $competitors,
			static function ( $competitor ) {
				return is_array( $competitor ) && isset( $competitor['name'], $competitor['type'], $competitor['key'] );
			}
		);
	}

	/**
	 * Funnel Settings link that opens the Instant Checkout tab of the Store Checkout funnel.
	 *
	 * @since x.x.x
	 * @return string Empty when no Store Checkout exists yet.
	 */
	public function get_toggle_url() {

		$flow_id = $this->to_int( \Cartflows_Helper::get_global_setting( '_cartflows_store_checkout' ) );

		if ( ! $flow_id ) {
			return '';
		}

		return add_query_arg(
			array(
				'page'          => 'cartflows',
				'path'          => 'store-checkout',
				'action'        => 'wcf-edit-flow',
				'flow_id'       => $flow_id,
				'flow_settings' => 'true',
				'section'       => 'instant_checkout',
			),
			admin_url( 'admin.php' )
		);
	}
}
