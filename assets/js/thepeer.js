jQuery( function( $ ) {

    if ( 'pending' !== tbz_wc_thepeer_params.order_status ) {
        return;
    }

    let thepeer_submit = false;

    $( '#wc-thepeer-form' ).hide();

    tbzWcThePeerPaymentHandler();

    $( '#wc-thepeer-payment-button' ).click( function() {
        tbzWcThePeerPaymentHandler();
    } );

    function tbzWcThePeerPaymentHandler() {

        $( '#wc-thepeer-form' ).hide();

        if ( thepeer_submit ) {
            thepeer_submit = false;
            return true;
        }

        let $form = $( 'form#payment-form, form#order_review' ),
            thepeer_txn_ref = $form.find( 'input.tbz_wc_thepeer_txn_ref' );

        thepeer_txn_ref.val( '' );

        const thepeer_callback = function( response ) {

            $form.append( '<input type="hidden" class="tbz_wc_thepeer_txn_ref" name="tbz_wc_thepeer_txn_ref" value="' + response.data.id + '"/>' );

            thepeer_submit = true;

            $form.submit();

            $( 'body' ).block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                },
                css: {
                    cursor: "wait"
                }
            });
        };

        const thePeerCheckout = new Thepeer.Checkout( {
            publicKey: tbz_wc_thepeer_params.public_key,
            amount: tbz_wc_thepeer_params.amount,
            email: tbz_wc_thepeer_params.customer_email,
            currency: tbz_wc_thepeer_params.currency,
            meta: {
                order_id: tbz_wc_thepeer_params.order_id,
            },
            onSuccess: thepeer_callback,
            onClose: function( event ) {
                $( this.el ).unblock();
                $( '#wc-thepeer-form' ).show();
            },
            onError: function (error) {
                console.log(error);
                $( this.el ).unblock();
                $( '#wc-thepeer-form' ).show();
            },
        } );

        thePeerCheckout.setup();
        thePeerCheckout.open();
    }

} );