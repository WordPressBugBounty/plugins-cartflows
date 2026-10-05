<?php
/**
 * CartFlows Flows ajax actions.
 *
 * @package CartFlows
 */

namespace CartflowsAdmin\AdminCore\Ajax;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CartflowsAdmin\AdminCore\Ajax\AjaxBase;
use CartflowsAdmin\AdminCore\Inc\AdminHelper;

/**
 * Class Steps.
 */
class MetaData extends AjaxBase {

	/**
	 * Instance
	 *
	 * @access private
	 * @var object Class object.
	 * @since 1.0.0
	 */
	private static $instance;

	/**
	 * Rows the pickers display, before `cartflows_product_search_result_limit`
	 * adjusts it for the product endpoint.
	 *
	 * @since x.x.x
	 * @var int
	 */
	const SEARCH_RESULT_LIMIT = 20;

	/**
	 * Floor for the rows read before the supported-product-type filter narrows them.
	 *
	 * @since x.x.x
	 * @var int
	 */
	const SEARCH_CANDIDATE_LIMIT = 100;

	/**
	 * Ceiling for `cartflows_product_search_result_limit`.
	 *
	 * @since x.x.x
	 * @var int
	 */
	const SEARCH_RESULT_LIMIT_MAX = 200;

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
	 * Register ajax events.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_ajax_events() {

		$ajax_events = array(
			'json_search_products',
			'json_search_coupons',
		);

		$this->init_ajax_events( $ajax_events );
	}

