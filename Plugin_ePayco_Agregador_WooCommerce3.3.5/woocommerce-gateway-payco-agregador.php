<?php
/**
 * @since             1.0.0
 * @package           ePaycoagregador_Woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       ePayco Agregador WooCommerce
 * Description:       Plugin ePayco WooCommerce.
 * Version:           3.0.0
 * Author:            ePayco
 * Author URI:        http://epayco.co
 *Lice
 * Text Domain:       epayco-woocommerce
 * Domain Path:       /languages
 */

if (!defined('WPINC')) {
    die;
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    add_action('plugins_loaded', 'init_epaycoagregador_woocommerce', 0);

    function init_epaycoagregador_woocommerce()
    {

        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        class WC_ePaycoagregador extends WC_Payment_Gateway
        {
            public function __construct()
            {
                $this->id = 'epaycoagregador';
                $this->icon = plugins_url('assets/images/epayco.png', __FILE__);
                $this->method_title = __('ePayco Checkout', 'epaycoagregador_woocommerce');
                $this->method_description = __('Acepta tarjetas de credito, depositos y transferencias.', 'epaycoagregador_woocommerce');
                $this->order_button_text = __('Pagar', 'epaycoagregador_woocommerce');
                $this->has_fields = false;
                $this->supports = array('products');

                $this->init_form_fields();
                $this->init_settings();

                $this->msg['message']   = "";
                $this->msg['class']     = "";

                $this->title = $this->get_option('epaycoagregador_title');
                $this->epayco_customerid = $this->get_option('epaycoagregador_customerid');
                $this->epayco_secretkey = $this->get_option('epaycoagregador_secretkey');
                $this->epayco_publickey = $this->get_option('epaycoagregador_publickey');
                $this->epayco_description = $this->get_option('epaycoagregador_description');
                $this->epayco_testmode = $this->get_option('epaycoagregador_testmode');
                $this->epayco_type_checkout=$this->get_option('epaycoagregador_type_checkout');
                $this->epayco_endorder_state=$this->get_option('epaycoagregador_endorder_state');
                $this->epayco_url_response=$this->get_option('epaycoagregador_url_response');
                $this->epayco_url_confirmation=$this->get_option('epaycoagregador_url_confirmation');
                $this->epayco_lang=$this->get_option('epaycoagregador_lang')?$this->get_option('epaycoagregador_lang'):'es';


                add_filter('woocommerce_thankyou_order_received_text', array(&$this, 'order_received_message'), 10, 2 );
                add_action('ePayco_Agregador_init', array( $this, 'ePayco_successful_request'));
                add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
                add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_ePayco_response' ) );
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('wp_ajax_nopriv_returndata',array($this,'datareturnepayco_ajax'));
                if ($this->epayco_testmode == "yes") {
                    if (class_exists('WC_Logger')) {
                        $this->log = new WC_Logger();
                    } else {
                        $this->log = WC_ePaycoagregador::woocommerce_instance()->logger();
                    }
                }
            }

            function order_received_message( $text, $order ) {
                if(!empty($_GET['msg'])){
                    return $text .' '.$_GET['msg'];
                }
                return $text;
            }

            public function is_valid_for_use()
            {
                return in_array(get_woocommerce_currency(), array('COP', 'USD'));
            }

            public function admin_options()
            {
                ?>
                <style>
                    tbody{

                    }

                    .epayco-table tr:not(:first-child) {
                        border-top: 1px solid #ededed;
                    }

                    .epayco-table tr th{
                            padding-left: 15px;
                            text-align: -webkit-right;
                    }

                    .epayco-table input[type="text"]{
                            padding: 8px 13px!important;
                            border-radius: 3px;
                            width: 100%!important;
                    }
                    .epayco-table .description{
                        color: #afaeae;
                    }
                    .epayco-table select{
                            padding: 8px 13px!important;
                            border-radius: 3px;
                            width: 100%!important;
                            height: 37px;
                    }
                    .epayco-required::before{
                        content: '* ';
                        font-size: 16px;
                        color: #F00;
                        font-weight: bold;
                    }
                </style>
                <div class="container-fluid">
                    <div class="panel panel-default" style="">
                        <img  src="https://369969691f476073508a-60bf0867add971908d4f26a64519c2aa.ssl.cf5.rackcdn.com/logos/logo_epayco_200px.png">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-pencil"></i>Configuración <?php _e('ePayco', 'epaycoagregador_woocommerce'); ?></h3>
                        </div>
                        <div style ="color: #31708f; background-color: #d9edf7; border-color: #bce8f1;padding: 10px;border-radius: 5px;">
                            <b>Este modulo le permite aceptar pagos seguros por la plataforma de pagos ePayco</b>
                            <br>Si el cliente decide pagar por ePayco, el estado del pedido cambiara a ePayco Esperando Pago
                            <br>Cuando el pago sea Aceptado o Rechazado ePayco envia una configuracion a la tienda para cambiar el estado del pedido.
                        </div>
                        <div class="panel-body" style="padding: 15px 0;background: #fff;margin-top: 15px;border-radius: 5px;border: 1px solid #dcdcdc;border-top: 1px solid #dcdcdc;">
                                <table class="form-table epayco-table">
                                <?php
                                    if ($this->is_valid_for_use()) :
                                        $this->generate_settings_html();
                                    else :
                                        if ( is_admin() && ! defined( 'DOING_AJAX')) {
                                            echo '<div class="error"><p><strong>' . __( 'ePayco: Requiere que la moneda sea USD O COP', 'epayco-woocommerce' ) . '</strong>: ' . sprintf(__('%s', 'woocommerce-mercadopago' ), '<a href="' . admin_url() . 'admin.php?page=wc-settings&tab=general#s2id_woocommerce_currency">' . __( 'Click aquí para configurar!', 'epaycoagregador_woocommerce') . '</a>' ) . '</p></div>';
                                        }
                                    endif;
                                ?>
                                </table>
                        </div>
                    </div>
                </div>
                <?php
            }

            public function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Habilitar/Deshabilitar', 'epaycoagregador_woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Habilitar ePayco Checkout', 'epaycoagregador_woocommerce'),
                        'default' => 'yes'
                    ),

                    'epaycoagregador_title' => array(
                        'title' => __('<span class="epayco-required">Título</span>', 'epaycoagregador_woocommerce'),
                        'type' => 'text',
                        'description' => __('Corresponde al titulo que el usuario ve durante el checkout.', 'epaycoagregador_woocommerce'),
                        'default' => __('Checkout ePayco (Tarjetas de crédito,debito,efectivo)', 'epaycoagregador_woocommerce'),
                        //'desc_tip' => true,
                    ),

                    'epaycoagregador_description' => array(
                        'title' => __('<span class="epayco-required">Descripción</span>', 'epaycoagregador_woocommerce'),
                        'type' => 'textarea',
                        'description' => __('Corresponde a la descripción que verá el usuaro durante el checkout', 'epaycoagregador_woocommerce'),
                        'default' => __('Checkout ePayco (Tarjetas de crédito,debito,efectivo)', 'epaycoagregador_woocommerce'),
                        //'desc_tip' => true,
                    ),

                    'epaycoagregador_customerid' => array(
                        'title' => __('<span class="epayco-required">P_CUST_ID_CLIENTE</span>', 'epaycoagregador_woocommerce'),
                        'type' => 'text',
                        'description' => __('ID de cliente que lo identifica en ePayco. Lo puede encontrar en su panel de clientes en la opción configuración.', 'epaycoagregador_woocommerce'),
                        'default' => '',
                        //'desc_tip' => true,
                        'placeholder' => '',
                    ),

                    'epaycoagregador_secretkey' => array(
                        'title' => __('<span class="epayco-required">P_KEY</span>', 'epaycoagregador_woocommerce'),
                        'type' => 'text',
                        'description' => __('LLave para firmar la información enviada y recibida de ePayco. Lo puede encontrar en su panel de clientes en la opción configuración.', 'epaycoagregador_woocommerce'),
                        'default' => '',
                        'placeholder' => ''
                    ),

                    'epaycoagregador_publickey' => array(
                        'title' => __('<span class="epayco-required">PUBLIC_KEY</span>', 'epaycoagregador_woocommerce'),
                        'type' => 'text',
                        'description' => __('LLave para autenticar y consumir los servicios de ePayco, Proporcionado en su panel de clientes en la opción configuración.', 'epaycoagregador_woocommerce'),
                        'default' => '',
                        'placeholder' => ''
                    ),

                    'epaycoagregador_testmode' => array(
                        'title' => __('Sitio en pruebas', 'epaycoagregador_woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Habilitar el modo de pruebas', 'epaycoagregador_woocommerce'),
                        'description' => __('Habilite para realizar pruebas', 'epaycoagregador_woocommerce'),
                        'default' => 'no',
                    ),

                    'epaycoagregador_type_checkout' => array(
                        'title' => __('Tipo Checkout', 'epaycoagregador_woocommerce'),
                        'type' => 'select',
                        'label' => __('Seleccione un tipo de Checkout:', 'epaycoagregador_woocommerce'),
                        'description' => __('(Onpage Checkout, el usuario al pagar permanece en el sitio) ó (Standart Checkout, el usario al pagar es redireccionado a la pasarela de ePayco)', 'epaycoagregador_woocommerce'),
                        'options' => array('false'=>"Onpage Checkout","true"=>"Standart Checkout"),
                         'css' =>'line-height: inherit',
                    ),

                    'epaycoagregador_endorder_state' => array(
                        'title' => __('Estado Final del Pedido', 'epaycoagregador_woocommerce'),
                        'type' => 'select',
                        'description' => __('Seleccione el estado del pedido que se aplicará a la hora de aceptar y confirmar el pago de la orden', 'epaycoagregador_woocommerce'),
                        'options' => array('processing'=>"Procesando","completed"=>"Completado"),
                         'css' =>'line-height: inherit',
                    ),

                    'epaycoagregador_url_response' => array(
                        'title' => __('Página de Respuesta', 'epaycoagregador_woocommerce'),
                        'type' => 'select',
                        'description' => __('Url de la tienda donde se redirecciona al usuario luego de pagar el pedido', 'epaycoagregador_woocommerce'),
                        'options'       => $this->get_pages(__('Seleccionar pagina', 'payco-woocommerce')),
                         'css' =>'line-height: inherit',
                    ),

                    'epaycoagregador_url_confirmation' => array(
                        'title' => __('Página de Confirmación', 'epaycoagregador_woocommerce'),
                        'type' => 'select',
                        'description' => __('Url de la tienda donde ePayco confirma el pago', 'epaycoagregador_woocommerce'),
                        'options'       => $this->get_pages(__('Seleccionar pagina', 'payco-woocommerce')),
                         'css' =>'line-height: inherit',
                    ),

                    'epaycoagregador_lang' => array(
                        'title' => __('Idioma del Checkout', 'epaycoagregador_woocommerce'),
                        'type' => 'select',
                        'description' => __('Seleccione el idioma del checkout', 'epaycoagregador_woocommerce'),
                        'options' => array('es'=>"Español","en"=>"Inglés"),
                         'css' =>'line-height: inherit',
                    ),

                );
            }

            /**
             * @param $order_id
             * @return array
             */
            public function process_payment($order_id)
            {
                
                $order = new WC_Order($order_id);

                $order->reduce_order_stock();
                
                if (version_compare( WOOCOMMERCE_VERSION, '2.1', '>=')) {
                    return array(
                        'result'    => 'success',
                        'redirect'  => add_query_arg('order-pay', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
                    );
                } else {
                    return array(
                        'result'    => 'success',
                        'redirect'  => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
                    );
                }

            }

            function get_pages($title = false, $indent = true) {

                $wp_pages = get_pages('sort_column=menu_order');
                $page_list = array();
                if ($title) $page_list[] = $title;
                foreach ($wp_pages as $page) {
                    $prefix = '';
                    // show indented child pages?
                    if ($indent) {
                        $has_parent = $page->post_parent;
                        while($has_parent) {
                            $prefix .=  ' - ';
                            $next_page = get_page($has_parent);
                            $has_parent = $next_page->post_parent;
                        }
                    }
                    // add to page list array array
                    $page_list[$page->ID] = $prefix . $page->post_title;
                }
                return $page_list;
            }
      


            /**
             * @param $order_id
             */
            public function receipt_page($order_id)
            {
                global $woocommerce;
                $order = new WC_Order($order_id);

                $descripcionParts = array();
                foreach ($order->get_items() as $product) {
                    $descripcionParts[] = $this->string_sanitize($product['name']);
                }

                $descripcion = implode(' - ', $descripcionParts);
                $currency = get_woocommerce_currency();
                $testMode = $this->epayco_testmode == "yes" ? "true" : "false";
                $basedCountry = WC()->countries->get_base_country();
                $external=$this->epayco_type_checkout;
                

                $redirect_url =get_site_url() . "/";
                $confirm_url=get_site_url() . "/";
              
                $redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
                $redirect_url = add_query_arg( 'order_id', $order_id, $redirect_url );

                $confirm_url = add_query_arg( 'wc-api', get_class( $this ), $confirm_url );
                $confirm_url = add_query_arg( 'order_id', $order_id, $confirm_url );
                $confirm_url = $redirect_url.'&confirmation=1';
                //$confirm_url = add_query_arg( 'confirmation', 1 );

                $name_billing=$order->get_billing_first_name().' '.$order->get_billing_last_name();
                $address_billing=$order->get_billing_address_1();
                $phone_billing=@$order->billing_phone;
                $email_billing=@$order->billing_email;
                

                $order = new WC_Order($order_id);

                $tax=$order->get_total_tax();
                $tax=round($tax,2);

                if((int)$tax>0){
                    $base_tax=$order->get_total()-$tax;
                }else{
                    $base_tax=0;
                    $tax=0;
                }

                 echo sprintf('
                    <p style="text-align: center;">
                       Enviando a transacción de pago... si el pedido no se envia automaticamente de click en el botón "Pagar con ePayco"
                    </p>
                    <form>
                        <script src="https://checkout.epayco.co/checkout.js"
                            class="epayco-button"
                            data-epayco-key="%s"
                            data-epayco-amount="%s"
                            data-epayco-tax="%s"
                            data-epayco-tax-base="%s"    
                            data-epayco-name="%s"
                            data-epayco-description="%s"
                            data-epayco-currency="%s"
                            data-epayco-invoice="%s"
                            data-epayco-country="%s"
                            data-epayco-test="%s"
                            data-epayco-external="%s"
                            data-epayco-response="%s" 
                            data-epayco-confirmation="%s"
                            data-epayco-email-billing="%s"
                            data-epayco-name-billing="%s"
                            data-epayco-address-billing="%s"
                            data-epayco-lang="%s"
                           data-epayco-mobilephone-billing="%s"
                            >
                        </script>
                    </form>
                ',$this->epayco_publickey, $order->get_total(),$tax,$base_tax, $descripcion, $descripcion, $currency, $order->get_id(), $basedCountry, $testMode, $external, $redirect_url,$confirm_url,
                    $email_billing,$name_billing,$address_billing,$this->epayco_lang,$phone_billing);
                   
                    $messageload = __('Espere por favor..Cargando checkout.','payco-woocommerce');
                    $js = "if(jQuery('button.epayco-button-render').length)    
                {
                jQuery('button.epayco-button-render').css('margin','auto');
                jQuery('button.epayco-button-render').css('display','block');
                }
                    setTimeout(function(){ 
                       document.getElementsByClassName('epayco-button-render' )[0].click();
                    }, 2500);
                ";

                if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')){
                    wc_enqueue_js($js);
                }else{
                    $woocommerce->add_inline_js($js);
                }
            }
            public function datareturnepayco_ajax()
            {
                die();
            }
            public function block($message)
            {
                return 'jQuery("body").block({
                        message: "' . esc_js($message) . '",
                        baseZ: 99999,
                        overlayCSS:
                        {
                            background: "#000",
                            opacity: "0.6",
                        },
                        css: {
                            padding:        "20px",
                            zindex:         "9999999",
                            textAlign:      "center",
                            color:          "#555",
                            border:         "1px solid #aaa",
                            backgroundColor:"#fff",
                            cursor:         "wait",
                            lineHeight:     "24px",
                        }
                    });';
            }

            public function agafa_dades($url) {
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    $timeout = 5;
                    $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                    curl_setopt($ch,CURLOPT_TIMEOUT,$timeout);
                    curl_setopt($ch,CURLOPT_MAXREDIRS,10);
                    $data = curl_exec($ch);
                    curl_close($ch);
                    return $data;
                }else{
                    $data =  @file_get_contents($url);
                    return $data;
                }
            }
            public function goter(){
                $context = stream_context_create(array(
                    'http' => array(
                        'method' => 'POST',
                        'header' => 'Content-Type: application/x-www-form-urlencoded',
                        'protocol_version' => 1.1,
                        'timeout' => 10,
                        'ignore_errors' => true
                    )
                ));
            }

            function check_ePayco_response(){
                @ob_clean();
                if ( ! empty( $_REQUEST ) ) {
                    header( 'HTTP/1.1 200 OK' );
                    do_action( "ePayco_Agregador_init", $_REQUEST );
                } else {
                    wp_die( __("ePayco Request Failure", 'epayco-woocommerce') );
                }
            }

            /**
             * @param $validationData
             */
            function ePayco_successful_request($validationData)
            {
               

                    global $woocommerce;
                    $order_id="";
                    $ref_payco="";
                    $signature="";

                    if(isset($_REQUEST['x_signature'])){
                        $explode=explode('?',$_GET['order_id']);
                        $order_id=$explode[0];
                        //$order_id=$_REQUEST['order_id'];
                        $ref_payco=$_REQUEST['x_ref_payco'];
                    }else{
                        $explode=explode('?',$_GET['order_id']);
                        $explode2=explode('?',$_GET['ref_payco']);
                        $order_id=$explode[0];
                            $strref_payco=explode("=",$explode[1]);
                            $ref_payco=$strref_payco[1];
                     if ( !$ref_payco) {
                        $ref_payco=$explode2[0];
                        var_dump("expression4",$ref_payco);
                     }              
           var_dump("expression2",$ref_payco,$explode,$explode2,$strref_payco[1]);
          
                            $message = __('Esperando respuesta por parte del servidor.','payco-woocommerce');
                            $js = $this->block($message);
                            $url = 'https://secure.epayco.co/validation/v1/reference/'.$ref_payco;
                            $responseData = $this->agafa_dades($url,false,$this->goter());
                            $jsonData = @json_decode($responseData, true);
                            $validationData = $jsonData['data'];
                           $ref_payco = $validationData['x_ref_payco'];
                    }
                                              var_dump("validar if ref_payco es null");

                                if (  $ref_payco == "NULL") {
                                    var_dump("es null");
                                    die();
                                     $order = new WC_Order($order_id);
                                   $message = 'Pago rechazado';
                                $messageClass = 'woocommerce-error';
                                $order->update_status('epayco-failed');
                                $order->add_order_note('Pago fallido');                       
                        if ($this->get_option('epayco_url_response_sub' ) == 0) {
                            $redirect_url = $order->get_checkout_order_received_url();
                        }else{
                            $woocommerce->cart->empty_cart();
                            $redirect_url = get_permalink($this->get_option('epayco_url_response_sub'));
                        }
                               $arguments=array();
                    foreach ($validationData as $key => $value) {
                        $arguments[$key]=$value;
                    }
                    unset($arguments["wc-api"]);
                    $arguments['msg']=urlencode($message);
                    $arguments['type']=$messageClass;
                    $redirect_url = add_query_arg($arguments , $redirect_url );
                    wp_redirect($redirect_url);
                    die();
                                }
                    //Validamos la firma
                                var_dump("validar firma",$ref_payco);

                    //Validamos la firma
                    if ($order_id!="" && $ref_payco!="") {
                        $order = new WC_Order($order_id);
                        $signature = hash('sha256',
                            $this->epayco_customerid.'^'
                            .$this->epayco_secretkey.'^'
                            .$validationData['x_ref_payco'].'^'
                            .$validationData['x_transaction_id'].'^'
                            .$validationData['x_amount'].'^'
                            .$validationData['x_currency_code']
                        );
                    }
                    
                    $message = '';
                    $messageClass = '';
var_dump($signature,$validationData['x_signature']);

                    if($signature == $validationData['x_signature']){
                        
                        switch ((int)$validationData['x_cod_response']) {
                            case 1:{
                                $message = 'Pago exitoso';
                                $messageClass = 'woocommerce-message';
                                $order->payment_complete($validationData['x_ref_payco']);
                                $order->update_status($this->epayco_endorder_state);
                                $order->add_order_note('Pago exitoso');
                                
                            }break;
                            case 2: {
                                $message = 'Pago rechazado';
                                $messageClass = 'woocommerce-error';
                                $order->update_status('failed');
                                $order->add_order_note('Pago fallido');
                                $this->restore_order_stock($order->id);
                            }break;
                            case 3:{
                                $message = 'Pago pendiente de aprobación';
                                $messageClass = 'woocommerce-info';
                                $order->update_status('on-hold');
                                $order->add_order_note('Pago pendiente');
                            }break;
                            case 4:{
                                $message = 'Pago fallido';
                                $messageClass = 'woocommerce-error';
                                $order->update_status('failed');
                                $order->add_order_note('Pago fallido');
                                $this->restore_order_stock($order->id);
                            }break;
                            default:{
                                $message = 'Pago '.$_REQUEST['x_transaction_state'];
                                $messageClass = 'woocommerce-error';
                                $order->update_status('failed');
                                $order->add_order_note($message);
                                $this->restore_order_stock($order->id);
                            }break;

                        }
                    }else {
                        $message = 'Firma no valida';
                        $messageClass = 'error';
                        $order->update_status('failed');
                        $order->add_order_note('Failed');
                        $this->restore_order_stock($order_id);
                    }

                    
                    if (isset($_REQUEST['confirmation'])) {
                        $redirect_url = get_permalink($this->get_option('epaycoagregador_url_confirmation'));

                        if ($this->get_option('epaycoagregador_url_confirmation' ) == 0) {
                            echo "ok";
                            die();
                        }
                    }else{

                        if ($this->get_option('epaycoagregador_url_response' ) == 0) {
                            $redirect_url = $order->get_checkout_order_received_url();
                        }else{
                            $woocommerce->cart->empty_cart();
                            $redirect_url = get_permalink($this->get_option('epaycoagregador_url_response'));
                        }
                    }


                    $arguments=array();

                    foreach ($validationData as $key => $value) {
                        $arguments[$key]=$value;
                    }
                    unset($arguments["wc-api"]);

                    $arguments['msg']=urlencode($message);
                    $arguments['type']=$messageClass;
                    $redirect_url = add_query_arg($arguments , $redirect_url );

                    wp_redirect($redirect_url);
                    die();
            }

            /**
             * @param $order_id
             */
            public function restore_order_stock($order_id)
            {
                $order = new WC_Order($order_id);
                if (!get_option('woocommerce_manage_stock') == 'yes' && !sizeof($order->get_items()) > 0) {
                    return;
                }
                foreach ($order->get_items() as $item) {
                    if ($item['product_id'] > 0) {
                        $_product = $order->get_product_from_item($item);
                        if ($_product && $_product->exists() && $_product->managing_stock()) {
                            $old_stock = $_product->stock;
                            $qty = apply_filters('woocommerce_order_item_quantity', $item['qty'], $this, $item);
                            $new_quantity = $_product->increase_stock($qty);
                            do_action('woocommerce_auto_stock_restored', $_product, $item);
                            $order->add_order_note(sprintf(__('Item #%s stock incremented from %s to %s.', 'woocommerce'), $item['product_id'], $old_stock, $new_quantity));
                            $order->send_stock_notifications($_product, $new_quantity, $item['qty']);
                        }
                    }
                }
            }

            public function string_sanitize($string, $force_lowercase = true, $anal = false) {

                $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
                               "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
                               "â€”", "â€“", ",", "<", ".", ">", "/", "?");
                $clean = trim(str_replace($strip, "", strip_tags($string)));
                $clean = preg_replace('/\s+/', "_", $clean);
                $clean = ($anal) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean ;
                return $clean;
            }

            public function getTaxesOrder($order){
                
                $taxes=($order->get_taxes());
                $tax=0;
                foreach($taxes as $tax){
                    $itemtax=$tax['item_meta']['tax_amount'][0];
                    //var_dump($itemtax);
                }
                return $itemtax;
            }

        }
        
        /**
         * @param $methods
         * @return array
         */
        function woocommerce_epaycoagregador_add_gateway($methods)
        {
            $methods[] = 'WC_ePaycoagregador';
            return $methods;
        }
        add_filter('woocommerce_payment_gateways', 'woocommerce_epaycoagregador_add_gateway');

        function epaycoagregador_woocommerce_addon_settings_link( $links ) {
            array_push( $links, '<a href="admin.php?page=wc-settings&tab=checkout&section=epaycoagregador">' . __( 'Configuración' ) . '</a>' );
            return $links;
        }
        add_filter( "plugin_action_links_".plugin_basename( __FILE__ ),'epaycoagregador_woocommerce_addon_settings_link' );
    }
}