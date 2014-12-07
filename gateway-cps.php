<?php
/*
	Plugin Name: WooCommerce Cornerstone Payment Systems / Solidstone Gateway
	Plugin URI: http://design.solidrock-ent.com/
	Description: A payment gateway for Cornerstone Payment Systems dashboard gateway (Solidstone project).
	Version: 1.0
	Author: Timothy Snowden (based on Woo code)
	Author URI: http://design.solidrock-ent.com/
*/

add_action( 'plugins_loaded', 'woocommerce_CPS_init', 0 );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_CPS_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	require_once( plugin_basename( 'classes/cps.class.php' ) );

	add_filter('woocommerce_payment_gateways', 'woocommerce_CPS_add_gateway' );
} // End woocommerce_CPS_init()

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */
function woocommerce_CPS_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_CPS';
	return $methods;
} // End woocommerce_CPS_add_gateway()