/* global wcf_fbt */
/**
 * Frequently Bought Together — Admin panel behaviour.
 *
 * Handles: sub-tab mode switch, card grid sync with WC's Select2, counter,
 * card × remove, drag reorder, and (Phase 3) the Auto Suggest workflow.
 *
 * Depends on jQuery, jquery-ui-sortable, and wc-enhanced-select (already
 * enqueued by WooCommerce on the product edit screen).
 */
( function ( $ ) {
	'use strict';

	// Same wait as Pro's fbt-tour.js, for the same unsettled metabox.
	const FOCUS_SETTLE_DELAY = 400;

	// Long enough to swallow a burst of keystrokes, short enough to feel live.
	const REPAINT_DELAY = 150;

	let $panel;
	let $chooser;
	let $values;
	let $aiValues;
	let $grid;
	let $empty;
	let $counter;
	let $headerRow;
	let $tabs;
	let $panes;
	let $sourceInputs;
	let $aiPane;
	let $enable;
	let $drawerRoot;
	let $drawerBody;
	let $drawerTitle;
	let $drawerMeta;
	let lastFocused = null;
	let drawerContext = null;
	// Incremented per drawer request so a slow response cannot paint over a newer one.
	let drawerRequestId = 0;
	let $liveRegion = null;
	let announceTimer = null;

	// Badge colour for a tone Pro sends with a row; unknown tones fall back to gray.
	const BADGE_TONE_CLASS = {
		warning: 'wcf-fbt-badge-yellow',
		success: 'wcf-fbt-badge-green',
		info: 'wcf-fbt-badge-brand',
		neutral: 'wcf-fbt-badge-gray',
	};

	// Labels for fallback sources when Pro sends no badge of its own.
	const FALLBACK_BADGE_LABELS = {
		category: 'badge_popular',
		popular: 'badge_popular',
		recent: 'badge_new',
	};

	function init() {
		maybeCaptureAccessKey();
		renderTabBadge();
		bindCalloutDismiss();
		maybeFocusPanel();

		$panel = $( '#cartflows_fbt_data' );

		if ( $panel.length ) {
			$chooser = $panel.find( '#_cartflows_fbt_product_search' );
			$values = $panel.find( '.wcf-fbt-picker-values' );
			$aiValues = $panel.find( '.wcf-fbt-picker-ai-values' );
			$grid = $panel.find( '.wcf-fbt-picker-rows' );
			$empty = $panel.find( '.wcf-fbt-picker-empty' );
			$counter = $panel.find( '[data-role="picker-count"]' );
			$headerRow = $panel.find( '.wcf-fbt-picker-header-row' );
			$tabs = $panel.find( '.wcf-fbt-sub-tab' );
			$panes = $panel.find( '.wcf-fbt-pane' );
			$sourceInputs = $panel.find( '.wcf-fbt-source-input' );
			$aiPane = $panel.find( '.wcf-fbt-pane-ai' );
			$enable = $panel.find( '#_cartflows_fbt_enabled' );

			bindEnable();
			bindCustomQty();
			bindPosition();
			bindDiscount();
			bindTabs();
			bindChooser();
			bindCards();
			bindSortable();
			bindAi();
			syncEmpty();
			syncCounter();
		}

		initDrawer();

		$( document ).on( 'click', '.wcf-fbt-cta-connect', function ( event ) {
			event.preventDefault();
			const redirectBack = encodeURIComponent( window.location.href );
			wp.apiFetch( {
				path: '/cartflows/v1/ai/auth?redirect_back=' + redirectBack,
			} ).then( function ( res ) {
				if ( res && res.success && res.data && res.data.auth_url ) {
					window.location = res.data.auth_url;
				}
			} );
		} );
	}

	function renderTabBadge() {
		if ( ! wcf_fbt.tab_badge ) {
			return;
		}

		const $link = $( 'li.cartflows_fbt_tab.wcf-fbt-tab-new' ).find( 'a' );
		if ( ! $link.length || $link.find( '.wcf-fbt-tab-badge' ).length ) {
			return;
		}

		$link.append(
			$( '<span/>', {
				class: 'wcf-fbt-tab-badge',
				text: wcf_fbt.tab_badge,
			} )
		);
	}

	function scrollWhenSettled( $box ) {
		const run = function () {
			// scrollIntoView on .panel-wrap blanks the panel, so move the window instead.
			window.scrollTo( {
				top: Math.max( 0, $box.offset().top - 60 ),
				// Not 'smooth': post-load plugin init scrolls abort the animation mid-flight.
				behavior: 'instant',
			} );
		};

		if ( 'complete' === document.readyState ) {
			setTimeout( run, FOCUS_SETTLE_DELAY );
			return;
		}

		$( window ).one( 'load', function () {
			setTimeout( run, FOCUS_SETTLE_DELAY );
		} );
	}

	function maybeFocusPanel() {
		if ( ! wcf_fbt.focus_arg ) {
			return;
		}

		const params = new URLSearchParams( window.location.search );
		if ( ! params.get( wcf_fbt.focus_arg ) ) {
			return;
		}

		const $tab = $( 'li.cartflows_fbt_tab' );
		const $box = $( '#woocommerce-product-data' );
		if ( ! $tab.length || ! $box.length ) {
			return;
		}

		// WC hides the tab for types outside its show_if_* classes, so do not force it open.
		const productType = $( '#product-type' ).val();
		if ( productType && ! $tab.hasClass( 'show_if_' + productType ) ) {
			return;
		}

		// WC collapses the metabox by class, so open it without POSTing closed-postboxes.
		$box.removeClass( 'closed' );
		$tab.find( 'a' ).trigger( 'click' );

		// TinyMCE and metabox init move the panel after DOM ready, so measure once the page settles.
		scrollWhenSettled( $box );

		// Drop the arg so a refresh does not re-trigger the jump.
		params.delete( wcf_fbt.focus_arg );
		const query = params.toString();
		window.history.replaceState(
			{},
			document.title,
			window.location.pathname +
				( query ? '?' + query : '' ) +
				window.location.hash
		);
	}

	function bindCalloutDismiss() {
		$( document ).on( 'click', '.wcf-fbt-callout-dismiss', function () {
			const $callout = $( this ).closest( '.wcf-fbt-callout' );
			const nonce = $callout.data( 'nonce' );

			$callout.remove();

			$.post( wcf_fbt.ajax_url, {
				action: 'cartflows_fbt_dismiss_callout',
				nonce,
			} );
		} );
	}

	function maybeCaptureAccessKey() {
		const params = new URLSearchParams( window.location.search );
		const accessKey = params.get( 'access_key' );
		if ( ! accessKey ) {
			return;
		}

		params.delete( 'access_key' );
		const cleanQuery = params.toString();
		const cleanUrl =
			window.location.pathname +
			( cleanQuery ? '?' + cleanQuery : '' ) +
			window.location.hash;
		window.history.replaceState( {}, document.title, cleanUrl );

		wp.apiFetch( {
			path: '/cartflows/v1/ai/auth',
			method: 'POST',
			data: { accessKey },
		} )
			.then( function ( res ) {
				if ( res && res.success ) {
					window.location.reload();
				}
			} )
			.catch( function () {} );
	}

	function bindCustomQty() {
		const $toggle = $panel.find( '#_cartflows_fbt_custom_qty' );
		const $shell = $panel.find( '.wcf-fbt-picker-shell' );
		if ( ! $toggle.length || ! $shell.length ) {
			return;
		}
		$toggle.on( 'change', function () {
			$shell.toggleClass( 'has-qty', $toggle.is( ':checked' ) );
		} );
	}

	function bindPosition() {
		const $position = $panel.find( '#_cartflows_fbt_position' );
		const $hint = $panel.find( '.wcf-fbt-shortcode-hint' );
		if ( ! $position.length || ! $hint.length ) {
			return;
		}
		$position.on( 'change', function () {
			$hint.toggleClass( 'is-visible', 'shortcode' === $position.val() );
		} );
	}

	function bindDiscount() {
		const $type = $panel.find( '#_cartflows_fbt_discount_type' );
		const $value = $panel.find( '#_cartflows_fbt_discount_value' );
		if ( ! $type.length || ! $value.length ) {
			return;
		}

		const $amount = $panel.find( '.wcf-fbt-discount-amount' );
		const $unit = $panel.find( '.wcf-fbt-discount-unit' );
		const $hint = $panel.find( '.wcf-fbt-discount-hint' );

		function sync() {
			const type = $type.val();
			const isPercent = 'discount_percent' === type;

			$amount.toggleClass( 'hidden', '' === type );
			$unit.text( isPercent ? '%' : $unit.data( 'currency' ) || '' );
			// Percent shares the order-bump ceiling; price off has none.
			$value.attr( 'max', isPercent ? 100 : null );
			$hint.text(
				'' === type
					? wcf_fbt.i18n.discount_hint_off
					: wcf_fbt.i18n.discount_hint_on
			);
			clampValue();
			syncDiscountDisplay();
		}

		function clampValue() {
			if (
				'discount_percent' === $type.val() &&
				parseFloat( $value.val() ) > 100
			) {
				$value.val( 100 );
			}
		}

		let repaintTimer = null;

		function repaintSoon() {
			clampValue();
			window.clearTimeout( repaintTimer );
			repaintTimer = window.setTimeout(
				syncDiscountDisplay,
				REPAINT_DELAY
			);
		}

		function repaintNow() {
			window.clearTimeout( repaintTimer );
			clampValue();
			syncDiscountDisplay();
		}

		$type.on( 'change', sync );
		$value.on( 'input', repaintSoon );
		$value.on( 'change', repaintNow );
		sync();
	}

	/**
	 * Read the panel's current bundle discount.
	 *
	 * @return {{type: string, value: number, active: boolean}} Current discount state.
	 */
	function readDiscount() {
		const type = $panel.find( '#_cartflows_fbt_discount_type' ).val() || '';
		const raw = parseFloat(
			$panel.find( '#_cartflows_fbt_discount_value' ).val()
		);
		const value = isNaN( raw ) ? 0 : Math.max( 0, raw );

		return { type, value, active: '' !== type && value > 0 };
	}

	/**
	 * Format a number the way wc_price() would, from the store's localized number format.
	 *
	 * @param {number} value Amount to render.
	 * @return {string} Formatted price.
	 */
	function formatPrice( value ) {
		const format = wcf_fbt.price_format || {};
		const decimals = parseInt( format.decimals, 10 );
		const fixed = Math.abs( value ).toFixed(
			isNaN( decimals ) ? 2 : decimals
		);
		const parts = fixed.split( '.' );
		let number = parts[ 0 ].replace(
			/\B(?=(\d{3})+(?!\d))/g,
			format.thousand_sep || ''
		);
		if ( parts[ 1 ] ) {
			number += ( format.decimal_sep || '.' ) + parts[ 1 ];
		}

		const symbol = wcf_fbt.currency_symbol || '';
		const sign = value < 0 ? '-' : '';

		if ( 'right' === format.position ) {
			return sign + number + symbol;
		}
		if ( 'right_space' === format.position ) {
			return sign + number + '\u00a0' + symbol;
		}
		if ( 'left_space' === format.position ) {
			return sign + symbol + '\u00a0' + number;
		}

		return sign + symbol + number;
	}

	/**
	 * Render a number with at most two decimals and no trailing zeros.
	 *
	 * @param {number} value Amount to render.
	 * @return {string} Trimmed number.
	 */
	function trimDecimal( value ) {
		return String( parseFloat( value.toFixed( 2 ) ) );
	}

	/**
	 * Repaint the topbar discount chip and every picker row's Price cell.
	 *
	 * @return {void}
	 */
	function syncDiscountDisplay() {
		const discount = readDiscount();

		$panel
			.find( '[data-role="picker-discount"]' )
			.text(
				discount.active
					? amountLabel( discount )
					: wcf_fbt.i18n.discount_none
			);

		$grid.find( '.wcf-fbt-picker-row' ).each( function () {
			const $price = $( this ).find( '.wcf-fbt-picker-price' );
			const base = parseFloat( $price.attr( 'data-base-price' ) ) || 0;

			// A zero base is a variable product or a price we never resolved — leave its own markup alone.
			if ( ! discount.active || base <= 0 ) {
				const original = $price.attr( 'data-original-html' );
				if ( undefined !== original ) {
					$price.html( original );
				}
				return;
			}

			const sale =
				'discount_percent' === discount.type
					? base - ( base * Math.min( 100, discount.value ) ) / 100
					: base - discount.value;

			$price.html( salePriceHtml( base, Math.max( 0, sale ) ) );
		} );
	}

	/**
	 * Render a discount amount the way Cartflows_Fbt::format_discount_amount() does.
	 *
	 * @param {{type: string, value: number}} discount Current discount state.
	 * @return {string} Amount label, e.g. "10%" or "$5.00".
	 */
	function amountLabel( discount ) {
		return 'discount_percent' === discount.type
			? trimDecimal( discount.value ) + '%'
			: formatPrice( discount.value );
	}

	/**
	 * Build strikethrough price markup matching wc_format_sale_price()'s a11y affordances.
	 *
	 * @param {number} base Original price.
	 * @param {number} sale Discounted price.
	 * @return {string} Price cell HTML.
	 */
	function salePriceHtml( base, sale ) {
		const original = formatPrice( base );
		const current = formatPrice( sale );

		return (
			'<del aria-hidden="true">' +
			escapeHtml( original ) +
			'</del> <span class="screen-reader-text">' +
			escapeHtml(
				wcf_fbt.i18n.price_original.replace( '%s', original )
			) +
			'</span><ins aria-hidden="true">' +
			escapeHtml( current ) +
			'</ins><span class="screen-reader-text">' +
			escapeHtml( wcf_fbt.i18n.price_current.replace( '%s', current ) ) +
			'</span>'
		);
	}

	function bindEnable() {
		$enable.on( 'change', function () {
			$panel.toggleClass( 'is-widget-enabled', $enable.is( ':checked' ) );
		} );
	}

	function bindTabs() {
		$tabs.on( 'click', function () {
			const mode = $( this ).data( 'mode' );
			switchMode( mode );
		} );
	}

	function switchMode( mode ) {
		$tabs.removeClass( 'is-active' );
		$tabs.filter( '[data-mode="' + mode + '"]' ).addClass( 'is-active' );
		$panes.removeClass( 'is-active' );
		$panes.filter( '[data-pane="' + mode + '"]' ).addClass( 'is-active' );
		$sourceInputs.prop( 'checked', false );
		$sourceInputs
			.filter( '[value="' + mode + '"]' )
			.prop( 'checked', true );
	}

	function bindChooser() {
		$chooser.on( 'select2:select', function ( event ) {
			const item =
				event.params && event.params.data ? event.params.data : null;
			if ( ! item || ! item.id ) {
				return;
			}
			addProduct( item.id, String( item.text || '' ).trim() );
			// Pop the just-added option off so the Select2 stays chip-free
			// and keeps rendering as a plain search input — the row list is
			// the source of truth for what's selected.
			$chooser.find( 'option[value="' + item.id + '"]' ).remove();
			$chooser.trigger( 'change' );
		} );
	}

	function bindCards() {
		$grid.on( 'click', '.wcf-fbt-picker-remove', function () {
			const id = $( this )
				.closest( '.wcf-fbt-picker-row' )
				.data( 'product-id' );
			if ( ! id ) {
				return;
			}
			removeCard( id );
		} );
	}

	function bindSortable() {
		if ( ! $.fn.sortable ) {
			return;
		}
		$grid.sortable( {
			handle: '.wcf-fbt-picker-drag',
			placeholder: 'wcf-fbt-picker-row is-sortable-placeholder',
			forcePlaceholderSize: true,
			tolerance: 'pointer',
			update: syncValueOrder,
		} );
	}

	function syncValueOrder() {
		const newOrder = [];
		$grid.find( '.wcf-fbt-picker-row' ).each( function () {
			newOrder.push( String( $( this ).data( 'product-id' ) ) );
		} );
		const $inputs = $values.find( 'input' ).detach();
		$.each( newOrder, function ( _idx, id ) {
			const $match = $inputs.filter( '[value="' + id + '"]' );
			if ( $match.length ) {
				$values.append( $match );
			}
		} );
	}

	function addProduct( id, name ) {
		if ( ! id ) {
			return;
		}
		if (
			$grid.find( '.wcf-fbt-picker-row[data-product-id="' + id + '"]' )
				.length
		) {
			return;
		}
		if (
			$grid.find( '.wcf-fbt-picker-row' ).length >= wcf_fbt.max_products
		) {
			return;
		}
		$values.append(
			'<input type="hidden" name="_cartflows_fbt_product_ids[]" value="' +
				escapeHtml( id ) +
				'" />'
		);
		addCard( id, name );
		updateExclude();
		fetchProductMeta( id );
	}

	function markAiSourced( id ) {
		if (
			! $aiValues.length ||
			$aiValues.find( 'input[value="' + id + '"]' ).length
		) {
			return;
		}
		$aiValues.append(
			'<input type="hidden" name="_cartflows_fbt_ai_product_ids[]" value="' +
				escapeHtml( id ) +
				'" />'
		);
	}

	function updateExclude() {
		const ids = [ parseInt( wcf_fbt.product_id, 10 ) ];
		$values.find( 'input' ).each( function () {
			ids.push( parseInt( $( this ).val(), 10 ) );
		} );
		// JSON array so jQuery.data() (which Select2 reads) returns an array — not a string.
		$chooser.attr( 'data-exclude', JSON.stringify( ids ) );
		// Bust jQuery's data cache so the next read picks up the new value.
		$chooser.removeData( 'exclude' );
	}

	function fetchProductMeta( id ) {
		$.ajax( {
			url: wcf_fbt.ajax_url,
			method: 'POST',
			data: {
				action: 'wcf_fbt_get_product_meta',
				security: $( '#wcf_fbt_ai_nonce' ).val(),
				product_id: id,
			},
			timeout: 8000,
		} ).done( function ( response ) {
			if ( ! response || ! response.success || ! response.data ) {
				return;
			}
			const $row = $grid.find(
				'.wcf-fbt-picker-row[data-product-id="' + id + '"]'
			);
			if ( ! $row.length ) {
				return;
			}
			if ( response.data.price_html ) {
				$row.find( '.wcf-fbt-picker-price' )
					.attr( 'data-base-price', response.data.price || 0 )
					.attr( 'data-original-html', response.data.price_html )
					.text( response.data.price_html );
			}
			if ( response.data.stock_label ) {
				$row.find( '.wcf-fbt-picker-stock' ).html(
					'<span class="wcf-fbt-badge ' +
						escapeHtml( response.data.stock_class || '' ) +
						'">' +
						escapeHtml( response.data.stock_label ) +
						'</span>'
				);
			}
			if ( response.data.thumb_url ) {
				$row.find( '.wcf-fbt-picker-thumb' ).attr(
					'src',
					response.data.thumb_url
				);
			}
			syncDiscountDisplay();
		} );
	}

	function addCard( id, name ) {
		if ( ! id ) {
			return;
		}
		if (
			$grid.find( '.wcf-fbt-picker-row[data-product-id="' + id + '"]' )
				.length
		) {
			return;
		}
		const html = [
			'<div class="wcf-fbt-picker-row" data-product-id="' +
				escapeHtml( id ) +
				'">',
			'<span class="wcf-fbt-picker-drag" aria-hidden="true">',
			'<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>',
			'</span>',
			'<img class="wcf-fbt-picker-thumb" src="' +
				escapeHtml( wcf_fbt.placeholder_thumb || '' ) +
				'" alt="" />',
			'<div class="wcf-fbt-picker-info">',
			'<p class="wcf-fbt-picker-name">' + escapeHtml( name ) + '</p>',
			'</div>',
			'<span class="wcf-fbt-picker-price" data-base-price="0"></span>',
			'<span class="wcf-fbt-picker-stock"></span>',
			'<span class="wcf-fbt-picker-qty">',
			'<input type="number" min="1" step="1" class="wcf-fbt-picker-qty-input" name="_cartflows_fbt_product_qty[' +
				escapeHtml( id ) +
				']" value="1" />',
			'</span>',
			'<button type="button" class="wcf-fbt-picker-remove" aria-label="' +
				escapeHtml( wcf_fbt.i18n.remove ) +
				'">',
			'<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>',
			'</button>',
			'</div>',
		].join( '' );
		$grid.append( html );
		syncEmpty();
		syncCounter();
		syncDiscountDisplay();
	}

	function removeCard( id ) {
		$grid
			.find( '.wcf-fbt-picker-row[data-product-id="' + id + '"]' )
			.remove();
		$values.find( 'input[value="' + id + '"]' ).remove();
		$aiValues.find( 'input[value="' + id + '"]' ).remove();
		updateExclude();
		syncEmpty();
		syncCounter();
	}

	function syncEmpty() {
		const hasRows = $grid.find( '.wcf-fbt-picker-row' ).length > 0;
		$empty.prop( 'hidden', hasRows );
		$grid.toggle( hasRows );
		$headerRow.toggle( hasRows );
	}

	function syncCounter() {
		const count = $grid.find( '.wcf-fbt-picker-row' ).length;
		$counter.text( count );
		$panel
			.find( '[data-role="products-label"]' )
			.text( wcf_fbt.i18n.products_label.replace( '%d', count ) );
	}

	function bindAi() {
		if ( ! $aiPane.length ) {
			return;
		}

		$aiPane.on( 'click', '.wcf-fbt-generate', function () {
			runSuggest( false );
		} );

		$aiPane.on( 'click', '.wcf-fbt-regen-all', function () {
			runSuggest( true );
		} );

		$aiPane.on( 'click', '.wcf-fbt-ai-accept', function () {
			const $item = $( this ).closest( '.wcf-fbt-ai-item' );
			const id = $item.data( 'product-id' );
			if ( ! id ) {
				return;
			}
			addProduct( id, $item.data( 'name' ) );
			markAiSourced( id );
			$item.remove();
			maybeShowAiHero();
		} );

		$aiPane.on( 'click', '.wcf-fbt-ai-reject', function ( event ) {
			event.preventDefault();
			const $item = $( this ).closest( '.wcf-fbt-ai-item' );
			$item.remove();
			maybeShowAiHero();
		} );
	}

	function maybeShowAiHero() {
		if ( $aiPane.find( '.wcf-fbt-ai-item' ).length ) {
			return;
		}
		$aiPane.html( renderAiHero() );
	}

	function selectedProductIds() {
		return $values
			.find( 'input' )
			.map( function () {
				return parseInt( $( this ).val(), 10 );
			} )
			.get();
	}

	function runSuggest( force ) {
		const exclude = selectedProductIds().concat(
			force ? collectShownIds( $aiPane ) : []
		);
		showAiLoading();
		fetchSuggestions( force, 'fbt', exclude )
			.then( function ( result ) {
				renderAiResults(
					result.products,
					result.message,
					result.notice
				);
			} )
			.catch( function ( error ) {
				showAiStatus( errorMessageFor( error ), true );
			} );
	}

	// Upsell and cross-sell have their own endpoints so their logic can diverge.
	const SUGGEST_ROUTES = {
		fbt: '/cartflows-pro/v1/frequently-bought-together/',
		upsell: '/cartflows-pro/v1/upsell-suggestions/',
		crosssell: '/cartflows-pro/v1/cross-sell-suggestions/',
	};

	const SHARED_SUGGEST_ROUTE = SUGGEST_ROUTES.fbt;

	// Per context: a Pro may ship one dedicated route and not the other, so a 404 for
	// cross-sell must not downgrade upsell as well.
	const sharedRouteOnly = {};

	function suggestQuery( force, context, exclude, route ) {
		let query =
			'?product_ids=' +
			encodeURIComponent( wcf_fbt.product_id ) +
			'&limit=4&include_alternatives=1';
		if ( route === SHARED_SUGGEST_ROUTE ) {
			query += '&context=' + encodeURIComponent( context );
		}
		if ( exclude && exclude.length ) {
			query += '&exclude=' + encodeURIComponent( exclude.join( ',' ) );
		}
		if ( force ) {
			query += '&force=1&_ts=' + Date.now();
		}
		return query;
	}

	function unwrapSuggestions( response ) {
		if ( ! response || ! response.success ) {
			return Promise.reject( new Error( 'no-success' ) );
		}
		return {
			products: response.products || [],
			message: response.message || '',
			notice: response.notice || '',
		};
	}

	function fetchSuggestions( force, context, exclude ) {
		const key = context || 'fbt';
		const route =
			sharedRouteOnly[ key ] || ! SUGGEST_ROUTES[ key ]
				? SHARED_SUGGEST_ROUTE
				: SUGGEST_ROUTES[ key ];
		const isShared = route === SHARED_SUGGEST_ROUTE;

		return wp
			.apiFetch( {
				path: route + suggestQuery( force, key, exclude, route ),
			} )
			.catch( function ( error ) {
				// A newer free can meet an older Pro with no dedicated route, so retry the shared one before reporting Pro missing.
				if ( isShared || ! error || 'rest_no_route' !== error.code ) {
					return Promise.reject( error );
				}
				sharedRouteOnly[ key ] = true;
				return wp.apiFetch( {
					path:
						SHARED_SUGGEST_ROUTE +
						suggestQuery(
							force,
							key,
							exclude,
							SHARED_SUGGEST_ROUTE
						),
				} );
			} )
			.then( unwrapSuggestions );
	}

	function collectShownIds( $scope ) {
		return $scope
			.find( '.wcf-fbt-ai-item' )
			.map( function () {
				return $( this ).data( 'product-id' );
			} )
			.get();
	}

	function errorMessageFor( error ) {
		if ( error && 'rest_no_route' === error.code ) {
			return wcf_fbt.i18n.ai_endpoint_missing;
		}
		return wcf_fbt.i18n.ai_error;
	}

	/**
	 * Markup for one status line.
	 *
	 * @param {string} message Text to show.
	 * @param {string} tone    '' for neutral, or 'error' / 'warning'.
	 * @return {string} HTML.
	 */
	function statusHtml( message, tone ) {
		return (
			'<div class="wcf-fbt-status' +
			( tone ? ' is-' + tone : '' ) +
			'">' +
			escapeHtml( message ) +
			'</div>'
		);
	}

	/**
	 * Read text out through the live region.
	 *
	 * @param {string} message Text to announce.
	 */
	function announce( message ) {
		// Clearing first makes a repeat of the same text count as a change, and drops a stale notice.
		clearAnnouncement();
		if ( ! $liveRegion || ! message ) {
			return;
		}
		announceTimer = setTimeout( function () {
			$liveRegion.text( message );
		}, 100 );
	}

	/**
	 * Empty the live region and cancel a pending announcement.
	 */
	function clearAnnouncement() {
		clearTimeout( announceTimer );
		if ( $liveRegion ) {
			$liveRegion.text( '' );
		}
	}

	/**
	 * The notice line plus the suggestion rows.
	 *
	 * @param {Array}  products Suggestions to render.
	 * @param {string} notice   Warning from Pro about these results, or ''.
	 * @return {string} HTML.
	 */
	function resultsListHtml( products, notice ) {
		let html = notice ? statusHtml( notice, 'warning' ) : '';
		html += '<div class="wcf-fbt-ai-list">';
		let i;
		for ( i = 0; i < products.length; i++ ) {
			html += renderAiRow( products[ i ] );
		}
		return html + '</div>';
	}

	function showAiLoading() {
		let rows = '';
		let i;
		for ( i = 0; i < 3; i++ ) {
			rows += '<div class="wcf-fbt-skeleton-row"></div>';
		}
		$aiPane.html(
			statusHtml( wcf_fbt.i18n.ai_loading, '' ) +
				'<div class="wcf-fbt-skeleton-list">' +
				rows +
				'</div>'
		);
	}

	/**
	 * Render a status line in the AI pane.
	 *
	 * The hero always comes back with it: it carries the only Generate button, and
	 * the pane's content is entirely JS-owned, so omitting it strands the admin.
	 *
	 * @param {string}  message Text to show.
	 * @param {boolean} isError Whether to use the error styling.
	 */
	function showAiStatus( message, isError ) {
		$aiPane.html(
			statusHtml( message, isError ? 'error' : '' ) + renderAiHero()
		);
	}

	function renderAiHero() {
		return [
			'<div class="wcf-fbt-ai-hero">',
			'<div class="wcf-fbt-ai-hero-icon" aria-hidden="true">' +
				escapeHtml( '✨' ) +
				'</div>',
			'<h4>' + escapeHtml( wcf_fbt.i18n.ai_hero_title ) + '</h4>',
			'<p>' + escapeHtml( wcf_fbt.i18n.ai_hero_desc ) + '</p>',
			'<button type="button" class="button button-primary wcf-fbt-generate">' +
				escapeHtml( wcf_fbt.i18n.ai_generate ) +
				'</button>',
			'</div>',
		].join( '' );
	}

	function renderAiResults( products, message, notice ) {
		if ( ! products || ! products.length ) {
			showAiStatus( message || wcf_fbt.i18n.ai_none, false );
			return;
		}
		const toolbar = [
			'<div class="wcf-fbt-ai-toolbar">',
			'<span>' + escapeHtml( wcf_fbt.i18n.ai_cached ) + '</span>',
			'<button type="button" class="button wcf-fbt-btn wcf-fbt-btn-sm wcf-fbt-regen-all">' +
				escapeHtml( wcf_fbt.i18n.ai_regen_all ) +
				'</button>',
			'</div>',
		].join( '' );
		$aiPane.html( toolbar + resultsListHtml( products, notice ) );
	}

	function renderAiRow( item ) {
		const percent =
			'number' === typeof item.match_percent ? item.match_percent : 0;
		const matchLabel = wcf_fbt.i18n.match_label.replace(
			'%s',
			Math.round( percent ) + '%'
		);
		const fallbackKey = FALLBACK_BADGE_LABELS[ item.source ];
		// A 0% match is a normalization artifact — show no badge rather than a misleading number.
		let badge = '';
		if ( item.badge && item.badge.label ) {
			badge =
				'<span class="wcf-fbt-badge ' +
				( BADGE_TONE_CLASS[ item.badge.tone ] ||
					'wcf-fbt-badge-gray' ) +
				'">' +
				escapeHtml( item.badge.label ) +
				'</span>';
		} else if ( fallbackKey ) {
			badge =
				'<span class="wcf-fbt-badge wcf-fbt-badge-gray">' +
				escapeHtml( wcf_fbt.i18n[ fallbackKey ] ) +
				'</span>';
		} else if ( Math.round( percent ) > 0 ) {
			badge =
				'<span class="wcf-fbt-badge wcf-fbt-badge-green">' +
				escapeHtml( matchLabel ) +
				'</span>';
		}
		return [
			'<div class="wcf-fbt-ai-item" data-product-id="' +
				escapeHtml( item.id ) +
				'" data-name="' +
				escapeHtml( item.name ) +
				'">',
			'<img class="wcf-fbt-card-thumb" src="' +
				escapeHtml( item.image || wcf_fbt.placeholder_thumb || '' ) +
				'" alt="" />',
			'<div class="wcf-fbt-ai-content">',
			'<div class="wcf-fbt-ai-row1">',
			'<p class="wcf-fbt-ai-name">' + escapeHtml( item.name ) + '</p>',
			badge,
			item.price_html
				? '<span class="wcf-fbt-badge wcf-fbt-badge-gray">' +
				  item.price_html +
				  '</span>'
				: '',
			'</div>',
			'</div>',
			'<div class="wcf-fbt-ai-actions">',
			'<button type="button" class="button button-primary wcf-fbt-btn wcf-fbt-btn-sm wcf-fbt-ai-accept">' +
				escapeHtml( wcf_fbt.i18n.ai_accept ) +
				'</button>',
			'<a href="#" class="button-link wcf-fbt-ai-reject" role="button">' +
				escapeHtml( wcf_fbt.i18n.ai_reject ) +
				'</a>',
			'</div>',
			'</div>',
		].join( '' );
	}

	function initDrawer() {
		$drawerRoot = $( '.wcf-fbt-drawer-root' );
		if ( ! $drawerRoot.length ) {
			return;
		}
		$drawerBody = $drawerRoot.find( '.wcf-fbt-drawer-body' );
		$drawerTitle = $drawerRoot.find( '.wcf-fbt-drawer-title' );
		$drawerMeta = $drawerRoot.find( '.wcf-fbt-drawer-meta' );
		// Inside the aria-modal dialog, which hides everything else, and beside the body, which .html() replaces.
		$liveRegion = $(
			'<div class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true"></div>'
		).insertAfter( $drawerBody );

		relocateSuggestButtons();

		$( document ).on( 'click', '.wcf-fbt-suggest-ai', function () {
			const context = $( this ).data( 'context' );
			openDrawer( context );
		} );

		$drawerRoot.on(
			'click',
			'.wcf-fbt-drawer-close, .wcf-fbt-drawer-done, .wcf-fbt-drawer-backdrop',
			closeDrawer
		);

		$( document ).on( 'keyup.wcffbtdrawer', function ( event ) {
			if ( 'Escape' === event.key && ! $drawerRoot.prop( 'hidden' ) ) {
				closeDrawer();
			}
		} );

		$drawerBody.on( 'click', '.wcf-fbt-ai-accept', drawerAccept );
		$drawerBody.on( 'click', '.wcf-fbt-ai-reject', drawerReject );

		$drawerRoot.on( 'click', '.wcf-fbt-drawer-regen', function () {
			$( this ).addClass( 'wcf-fbt-is-regenerating' );
			runDrawerSuggest( true );
		} );
	}

	/**
	 * Move each "Auto Suggest" button next to the WC field it targets.
	 *
	 * WC's `woocommerce_product_options_related` hook fires below both fields, so
	 * PHP renders both buttons together. This lifts each button into its matching
	 * .form-field row so it sits inline with the Select2, matching the mockup.
	 */
	function relocateSuggestButtons() {
		const $wrapper = $( '.wcf-fbt-linked-buttons' );
		if ( ! $wrapper.length ) {
			return;
		}
		$wrapper.find( '.wcf-fbt-suggest-ai' ).each( function () {
			const $btn = $( this );
			const targetId = $btn.data( 'target-select' );
			if ( ! targetId ) {
				return;
			}
			const $target = $( '#' + targetId );
			if ( ! $target.length ) {
				return;
			}
			const $field = $target.closest( '.form-field' );
			if ( $field.length ) {
				$field.append( $btn );
			}
		} );
		if ( ! $wrapper.children().length ) {
			$wrapper.remove();
		}
	}

	function openDrawer( context ) {
		if ( 'upsell' !== context && 'crosssell' !== context ) {
			return;
		}
		drawerContext = context;
		const title =
			'upsell' === context
				? wcf_fbt.i18n.drawer_title_upsell
				: wcf_fbt.i18n.drawer_title_crosssell;
		$drawerTitle.text( title );
		$drawerMeta.text( '' );
		lastFocused = $drawerRoot[ 0 ].ownerDocument.activeElement;
		$drawerRoot.prop( 'hidden', false ).addClass( 'is-open' );
		runDrawerSuggest( false );
	}

	function closeDrawer() {
		if ( ! $drawerRoot || ! $drawerRoot.length ) {
			return;
		}
		$drawerRoot
			.prop( 'hidden', true )
			.removeClass( 'is-open is-empty is-disconnected' );
		drawerContext = null;
		++drawerRequestId;
		// A response dropped as stale never reaches the code that would clear this.
		clearRegenerating();
		clearAnnouncement();
		if ( lastFocused && lastFocused.focus ) {
			lastFocused.focus();
		}
	}

	function runDrawerSuggest( force ) {
		if ( ! drawerContext ) {
			return;
		}
		if ( ! wcf_fbt.auth_connected ) {
			showDrawerConnect();
			return;
		}
		const chosen = (
			$( '#' + wcf_fbt.select_ids[ drawerContext ] ).val() || []
		).map( Number );
		const exclude = chosen.concat(
			force ? collectShownIds( $drawerBody ) : []
		);
		const requestContext = drawerContext;
		// Every writer of drawerContext bumps this id in the same step, so the id
		// alone identifies a superseded response. Keep that invariant if you add one.
		const requestId = ++drawerRequestId;
		const isStale = function () {
			return requestId !== drawerRequestId;
		};
		showDrawerLoading();
		fetchSuggestions( force, requestContext, exclude )
			.then( function ( result ) {
				if ( isStale() ) {
					return;
				}
				renderDrawerResults(
					result.products,
					result.message,
					result.notice
				);
			} )
			.catch( function ( error ) {
				if ( isStale() ) {
					return;
				}
				showDrawerStatus( errorMessageFor( error ), true );
			} );
	}

	function clearRegenerating() {
		$drawerRoot
			.find( '.wcf-fbt-drawer-regen' )
			.removeClass( 'wcf-fbt-is-regenerating' );
	}

	function showDrawerConnect() {
		$drawerRoot.removeClass( 'is-empty' ).addClass( 'is-disconnected' );
		$drawerMeta.text( '' );
		$drawerBody.html(
			[
				'<div class="wcf-fbt-ai-hero">',
				'<div class="wcf-fbt-ai-hero-icon" aria-hidden="true">' +
					escapeHtml( '✨' ) +
					'</div>',
				'<h4>' + escapeHtml( wcf_fbt.i18n.connect_title ) + '</h4>',
				'<p>' + escapeHtml( wcf_fbt.i18n.connect_desc ) + '</p>',
				'<a href="#" class="button button-primary wcf-fbt-upgrade-cta wcf-fbt-cta-connect">' +
					escapeHtml( wcf_fbt.i18n.connect_btn ) +
					'</a>',
				'</div>',
			].join( '' )
		);
	}

	function showDrawerLoading() {
		let rows = '';
		let i;
		for ( i = 0; i < 3; i++ ) {
			rows += '<div class="wcf-fbt-skeleton-row"></div>';
		}
		$drawerRoot.removeClass( 'is-empty' );
		clearAnnouncement();
		$drawerBody.html(
			statusHtml( wcf_fbt.i18n.ai_loading, '' ) +
				'<div class="wcf-fbt-skeleton-list">' +
				rows +
				'</div>'
		);
	}

	/**
	 * Render a status line in the drawer body.
	 *
	 * @param {string}  message Text to show.
	 * @param {boolean} isError Whether to use the error styling.
	 */
	function showDrawerStatus( message, isError ) {
		clearRegenerating();
		// The meta line advertises a cached result, which no longer describes what is shown.
		$drawerMeta.text( '' );
		$drawerBody.html( statusHtml( message, isError ? 'error' : '' ) );
	}

	function renderDrawerResults( products, message, notice ) {
		$drawerRoot.removeClass( 'is-disconnected' );
		clearRegenerating();
		if ( ! products || ! products.length ) {
			showDrawerStatus( message || wcf_fbt.i18n.ai_none, false );
			return;
		}
		$drawerBody.html( resultsListHtml( products, notice ) );
		announce( notice );
		$drawerMeta.text( wcf_fbt.i18n.ai_cached );
	}

	function drawerAccept() {
		const $item = $( this ).closest( '.wcf-fbt-ai-item' );
		const id = $item.data( 'product-id' );
		if ( ! id || ! drawerContext ) {
			return;
		}
		const targetSelectId = wcf_fbt.select_ids[ drawerContext ];
		if ( ! targetSelectId ) {
			return;
		}
		const $target = $( '#' + targetSelectId );
		if ( ! $target.length ) {
			return;
		}
		const name = $item.data( 'name' );
		let $option = $target.find( 'option[value="' + id + '"]' );
		if ( ! $option.length ) {
			$option = $( '<option>', {
				value: id,
				text: name,
				selected: true,
			} );
			$target.append( $option );
		} else {
			$option.prop( 'selected', true );
		}
		$target.trigger( 'change' );
		$item.remove();
		maybeShowDrawerEmpty();
	}

	function drawerReject( event ) {
		event.preventDefault();
		const $item = $( this ).closest( '.wcf-fbt-ai-item' );
		$item.remove();
		maybeShowDrawerEmpty();
	}

	function maybeShowDrawerEmpty() {
		if ( $drawerBody.find( '.wcf-fbt-ai-item' ).length ) {
			return;
		}
		$drawerBody.empty();
		$drawerMeta.text( '' );
		$drawerRoot.addClass( 'is-empty' );
	}

	function escapeHtml( str ) {
		return String( null === str || undefined === str ? '' : str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	$( document ).ready( init );
} )( jQuery );
