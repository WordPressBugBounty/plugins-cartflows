<?php
/**
 * AI Initialization.
 *
 * @package cartflows
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * AI Initialization.
 *
 * @since x.x.x
 */
class Cartflows_Ai_Init {

	/**
	 * Member Variable
	 *
	 * @var object instance
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
	 * Constructor
	 *
	 * @since x.x.x
	 */
	public function __construct() {
		if ( apply_filters( 'cartflows_ai_auth_enabled', true ) ) {
			include_once CARTFLOWS_DIR . 'modules/content-generation/class-cartflows-ai-auth.php';
			include_once CARTFLOWS_DIR . 'modules/content-generation/class-cartflows-ai-api.php';
		}
	}
}

/**
 *  Kicking this off by calling 'get_instance()' method
 */
Cartflows_Ai_Init::get_instance();
