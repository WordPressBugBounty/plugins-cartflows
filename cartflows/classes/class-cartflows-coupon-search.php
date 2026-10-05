<?php
/**
 * Coupon code search for the admin pickers.
 *
 * See `architecture.md` in this folder for why both admin trees share it.
 *
 * @package CartFlows
 * @since x.x.x
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cartflows_Coupon_Search.
 *
 * Stateless helper — every method is static and there is no instance.
 *
 * @since x.x.x
 */
class Cartflows_Coupon_Search {

	/**
	 * Object cache group for coupon search results.
	 *
	 * @since x.x.x
	 * @var string
	 */
	const CACHE_GROUP = 'wcf_funnel_Cart';

	/**
	 * Search published coupon codes, exact match then prefix then the rest.
	 *
	 * @since x.x.x
	 *
	 * @param string $term  Raw search term.
	 * @param int    $limit Maximum rows to read from the database.
	 * @return array<int, int> Coupon IDs, best match first.
	 */
	public static function search_coupon_ids( $term, $limit = 100 ) {

		global $wpdb;

		$term  = trim( (string) $term );
		$limit = max( 1, (int) $limit );

		if ( '' === $term ) {
			return array();
		}

		$cache_key = self::get_cache_key( $term, $limit );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID
					FROM {$wpdb->posts}
					WHERE post_type = %s
					AND post_status = %s
					AND post_title LIKE %s
					ORDER BY
						CASE
							WHEN post_title = %s THEN 0
							WHEN post_title LIKE %s THEN 1
							ELSE 2
						END ASC, post_title ASC
					LIMIT %d",
				'shop_coupon',
				'publish',
				'%' . $wpdb->esc_like( $term ) . '%',
				$term,
				$wpdb->esc_like( $term ) . '%',
				$limit
			)
		); // db call ok.

		$ids = is_array( $rows ) ? array_map( 'intval', wp_list_pluck( $rows, 'ID' ) ) : array();

		// A short TTL bounds the entries the posts namespace leaves orphaned.
		wp_cache_set( $cache_key, $ids, self::CACHE_GROUP, MINUTE_IN_SECONDS * 5 );

		return $ids;
	}

	/**
	 * Cache key for one coupon search.
	 *
	 * Folds in the posts namespace so writing any post rolls every stored key.
	 *
	 * @since x.x.x
	 *
	 * @param string $term  Raw search term.
	 * @param int    $limit Row limit the cached result was read with.
	 * @return string Cache key.
	 */
	private static function get_cache_key( $term, $limit ) {

		// Without the namespace a created, renamed or unpublished coupon stays missing until the cache is flushed.
		$last_changed = wp_cache_get_last_changed( 'posts' );

		return 'wcf_search_coupons_' . md5( $term . '|' . $limit . '|' . $last_changed );
	}

	/**
	 * Format matched coupons for the picker, dropping unknown discount types.
	 *
	 * @since x.x.x
	 *
	 * @param array<int, int> $ids   Coupon IDs, best match first.
	 * @param int             $limit Maximum rows to return.
	 * @return array<int, array<string, string>> Picker rows.
	 */
	public static function format_for_picker( $ids, $limit ) {

		$limit = max( 1, (int) $limit );
		$found = array();

		if ( empty( $ids ) ) {
			return $found;
		}

		// One prime beats the post and meta read each get_the_title() and get_post_meta() would make per candidate.
		_prime_post_caches( $ids, false, true );

		$discount_types = wc_get_coupon_types();

		foreach ( $ids as $coupon_id ) {

			$discount_type = get_post_meta( $coupon_id, 'discount_type', true );

			if ( empty( $discount_types[ $discount_type ] ) ) {
				continue;
			}

			$title = get_the_title( $coupon_id );

			$found[] = array(
				'value' => $title,
				'label' => $title . ' (Type: ' . $discount_types[ $discount_type ] . ')',
			);

			// The candidate set is deliberately wider than the cap; stop as soon as the cap is met.
			if ( count( $found ) >= $limit ) {
				break;
			}
		}

		return $found;
	}
}
