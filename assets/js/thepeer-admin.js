jQuery( function( $ ) {
	'use strict';

	let wc_tbz_thepeer_admin = {

		init: function() {

			$( document.body ).on( 'change', '#woocommerce_thepeer_test_mode', function() {
				var test_public_key = $( '#woocommerce_thepeer_test_public_key' ).parents( 'tr' ).eq( 0 ),
					test_secret_key = $( '#woocommerce_thepeer_test_secret_key' ).parents( 'tr' ).eq( 0 ),
					live_public_key = $( '#woocommerce_thepeer_live_public_key' ).parents( 'tr' ).eq( 0 ),
					live_secret_key = $( '#woocommerce_thepeer_live_secret_key' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					test_public_key.show();
					test_secret_key.show();
					live_public_key.hide();
					live_secret_key.hide();
				} else {
					test_public_key.hide();
					test_secret_key.hide();
					live_public_key.show();
					live_secret_key.show();
				}
			} );

			$( '#woocommerce_thepeer_test_mode' ).change();

			$( '#woocommerce_thepeer_test_secret_key, #woocommerce_thepeer_live_secret_key' ).after(
				'<button class="tbz-wc-thepeer-toggle-secret" style="height: 30px; margin-left: 2px; cursor: pointer"><span class="dashicons dashicons-visibility"></span></button>'
			);

			$( '.tbz-wc-thepeer-toggle-secret' ).on( 'click', function( event ) {
				event.preventDefault();

				let $dashicon = $( this ).closest( 'button' ).find( '.dashicons' );
				let $input = $( this ).closest( 'tr' ).find( '.input-text' );
				let inputType = $input.attr( 'type' );

				if ( 'text' == inputType ) {
					$input.attr( 'type', 'password' );
					$dashicon.removeClass( 'dashicons-hidden' );
					$dashicon.addClass( 'dashicons-visibility' );
				} else {
					$input.attr( 'type', 'text' );
					$dashicon.removeClass( 'dashicons-visibility' );
					$dashicon.addClass( 'dashicons-hidden' );
				}
			} );
		}
	};

	wc_tbz_thepeer_admin.init();
});
