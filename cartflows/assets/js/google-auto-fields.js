( function ( $ ) {
	const settings = window.wcf_google_places || {};
	const i18n = settings.i18n || {};
	const legacyAutocomplete = {};
	const suggestionWidgets = {};
	let useLegacyApi = false;

	// Remembers for a day that the key refused Places API (New), so shoppers skip the failing first request.
	const LEGACY_API_FLAG = 'wcf_places_legacy_until';

	const componentForm = {
		street_number: 'long_name',
		route: 'long_name',
		locality: 'long_name',
		postal_town: 'long_name',
		sublocality_level_2: 'long_name',
		administrative_area_level_1: 'short_name',
		administrative_area_level_2: 'short_name',
		country: 'short_name',
		postal_code: 'short_name',
	};

	function handleAdminAreaLevel( fieldVal, elementId, default_state ) {
		const sanitized_field_value = fieldVal.replace( /\s+/g, '' );
		const $state = $( `#${ elementId }` );

		// Compare values directly; names like "Co. Dublin" are not valid in an attribute selector.
		if (
			$state.is( 'select' ) &&
			$state.find( 'option' ).filter( function () {
				return this.value === sanitized_field_value;
			} ).length > 0
		) {
			return sanitized_field_value;
		}

		return default_state;
	}

	function getSelectedCountry( type ) {
		// Single-country stores render the country as a hidden input, so read the value, not :selected.
		const country = $( `#${ type }_country` ).val();

		return country ? String( country ) : '';
	}

	// Show Google's error to store managers only; shoppers never see it.
	function reportError( message ) {
		if ( ! settings.show_errors || ! message ) {
			return;
		}

		const $field = $(
			'#billing_address_1_field, #shipping_address_1_field'
		).first();

		if ( ! $field.length ) {
			return;
		}

		$( '.wcf-address-autocomplete-error' ).remove();
		$field.append(
			$( '<span>', {
				class: 'wcf-address-autocomplete-error',
				role: 'alert',
				text: message,
			} )
		);
	}

	window.gm_authFailure =
		window.gm_authFailure ||
		function () {
			reportError( i18n.auth_failed );
		};

	// Converts a Places API (New) Place into the legacy PlaceResult shape the parser reads.
	function toLegacyPlace( place ) {
		return {
			name: place.displayName,
			address_components: ( place.addressComponents || [] ).map(
				( component ) => ( {
					long_name: component.longText,
					short_name: component.shortText,
					types: component.types,
				} )
			),
		};
	}

	function fillAddressFields( type, place ) {
		if (
			'shipping' === type &&
			! $( '#ship-to-different-address-checkbox' ).is( ':checked' )
		) {
			return;
		}

		// Bail before clearing the form when the place carries no address data.
		if (
			! place ||
			! place.address_components ||
			! place.address_components.length
		) {
			return;
		}

		$( `#${ type }_address_1` ).val( '' );
		$( `#${ type }_country` ).val( '' );
		$( `#${ type }_address_2` ).val( '' );
		$( `#${ type }_city` ).val( '' );
		$( `#${ type }_state` ).val( '' );
		$( `#${ type }_postcode` ).val( '' );

		const add1_attr = [ 'street_number', 'route' ];
		const add2_attr = [ 'sublocality_level_2' ];

		let country = '',
			state = '',
			city = '',
			address_1 = '',
			address_2 = '',
			postal_code = '';

		// Get each component of the address from the place details
		// and fill the corresponding field on the form.
		if ( place && place.address_components.length > 0 ) {
			for ( const address_value of place.address_components ) {
				const addressType = address_value.types[ 0 ];

				if ( componentForm[ addressType ] ) {
					const fieldVal =
						address_value[ componentForm[ addressType ] ];

					if ( addressType === 'country' ) {
						country = fieldVal;
					}

					if (
						addressType === 'administrative_area_level_1' ||
						addressType === 'administrative_area_level_2'
					) {
						state = handleAdminAreaLevel(
							fieldVal,
							`${ type }_state`,
							state
						);
					}

					if (
						addressType === 'locality' ||
						addressType === 'postal_town'
					) {
						city = fieldVal;
					}

					if ( addressType === 'postal_code' ) {
						postal_code = fieldVal;
					}

					if ( add1_attr.indexOf( addressType ) !== -1 ) {
						address_1 += fieldVal + ' ';
					}

					if ( add2_attr.indexOf( addressType ) !== -1 ) {
						address_2 += fieldVal + ' ';
					}
				}
			}

			if ( $( `#${ type }_address_1` ).length > 0 ) {
				if ( address_1.length > 0 ) {
					$( `#${ type }_address_1` )
						.val( address_1.trimEnd() )
						.trigger( 'focus' );
				}
			}

			if ( $( `#${ type }_address_2` ).length > 0 ) {
				$( `#${ type }_address_2` )
					.val( address_2.trimEnd() )
					.trigger( 'focus' );
			}

			if ( $( `#${ type }_postcode` ).length > 0 ) {
				$( `#${ type }_postcode` )
					.val( postal_code )
					.trigger( 'focus' );
			}

			if ( $( `#${ type }_city` ).length > 0 ) {
				$( `#${ type }_city` ).val( city ).trigger( 'focus' );
			}

			if ( $( `#${ type }_state` ).length > 0 ) {
				$( `#${ type }_state` )
					.val( state )
					.trigger( 'focus' )
					.trigger( 'change' );
			}

			if ( $( `#${ type }_country` ).length > 0 ) {
				$( `#${ type }_country` )
					.val( country )
					.trigger( 'focus' )
					.trigger( 'change' );
			}

			// Use Google's place name for address_1, as street number and route order differ by country.
			if (
				place.name &&
				( '' !== place.name || 'undefined' !== typeof place.name )
			) {
				$( `#${ type }_address_1` ).val( place.name );
			}
		}
	}

	// Legacy Places API widget, used only when Places API (New) is unavailable for the key.
	function initLegacyAutocomplete( type ) {
		const address_field = document.getElementById( `${ type }_address_1` );

		// Bail if the field is not present in the checkout form.
		if ( ! address_field ) {
			return;
		}

		const country = getSelectedCountry( type );
		const options = {
			componentRestrictions: { country: [ country ] },
			types: [ 'geocode' ],
		};

		// Remove country restriction if country field is disabled or not present in checkout form.
		if ( '' === country ) {
			delete options.componentRestrictions;
		}

		const autocomplete = new google.maps.places.Autocomplete(
			address_field,
			options
		);

		autocomplete.addListener( 'place_changed', function () {
			fillAddressFields( type, autocomplete.getPlace() );
		} );

		legacyAutocomplete[ type ] = autocomplete;
	}

	// Only a disabled API or a refused key means the new API can never work; other failures are transient.
	function isNewApiUnavailable( error ) {
		const code = error && error.code ? String( error.code ) : '';
		const message = error && error.message ? error.message : '';

		return (
			'PERMISSION_DENIED' === code ||
			'UNAUTHENTICATED' === code ||
			/PERMISSION_DENIED|REQUEST_DENIED|UNAUTHENTICATED|has not been used|is disabled|not authorized/i.test(
				message
			)
		);
	}

	function prefersLegacyApi() {
		try {
			return (
				Number( window.localStorage.getItem( LEGACY_API_FLAG ) ) >
				Date.now()
			);
		} catch ( e ) {
			return false;
		}
	}

	function rememberLegacyApi() {
		try {
			window.localStorage.setItem(
				LEGACY_API_FLAG,
				String( Date.now() + 24 * 60 * 60 * 1000 )
			);
		} catch ( e ) {
			// Storage can be blocked; the next page load just probes the new API again.
		}
	}

	function switchToLegacyApi( message ) {
		if ( useLegacyApi ) {
			return;
		}

		useLegacyApi = true;

		Object.keys( suggestionWidgets ).forEach( ( type ) => {
			suggestionWidgets[ type ]();
			delete suggestionWidgets[ type ];
		} );

		initLegacyAutocomplete( 'billing' );
		initLegacyAutocomplete( 'shipping' );

		// Replay the shopper's current text so the legacy widget answers the query that just failed.
		[ 'billing', 'shipping' ].forEach( ( type ) => {
			const input = document.getElementById( `${ type }_address_1` );

			if (
				input &&
				input.value &&
				input === input.ownerDocument.activeElement
			) {
				input.dispatchEvent(
					new window.Event( 'input', { bubbles: true } )
				);
			}
		} );

		reportError( message );
	}

	// Places API (New) suggestion list attached to the existing WooCommerce address input.
	function initPlacesSuggestions( type ) {
		const $input = $( `#${ type }_address_1` );

		// Bail if the field is not present in the checkout form.
		if ( ! $input.length ) {
			return;
		}

		const listId = `wcf-${ type }-address-suggestions`;
		const $list = $( '<ul>', {
			id: listId,
			class: 'wcf-address-suggestions',
			role: 'listbox',
			'aria-label': i18n.suggestions_label || '',
		} ).hide();

		const originalAutocomplete = $input.attr( 'autocomplete' );
		let sessionToken = null;
		let timer = null;
		let requestId = 0;
		let predictions = [];
		let activeIndex = -1;

		$input
			.attr( {
				autocomplete: 'off',
				role: 'combobox',
				'aria-autocomplete': 'list',
				'aria-expanded': 'false',
				'aria-controls': listId,
			} )
			.after( $list );

		function closeList() {
			predictions = [];
			activeIndex = -1;
			$list.hide().empty();
			$input
				.attr( 'aria-expanded', 'false' )
				.removeAttr( 'aria-activedescendant' );
		}

		function highlight( index ) {
			const $items = $list.children();

			activeIndex = index;
			$items.attr( 'aria-selected', 'false' );

			if ( index < 0 ) {
				$input.removeAttr( 'aria-activedescendant' );
				return;
			}

			$items.eq( index ).attr( 'aria-selected', 'true' );
			$input.attr( 'aria-activedescendant', `${ listId }-${ index }` );
		}

		function selectPrediction( index ) {
			const prediction = predictions[ index ];

			if ( ! prediction ) {
				return;
			}

			closeList();
			clearTimeout( timer );
			requestId++;
			$input.val( prediction.text.toString() );

			// Fetching fields ends the billing session, so the next search starts a new one.
			sessionToken = null;

			const place = prediction.toPlace();

			place
				.fetchFields( {
					fields: [ 'addressComponents', 'displayName' ],
				} )
				.then( function () {
					fillAddressFields( type, toLegacyPlace( place ) );
				} )
				.catch( function ( error ) {
					reportError( error && error.message );
				} );
		}

		function renderList() {
			$list.empty();

			if ( ! predictions.length ) {
				closeList();
				return;
			}

			predictions.forEach( ( prediction, index ) => {
				$list.append(
					$( '<li>', {
						id: `${ listId }-${ index }`,
						class: 'wcf-address-suggestion',
						role: 'option',
						'aria-selected': 'false',
						text: prediction.text.toString(),
					} ).on( 'mousedown', function ( event ) {
						// Prevent the input blur from closing the list before the click lands.
						event.preventDefault();
						selectPrediction( index );
					} )
				);
			} );

			activeIndex = -1;
			$list.show();
			$input.attr( 'aria-expanded', 'true' );
		}

		function search() {
			const value = $input.val().trim();

			if ( value.length < 3 ) {
				closeList();
				return;
			}

			const { AutocompleteSuggestion, AutocompleteSessionToken } =
				google.maps.places;

			sessionToken = sessionToken || new AutocompleteSessionToken();

			const request = {
				input: value,
				sessionToken,
				includedPrimaryTypes: [ 'geocode' ],
			};
			const country = getSelectedCountry( type );

			if ( '' !== country ) {
				request.includedRegionCodes = [ country ];
			}

			const currentRequest = ++requestId;

			AutocompleteSuggestion.fetchAutocompleteSuggestions( request )
				.then( function ( response ) {
					if ( currentRequest !== requestId ) {
						return;
					}

					predictions = ( response.suggestions || [] )
						.map( ( suggestion ) => suggestion.placePrediction )
						.filter( Boolean );

					renderList();
				} )
				.catch( function ( error ) {
					if ( isNewApiUnavailable( error ) ) {
						rememberLegacyApi();
						switchToLegacyApi(
							( i18n.new_api_failed || '' ) + ' ' + error.message
						);
						return;
					}

					if ( currentRequest === requestId ) {
						closeList();
						reportError(
							( i18n.request_failed || '' ) +
								' ' +
								( error && error.message ? error.message : '' )
						);
					}
				} );
		}

		$input.on( 'input.wcfPlaces', function () {
			clearTimeout( timer );
			timer = setTimeout( search, 250 );
		} );

		$input.on( 'keydown.wcfPlaces', function ( event ) {
			if ( ! predictions.length ) {
				return;
			}

			const count = predictions.length;

			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					highlight( ( activeIndex + 1 ) % count );
					break;
				case 'ArrowUp':
					event.preventDefault();
					highlight( activeIndex <= 0 ? count - 1 : activeIndex - 1 );
					break;
				case 'Enter':
					if ( activeIndex >= 0 ) {
						// Stop Enter from submitting the checkout form.
						event.preventDefault();
						selectPrediction( activeIndex );
					}
					break;
				case 'Escape':
					closeList();
					break;
			}
		} );

		// Drop the pending search too, or its response reopens the list after focus has left.
		$input.on( 'blur.wcfPlaces', function () {
			clearTimeout( timer );
			requestId++;
			closeList();
		} );

		suggestionWidgets[ type ] = function () {
			clearTimeout( timer );
			requestId++;
			$input.off( '.wcfPlaces' );
			$list.remove();
			$input
				.removeAttr(
					'role aria-autocomplete aria-expanded aria-controls aria-activedescendant'
				)
				.attr( 'autocomplete', originalAutocomplete || null );
		};
	}

	// Only the legacy widget holds a country restriction; the new API reads it per request.
	$( document ).on(
		'change',
		'#billing_country, #shipping_country',
		function () {
			const type = 'billing_country' === this.id ? 'billing' : 'shipping';
			const country = $( this ).val();

			if ( useLegacyApi && legacyAutocomplete[ type ] ) {
				legacyAutocomplete[ type ].setComponentRestrictions( {
					country: country ? [ country ] : [],
				} );
			}
		}
	);

	$( function () {
		if (
			'undefined' === typeof google ||
			! google.maps ||
			! google.maps.places
		) {
			return;
		}

		if ( ! google.maps.places.AutocompleteSuggestion ) {
			switchToLegacyApi( i18n.new_api_missing );
			return;
		}

		// Store managers always probe the new API so they see its current status after a key change.
		if ( ! settings.show_errors && prefersLegacyApi() ) {
			switchToLegacyApi();
			return;
		}

		initPlacesSuggestions( 'billing' );
		initPlacesSuggestions( 'shipping' );
	} );
} )( jQuery );
