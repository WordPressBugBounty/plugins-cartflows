<?php
/**
 * Divi page builder compatibility
 *
 * @package CartFlows
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class for divi page builder compatibility
 */
class Cartflows_Divi_Compatibility {

	/**
	 * Member Variable
	 *
	 * @var instance
	 */
	private static $instance;

	/**
	 *  Initiator
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 *  Constructor
	 */
	public function __construct() {

		add_filter( 'cartflows_container_atts', array( $this, 'add_id_for_cartflows_container' ) );
		add_filter( 'et_theme_builder_template_layouts', array( $this, 'disable_theme_builder_header_footer' ), 99 );
	}

	/**
	 * Disable Divi Theme Builder header/footer on steps that load a CartFlows template, which never calls get_header()/get_footer().
	 * Otherwise Divi drops the #et-boc content wrapper and the step's Divi styles stop applying.
	 *
	 * @since x.x.x
	 * @param array<string, mixed> $layouts Theme Builder layouts for the current request.
	 * @return array<string, mixed>
	 */
	public function disable_theme_builder_header_footer( $layouts ) {

		if ( ! is_array( $layouts ) || ! is_singular( CARTFLOWS_STEP_POST_TYPE ) || ! defined( 'ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE' ) || ! defined( 'ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE' ) ) {
			return $layouts;
		}

		// A TB body layout makes Divi replace the step template with its own, which calls get_header()/get_footer().
		$body_layout = defined( 'ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE' ) && isset( $layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ] ) ? $layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ] : null;

		if ( is_array( $body_layout ) && ! empty( $body_layout['override'] ) ) {
			return $layouts;
		}

		// Divi reads the layouts on `wp`, before template_include, so predict the template load_page_template() will load.
		$file = Cartflows_Step_Post_Type::get_instance()->get_page_template_file( Cartflows_Helper::get_current_page_template( get_queried_object_id() ) );

		if ( ! file_exists( $file ) || ! apply_filters( 'cartflows_allow_to_change_page_template', true ) || $this->is_theme_file( $file ) ) {
			return $layouts;
		}

		foreach ( array( ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE, ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ) as $layout_type ) {
			$layout = isset( $layouts[ $layout_type ] ) ? $layouts[ $layout_type ] : null;

			if ( is_array( $layout ) ) {
				$layout['id']            = 0;
				$layout['enabled']       = false;
				$layout['override']      = false;
				$layouts[ $layout_type ] = $layout;
			}
		}

		return $layouts;
	}

	/**
	 * Whether a template file belongs to the active (parent or child) theme; theme templates call get_header()/get_footer().
	 *
	 * @since x.x.x
	 * @param string $file Template file path.
	 * @return bool
	 */
	private function is_theme_file( $file ) {

		$file = wp_normalize_path( $file );

		foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $theme_dir ) {
			if ( 0 === strpos( $file, trailingslashit( wp_normalize_path( $theme_dir ) ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add id attribute to cartflows container which is needed to apply style to divi elements.
	 *
	 * @param array $atts container HTML attributes.
	 * @return array
	 */
	public function add_id_for_cartflows_container( $atts ) {

		$atts['id'] = 'page-container';

		return $atts;
	}
}

/**
 *  Kicking this off by calling 'get_instance()' method
 */
Cartflows_Divi_Compatibility::get_instance();
