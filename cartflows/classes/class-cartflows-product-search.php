<?php
/**
 * Title and SKU scoped product search for the admin pickers.
 *
 * See `architecture.md` in this folder for why this exists rather than calling
 * `WC_Data_Store::search_products()`.
 *
 * @package CartFlows
 * @since x.x.x
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cartflows_Product_Search.
 *
 * Stateless helper — every method is static and there is no instance.
 *
 * @since x.x.x
 */
class Cartflows_Product_Search {

	/**
	 * Post types searched by the product pickers.
	 *
	 * @since x.x.x
	 * @var array<int, string>
	 */
	const POST_TYPES = array( 'product', 'product_variation' );

	/**
	 * Search published products by title, SKU and GTIN, best match first.
	 *
	 * Ranked exact code, exact title, prefix, then the rest; within a tier
	 * top-level products come before variations.
	 *
	 * @since x.x.x
	 *
	 * @param string $term  Raw search term.
	 * @param int    $limit Maximum rows to read from the database.
	 * @return array<int, int> Product and variation IDs, best match first.
	 */
	public static function search_product_ids( $term, $limit = 20 ) {

		global $wpdb;

		$term  = trim( (string) $term );
		$limit = max( 1, (int) $limit );

		if ( '' === $term ) {
			return array();
		}

		// Applied so search integrations that short-circuit WooCommerce's product search keep working here.
		$custom_results = apply_filters( 'woocommerce_product_pre_search_products', false, $term, '', true, false, $limit );

		if ( is_array( $custom_results ) ) {
			return array_values( wp_parse_id_list( $custom_results ) );
		}

		$statuses = self::get_post_statuses();

		if ( empty( $statuses ) ) {
			return array();
		}

		// One join per meta key: matching both keys in one join fans a product with two codes into four rows.
		$own_sku     = "COALESCE( sku_meta.meta_value, '' )";
		$own_gtin    = "COALESCE( gtin_meta.meta_value, '' )";
		$parent_sku  = "COALESCE( parent_sku_meta.meta_value, '' )";
		$parent_gtin = "COALESCE( parent_gtin_meta.meta_value, '' )";

		$exact_code   = "( {$own_sku} = %s OR {$own_gtin} = %s )";
		$inherit_code = "( ( {$own_sku} = '' AND {$parent_sku} = %s ) OR ( {$own_gtin} = '' AND {$parent_gtin} = %s ) )";

		$match_sql    = array();
		$match_params = array();

		// Terms are parsed the way WooCommerce parses them, so stopwords and quoted phrases behave as merchants expect.
		foreach ( self::get_term_groups( $term ) as $words ) {

			$word_sql = array();

			foreach ( $words as $word ) {
				$word_sql[]     = 'posts.post_title LIKE %s';
				$match_params[] = '%' . $wpdb->esc_like( (string) $word ) . '%';
			}

			if ( ! empty( $word_sql ) ) {
				$match_sql[] = '( ' . implode( ' AND ', $word_sql ) . ' )';
			}
		}

		$where_sql = ! empty( $match_sql ) ? '( ' . implode( ' OR ', $match_sql ) . ' )' : '( 1 = 0 )';

		// Exact code matches always run, so a SKU or GTIN containing a space stays reachable by typing it in full.
		$where_sql     .= ' OR ' . $exact_code;
		$match_params[] = $term;
		$match_params[] = $term;

		// An inherited parent code matches exactly only: a partial one drags in every code-less variation and fills the row budget.
		$where_sql     .= ' OR ' . $inherit_code;
		$match_params[] = $term;
		$match_params[] = $term;

		// Only the partial match is skipped for a phrase: it is a leading wildcard LIKE, and codes do not contain spaces.
		if ( ! preg_match( '/\s/', $term ) ) {
			$where_sql     .= " OR {$own_sku} LIKE %s OR {$own_gtin} LIKE %s";
			$match_params[] = '%' . $wpdb->esc_like( $term ) . '%';
			$match_params[] = '%' . $wpdb->esc_like( $term ) . '%';
		}

		$type_placeholders   = implode( ', ', array_fill( 0, count( self::POST_TYPES ), '%s' ) );
		$status_placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		// Argument order follows the placeholders as they appear in the statement: ranking CASE, post types, statuses, match clause, limit.
		$query_params = array_merge(
			array( $term, $term, $term, $term, $term, $wpdb->esc_like( $term ) . '%' ),
			self::POST_TYPES,
			$statuses,
			$match_params,
			array( $limit )
		);

		// Grouped to one row per product: duplicate meta rows would otherwise let one product hold several LIMIT slots.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders and table names only; every value is passed to prepare().
		$sql = "SELECT posts.ID, posts.post_parent, posts.post_title,
					MIN(
						CASE
							WHEN {$exact_code} THEN 0
							WHEN {$inherit_code} THEN 0
							WHEN posts.post_title = %s THEN 1
							WHEN posts.post_title LIKE %s THEN 2
							ELSE 3
						END
					) AS search_rank
				FROM {$wpdb->posts} posts
				LEFT JOIN {$wpdb->postmeta} sku_meta ON sku_meta.post_id = posts.ID AND sku_meta.meta_key = '_sku'
				LEFT JOIN {$wpdb->postmeta} gtin_meta ON gtin_meta.post_id = posts.ID AND gtin_meta.meta_key = '_global_unique_id'
				LEFT JOIN {$wpdb->postmeta} parent_sku_meta ON posts.post_type = 'product_variation' AND parent_sku_meta.post_id = posts.post_parent AND parent_sku_meta.meta_key = '_sku'
				LEFT JOIN {$wpdb->postmeta} parent_gtin_meta ON posts.post_type = 'product_variation' AND parent_gtin_meta.post_id = posts.post_parent AND parent_gtin_meta.meta_key = '_global_unique_id'
				WHERE posts.post_type IN ( {$type_placeholders} )
				AND posts.post_status IN ( {$status_placeholders} )
				AND ( {$where_sql} )
				GROUP BY posts.ID, posts.post_parent, posts.post_title
				ORDER BY search_rank ASC, ( posts.post_parent > 0 ) ASC, posts.post_title ASC, posts.ID ASC
				LIMIT %d";
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only picker search; prepared above with an argument array.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) );

		return self::collect_ids( is_array( $rows ) ? $rows : array(), $term, $statuses );
	}

	/**
	 * Hydrate ranked IDs into supported products, stopping once the cap is met.
	 *
	 * Products are loaded one at a time so a wide candidate set does not cost a
	 * `WC_Product` per candidate, and a variation is never split from its parent.
	 *
	 * @since x.x.x
	 *
	 * @param array<int, int>    $ids             Ranked product and variation IDs.
	 * @param array<int, string> $supported_types Product types the picker accepts.
	 * @param int                $limit           Maximum products to return.
	 * @param array<int, int>    $exclude         Product IDs to skip before the cap applies.
	 * @return array<int, \WC_Product> Supported products, best match first.
	 */
	public static function collect_supported_products( $ids, $supported_types, $limit, $exclude = array() ) {

		$ids      = array_values( array_map( 'intval', $ids ) );
		$limit    = max( 1, (int) $limit );
		$exclude  = array_flip( array_map( 'intval', $exclude ) );
		$total    = count( $ids );
		$products = array();

		for ( $index = 0; $index < $total; $index++ ) {

			$product = self::load_supported_product( $ids[ $index ], $supported_types, $exclude );

			if ( ! $product ) {
				continue;
			}

			$pair      = array( $product );
			$parent_id = (int) $product->get_parent_id();

			// The parent always follows its variation, so the two are capped as one unit.
			if ( $parent_id && isset( $ids[ $index + 1 ] ) && $parent_id === $ids[ $index + 1 ] ) {

				$parent = self::load_supported_product( $parent_id, $supported_types, $exclude );

				if ( $parent ) {
					$pair[] = $parent;
				}

				$index++;
			}

			if ( count( $products ) + count( $pair ) > $limit ) {

				// A pair wider than the whole cap still has to return the matched row itself.
				if ( empty( $products ) ) {
					$products[] = $pair[0];
				}

				break;
			}

			$products = array_merge( $products, $pair );

			if ( count( $products ) >= $limit ) {
				break;
			}
		}

		return $products;
	}

	/**
	 * Load one product if the picker may show it.
	 *
	 * @since x.x.x
	 *
	 * @param int                $product_id      Product or variation ID.
	 * @param array<int, string> $supported_types Product types the picker accepts.
	 * @param array<int, int>    $exclude         Excluded IDs, flipped for lookup.
	 * @return \WC_Product|false The product, or false when it may not be shown.
	 */
	private static function load_supported_product( $product_id, $supported_types, $exclude ) {

		if ( isset( $exclude[ $product_id ] ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product || ! wc_products_array_filter_readable( $product ) ) {
			return false;
		}

		if ( ! in_array( $product->get_type(), $supported_types, true ) ) {
			return false;
		}

		return $product;
	}

	/**
	 * Post statuses the current user may search.
	 *
	 * @since x.x.x
	 *
	 * @return array<int, string> Post statuses.
	 */
	private static function get_post_statuses() {

		$statuses = apply_filters(
			'woocommerce_search_products_post_statuses',
			current_user_can( 'edit_private_products' ) ? array( 'private', 'publish' ) : array( 'publish' )
		);

		$statuses = is_array( $statuses ) ? $statuses : array( 'publish' );

		return array_values( array_map( 'strval', $statuses ) );
	}

	/**
	 * Split the term into OR groups of searchable words.
	 *
	 * Mirrors WooCommerce: groups are OR'd, the words inside one are AND'd.
	 *
	 * @since x.x.x
	 *
	 * @param string $term Raw search term.
	 * @return array<int, array<int, string>> Groups of words.
	 */
	private static function get_term_groups( $term ) {

		$groups = stristr( $term, ' or ' ) ? preg_split( '/\s+or\s+/i', $term ) : array( $term );
		$groups = is_array( $groups ) ? $groups : array( $term );
		$parsed = array();

		foreach ( $groups as $group ) {

			$words = array();

			if ( preg_match_all( '/".*?("|$)|((?<=[\t ",+])|^)[^\t ",+]+/', $group, $matches ) ) {
				$words = self::get_valid_search_terms( $matches[0] );
			}

			$count = count( $words );

			// Match the group as one phrase when it is all stopwords, or long enough that word-by-word matching returns noise.
			if ( 9 < $count || 0 === $count ) {
				$words = array( $group );
			}

			$parsed[] = $words;
		}

		return $parsed;
	}

	/**
	 * Drop stopwords and single characters from parsed search words.
	 *
	 * @since x.x.x
	 *
	 * @param array<int, string> $words Parsed words.
	 * @return array<int, string> Words worth searching on.
	 */
	private static function get_valid_search_terms( $words ) {

		$valid     = array();
		$stopwords = self::get_search_stopwords();

		foreach ( $words as $word ) {

			$word = preg_match( '/^".+"$/', $word ) ? trim( $word, "\"'" ) : trim( $word, "\"' " );

			if ( '' === $word || ( 1 === strlen( $word ) && preg_match( '/^[a-z\-]$/i', $word ) ) ) {
				continue;
			}

			if ( in_array( strtolower( $word ), $stopwords, true ) ) {
				continue;
			}

			$valid[] = $word;
		}

		return $valid;
	}

	/**
	 * Very common words excluded from word-by-word matching.
	 *
	 * @since x.x.x
	 *
	 * @return array<int, string> Stopwords.
	 */
	private static function get_search_stopwords() {

		$stopwords = explode(
			',',
			/* translators: This is a comma-separated list of very common words that should be excluded from a search, like a, an and the. These are usually called "stopwords". Do not translate these individual words literally — provide the commonly accepted stopwords in your language. */
			_x( 'about,an,are,as,at,be,by,com,for,from,how,in,is,it,of,on,or,that,the,this,to,was,what,when,where,who,will,with,www', 'Comma-separated list of search stopwords in your language', 'cartflows' )
		);

		$stopwords = array_map( 'strtolower', array_map( 'trim', $stopwords ) );

		// The same filter WooCommerce and WordPress core run their stopword lists through.
		$stopwords = apply_filters( 'wp_search_stopwords', $stopwords );

		return is_array( $stopwords ) ? array_values( array_map( 'strval', $stopwords ) ) : array();
	}

	/**
	 * Flatten the result rows into a de-duplicated list of IDs.
	 *
	 * Each matched variation is followed immediately by its parent.
	 *
	 * @since x.x.x
	 *
	 * @param array<int, object> $rows     Result rows carrying ID and post_parent.
	 * @param string             $term     Raw search term.
	 * @param array<int, string> $statuses Post statuses the current user may search.
	 * @return array<int, int> Product and variation IDs.
	 */
	private static function collect_ids( $rows, $term, $statuses ) {

		$ids = self::get_id_match( $term, $statuses );

		foreach ( $rows as $row ) {

			if ( ! isset( $row->ID ) ) {
				continue;
			}

			$ids[] = (int) $row->ID;

			if ( ! empty( $row->post_parent ) ) {
				$ids[] = (int) $row->post_parent;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Resolve a numeric term as a product or variation ID.
	 *
	 * WooCommerce does this, so merchants who paste an ID keep finding it.
	 *
	 * @since x.x.x
	 *
	 * @param string             $term     Raw search term.
	 * @param array<int, string> $statuses Post statuses the current user may search.
	 * @return array<int, int> The product ID and its parent, or an empty array.
	 */
	private static function get_id_match( $term, $statuses ) {

		if ( ! is_numeric( $term ) ) {
			return array();
		}

		$post_id = absint( $term );

		if ( ! in_array( (string) get_post_type( $post_id ), self::POST_TYPES, true ) ) {
			return array();
		}

		// Without this a pasted ID surfaces a trashed or draft product above every ranked row.
		if ( ! in_array( (string) get_post_status( $post_id ), $statuses, true ) ) {
			return array();
		}

		$ids    = array( $post_id );
		$parent = (int) wp_get_post_parent_id( $post_id );

		if ( $parent ) {
			$ids[] = $parent;
		}

		return $ids;
	}
}
