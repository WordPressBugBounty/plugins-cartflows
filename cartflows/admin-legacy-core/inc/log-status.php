<?php
/**
 * CartFlows Log status.
 *
 * @package CartFlows
 */

namespace CartflowsAdmin\AdminLegacyCore\Inc;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LogStatus.
 *
 * Reads the log directory for the debug UI. The download/delete handlers live in
 * ajax/debugger.php, which resolves the filename through get_log_files() and adds a
 * realpath containment check; the unreferenced copies that used to sit here took
 * $_REQUEST['handle'] straight onto the log path and were removed.
 */
class LogStatus {

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
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Get all log files in the log directory.
	 *
	 * @return array
	 */
	public function get_log_files() {
		// Condition for multisite instances:
		// Return empty array if log directory doesn't exist yet or is not readable.
		if ( ! is_dir( CARTFLOWS_LOG_DIR ) || ! is_readable( CARTFLOWS_LOG_DIR ) ) {
			return array();
		}

		$files  = scandir( CARTFLOWS_LOG_DIR );
		$result = array();

		if ( ! empty( $files ) ) {
			foreach ( $files as $key => $file ) {
				if ( ! is_dir( $file ) && strstr( $file, '.log' ) ) {
					$result[ pathinfo( $file, PATHINFO_FILENAME ) ] = $file;
				}
			}
		}

		return $result;
	}
}

LogStatus::get_instance();
