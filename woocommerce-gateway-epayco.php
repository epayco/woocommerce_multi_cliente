<?php
/**
 * Plugin Name: Epayco Payment
 * Description: Epayco payment for WooCommerce
 * Version: 5.x
 * Author: Epayco
 * Author URI: http://epayco.co
 * License: LGPL 3.0
 * Text Domain: epayco
 * Domain Path: /lang
 */

add_action('plugins_loaded', 'woocommerce_epayco_init', 0);
function woocommerce_epayco_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;
    include_once('lib/EpaycoOrderAgregador.php');
    include_once('includes/class-woocommerce-epayco.php');
    add_filter('woocommerce_payment_gateways', 'woocommerce_epayco_add_gateway');
    add_action('plugins_loaded', 'epayco_agregador_multitienda_update_db_check');
}

function woocommerce_epayco_add_gateway($methods)
{
    $methods[] = 'WC_Gateway_Epayco';
    return $methods;
}

//Actualizaci贸n de versi贸n
global $epayco_multitienda_db_version;
    $epayco_multitienda_db_version = '1.0';

//Verificar si la version de la base de datos esta actualizada
function epayco_agregador_multitienda_update_db_check()
    {
        global $epayco_agregador_multitienda_db_version;
        $installed_ver = get_option('epayco_agregador_multitienda_db_version');
        if ($installed_ver == null || $installed_ver != $epayco_agregador_multitienda_db_version) {
            EpaycoOrderAgregador::setup();
            update_option('epayco_agregador_multitienda_db_version', $epayco_agregador_multitienda_db_version);
        }
    }  