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
    add_action( 'plugins_loaded', 'register_epayco_agregador_order_status' );
    add_filter( 'wc_order_statuses', 'add_epayco_agregador_to_order_statuses' );
    add_action('admin_head', 'styling_admin_order_list_epayco' );
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

function register_epayco_agregador_order_status() {
        register_post_status( 'wc-epayco-failed', array(
            'label'                     => 'ePayco Pago Fallido',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'ePayco Pago Fallido <span class="count">(%s)</span>', 'ePayco Pago Fallido <span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-epayco_failed', array(
            'label'                     => 'ePayco Pago Fallido Prueba',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'ePayco Pago Fallido Prueba <span class="count">(%s)</span>', 'ePayco Pago Fallido Prueba <span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-epayco-cancelled', array(
            'label'                     => 'ePayco Pago Cancelado',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'ePayco Pago Cancelado <span class="count">(%s)</span>', 'ePayco Pago Cancelado <span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-epayco_cancelled', array(
            'label'                     => 'ePayco Pago Cancelado Prueba',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'ePayco Pago Cancelado Prueba <span class="count">(%s)</span>', 'ePayco Pago Cancelado Prueba <span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-epayco-on-hold', array(
            'label'                     => 'ePayco Pago Pendiente',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'ePayco Pago Pendiente <span class="count">(%s)</span>', 'ePayco Pago Pendiente <span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-epayco_on_hold', array(
            'label'                     => 'ePayco Pago Pendiente Prueba',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'ePayco Pago Pendiente Prueba <span class="count">(%s)</span>', 'ePayco Pago Pendiente Prueba <span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-epayco-processing', array(
            'label'                     => 'ePayco Procesando Pago',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'ePayco Procesando Pago <span class="count">(%s)</span>', 'ePayco Procesando Pago <span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-epayco_processing', array(
            'label'                     => 'ePayco Procesando Pago Prueba',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'ePayco Procesando Pago Prueba<span class="count">(%s)</span>', 'ePayco Procesando Pago Prueba<span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-processing', array(
            'label'                     => 'Procesando',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'Procesando<span class="count">(%s)</span>', 'Procesando<span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-processing_test', array(
            'label'                     => 'Procesando Prueba',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'Procesando Prueba<span class="count">(%s)</span>', 'Procesando Prueba<span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-epayco-completed', array(
            'label'                     => 'ePayco Pago Completado',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'ePayco Pago Completado <span class="count">(%s)</span>', 'ePayco Pago Completado <span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-epayco_completed', array(
            'label'                     => 'ePayco Pago Completado Prueba',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'ePayco Pago Completado Prueba <span class="count">(%s)</span>', 'ePayco Pago Completado Prueba <span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-completed', array(
            'label'                     => 'Completado',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'Completado<span class="count">(%s)</span>', 'Completado<span class="count">(%s)</span>' )
        ));

        register_post_status( 'wc-completed_test', array(
            'label'                     => 'Completado Prueba',
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop( 'Completado Prueba<span class="count">(%s)</span>', 'Completado Prueba<span class="count">(%s)</span>' )
        ));
}        


function add_epayco_agregador_to_order_statuses( $order_statuses ) {
        $new_order_statuses = array();
        $epayco_agregador_order = get_option('epaycor_order_status');
        $testMode = $epayco_agregador_order == "yes" ? "true" : "false";
        foreach ( $order_statuses as $key => $status ) {
            $new_order_statuses[ $key ] = $status;
            if ( 'wc-cancelled' === $key ) {
                if($testMode=="true"){
                    $new_order_statuses['wc-epayco_cancelled'] = 'ePayco Pago Cancelado Prueba';
                }else{
                    $new_order_statuses['wc-epayco-cancelled'] = 'ePayco Pago Cancelado';
                }
            }

            if ( 'wc-failed' === $key ) {
                if($testMode=="true"){
                    $new_order_statuses['wc-epayco_failed'] = 'ePayco Pago Fallido Prueba';
                }else{
                    $new_order_statuses['wc-epayco-failed'] = 'ePayco Pago Fallido';
                }
            }

            if ( 'wc-on-hold' === $key ) {
                if($testMode=="true"){
                    $new_order_statuses['wc-epayco_on_hold'] = 'ePayco Pago Pendiente Prueba';
                }else{
                    $new_order_statuses['wc-epayco-on-hold'] = 'ePayco Pago Pendiente';
                }
            }

            if ( 'wc-processing' === $key ) {
                if($testMode=="true"){
                    $new_order_statuses['wc-epayco_processing'] = 'ePayco Pago Procesando Prueba';
                }else{
                    $new_order_statuses['wc-epayco-processing'] = 'ePayco Pago Procesando';
                }
            }else {
                if($testMode=="true"){
                    $new_order_statuses['wc-processing_test'] = 'Procesando Prueba';
                }else{
                    $new_order_statuses['wc-processing'] = 'Procesando';
                }
            }

            if ( 'wc-completed' === $key ) {
                if($testMode=="true"){
                    $new_order_statuses['wc-epayco_completed'] = 'ePayco Pago Completado Prueba';
                }else{
                    $new_order_statuses['wc-epayco-completed'] = 'ePayco Pago Completado';
                }
            }else{
                if($testMode=="true"){
                    $new_order_statuses['wc-completed_test'] = 'Completado Prueba';
                }else{
                    $new_order_statuses['wc-completed'] = 'Completado';
                }
            }
        }
    return $new_order_statuses;
}   

function styling_admin_order_list_epayco() {
        global $pagenow, $post;
        if( $pagenow != 'edit.php') return; // Exit
        if( get_post_type($post->ID) != 'shop_order' ) return; // Exit
        
        $epayco_agregador_order = get_option('epaycor_order_status');
        $testMode = $epayco_agregador_order == "yes" ? "true" : "false";
        
        if($testMode=="true"){
            $order_status_failed = 'epayco_failed';
            $order_status_on_hold = 'epayco_on_hold';
            $order_status_processing = 'epayco_processing';
            $order_status_processing_ = 'processing_test';
            $order_status_completed = 'epayco_completed';
            $order_status_cancelled = 'epayco_cancelled';
            $order_status_completed_ = 'completed_test';

        }else{
            $order_status_failed = 'epayco-failed';
            $order_status_on_hold = 'epayco-on-hold';
            $order_status_processing = 'epayco-processing';
            $order_status_processing_ = 'processing';
            $order_status_completed = 'epayco-completed';
            $order_status_cancelled = 'epayco-cancelled';
            $order_status_completed_ = 'completed';
        }
        ?>
        <style>
            .order-status.status-<?php echo sanitize_title( $order_status_failed); ?> {
                background: #eba3a3;
                color: #761919;
            }
            .order-status.status-<?php echo sanitize_title( $order_status_on_hold); ?> {
                background: #f8dda7;
                color: #94660c;
            }
            .order-status.status-<?php echo sanitize_title( $order_status_processing ); ?> {
                background: #c8d7e1;
                color: #2e4453;
            }
            .order-status.status-<?php echo sanitize_title( $order_status_processing_ ); ?> {
                background: #c8d7e1;
                color: #2e4453;
            }
            .order-status.status-<?php echo sanitize_title( $order_status_completed ); ?> {
                background: #d7f8a7;
                color: #0c942b;
            }
            .order-status.status-<?php echo sanitize_title( $order_status_completed_ ); ?> {
                background: #d7f8a7;
                color: #0c942b;
            }
            .order-status.status-<?php echo sanitize_title( $order_status_cancelled); ?> {
                background: #eba3a3;
                color: #761919;
            }
        </style>

        <?php    
}            