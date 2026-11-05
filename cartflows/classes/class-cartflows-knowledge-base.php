<?php
/**
 * CartFlows Knowledge Base docs.
 *
 * @package CartFlows
 * @since   x.x.x
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cartflows_Knowledge_Base.
 *
 * @since x.x.x
 */
class Cartflows_Knowledge_Base {

	/**
	 * Action Scheduler hook that refreshes the docs payload.
	 */
	const DOCS_HOOK = 'cartflows_update_knowledge_base_data';

	/**
	 * Action Scheduler hook that purges the failed rows left by #2290.
	 */
	const CLEANUP_HOOK = 'cartflows_cleanup_failed_knowledge_base_actions';

	/**
	 * Failed rows deleted per cleanup run before the job re-queues itself.
	 */
	const CLEANUP_BATCH_SIZE = 500;

	/**
	 * Option flagging that the backlog purge has finished, so it is never re-queued.
	 */
	const CLEANUP_DONE_OPTION = 'cartflows-kb-failed-actions-cleaned';

	/**
	 * Cleanup runs allowed after a batch that proved no progress, before the chain gives up.
	 */
	const CLEANUP_MAX_RETRIES = 2;

	/**
	 * Option holding the last immediate first run granted, so a fatal cannot re-queue per request.
	 */
	const IMMEDIATE_START_OPTION = 'cartflows-kb-docs-immediate-start';

	/**
	 * Instance
	 *
	 * @access private
	 * @var Cartflows_Knowledge_Base|null Class object.
	 * @since x.x.x
	 */
	private static $instance;

