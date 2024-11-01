<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_ThePeer_Gateway
 */
class WC_ThePeer_Gateway extends WC_Payment_Gateway {

	/**
	 * Checkout page title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Is gateway enabled?
	 *
	 * @var bool
	 */
	public $enabled;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $test_mode;

	/**
	 * Should orders be marked as complete after payment?
	 *
	 * @var bool
	 */
	public $autocomplete_order;

	/**
	 * Thepeer test public key.
	 *
	 * @var string
	 */
	public $test_public_key;

	/**
	 * Thepeer test secret key.
	 *
	 * @var string
	 */
	public $test_secret_key;

	/**
	 * Thepeer live public key.
	 *
	 * @var string
	 */
	public $live_public_key;

	/**
	 * Thepeer live secret key.
	 *
	 * @var string
	 */
	public $live_secret_key;

	/**
	 * API public key.
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * API secret key.
	 *
	 * @var string
	 */
	public $secret_key;

	public $msg;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'thepeer';
		$this->method_title       = 'Thepeer';
		$this->method_description = sprintf( 'Thepeer is the easiest way to collect payments from customer’s digital wallets. <a href="%1$s" target="_blank">Sign up</a> for a Thepeer account, and <a href="%2$s" target="_blank">get your API keys</a>.', 'https://thepeer.co', 'https://dashboard.thepeer.co/settings/api-keys-and-webhooks' );

		$this->has_fields = true;

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title              = $this->get_option( 'title' );
		$this->enabled            = $this->get_option( 'enabled' );
		$this->test_mode          = $this->get_option( 'test_mode' ) === 'yes';
		$this->autocomplete_order = $this->get_option( 'autocomplete_order' ) === 'yes';

		$this->test_public_key = $this->get_option( 'test_public_key' );
		$this->test_secret_key = $this->get_option( 'test_secret_key' );

		$this->live_public_key = $this->get_option( 'live_public_key' );
		$this->live_secret_key = $this->get_option( 'live_secret_key' );

		$this->public_key = $this->test_mode ? $this->test_public_key : $this->live_public_key;
		$this->secret_key = $this->test_mode ? $this->test_secret_key : $this->live_secret_key;

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

		// Payment listener/API hook.
		add_action( 'woocommerce_api_wc_thepeer_gateway', array( $this, 'verify_thepeer_payment' ) );

		// Webhook listener/API hook.
		add_action( 'woocommerce_api_wc_thepeer_webhook', array( $this, 'process_webhook' ) );

