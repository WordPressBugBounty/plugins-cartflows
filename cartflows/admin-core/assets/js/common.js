( function ( $ ) {
	const wcf_back_step_button = function () {
		if ( 'cartflows_step' === typenow ) {
			const step_back_button = $( '#wcf-gutenberg-back-step-button' );

			if ( step_back_button.length > 0 ) {
				$( '#editor' )
					.find( '.edit-post-header__toolbar' )
					.append( step_back_button.html() );
			}
		}
	};

	$( function () {
		setTimeout( function () {
			wcf_back_step_button();
		}, 300 );
	} );

	function installSuccess( event, args ) {
		event.preventDefault();
		const plugin_slug = args.slug;
		activatePlugin( plugin_slug );
	}

	function activatePlugin( plugin_slug ) {
		const plugin_init = plugin_slug + '/' + plugin_slug + '.php';
		$.ajax( {
			type: 'POST',
			dataType: 'json',
			url: cartflows_admin.ajax_url,
			data: {
				action: 'cartflows_activate_plugin',
				security: cartflows_admin.activate_plugin_nonce,
				init: plugin_init,
			},
			success( response ) {
				if ( response.data && response.data.success ) {
					if ( 'woocommerce-payments' === plugin_slug ) {
						window.location.replace(
							cartflows_admin.admin_base_url +
								`admin.php?page=wc-admin&path=/payments/connect`
						);
					} else {
						window.location.reload();
					}
				}
			},
			error() {
				$( 'body' ).css( 'cursor', 'default' );
				alert( 'Something went wrong!' );
			},
		} );
	}

	$( document ).on( 'wp-plugin-install-success', installSuccess );
} )( jQuery );