	/**
	 * Initiator
	 *
	 * @since x.x.x
	 * @return Cartflows_Knowledge_Base initialized object of class.
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers the queue callbacks on every request, because Action Scheduler also dispatches
	 * from WP-Cron, where is_admin() is false and the admin classes never load.
	 *
	 * @since x.x.x
	 */
	public function __construct() {

		add_action( self::DOCS_HOOK, array( __CLASS__, 'run_docs_job' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup_failed_docs_actions' ) );
	}

	/**
	 * Queue callback for the docs refresh, discarding the result an action callback may not return.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public static function run_docs_job() {

		self::update_docs_data();
	}

	/**
	 * Fetch CartFlows's REST knowledge base data, reporting whether a payload was stored.
	 * Scheduling lives in Cartflows_Admin::run_scheduled_docs_job(), which stays admin-only.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public static function update_docs_data() {

		// Only the legacy admin renders the screen that reads this, and a site that left it keeps the old schedule and payload.
		if ( ! self::is_legacy_admin() ) {
			self::discard_docs_job( true );
			return false;
		}

		$response = wp_safe_remote_get(
			'https://cartflows.com/wp-json/powerful-docs/v1/get-docs',
			array(
				'timeout'             => 15, //phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- A weekly cron fetch of a ~1 MB payload, not a page request.
				'redirection'         => 0,
				'limit_response_size' => 5 * MB_IN_BYTES,
			)
		);

		// Only a 200 carries a payload, and the next retry is a week away, so keep the docs already stored.
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$docs_json = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! self::is_valid_docs_payload( $docs_json ) ) {
			return false;
		}

		// Never autoload — this payload runs to hundreds of KB and only the Knowledge Base screen reads it.
		update_option( 'cartflows_docs_data', $docs_json, false );

		return true;
	}

	/**
	 * Whether the legacy admin is the active UI, read from the option rather than CARTFLOWS_LEGACY_ADMIN.
	 * The constant is frozen before plugins_loaded, so a toggle handler would see its pre-toggle value.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public static function is_legacy_admin() {

		// Same allowlist as Cartflows_Loader::define_constants(), because older code stored a boolean here.
		return in_array( get_option( 'cartflows-legacy-admin', false ), array( true, 1, '1', 'enable', 'yes' ), true );
	}

	/**
	 * Cancel every queued docs refresh for this hook.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public static function unschedule_docs_job() {

		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::DOCS_HOOK );
	}

	/**
	 * Drop the leftover refresh and the payload it maintained, on an install whose UI reads neither.
	 * Matches what the three legacy-admin toggles already do, for sites that never used one.
	 *
	 * @since x.x.x
	 * @param bool $defer_to_shutdown Whether the caller is the running callback itself.
	 * @return void
	 */
	public static function discard_docs_job( $defer_to_shutdown = false ) {

		// Action Scheduler repeats a recurring action after its callback returns, so only shutdown is late enough to cancel the instance it creates.
		if ( $defer_to_shutdown ) {
			add_action( 'shutdown', array( __CLASS__, 'discard_docs_job' ), 100, 0 );
			return;
		}

		// Re-read, because a deferred run decided this earlier in the request and nothing since is stopped from switching the legacy admin back on.
		if ( self::is_legacy_admin() ) {
			return;
		}

		self::unschedule_docs_job();

		// Hundreds of KB that only the legacy Knowledge Base screen ever read.
		delete_option( 'cartflows_docs_data' );
	}

	/**
	 * Whether the stored option holds a payload the Knowledge Base screen can actually render.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public static function has_valid_docs_payload() {

		return self::is_valid_docs_payload( get_option( 'cartflows_docs_data', false ) );
	}

	/**
	 * Whether a decoded payload carries both keys the screen reads, in the shapes it reads them.
	 * A REST error body decodes to an object too, so the shape is checked rather than the keys' presence.
	 *
	 * @since x.x.x
	 * @param mixed $payload Decoded payload to test.
	 * @return bool
	 */
	public static function is_valid_docs_payload( $payload ) {

		if ( ! is_object( $payload ) || ! isset( $payload->docs, $payload->categories ) ) {
			return false;
		}

		if ( ! is_array( $payload->docs ) ) {
			return false;
		}

		// json_decode() returns an object for a JSON object and an array only for `[]`, an empty category list.
		return is_object( $payload->categories ) || array() === $payload->categories;
	}

	/**
	 * Claim the one immediate first run allowed per throttle window.
	 * Without the throttle a callback that fatals re-queues on every admin request, which is #2290 itself.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public static function claim_immediate_docs_start() {

		$now        = time();
		$stored     = get_option( self::IMMEDIATE_START_OPTION, 0 );
		$last_claim = is_numeric( $stored ) ? (int) $stored : 0;

		// A clock moved backwards leaves a stamp in the future, so only a stamp inside the window blocks.
		if ( $last_claim > 0 && $last_claim <= $now && ( $now - $last_claim ) < DAY_IN_SECONDS ) {
			return false;
		}

		update_option( self::IMMEDIATE_START_OPTION, $now, false );

		return true;
	}

	/**
	 * Delete one batch of failed docs actions, re-queueing itself while more remain.
	 * Batched because affected installs carry tens of thousands of rows.
	 *
	 * @since x.x.x
	 * @param int $retry Runs already spent on batches that proved no progress.
	 * @return void
	 */
	public static function cleanup_failed_docs_actions( $retry = 0 ) {

		if ( ! class_exists( 'ActionScheduler' ) || ! class_exists( 'ActionScheduler_Store' ) ) {
			return;
		}

		$retry = max( 0, (int) $retry );
		$store = ActionScheduler::store();

		$action_ids = $store->query_actions(
			array(
				'hook'     => self::DOCS_HOOK,
				'status'   => ActionScheduler_Store::STATUS_FAILED,
				'per_page' => self::CLEANUP_BATCH_SIZE,
				'orderby'  => 'none',
			)
		);

		// A query that failed returns the same empty array as a drained backlog, so a count has to confirm it.
		if ( ! is_array( $action_ids ) || empty( $action_ids ) ) {

			if ( 0 === self::count_failed_docs_actions() ) {
				self::mark_cleanup_complete();
				return;
			}

			self::retry_cleanup( $retry );
			return;
		}

		$remaining_before = self::count_failed_docs_actions();

		foreach ( $action_ids as $action_id ) {
			try {
				$store->delete_action( (int) $action_id );
			} catch ( Throwable $e ) {
				// delete_action() throws when the row is already gone, so swallow it and keep draining the batch.
				continue;
			}
		}

		$remaining_after = self::count_failed_docs_actions();

		if ( 0 === $remaining_after ) {
			self::mark_cleanup_complete();
			return;
		}

		// Continue only while the backlog actually shrinks: counting non-throwing calls would loop forever on a store that deletes nothing.
		if ( null !== $remaining_before && null !== $remaining_after && $remaining_after < $remaining_before ) {
			self::queue_cleanup_continuation( 0, MINUTE_IN_SECONDS );
			return;
		}

		// A lock timeout looks exactly like a store that deletes nothing, so spend a bounded number of slower attempts before giving up.
		self::retry_cleanup( $retry );
	}

	/**
	 * Queue one more attempt after a batch that proved no progress, while attempts remain.
	 * Bounded by a counter carried in the action args, so the chain still terminates.
	 *
	 * @since x.x.x
	 * @param int $retry Runs already spent on batches that proved no progress.
	 * @return void
	 */
	private static function retry_cleanup( $retry ) {

		if ( $retry >= self::CLEANUP_MAX_RETRIES ) {
			return;
		}

		self::queue_cleanup_continuation( $retry + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Queue the next cleanup run, carrying the counter that bounds the no-progress retries.
	 * A run that made progress passes 0, so only consecutive stalled runs exhaust the budget.
	 *
	 * @since x.x.x
	 * @param int $retry Counter to hand the next run.
	 * @param int $delay Seconds to wait before that run.
	 * @return void
	 */
	private static function queue_cleanup_continuation( $retry, $delay ) {

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		// Queued without the schedule_cleanup() duplicate guard: this very run is still in-progress, which that guard counts as already queued.
		as_schedule_single_action( time() + $delay, self::CLEANUP_HOOK, array( $retry ) );
	}

	/**
	 * Whether the one-time purge of the failed docs rows has already finished.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public static function is_cleanup_complete() {

		return (bool) get_option( self::CLEANUP_DONE_OPTION, false );
	}

	/**
	 * Record that no failed docs rows remain, so later upgrades stop queueing the purge.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public static function mark_cleanup_complete() {

		update_option( self::CLEANUP_DONE_OPTION, 'yes', false );
	}

	/**
	 * Queue a cleanup run, skipping it when one is already queued or running.
	 *
	 * @since x.x.x
	 * @param int $timestamp Unix timestamp to run the cleanup at.
	 * @return void
	 */
	public static function schedule_cleanup( $timestamp ) {

		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}

		if ( ! class_exists( 'ActionScheduler' ) || ! class_exists( 'ActionScheduler_Store' ) ) {
			return;
		}

		if ( false !== as_next_scheduled_action( self::CLEANUP_HOOK ) ) {
			return;
		}

		// Most installs never ran the broken job, so settle them on this count query instead of queueing a row that deletes nothing.
		if ( 0 === self::count_failed_docs_actions() ) {
			self::mark_cleanup_complete();
			return;
		}

		as_schedule_single_action( $timestamp, self::CLEANUP_HOOK, array( 0 ) );
	}

	/**
	 * Count the failed rows left behind for the docs hook, or null when the query itself failed.
	 * A failed count must not cast to a real zero: that would flag the backlog clean while rows remain.
	 *
	 * @since x.x.x
	 * @return int|null
	 */
	public static function count_failed_docs_actions() {

		if ( ! class_exists( 'ActionScheduler' ) || ! class_exists( 'ActionScheduler_Store' ) ) {
			return null;
		}

		// per_page is ignored by a count on the DB store, but it bounds the inline migration a count triggers on the hybrid store.
		$count = ActionScheduler::store()->query_actions(
			array(
				'hook'     => self::DOCS_HOOK,
				'status'   => ActionScheduler_Store::STATUS_FAILED,
				'per_page' => 1,
			),
			'count'
		);

		return is_numeric( $count ) ? (int) $count : null;
	}
}

Cartflows_Knowledge_Base::get_instance();
