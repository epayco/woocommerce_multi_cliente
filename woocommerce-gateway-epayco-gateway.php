<?php
/**
 * Plugin Name: Epayco Payment Gateway
 * Description: Epayco payment Gateway for WooCommerce
 * Version: 5.x
 * Author: Epayco
 * Author URI: http://epayco.co
 * License: LGPL 3.0
 * Text Domain: epayco
 * Domain Path: /lang
 */

add_action('plugins_loaded', 'woocommerce_epayco_gateway_init', 0);
function woocommerce_epayco_gateway_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;
    include_once('includes/class-woocommerce-epayco-gateway.php');
    add_filter('woocommerce_payment_gateways', 'woocommerce_epayco_gateway_add_gateway');
}

function woocommerce_epayco_gateway_add_gateway($methods)
{
    $methods[] = 'WC_Gateway_Epayco_gateway';
    return $methods;
}