		// Check if the gateway can be used.
		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = false;
		}
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 */
	public function is_valid_for_use() {

		if ( ! in_array( strtoupper(get_woocommerce_currency() ), apply_filters( 'woocommerce_thepeer_supported_currencies', array( 'NGN', 'USD' ) ) ) ) {

			/* translators: %s: URL to WooCommerce general settings page */
			$this->msg = sprintf( __( 'Thepeer does not support your store currency. Kindly set it to either NGN (&#8358) or USD ($) <a href="%s">here</a>', 'thepeer-checkout' ), admin_url( 'admin.php?page=wc-settings&tab=general' ) );

			return false;

		}

		return true;

	}

	/**
	 * Display the payment icon on the checkout page
	 */
	public function get_icon() {

		$icon = '<img src="' . WC_HTTPS::force_https_url( plugins_url( 'assets/images/thepeer_wallets.png', TBZ_WC_THEPEER_MAIN_FILE ) ) . '" alt="Some of the wallets available on Thepeer"  style="height: 64px; width: auto; display: block;"/>';

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );

	}

	/**
	 * Check if Thepeer merchant details is filled
	 */
	public function admin_notices() {

		if ( 'no' === $this->enabled ) {
			return;
		}

		// Check required fields.
		if ( ! ( $this->public_key && $this->secret_key ) ) {
			/* translators: %s: Thepeer WooCommerce payment gateway settings page */
			echo '<div class="error"><p>' . sprintf( __( 'Please enter your Thepeer merchant details <a href="%s">here</a> to be able to accept payment via Thepeer on your WooCommerce store.', 'thepeer-checkout' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=thepeer' ) ) . '</p></div>';
		}
	}

	/**
	 * Check if Thepeer gateway is enabled.
	 */
	public function is_available() {

		if ( 'yes' === $this->enabled ) {

			if ( ! ( $this->public_key && $this->secret_key ) ) {

				return false;

			}

			return true;

		}

		return false;

	}

	/**
	 * Admin Panel Options
	 */
	public function admin_options() {

		?>

		<h2><?php _e( 'Thepeer', 'thepeer-checkout' ); ?></h2>

		<h4>
			<strong>
				<?php
				/* translators: 1: URL to Thepeer developers settings page, 2: Thepeer WooCommerce payment gateway webhook URL. */
				printf( __( 'Required: To avoid situations where bad network makes it impossible to verify transactions, set your webhook URL <a href="%1$s" target="_blank" rel="noopener noreferrer">here</a> to the URL below<span style="color: red"><pre><code>%2$s</code></pre></span>', 'thepeer-checkout' ), 'https://dashboard.thepeer.co/settings/api-keys-and-webhooks', strtolower( WC()->api_request_url( 'WC_ThePeer_Webhook' ) ) );
				?>
			</strong>
		</h4>

		<?php

		if ( $this->is_valid_for_use() ) {

			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';

		} else {
			?>

			<div class="inline error"><p><strong><?php _e( 'Thepeer Payment Gateway Disabled', 'thepeer-checkout' ); ?></strong>: <?php echo $this->msg; ?></p></div>

			<?php
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __( 'Enable/Disable', 'thepeer-checkout' ),
				'label'       => __( 'Enable Thepeer', 'thepeer-checkout' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Thepeer as a payment option on the checkout page.', 'thepeer-checkout' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'title'              => array(
				'title'       => __( 'Title', 'thepeer-checkout' ),
				'type'        => 'text',
				'description' => __( 'This controls the payment method title which the user sees during checkout.', 'thepeer-checkout' ),
				'desc_tip'    => true,
				'default'     => __( 'Pay with Wallets', 'thepeer-checkout' ),
			),
			'test_mode'          => array(
				'title'       => __( 'Test mode', 'thepeer-checkout' ),
				'label'       => __( 'Enable Test Mode', 'thepeer-checkout' ),
				'type'        => 'checkbox',
				'description' => __( 'Test mode enables you to test payments before going live. <br />Once you are live uncheck this.', 'thepeer-checkout' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_public_key'    => array(
				'title'       => __( 'Test Public Key', 'thepeer-checkout' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Test Public Key here.', 'thepeer-checkout' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_secret_key'    => array(
				'title'       => __( 'Test Secret Key', 'thepeer-checkout' ),
				'type'        => 'password',
				'description' => __( 'Required: Enter your Test Secret Key here', 'thepeer-checkout' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'live_public_key'    => array(
				'title'       => __( 'Live Public Key', 'thepeer-checkout' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Live Public Key here.', 'thepeer-checkout' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'live_secret_key'    => array(
				'title'       => __( 'Live Secret Key', 'thepeer-checkout' ),
				'type'        => 'password',
				'description' => __( 'Required: Enter your Live Secret Key here.', 'thepeer-checkout' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'autocomplete_order' => array(
				'title'       => __( 'Autocomplete Order After Payment', 'thepeer-checkout' ),
				'label'       => __( 'Autocomplete Order', 'thepeer-checkout' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, the order will be marked as complete after successful payment', 'thepeer-checkout' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
		);

	}

	/**
	 * Outputs scripts used by Thepeer.
	 */
	public function payment_scripts() {

		if ( isset( $_GET['pay_for_order'] ) || ! is_checkout_pay_page() ) {
			return;
		}

		if ( 'no' === $this->enabled ) {
			return;
		}

		$order_key = urldecode( sanitize_text_field( $_GET['key'] ) );
		$order_id  = absint( get_query_var( 'order-pay' ) );

		$order = wc_get_order( $order_id );

		$payment_method = method_exists( $order, 'get_payment_method' ) ? $order->get_payment_method() : $order->payment_method;

		if ( $this->id !== $payment_method ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'thepeer', 'https://cdn.thepeer.co/v1/chain.js', array( 'jquery' ), TBZ_WC_THEPEER_VERSION );
		wp_enqueue_script( 'thepeer-wc', plugins_url( 'assets/js/thepeer' . $suffix . '.js', TBZ_WC_THEPEER_MAIN_FILE ), array( 'jquery', 'thepeer' ), TBZ_WC_THEPEER_VERSION );

		$thepeer_params = array(
			'public_key' => $this->public_key,
		);

		if ( is_checkout_pay_page() && get_query_var( 'order-pay' ) ) {

			$email      = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;
			$first_name = method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name;
			$last_name  = method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name;
			$name       = trim( $first_name . ' ' . $last_name );

			$amount = $order->get_total();

			$txnref = 'WC|' . $order_id . '|' . time();

			$the_order_id  = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
			$the_order_key = method_exists( $order, 'get_order_key' ) ? $order->get_order_key() : $order->order_key;

			if ( absint( $the_order_id ) === $order_id && $the_order_key === $order_key ) {

				$thepeer_params['reference']      = $txnref;
				$thepeer_params['amount']         = $amount * 100;
				$thepeer_params['currency']       = $order->get_currency();
				$thepeer_params['customer_email'] = $email;
				$thepeer_params['customer_name']  = $name;
				$thepeer_params['order_id']       = $order_id;
				$thepeer_params['order_status']   = $order->get_status();

				$order->add_meta_data( '_thepeer_txn_ref', $txnref, true );
				$order->save();
			}
		}

		wp_localize_script( 'thepeer-wc', 'tbz_wc_thepeer_params', $thepeer_params );
	}

	/**
	 * Load admin scripts
	 */
	public function admin_scripts() {

		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'tbz_wc_thepeer_admin', plugins_url( 'assets/js/thepeer-admin' . $suffix . '.js', TBZ_WC_THEPEER_MAIN_FILE ), array(), TBZ_WC_THEPEER_VERSION, true );
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id WC Order ID.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Displays the payment page
	 */
	public function receipt_page( $order_id ) {

		$order = wc_get_order( $order_id );

		echo '<div id="wc-thepeer-form">';

		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Thepeer.', 'thepeer-checkout' ) . '</p>';

		echo '<div id="tbz_wc_thepeer_form"><form id="order_review" method="post" action="' . WC()->api_request_url( 'WC_ThePeer_Gateway' ) . '"></form><button class="button alt" id="wc-thepeer-payment-button">' . __( 'Pay Now', 'thepeer-checkout' ) . '</button>';
		echo ' <a class="button cancel" id="thepeer-cancel-payment-button" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'thepeer-checkout' ) . '</a></div>';


		echo '</div>';
	}

	/**
	 * Verify thepeer payment
	 */
	public function verify_thepeer_payment() {

		@ob_clean();

		if ( isset( $_REQUEST['tbz_wc_thepeer_txn_ref'] ) ) {
			$txn_ref = sanitize_text_field( $_REQUEST['tbz_wc_thepeer_txn_ref'] );
		} else {
			$txn_ref = false;
		}

		if ( false === $txn_ref ) {
			wp_redirect( wc_get_page_permalink( 'cart' ) );
			exit;
		}

		$thepeer_txn = $this->verify_transaction( $txn_ref );

		if ( false === $thepeer_txn ) {
			wp_redirect( wc_get_page_permalink( 'cart' ) );
			exit;
		}

		if ( 'success' === strtolower( $thepeer_txn->transaction->status ) ) {

			$order_id = (int) $thepeer_txn->transaction->checkout->meta->order_id;
			$order    = wc_get_order( $order_id );

			if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
				wp_redirect( $this->get_return_url( $order ) );
				exit;
			}

			$order_total      = $order->get_total();
			$amount_paid      = $thepeer_txn->transaction->amount / 100;
			$thepeer_txn_ref  = $thepeer_txn->transaction->reference;
			$order_currency   = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();
			$currency_symbol  = get_woocommerce_currency_symbol( $order_currency );

			// check if the amount paid is equal to the order amount.
			if ( $amount_paid < $order_total ) {

				$order->update_status( 'on-hold' );

				if ( method_exists( $order, 'set_transaction_id' ) ) {
					$order->set_transaction_id( $thepeer_txn_ref );
					$order->save();
				}

				/* translators: 1: Line break, 2: Line break, 3: Line break. */
				$notice      = sprintf( __( 'Thank you for shopping with us.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'thepeer-checkout' ), '<br />', '<br />', '<br />' );
				$notice_type = 'notice';

				// Add Customer Order Note.
				$order->add_order_note( $notice, 1 );

				// Add Admin Order Note
				/* translators: 1: Line break, 2: Thepeer transaction reference. */
				$admin_order_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s%5$s</strong> while the total order amount is <strong>%6$s%7$s</strong>%8$s<strong>Thepeer Transaction Reference:</strong> %9$s', 'thepeer-checkout' ), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $thepeer_txn_ref );
				$order->add_order_note( $admin_order_note );

				function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

				wc_add_notice( $notice, $notice_type );

				wp_redirect( $this->get_return_url( $order ) );
				exit;
			}

			$order->payment_complete( $thepeer_txn_ref );

			/* translators: %s: Thepeer transaction reference. */
			$order->add_order_note( sprintf( __( 'Payment via Thepeer successful (Transaction Reference: %s)', 'thepeer-checkout' ), $thepeer_txn_ref ) );

			if ( $this->autocomplete_order ) {
				$order->update_status( 'completed' );
			}

			WC()->cart->empty_cart();

			wp_redirect( $this->get_return_url( $order ) );
			exit;
		}

		// Payment failed.
		$order_id = (int) $thepeer_txn->transaction->checkout->meta->order_id;
		$order    = wc_get_order( $order_id );
		$order->update_status( 'failed', __( 'Thepeer payment failed.', 'thepeer-checkout' ) );

		wp_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Process Webhook
	 */
	public function process_webhook() {

		if ( ( strtoupper( $_SERVER['REQUEST_METHOD'] ) !== 'POST' ) ) {
			exit;
		}

		$body = @file_get_contents( 'php://input' );

		$webhook_body = json_decode( $body, true );

		// Validate webhook.
		$signature = hash_hmac( 'sha1', $body, $this->secret_key );

		if ( $signature !== $_SERVER['HTTP_X_THEPEER_SIGNATURE'] ) {
			exit;
		}

		if ( empty( $webhook_body['transaction'] || 'transaction' !== $webhook_body['type'] ) ) {
			exit;
		}

		if ( ! isset( $webhook_body['transaction']['id'] ) ) {
			exit;
		}

		$thepeer_txn = $this->verify_transaction( $webhook_body['transaction']['id'] );

		if ( false === $thepeer_txn ) {
			exit;
		}

		if ( 'success' !== strtolower( $thepeer_txn->transaction->status ) ) {
			exit;
		}

		$gateway_txn_ref = $thepeer_txn->transaction->reference;

		$order_id = (int) $thepeer_txn->transaction->checkout->meta->order_id;

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			exit;
		}

		http_response_code( 200 );

		if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
			exit;
		}

		$order_total      = $order->get_total();
		$amount_paid      = $thepeer_txn->transaction->amount / 100;
		$order_currency   = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();
		$currency_symbol  = get_woocommerce_currency_symbol( $order_currency );

		// check if the amount paid is equal to the order amount.
		if ( $amount_paid < $order_total ) {

			$order->update_status( 'on-hold' );

			if ( method_exists( $order, 'set_transaction_id' ) ) {
				$order->set_transaction_id( $gateway_txn_ref );
				$order->save();
			}

			$notice      = sprintf( __( 'Thank you for shopping with us.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status.', 'thepeer-checkout' ), '<br />', '<br />', '<br />' );
			$notice_type = 'notice';

			// Add Customer Order Note.
			$order->add_order_note( $notice, 1 );

			// Add Admin Order Note
			$admin_order_note = sprintf( __( '<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Amount paid is less than the total order amount.%3$sAmount Paid was <strong>%4$s (%5$s)</strong> while the total order amount is <strong>%6$s (%7$s)</strong>%8$s<strong>thepeer Transaction Reference:</strong> %9$s', 'thepeer-checkout' ), '<br />', '<br />', '<br />', $currency_symbol, $amount_paid, $currency_symbol, $order_total, '<br />', $gateway_txn_ref );
			$order->add_order_note( $admin_order_note );

			function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

		} else {

			$order->payment_complete( $gateway_txn_ref );

			$order->add_order_note( sprintf( __( 'Payment via thepeer successful (Transaction Reference: %s)', 'thepeer-checkout' ), $gateway_txn_ref ) );

			WC()->cart->empty_cart();

			if ( $this->autocomplete_order ) {
				$order->update_status( 'completed' );
			}
		}

		wc_empty_cart();

		exit;
	}

	private function verify_transaction( $txn_id ) {

		$api_url = "https://api.thepeer.co/transactions/$txn_id";

		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'X-Api-Key'    => $this->secret_key,
		);

		$args = array(
			'headers' => $headers,
			'timeout' => 60,
		);

		$request = wp_remote_get( $api_url, $args );

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			return json_decode( wp_remote_retrieve_body( $request ) );
		}

		return false;
	}
}
