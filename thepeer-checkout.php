<?php
/*
	Plugin Name:			Thepeer Payment Gateway for WooCommerce
	Description:            WooCommerce payment gateway for Thepeer
	Version:                1.0.6
	Author: 				thepeer
	Author URI: 			https://thepeer.co/
	License:        		GPL-2.0+
	License URI:    		http://www.gnu.org/licenses/gpl-2.0.txt
	WC requires at least:   3.8.0
	WC tested up to:        6.3
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'TBZ_WC_THEPEER_MAIN_FILE' ) ) {
	define( 'TBZ_WC_THEPEER_MAIN_FILE', __FILE__ );
}

if ( ! defined( 'TBZ_WC_THEPEER_URL' ) ) {
	define( 'TBZ_WC_THEPEER_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
}

if ( ! defined( 'TBZ_WC_THEPEER_VERSION' ) ) {
	define( 'TBZ_WC_THEPEER_VERSION', '1.0.0' );
}

/**
 * Initialize Thepeer WooCommerce payment gateway.
 */
function tbz_wc_thepeer_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once dirname( __FILE__ ) . '/includes/class-wc-thepeer-gateway.php';

	add_filter( 'woocommerce_payment_gateways', 'tbz_wc_add_thepeer_gateway' );

}
add_action( 'plugins_loaded', 'tbz_wc_thepeer_init' );

/**
 * Add Settings link to the plugin entry in the plugins menu
 **/
function tbz_wc_thepeer_plugin_action_links( $links ) {

	$settings_link = array(
		'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=thepeer' ) . '" title="View Settings">Settings</a>'
	);

	return array_merge( $settings_link, $links );

}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'tbz_wc_thepeer_plugin_action_links' );


/**
 * Add Thepeer Gateway to WC
 **/
function tbz_wc_add_thepeer_gateway( $methods ) {

	$methods[] = 'WC_ThePeer_Gateway';

	return $methods;

}

/**
 * Display the test mode notice
 **/
function tbz_wc_thepeer_test_mode_notice(){

	$settings = get_option( 'woocommerce_thepeer_settings' );

	$test_mode = isset( $settings['test_mode'] ) ? $settings['test_mode'] : '';

	if ( 'yes' === $test_mode ) {
		/* translators: 1. Thepeer settings page URL link. */
		echo '<div class="error"><p>' . sprintf( __( 'Thepeer test mode is still enabled, Click <strong><a href="%s">here</a></strong> to disable it when you want to start accepting live payment on your site.', 'thepeer-checkout' ), esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=thepeer' ) ) ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'tbz_wc_thepeer_test_mode_notice' );