	/**
	 * AJAX handler to search products for the admin pickers.
	 *
	 * Searches titles, SKUs and GTINs only, ranked exact match first. It
	 * deliberately does not search descriptions.
	 *
	 * @see \Cartflows_Product_Search::search_product_ids()
	 *
	 * @return void
	 */
	public function json_search_products() {

		if ( ! current_user_can( 'cartflows_manage_flows_steps' ) ) {
			return;
		}

		check_ajax_referer( 'cartflows_json_search_products', 'security' );

		if ( ! isset( $_POST['term'] ) ) {
			return;
		}

		$term = ! empty( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';

		// sanitize_text_field() trims, so a whitespace-only term arrives empty here and would otherwise match every product.
		if ( '' === trim( $term ) ) {
			wp_send_json( array() );
		}

		// CartFlows supported product types.
		$supported_product_types = apply_filters( 'cartflows_supported_product_types_for_search', array( 'simple', 'variable', 'variation', 'subscription', 'variable-subscription', 'subscription_variation', 'course' ) );

		// Allowed product types.
		if ( isset( $_POST['allowed_products'] ) && ! empty( $_POST['allowed_products'] ) ) {

			$allowed_product_types = sanitize_text_field( ( wp_unslash( $_POST['allowed_products'] ) ) );

			$allowed_product_types = $this->sanitize_data_attributes( $allowed_product_types );

			$supported_product_types = $allowed_product_types;
		}

		// Include product types.
		if ( isset( $_POST['include_products'] ) && ! empty( $_POST['include_products'] ) ) {

			$include_product_types = sanitize_text_field( ( wp_unslash( $_POST['include_products'] ) ) );

			$include_product_types = $this->sanitize_data_attributes( $include_product_types );

			$supported_product_types = array_merge( $supported_product_types, $include_product_types );
		}

		// Exclude product types.
		if ( isset( $_POST['exclude_products'] ) && ! empty( $_POST['exclude_products'] ) ) {

			$excluded_product_types = sanitize_text_field( ( wp_unslash( $_POST['exclude_products'] ) ) );

			$excluded_product_types = $this->sanitize_data_attributes( $excluded_product_types );

			$supported_product_types = array_diff( $supported_product_types, $excluded_product_types );
		}

		// Resolved before the search so raising the filter actually widens the candidate set it is sliced from.
		$result_limit = (int) apply_filters( 'cartflows_product_search_result_limit', self::SEARCH_RESULT_LIMIT );

		// Clamped at both ends: the candidate set is a multiple of this, and an unbounded filter value would exhaust memory here.
		$result_limit    = min( self::SEARCH_RESULT_LIMIT_MAX, max( 1, $result_limit ) );
		$candidate_limit = max( self::SEARCH_CANDIDATE_LIMIT, $result_limit * 5 );

		// Excluded here rather than in the browser: filtering after the cap hides products that were never sent.
		$excluded_product_ids = isset( $_POST['exclude_product_ids'] ) ? $this->sanitize_id_list( sanitize_text_field( wp_unslash( $_POST['exclude_product_ids'] ) ) ) : array();

		// Read a wide candidate set: the supported-type filter below would otherwise shrink an already capped list.
		$ids = \Cartflows_Product_Search::search_product_ids( $term, $candidate_limit );

		// One prime beats the post and meta reads wc_get_product() and get_type() would each make per candidate.
		if ( ! empty( $ids ) ) {
			_prime_post_caches( $ids, true, true );
		}

		// Hydrated lazily and capped pair-aware: the candidate set is far wider than the rows that get rendered.
		$product_objects = \Cartflows_Product_Search::collect_supported_products( $ids, $supported_product_types, $result_limit, $excluded_product_ids );

		$products_found = array();

		foreach ( $product_objects as $product_object ) {
			$formatted_name = $product_object->get_name();
			$managing_stock = $product_object->managing_stock();
			$is_in_stock    = $product_object->is_in_stock();
			$product_type   = $product_object->get_type();

			$availibility_text   = $this->get_stock_availability_text( $product_object );
			$product_price_range = $this->get_formatted_product_price_range( $product_object );


			if ( $managing_stock && ! empty( $_GET['display_stock'] ) ) {
				$stock_amount = $product_object->get_stock_quantity();
				/* Translators: %d stock amount */
				$formatted_name .= ' &ndash; ' . sprintf( __( 'Stock: %d', 'cartflows' ), wc_format_stock_quantity_for_display( $stock_amount, $product_object ) );
			}

			array_push(
				$products_found,
				array(
					'value'          => $product_object->get_id(),
					'label'          => $formatted_name,
					'original_price' => \Cartflows_Helper::get_product_original_price( $product_object ),
					'product_name'   => $product_object->get_name(),
					'product_desc'   => $product_object->get_short_description(),
					'product_image'  => get_the_post_thumbnail_url( $product_object->get_id() ),
					'product_type'   => $product_type,
					'type_label'     => $this->get_product_type_label( $product_object ),
					'stock_status'   => $availibility_text,
					'in_stock'       => $is_in_stock,
					'price_range'    => $product_price_range,
				)
			);
		}

		wp_send_json( $products_found );
	}

	/**
	 * AJAX handler to search coupons for the admin pickers.
	 *
	 * Matches anywhere in the coupon code, ranked exact then prefix then the rest.
	 *
	 * @return void
	 */
	public function json_search_coupons() {

		if ( ! current_user_can( 'cartflows_manage_flows_steps' ) ) {
			return;
		}

		check_ajax_referer( 'cartflows_json_search_coupons', 'security' );

		if ( ! isset( $_POST['term'] ) ) {
			return;
		}

		$term = ! empty( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';

		if ( '' === trim( $term ) ) {
			die();
		}

		$ids           = \Cartflows_Coupon_Search::search_coupon_ids( $term, self::SEARCH_CANDIDATE_LIMIT );
		$coupons_found = \Cartflows_Coupon_Search::format_for_picker( $ids, self::SEARCH_RESULT_LIMIT );

		wp_send_json( $coupons_found );
	}

	/**
	 * Sanitize a comma separated list of product IDs.
	 *
	 * @param mixed $raw Raw request value.
	 * @return array<int, int> Positive integer IDs.
	 */
	public function sanitize_id_list( $raw ) {

		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'absint', $raw ) ) );
	}

	/**
	 * Function to sanitize the product type data attribute.
	 *
	 * @param array $product_types product types.
	 */
	public function sanitize_data_attributes( $product_types = array() ) {

		if ( ! is_array( $product_types ) ) {
				$product_types = explode( ',', $product_types );
		}

			// Sanitize the excluded types against valid product types.
		foreach ( $product_types as $index => $value ) {
			$product_types[ $index ] = strtolower( trim( $value ) );
		}
			return $product_types;
	}

	/**
	 * Function to get the stock availability text for a given product.
	 *
	 * This function checks the stock status of a product and returns a string indicating its availability.
	 * The availability text is translated to ensure it is user-friendly and consistent with the plugin's language.
	 *
	 * @param \WC_Product $product The product object to check the stock availability for.
	 * @return string The stock availability text for the product.
	 */
	public function get_stock_availability_text( $product ) {

		$availability_text = '';

		if ( ! is_a( $product, 'WC_Product' ) ) {
			return ''; // Return empty if product is not valid.
		}

		$availability = $product->get_availability();

		if ( ! empty( $availability['class'] ) ) {

			switch ( $availability['class'] ) {
				case 'available-on-backorder':
					$availability_text = __( 'On backorder', 'cartflows' );
					break;
				case 'in-stock':
					$availability_text = __( 'In stock', 'cartflows' );
					break;
				case 'out-of-stock':
					$availability_text = __( 'Out of stock', 'cartflows' );
					break;
				default:
					break;
			}
		}

		return $availability_text;
	}

	/**
	 * Get a short, human readable label for product types that behave differently in a funnel.
	 *
	 * Returned as its own field rather than appended to the product name, because the name is
	 * also used for the selected-product chip. Only types that need distinguishing are labelled;
	 * everything else returns an empty string so no tag is rendered.
	 *
	 * @since 3.1.4
	 * @param \WC_Product $product The product object.
	 * @return string Label to show beside the product, or an empty string.
	 */
	public function get_product_type_label( $product ) {

		if ( ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}

		$labels = apply_filters(
			'cartflows_product_type_labels_for_search',
			array(
				'bundle' => __( 'Bundle', 'cartflows' ),
			)
		);

		$product_type = $product->get_type();

		return isset( $labels[ $product_type ] ) ? $labels[ $product_type ] : '';
	}

	/**
	 * Function to generate a formatted price range for a product.
	 *
	 * This function determines the price range for a product based on its type. For variable products, it calculates the price range from the minimum and maximum prices of its variations. For other product types, it uses the product's price or regular price if available.
	 * The function returns a formatted string representing the price range.
	 *
	 * @param \WC_Product $product The product object to generate the price range for.
	 * @return string The formatted price range for the product.
	 */
	public function get_formatted_product_price_range( $product ) {
		$original_price = 0;

		$product_type = $product->get_type();

		if ( 'variable' === $product_type ) {
			// Code for variation product.
			$variation_price_range = $product->get_variation_prices( true );

			$price         = array();
			$min_price     = isset( $variation_price_range['price'] ) ? current( $variation_price_range['price'] ) : null;
			$max_price     = isset( $variation_price_range['price'] ) ? end( $variation_price_range['price'] ) : null;
			$min_reg_price = isset( $variation_price_range['regular_price'] ) ? current( $variation_price_range['regular_price'] ) : null;
			$max_reg_price = isset( $variation_price_range['regular_price'] ) ? end( $variation_price_range['regular_price'] ) : null;

			if ( $min_price !== $max_price && ! is_null( $min_price ) && ! is_null( $max_price ) ) {
				// Build the range from each bound directly so WooCommerce's screen-reader "Price range: ... through ..." text is not appended.
				$original_price = html_entity_decode( wp_strip_all_tags( wc_price( $min_price ) ) ) . ' – ' . html_entity_decode( wp_strip_all_tags( wc_price( $max_price ) ) );
			}

			if ( $min_reg_price !== $max_reg_price && ! is_null( $min_reg_price ) && ! is_null( $max_reg_price ) ) {
				if ( ! empty( $original_price ) ) {
					$original_price = html_entity_decode( wp_strip_all_tags( wc_price( $min_reg_price ) ) ) . ' – ' . html_entity_decode( wp_strip_all_tags( wc_price( $max_reg_price ) ) );
				}
			}
		} elseif ( 'bundle' === $product_type && method_exists( $product, 'get_bundle_price' ) ) {
			/*
			 * A bundle's own price is only its container base price, which is 0 whenever the
			 * bundled items are priced individually. Use the bundle's min/max price instead so
			 * the selector shows what the customer would actually pay, as it does for variable
			 * products. Guarded on the method so this stays inert without Product Bundles.
			 */
			$min_price = $product->get_bundle_price( 'min', false );
			$max_price = $product->get_bundle_price( 'max', false );

			if ( '' !== $min_price && null !== $min_price ) {
				$original_price = html_entity_decode( wp_strip_all_tags( wc_price( $min_price ) ) );

				if ( '' !== $max_price && null !== $max_price && (float) $max_price !== (float) $min_price ) {
					$original_price .= ' – ' . html_entity_decode( wp_strip_all_tags( wc_price( $max_price ) ) );
				}
			}
		} else {
			$price          = $product->get_price();
			$original_price = ! empty( $price ) ? $price : $product->get_regular_price();
			$original_price = html_entity_decode( wp_strip_all_tags( wc_price( $original_price ) ) );
		}

		return $original_price;
	}
}
