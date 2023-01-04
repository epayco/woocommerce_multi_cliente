<?php
use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
class WC_Gateway_Epayco extends WC_Payment_Gateway
{
    private $pluginVersion = '4.9.1';
    private $epayco_feedback;
    private $sandbox;
    private $enable_for_shipping;

    function __construct()
    {
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();
        $this->title               = $this->get_option('title');
        $this->description         = $this->get_option('description');
        $this->epayco_feedback       = $this->get_option('epayco_feedback', true);
        $this->epayco_customerid = $this->get_option('epayco_customerid');
        $this->epayco_secretkey = $this->get_option('epayco_secretkey');
        $this->epayco_publickey = $this->get_option('epayco_publickey');
        $this->epayco_description = $this->get_option('epayco_description');
        $this->epayco_testmode = $this->get_option('epayco_testmode');
        $this->epayco_lang = $this->get_option('epayco_lang');
        $this->epayco_type_checkout = $this->get_option('epayco_type_checkout');
        $this->epayco_endorder_state = $this->get_option('epayco_endorder_state');
        $this->custom_order_numbers_enabled = $this->get_option( 'alg_wc_custom_order_numbers_enabled');
        $this->alg_wc_custom_order_numbers_prefix = $this->get_option( 'alg_wc_custom_order_numbers_prefix');
        
        
         $custom_order_numbers_enabled = $this->custom_order_numbers_enabled == "yes" ? "true" : "false";
                if ( 'true' == $custom_order_numbers_enabled ) {
                    add_action( 'woocommerce_new_order', array( $this, 'add_new_order_number' ), 11 );
                    add_filter( 'woocommerce_order_number', array( $this, 'display_order_number' ), PHP_INT_MAX, 2 );
                    add_action( 'admin_notices', array( $this, 'alg_custom_order_numbers_update_admin_notice' ) );
                    add_action( 'admin_notices', array( $this, 'alg_custom_order_numbers_update_success_notice' ) );
                    // Add a recurring As action.
                    add_action( 'admin_init', array( $this, 'alg_custom_order_numbers_add_recurring_action' ) );
                    add_action( 'admin_init', array( $this, 'alg_custom_order_numbers_stop_recurring_action' ) );
                    add_action( 'alg_custom_order_numbers_update_old_custom_order_numbers', array( $this, 'alg_custom_order_numbers_update_old_custom_order_numbers_callback' ) );
                    // Include JS script for the notice.
                    add_action( 'admin_enqueue_scripts', array( $this, 'alg_custom_order_numbers_setting_script' ) );
                    add_action( 'wp_ajax_alg_custom_order_numbers_admin_notice_dismiss', array( $this, 'alg_custom_order_numbers_admin_notice_dismiss' ) );
                    add_action( 'woocommerce_settings_save_alg_wc_custom_order_numbers', array( $this, 'woocommerce_settings_save_alg_wc_custom_order_numbers_callback' ), PHP_INT_MAX );
                    add_action( 'woocommerce_shop_order_search_fields', array( $this, 'search_by_custom_number' ) );
                    //add_action( 'admin_menu', array( $this, 'add_renumerate_orders_tool' ), PHP_INT_MAX );
                    if ( 'yes' === apply_filters( 'alg_wc_custom_order_numbers', 'no', 'manual_counter_value' ) ) {
                        add_action( 'add_meta_boxes', array( $this, 'add_order_number_meta_box' ) );
                        add_action( 'save_post_shop_order', array( $this, 'save_order_number_meta_box' ), PHP_INT_MAX, 2 );
                    }

                    // check if subscriptions is enabled.
                    if ( in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
                        add_action( 'woocommerce_checkout_subscription_created', array( $this, 'update_custom_order_meta' ), PHP_INT_MAX, 1 );
                        add_filter( 'wcs_renewal_order_created', array( $this, 'remove_order_meta_renewal' ), PHP_INT_MAX, 2 );
                        // To unset the CON meta key at the time of renewal of subscription, so that renewal orders don't have duplicate order numbers.
                        add_filter( 'wcs_renewal_order_meta', array( $this, 'remove_con_metakey_in_wcs_order_meta' ), 10, 3 );
                    }
                    add_filter( 'pre_update_option_alg_wc_custom_order_numbers_prefix', array( $this, 'pre_alg_wc_custom_order_numbers_prefix' ), 10, 2 );
                    add_action( 'admin_init', array( $this, 'alg_custom_order_number_old_orders_without_meta_key' ) );
                    add_action( 'admin_init', array( $this, 'alg_custom_order_numbers_add_recurring_action_to_add_meta_key' ) );
                    add_action( 'alg_custom_order_numbers_update_meta_key_in_old_con', array( $this, 'alg_custom_order_numbers_update_meta_key_in_old_con_callback' ) );
                    add_action( 'wp_ajax_alg_custom_order_numbers_admin_meta_key_notice_dismiss', array( $this, 'alg_custom_order_numbers_admin_meta_key_notice_dismiss' ) );

                }
        
        // Saving hook
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        // Payment listener/API hook
       // add_action('woocommerce_api_wc_gateway_' . $this->id, [$this, 'gateway_ipn']);
         add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_ePayco_response' ) );
        // Status change hook
        add_action('woocommerce_order_status_changed', [$this, 'change_status_action'], 10, 3);

         add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
         add_action('ePayco_init', array( $this, 'ePayco_successful_request'));
            $this->init_OpenEpayco();
            
            
            
    }

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties() {
        $this->id                 = 'epayco';
        $this->icon               = apply_filters('woocommerce_epayco_icon', plugin_dir_url(__FILE__).'/logo.png');
        $this->method_title       = __('ePayco Checkout', 'epayco');
        $this->method_description = __('Acepta tarjetas de credito, depositos y transferencias.', 'epayco');
        $this->has_fields         = false;
        $this->supports           = ['products', 'refunds'];
    }

    protected function init_OpenEpayco($currency = null)
    {
        $isSandbox = 'yes' === $this->get_option('sandbox');

        if ($this->isWpmlActiveAndConfigure())
        {
            $optionSuffix = '_' . (null !== $currency ? $currency : get_woocommerce_currency());
        } else {
            $optionSuffix = '';
        }
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
                            height: 37px!important;
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
                        <img  src="<?php echo plugin_dir_url(__FILE__).'/logo.png' ?>">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-pencil"></i>Configuración <?php _e('ePayco', 'epayco'); ?></h3>
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
                                echo '<div class="error"><p><strong>' . __( 'ePayco: Requiere que la moneda sea USD O COP', 'epayco' ) . '</strong>: ' . sprintf(__('%s', 'woocommerce-mercadopago' ), '<a href="' . admin_url() . 'admin.php?page=wc-settings&tab=general#s2id_woocommerce_currency">' . __( 'Click aquí para configurar!', 'epayco') . '</a>' ) . '</p></div>';
                                        }
                                    endif;
                                ?>
                                </table>
                        </div>
                    </div>
                </div>
                <?php
            }
            

    function init_form_fields()
    {
        global $woocommerce_wpml;

        $currencies = [];

        if ($this->isWpmlActiveAndConfigure())
        {
            $currencies = $woocommerce_wpml->multi_currency->get_currency_codes();
        }

        $this->form_fields = array_merge($this->getFormFieldsBasic(), $this->getFormFieldConfig($currencies), $this->getFormFieldInfo());
    }

    /**
     * Check If The Gateway Is Available For Use.
     * Copy from COD module
     *
     * @return bool
     */
    public function is_available()
    {
        if (!is_admin()) {
            $order = null;

            if (!WC()->cart && is_page(wc_get_page_id('checkout')) && 0 < get_query_var('order-pay')) {
                $order_id = absint(get_query_var('order-pay'));
                $order = wc_get_order($order_id);
            }
        }

        return parent::is_available();
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * Copy from COD
     *
     * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
     * @return array $canonical_rate_ids    Rate IDs in a canonical format.
     */
    private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

        $canonical_rate_ids = array();

        foreach ( $order_shipping_items as $order_shipping_item ) {
            $canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
        }

        return $canonical_rate_ids;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * Copy from COD
     *
     * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
     * @return array $canonical_rate_ids  Rate IDs in a canonical format.
     */
    private function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

        $shipping_packages  = WC()->shipping()->get_packages();
        $canonical_rate_ids = array();

        if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
            foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
                if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
                    $chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
                    $canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
                }
            }
        }

        return $canonical_rate_ids;
    }

    /**
     * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
     *
     * Copy from COD
     *
     * @param array $rate_ids Rate ids to check.
     * @return boolean
     */
    private function get_matching_rates( $rate_ids ) {
        // First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
        return array_unique( array_merge( array_intersect( $this->enable_for_shipping, $rate_ids ), array_intersect( $this->enable_for_shipping, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
    }

    function process_payment($order_id)
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


        public function receipt_page($order_id)
            {
                global $woocommerce;
                $order = new WC_Order($order_id);
                $descripcionParts = array();
                foreach ($order->get_items() as $product) {
                    $descripcionParts[] = $this->string_sanitize($product['name']);
                }
                $descripcion = implode(' - ', $descripcionParts);
                $currency = strtolower(get_woocommerce_currency());
                $basedCountry = WC()->countries->get_base_country();
                
                $redirect_url =get_site_url() . "/";
                $confirm_url=get_site_url() . "/";
              
                $redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
                $redirect_url = add_query_arg( 'order_id', $order_id, $redirect_url );
                $confirm_url = add_query_arg( 'wc-api', get_class( $this ), $confirm_url );
                $confirm_url = add_query_arg( 'order_id', $order_id, $confirm_url );
                $confirm_url = $redirect_url.'&confirmation=1';
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
                    $base_tax=$order->get_total();
                    $tax=0;
                }

                $external_type = $this->epayco_type_checkout;
          		$epayco_lang = $this->epayco_lang;
          		if ($epayco_lang == 'es') {
                    $message = '<span class="animated-points">Cargando métodos de pago</span>
                    <br>
                        <small class="epayco-subtitle"> Si no se cargan automáticamente, de clic en el botón "Pagar con ePayco"</small>';
                    $button = plugin_dir_url(__FILE__).'/Boton-color-espanol.png';
          		}else{
                    $message = '<span class="animated-points">Loading payment methods</span>
                    <br>
                        <small class="epayco-subtitle">If they are not charged automatically, click the  "Pay with ePayco" button</small>';
                    $button =plugin_dir_url(__FILE__).'/Boton-color-Ingles.png';
          		}
          		$test_mode = $this->epayco_testmode == "yes" ? "true" : "false";
          		
          		//Busca si ya se restauro el stock
                if (!EpaycoOrderAgregador::ifExist($order_id)) {
                    //si no se restauro el stock restaurarlo inmediatamente
                    EpaycoOrderAgregador::create($order_id,1);
                }
                
                $order_number_meta = get_post_meta( $order_id, '_alg_wc_full_custom_order_number', true );
                $orderId= (!empty($order->get_data()["number"]))?$order->get_data()["number"] : $order->get_id();
                               
               echo('
                    <style>
                        .epayco-title{
                            max-width: 900px;
                            display: block;
                            margin:auto;
                            color: #444;
                            font-weight: 700;
                            margin-bottom: 25px;
                        }
                        .loader-container{
                            position: relative;
                            padding: 20px;
                            color: #f0943e;
                        }
                        .epayco-subtitle{
                            font-size: 14px;
                        }
                        .epayco-button-render{
                            transition: all 500ms cubic-bezier(0.000, 0.445, 0.150, 1.025);
                            transform: scale(1.1);
                            box-shadow: 0 0 4px rgba(0,0,0,0);
                        }
                        .epayco-button-render:hover {
                            /*box-shadow: 0 0 4px rgba(0,0,0,.5);*/
                            transform: scale(1.2);
                        }
                        .animated-points::after{
                            content: "";
                            animation-duration: 2s;
                            animation-fill-mode: forwards;
                            animation-iteration-count: infinite;
                            animation-name: animatedPoints;
                            animation-timing-function: linear;
                            position: absolute;
                        }
                        .animated-background {
                            animation-duration: 2s;
                            animation-fill-mode: forwards;
                            animation-iteration-count: infinite;
                            animation-name: placeHolderShimmer;
                            animation-timing-function: linear;
                            color: #f6f7f8;
                            background: linear-gradient(to right, #7b7b7b 8%, #999 18%, #7b7b7b 33%);
                            background-size: 800px 104px;
                            position: relative;
                            background-clip: text;
                            -webkit-background-clip: text;
                            -webkit-text-fill-color: transparent;
                        }
                        
                        .loading::before{
                            -webkit-background-clip: padding-box;
                            background-clip: padding-box;
                            box-sizing: border-box;
                            border-width: 2px;
                            border-color: currentColor currentColor currentColor transparent;
                            position: absolute;
                            margin: auto;
                            top: 0;
                            left: 0;
                            right: 0;
                            bottom: 0;
                            content: " ";
                            display: inline-block;
                            background: center center no-repeat;
                            background-size: cover;
                            border-radius: 50%;
                            border-style: solid;
                            width: 30px;
                            height: 30px;
                            opacity: 1;
                            -webkit-animation: loaderAnimation 1s infinite linear,fadeIn 0.5s ease-in-out;
                            -moz-animation: loaderAnimation 1s infinite linear, fadeIn 0.5s ease-in-out;
                            animation: loaderAnimation 1s infinite linear, fadeIn 0.5s ease-in-out;
                        }
                        @keyframes animatedPoints{
                            33%{
                                content: "."
                            }
                            66%{
                                content: ".."
                            }
                            100%{
                                content: "..."
                            }
                        }
                        @keyframes placeHolderShimmer{
                            0%{
                                background-position: -800px 0
                            }
                            100%{
                                background-position: 800px 0
                            }
                        }
                        @keyframes loaderAnimation{
                            0%{
                                -webkit-transform:rotate(0);
                                transform:rotate(0);
                                animation-timing-function:cubic-bezier(.55,.055,.675,.19)
                            }
                            50%{
                                -webkit-transform:rotate(180deg);
                                transform:rotate(180deg);
                                animation-timing-function:cubic-bezier(.215,.61,.355,1)
                            }
                            100%{
                                -webkit-transform:rotate(360deg);
                                transform:rotate(360deg)
                            }
                        }
                    </style>
                    ');
                echo sprintf('
                        <div class="loader-container">
                            <div class="loading"></div>
                        </div>
                        <p style="text-align: center;" class="epayco-title">
                                '.$message.'
                        </p>
                        <div id="epayco_form" style="text-align: center;">
                            <form>
                            <script
                                src="https://epayco-checkout-testing.s3.amazonaws.com/checkout.preprod.js?version=1643645084821"
                                class="epayco-button"
                                data-epayco-key="%s"
                                data-epayco-test="%s"
                                data-epayco-amount="%s"
                                data-epayco-tax="%s"
                                data-epayco-tax-base="%s"
                                data-epayco-name="%s"
                                data-epayco-description="%s"
                                data-epayco-currency="%s"                         
                                data-epayco-invoice="%s" 
                                data-epayco-extra1="%s"
                                data-epayco-country="%s"
                                data-epayco-external="%s"                       
                                data-epayco-response="%s"
                                data-epayco-confirmation="%s"
                                data-epayco-email-billing="%s"
                                data-epayco-name-billing="%s"
                                data-epayco-address-billing="%s"
                                data-epayco-lang="%s"
                                data-epayco-mobilephone-billing="%s"
                                data-epayco-button="'.$button.'"
                                data-epayco-autoclick="true"
                                >
                            </script>
                            <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
                            <script>
                            window.onload = function() {
                                document.addEventListener("contextmenu", function(e){
                                    e.preventDefault();
                                }, false);
                            } 
                            $(document).keydown(function (event) {
                                if (event.keyCode == 123) {
                                    return false;
                                } else if (event.ctrlKey && event.shiftKey && event.keyCode == 73) {        
                                    return false;
                                }
                            });
                            </script>
                        </form>
                        </div>       
                ',$this->epayco_publickey,$test_mode,$order->get_total(),$tax,$base_tax, $descripcion, 
                $descripcion, $currency, $orderId, $order->get_id(), $basedCountry, $external_type, $redirect_url,$confirm_url,
                    $email_billing,$name_billing,$address_billing,$epayco_lang,$phone_billing);
                
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


        function check_ePayco_response(){
                @ob_clean();
                if ( ! empty( $_REQUEST ) ) {
                    header( 'HTTP/1.1 200 OK' );
                    do_action( "ePayco_init", $_REQUEST );
                } else {
                    wp_die( __("ePayco Request Failure", 'epayco-woocommerce') );
                }
            }
            
        public function authSignature($x_ref_payco, $x_transaction_id, $x_amount, $x_currency_code){
                $signature = hash('sha256',
                    trim($this->epayco_customerid).'^'
                    .trim($this->epayco_secretkey).'^'
                    .$x_ref_payco.'^'
                    .$x_transaction_id.'^'
                    .$x_amount.'^'
                    .$x_currency_code
                );

            return $signature;
        }    

           function ePayco_successful_request($validationData)
            {
            
                    global $woocommerce;
                    $order_id="";
                    $ref_payco="";
                    $signature="";

                    if(isset($_REQUEST['x_signature'])){
                       $order_id = trim(sanitize_text_field($_GET['order_id']));
                       $x_ref_payco = trim(sanitize_text_field($_REQUEST['x_ref_payco']));
                       $x_transaction_id = trim(sanitize_text_field($_REQUEST['x_transaction_id']));
                       $x_amount = trim(sanitize_text_field($_REQUEST['x_amount']));
                       $x_currency_code = trim(sanitize_text_field($_REQUEST['x_currency_code']));
                       $x_signature = trim(sanitize_text_field($_REQUEST['x_signature']));
                       $x_cod_transaction_state=(int)trim(sanitize_text_field($_REQUEST['x_cod_response']));
                       $x_test_request = trim(sanitize_text_field($_REQUEST['x_test_request']));
                       $x_approval_code = trim(sanitize_text_field($_REQUEST['x_approval_code']));
                        $signature = hash('sha256',
                                 trim($this->epayco_customerid).'^'
                                .trim($this->epayco_secretkey).'^'
                                .$x_ref_payco.'^'
                                .$x_transaction_id.'^'
                                .$x_amount.'^'
                                .$x_currency_code
                            );
                    }else{
                       
                        $order_id_info = sanitize_text_field($_GET['order_id']);
                        $order_id_explode = explode('=',$order_id_info);
                        $order_id_rpl  = str_replace('?ref_payco','',$order_id_explode);
                        $order_id = $order_id_rpl[0];
                        $ref_payco = sanitize_text_field($_GET['ref_payco']);
                        $isConfirmation = sanitize_text_field($_GET['confirmation']) == 1;
                        if(empty($ref_payco)){
                            $ref_payco =$order_id_rpl[1];
                        }
                      
                        if (!$ref_payco) {
                            $explode=explode('=',$order_id);
                            $ref_payco=$explode[1];
                            $explode2 = explode('?', $order_id );
                            $order_id=$explode2[0];
                        }
                        $url = 'https://secure.epayco.io/validation/v1/reference/'.$ref_payco;
                        $response = wp_remote_get(  $url );
                        $body = wp_remote_retrieve_body( $response ); 
                        $jsonData = @json_decode($body, true);
                        $validationData = $jsonData['data'];
                        $x_test_request = trim($validationData['x_test_request']);
                        $x_amount = trim($validationData['x_amount']);
                        $x_approval_code = trim($validationData['x_approval_code']);
                        $x_cod_transaction_state = (int)trim($validationData['x_cod_transaction_state']);
                        $x_ref_payco = (int)trim($validationData['x_ref_payco']);
                        $x_transaction_id = (int)trim($validationData['x_transaction_id']);
                        $x_currency_code = trim($validationData['x_currency_code']);
                        //Validamos la firma
                        if ($order_id!="" && $ref_payco!="") {
                            $signature = hash('sha256',
                                 trim($this->epayco_customerid).'^'
                                .trim($this->epayco_secretkey).'^'
                                .trim($validationData['x_ref_payco']).'^'
                                .trim($validationData['x_transaction_id']).'^'
                                .trim($validationData['x_amount']).'^'
                                .trim($validationData['x_currency_code'])
                            );
                        }

                    }
                    
                    $order = new WC_Order($order_id);
                        

                    $message = '';
                    $messageClass = '';
                    
                    $current_state = $order->get_status();
                    $isTestTransaction = $x_test_request == 'TRUE' ? "yes" : "no";
                    update_option('epaycor_order_status', $isTestTransaction);
                    $isTestMode = get_option('epaycor_order_status') == "yes" ? "true" : "false";
                    $isTestPluginMode = $this->epayco_testmode;
                    
                    if($order->get_total() == $x_amount){
                        if("yes" == $isTestPluginMode){
                            $validation = true;
                        }
                        if("no" == $isTestPluginMode ){
                            if($x_approval_code != "000000" && $x_cod_transaction_state == 1){
                                $validation = true;
                            }else{
                                if($x_cod_transaction_state != 1){
                                    $validation = true;
                                }else{
                                    $validation = false;
                                }
                            }
                            
                        }
                    }else{
                         $validation = false;
                    }
                    
                    if ($order_id != "" && $x_ref_payco != "") {
                        $authSignature = $this->authSignature($x_ref_payco, $x_transaction_id, $x_amount, $x_currency_code);
                    }
                    
                    if($authSignature == $signature && $validation){
                          switch ($x_cod_transaction_state) {
                            case 1:{
                                if($current_state == "epayco_failed" ||
                                    $current_state == "epayco_cancelled" ||
                                    $current_state == "failed" ||
                                    $current_state == "epayco-cancelled" ||
                                    $current_state == "epayco-failed"
                                ){}else{
                                 //Busca si ya se descontó el stock
                                if (!EpaycoOrderAgregador::ifStockDiscount($order_id)){
                                    
                                    //se descuenta el stock
                                    EpaycoOrderAgregador::updateStockDiscount($order_id,1);
                                        
                                }
                                if($isTestMode=="true"){
                                    $message = 'Pago exitoso Prueba';
                                    switch ($this->epayco_endorder_state ){
                                        case 'epayco-processing':{
                                            $orderStatus ='epayco_processing';
                                        }break;
                                        case 'epayco-completed':{
                                            $orderStatus ='epayco_completed';
                                        }break;
                                        case 'processing':{
                                            $orderStatus ='processing_test';
                                        }break;
                                        case 'completed':{
                                            $orderStatus ='completed_test';
                                        }break;
                                    }
                                }else{
                                    $message = 'Pago exitoso';
                                    $orderStatus = $this->epayco_endorder_state;
                                }
                                $order->payment_complete($x_ref_payco);
                                $order->update_status($orderStatus);
                                $order->add_order_note($message);
                                echo "1";
                            }
                            }break;
                            case 2: {
                                 if($isTestMode=="true"){
                                    if($current_state =="epayco_failed" ||
                                        $current_state =="epayco_cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco_processing" ||
                                        $current_state == "epayco_completed" ||
                                        $current_state == "processing_test" ||
                                        $current_state == "completed_test"
                                    ){}else{
                                        $message = 'Pago rechazado Prueba: ' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco_cancelled');
                                        $order->add_order_note($message);
                                        if($current_state !="epayco-cancelled"){
                                            if("yes" == $isTestPluginMode AND $isTestMode == "true"){
                                            $this->restore_order_stock($order->id);
                                            }
                                        }
                                    }
                                }else{
                                    if($current_state =="epayco-failed" ||
                                        $current_state =="epayco-cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco-processing" ||
                                        $current_state == "epayco-completed" ||
                                        $current_state == "processing" ||
                                        $current_state == "completed"
                                    ){}else{
                                        $message = 'Pago rechazado' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco-cancelled');
                                        $order->add_order_note('Pago fallido');
                                        if("no" == $isTestPluginMode AND $isTestMode == "false"){
                                        $this->restore_order_stock($order->id);
                                        }
                                    }
                                }
                                echo "2";
                                if(!$isConfirmation){
                                    $woocommerce->cart->empty_cart();
                                    foreach ($order->get_items() as $item) {
                                        // Get an instance of corresponding the WC_Product object
                                        $product_id = $item->get_product()->id;
                                        $qty = $item->get_quantity(); // Get the item quantity
                                        WC()->cart->add_to_cart( $product_id ,(int)$qty);
                                    }
                                    wp_safe_redirect( wc_get_checkout_url() );
                                    exit();
                                }
                            }break;
                            case 3:{
                                      
                                  //Busca si ya se restauro el stock y si se configuro reducir el stock en transacciones pendientes
                                if (!EpaycoOrderAgregador::ifStockDiscount($order_id)) {
                                    //actualizar el stock
                                    EpaycoOrderAgregador::updateStockDiscount($order_id,1);
                                }
                               
                                if($isTestMode=="true"){
                                    $message = 'Pago pendiente de aprobación Prueba';
                                    $orderStatus = "epayco_on_hold";
                                }else{
                                    $message = 'Pago pendiente de aprobación';
                                    $orderStatus = "epayco-on-hold";
                                }
                                $order->update_status($orderStatus);
                                $order->add_order_note($message);

                            }break;
                            case 4:{
                                if($isTestMode=="true"){
                                    if($current_state =="epayco_failed" ||
                                        $current_state =="epayco_cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco_processing" ||
                                        $current_state == "epayco_completed" ||
                                        $current_state == "processing_test" ||
                                        $current_state == "completed_test"
                                    ){}else{
                                        $message = 'Pago Fallido Prueba: ' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco_failed');
                                        $order->add_order_note($message);
                                        if($current_state !="epayco-cancelled"){
                                            if("yes" == $isTestPluginMode AND $isTestMode == "true"){
                                            $this->restore_order_stock($order->id);
                                            }
                                        }
                                    }
                                }else{
                                    if($current_state =="epayco-failed" ||
                                        $current_state =="epayco-cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco-processing" ||
                                        $current_state == "epayco-completed" ||
                                        $current_state == "processing" ||
                                        $current_state == "completed"
                                    ){}else{
                                        $message = 'Pago Fallido' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco-failed');
                                        $order->add_order_note('Pago fallido');
                                        if("no" == $isTestPluginMode AND $isTestMode == "false"){
                                        $this->restore_order_stock($order->id);
                                        }
                                    }
                                }
                                echo "4";
                                if(!$isConfirmation){
                                    $woocommerce->cart->empty_cart();
                                    foreach ($order->get_items() as $item) {
                                        // Get an instance of corresponding the WC_Product object
                                        $product_id = $item->get_product()->id;
                                        $qty = $item->get_quantity(); // Get the item quantity
                                        WC()->cart->add_to_cart( $product_id ,(int)$qty);
                                    }
                                    wp_safe_redirect( wc_get_checkout_url() );
                                    exit();
                                }
                            }break;
                            case 6:{
                                $message = 'Pago Reversada' .$x_ref_payco;
                                $messageClass = 'woocommerce-error';
                                $order->update_status('refunded');
                                $order->add_order_note('Pago Reversado');
                                $this->restore_order_stock($order->id);
                                echo "6";
                            }break;
                            case 10: {
                                if($isTestMode == "true"){
                                    if($current_state =="epayco_failed" ||
                                        $current_state =="epayco_cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco_processing" ||
                                        $current_state == "epayco_completed" ||
                                        $current_state == "processing_test" ||
                                        $current_state == "completed_test"
                                    ){}else{
                                        $message = 'Pago Fallido Prueba: ' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco_failed');
                                        $order->add_order_note($message);
                                        if($current_state !="epayco-cancelled"){
                                            if("yes" == $isTestPluginMode AND $isTestMode == "true"){
                                            $this->restore_order_stock($order->id);
                                            }
                                        }
                                    }
                                }else{
                                    
                                    if($current_state =="epayco-failed" ||
                                        $current_state =="epayco-cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco-processing" ||
                                        $current_state == "epayco-completed" ||
                                        $current_state == "processing" ||
                                        $current_state == "completed"
                                    ){}else{
                                        $message = 'Pago Fallido' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco-failed');
                                        $order->add_order_note('Pago fallido');
                                        if("no" == $isTestPluginMode AND $isTestMode == "false"){
                                        $this->restore_order_stock($order->id);
                                        }
                                    }
                                }
                                echo "10";
                            if(!$isConfirmation){
                                $woocommerce->cart->empty_cart();
                                foreach ($order->get_items() as $item) {
                                    // Get an instance of corresponding the WC_Product object
                                    $product_id = $item->get_product()->id;
                                    $qty = $item->get_quantity(); // Get the item quantity
                                    WC()->cart->add_to_cart( $product_id ,(int)$qty);
                                }
                                wp_safe_redirect( wc_get_checkout_url() );
                                exit();
                            }
                           }break;
                           case 11: {
                                if($isTestMode == "true"){
                                    if($current_state =="epayco_failed" ||
                                        $current_state =="epayco_cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco_processing" ||
                                        $current_state == "epayco_completed" ||
                                        $current_state == "processing_test" ||
                                        $current_state == "completed_test"
                                    ){}else{
                                        $message = 'Pago Cancelado Prueba: ' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco_cancelled');
                                        $order->add_order_note($message);
                                        if($current_state !="epayco-cancelled"){
                                            if("yes" == $isTestPluginMode AND $isTestMode == "true"){
                                            $this->restore_order_stock($order->id);
                                            }
                                        }
                                    }
                                }else{
                                    
                                    if($current_state =="epayco-failed" ||
                                        $current_state =="epayco-cancelled" ||
                                        $current_state =="failed" ||
                                        $current_state == "epayco-processing" ||
                                        $current_state == "epayco-completed" ||
                                        $current_state == "processing" ||
                                        $current_state == "completed"
                                    ){}else{
                                        $message = 'Pago Cancelado' .$x_ref_payco;
                                        $messageClass = 'woocommerce-error';
                                        $order->update_status('epayco-cancelled');
                                        $order->add_order_note('Pago Cancelado');
                                        if("no" == $isTestPluginMode AND $isTestMode == "false"){
                                        $this->restore_order_stock($order->id);
                                        }
                                    }
                                }
                                echo "11";
                                if(!$isConfirmation){
                                $woocommerce->cart->empty_cart();
                                foreach ($order->get_items() as $item) {
                                    // Get an instance of corresponding the WC_Product object
                                    $product_id = $item->get_product()->id;
                                    $qty = $item->get_quantity(); // Get the item quantity
                                    WC()->cart->add_to_cart( $product_id ,(int)$qty);
                                }
                                wp_safe_redirect( wc_get_checkout_url() );
                                exit();
                            }
                           }break;
                            default:{
                                if(
                                    $current_state =="epayco_failed" ||
                                    $current_state =="epayco_cancelled" ||
                                    $current_state =="failed" ||
                                    $current_state == "epayco_processing" ||
                                    $current_state == "epayco_completed" ||
                                    $current_state == "processing_test" ||
                                    $current_state == "completed_test"
                                    
                                    ){}else{
                                    $message = 'Pago '.$_REQUEST['x_transaction_state'] . $x_ref_payco;
                                    $messageClass = 'woocommerce-error';
                                    $order->update_status('epayco-failed');
                                    $order->add_order_note('Pago fallido o abandonado');
                                    $this->restore_order_stock($order->id);
                                    }
                                    echo "default";

                            }break;
                        }

                    }else{
                        
                       if($isTestMode == "true"){
                            if($current_state =="epayco_failed" ||
                                $current_state =="epayco_cancelled" ||
                                $current_state =="epayco-cancelled" ||
                                $current_state =="failed" ||
                                $current_state == "epayco_processing" ||
                                $current_state == "epayco_completed" ||
                                $current_state == "processing_test" ||
                                $current_state == "completed_test"
                            ){
                                if($x_cod_transaction_state == 1){
                                    $message = 'Pago exitoso Prueba';
                                    switch ($this->epayco_endorder_state ){
                                            case 'epayco-processing':{
                                                $orderStatus ='epayco_processing';
                                            }break;
                                            case 'epayco-completed':{
                                                $orderStatus ='epayco_completed';
                                            }break;
                                            case 'processing':{
                                                $orderStatus ='processing_test';
                                            }break;
                                            case 'completed':{
                                                $orderStatus ='completed_test';
                                            }break;
                                        }
                                         $order->update_status($orderStatus);
                                         $order->add_order_note($message);
                                    
                                    }
                            }else{
                               
                                if($x_cod_transaction_state == 1){
                                $message = 'Pago exitoso Prueba';
                                switch ($this->epayco_endorder_state ){
                                        case 'epayco-processing':{
                                            $orderStatus ='epayco_processing';
                                        }break;
                                        case 'epayco-completed':{
                                            $orderStatus ='epayco_completed';
                                        }break;
                                        case 'processing':{
                                            $orderStatus ='processing_test';
                                        }break;
                                        case 'completed':{
                                            $orderStatus ='completed_test';
                                        }break;
                                    }
                         
                                $order->update_status($orderStatus);
                                $order->add_order_note($message);
                                if($current_state !=  "epayco_on_hold"){
                                    $this->restore_order_stock($order->id);
                                }
                                }else{
                               $order->update_status('epayco_failed');
                                $order->add_order_note('Pago fallido o abandonado');
                                $this->restore_order_stock($order->id);
                                }
                            }
                        }else{
                           if($current_state =="epayco-failed" ||
                                $current_state =="epayco-cancelled" ||
                                $current_state =="failed" ||
                                $current_state == "epayco-processing" ||
                                $current_state == "epayco-completed" ||
                                $current_state == "processing" ||
                                $current_state == "completed"
                            ){}else{
                                if($x_cod_transaction_state == 1){
                                $message = 'Pago exitoso';
                                $orderStatus = $this->epayco_endorder_state;
                                }else{ 
                                $order->update_status('epayco-failed');
                                $order->add_order_note('Pago fallido o abandonado');
                                $this->restore_order_stock($order->id);
                                } 
                            } 
                        }
                        
                    }
   

                    if (isset($_REQUEST['confirmation'])) {
                        echo $current_state;
                        die();
                        
                    }else{
                        
                         $redirect_url = $order->get_checkout_order_received_url(); 
                        wp_redirect($redirect_url);
                    die();
                    }
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
            
            
    /**
    * @param $order_id
    */
    public function restore_order_stock($order_id,$operation = 'increase')
    {
    $order = wc_get_order($order_id);
    if (!get_option('woocommerce_manage_stock') == 'yes' && !sizeof($order->get_items()) > 0) {
            return;
        }
        foreach ($order->get_items() as $item) {
            // Get an instance of corresponding the WC_Product object
            $product = $item->get_product();
            $qty = $item->get_quantity(); // Get the item quantity
            wc_update_product_stock($product, $qty, $operation);
        }
    }


    /**
     * @param $value
     * @return int
     */
    private function toAmount($value)
    {
        return (int)round($value * 100);
    }

    /**
     * @return string
     */
    private function getLanguage()
    {
        return substr(get_locale(), 0, 2);
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    private function getOrderCurrency($order)
    {
        return method_exists($order,'get_currency') ? $order->get_currency() : $order->get_order_currency();
    }

    /**
     * @param WC_Order $order
     */
    private function reduceStock($order)
    {
        function_exists('wc_reduce_stock_levels') ?
            wc_reduce_stock_levels($order->get_id()) : $order->reduce_order_stock();

    }

    /**
     * @param string $notification
     * @return null|string
     */
    private function extractCurrencyFromNotification($notification)
    {
        $notification = json_decode($notification);

        if (is_object($notification) && $notification->order && $notification->order->currencyCode) {
            return $notification->order->currencyCode;
        }
        return null;
    }

    /**
     * @return string
     */
    private function getIP()
    {
        return ($_SERVER['REMOTE_ADDR'] == '::1' || $_SERVER['REMOTE_ADDR'] == '::' ||
            !preg_match('/^((?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9]).){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9])$/m',
                $_SERVER['REMOTE_ADDR'])) ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'];
    }

    /** @return bool */
    private function isWpmlActiveAndConfigure() {
        global $woocommerce_wpml;

        return $woocommerce_wpml
            && property_exists($woocommerce_wpml, 'multi_currency')
            && $woocommerce_wpml->multi_currency
            && count($woocommerce_wpml->multi_currency->get_currency_codes()) > 1;
    }

    /**
     * @return array
     */
    private function getFormFieldsBasic()
    {
        return array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'label' => __('Habilitar ePayco Checkout', 'epayco'),
                'type' => 'checkbox',
                'description' => __('Para obtener las credenciales de configuración, <a href="https://dashboard.epayco.co/login?utm_campaign=epayco&utm_medium=button-header&utm_source=web#registro" target="_blank">Inicie sesión</a>.', 'epayco'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title:', 'epayco'),
                'type' => 'text',
                'description' => __('Corresponde al titulo que el usuario ve durante el checkout.', 'epayco'),
                'default' => __('ePayco Checkout', 'epayco'),
                'desc_tip' => true
            )
        );
    }

    /**
     * @return array
     */
    private function getFormFieldInfo()
    {
        return array(
            'description' => array(
                'title' => __('Description:', 'epayco'),
                'type' => 'text',
                'description' => __('Corresponde a la descripción que verá el usuaro durante el checkout', 'epayco'),
                'default' => __('Checkout ePayco (Tarjetas de crédito,debito,efectivo)', 'epayco'),
                'desc_tip' => true
            ),
            'epayco_feedback' => array(
                'title' => __('Redireccion con data:', 'epayco'),
                'type' => 'checkbox',
                'description' => __('Automatic collection makes it possible to automatically confirm incoming payments.', 'epayco'),
                'label' => ' ',
                'default' => 'no',
                'desc_tip' => true
            )
        );
    }

    /**
     * @param array $currencies
     * @return array
     */
    private function getFormFieldConfig($currencies = [])
    {
        if (count($currencies) < 2) {
            $currencies = array('');
        }
        $config = array();

        foreach ($currencies as $code) {
            $idSuffix = ($code ? '_' : '') . $code;
            $namePrefix = $code . ($code ? ' - ' : '');

            $config += array(
                'epayco_customerid' . $idSuffix => array(
                    'title' => $namePrefix . __('<span class="epayco-required">P_CUST_ID_CLIENTE</span>', 'epayco'),
                    'type' => 'text',
                    'description' => $namePrefix . __('ID de cliente que lo identifica en ePayco. Lo puede encontrar en su panel de clientes en la opción configuración.', 'epayco'),
                    'desc_tip' => true
                ),
                'epayco_secretkey' . $idSuffix => array(
                    'title' => $namePrefix . __('<span class="epayco-required">P_KEY</span>', 'epayco'),
                    'type' => 'text',
                    'description' => __('LLave para firmar la información enviada y recibida de ePayco. Lo puede encontrar en su panel de clientes en la opción configuración.', 'epayco'),
                    'desc_tip' => true
                ),
                'epayco_publickey' . $idSuffix => array(
                    'title' => $namePrefix . __('<span class="epayco-required">PUBLIC_KEY</span>', 'epayco'),
                    'type' => 'text',
                    'description' => __('LLave para autenticar y consumir los servicios de ePayco, Proporcionado en su panel de clientes en la opción configuración.', 'ePayco'),
                    'desc_tip' => true
                ),
                'epayco_testmode' . $idSuffix => array(
                    'title' => $namePrefix . __('Sitio en pruebas', 'epayco'),
                    'type' => 'checkbox',
                    'label' => __('Habilitar el modo de pruebas', 'epayco'),
                    'description' => __('Habilite para realizar pruebas', 'epayco'),
                    'default' => 'no',
                    'desc_tip' => true
                ),
                'epayco_lang' . $idSuffix => array(
                    'title' => $namePrefix . __('Idioma del Checkout', 'epayco'),
                    'type' => 'select',
                    'css' =>'line-height: inherit',
                    'description' => __('Habilite para realizar pruebas', 'epayco'),
                      'options' => array('es'=>"Español","en"=>"Inglés"),
                    'desc_tip' => true
                ),
                'epayco_type_checkout' . $idSuffix => array(
                    'title' => $namePrefix . __('Tipo Checkout', 'epayco'),
                    'type' => 'select',
                    'css' =>'line-height: inherit',
                    'label' => __('Seleccione un tipo de Checkout:', 'epayco'),
                    'description' => __('(Onpage Checkout, el usuario al pagar permanece en el sitio) ó (Standart Checkout, el usario al pagar es redireccionado a la pasarela de ePayco)', 'epayco'),
                    'options' => array('false'=>"Onpage Checkout","true"=>"Standart Checkout"),
                    'desc_tip' => true
                ),
                'epayco_endorder_state' => array(
                        'title' => __('Estado Final del Pedido', 'epayco'),
                        'type' => 'select',
                        'css' =>'line-height: inherit',
                        'description' => __('Seleccione el estado del pedido que se aplicará a la hora de aceptar y confirmar el pago de la orden', 'epayco'),
                        'options' => array(
                            'epayco-processing'=>"ePayco Procesando Pago",
                            "epayco-completed"=>"ePayco Pago Completado",
                            'processing'=>"Procesando",
                            "completed"=>"Completado"
                        ),
                ),
                'alg_wc_custom_order_numbers_enabled' => array(
                        'title'    => __( 'WooCommerce Custom Order Numbers', 'epayco' ),
                        'desc'     => '<strong>' . __( 'Enable plugin', 'epayco' ) . '</strong>',
                        'desc_tip' => __( 'Custom Order Numbers for WooCommerce.', 'epayco' ),
                        'id'       => 'alg_wc_custom_order_numbers_enabled',
                        'default'  => 'yes',
                        'type'     => 'checkbox',
                    ),
                    'alg_wc_custom_order_numbers_counter' => array(
                        'title'    => __( '', 'epayco' ),
                        'id'       => 'alg_wc_custom_order_numbers_counter',
                        'default'  => 1,
                        'type'     => 'number',
                        'css' =>'display: none',
                    ),
                    'alg_wc_custom_order_numbers_prefix' => array(
                        'title'    => __( 'Order number custom prefix', 'epayco' ),
                        'desc_tip' => __( 'Prefix before order number (optional). This will change the prefixes for all existing orders.', 'epayco' ),
                        'id'       => 'alg_wc_custom_order_numbers_prefix',
                        'default'  => '',
                        'type'     => 'text',
                    )
             
            );
        }
        return $config;
    }
    

    private function getShippingMethods()
    {
        $options    = [];
        $data_store = WC_Data_Store::load( 'shipping-zone' );
        $raw_zones  = $data_store->get_zones();

        foreach ( $raw_zones as $raw_zone ) {
            $zones[] = new WC_Shipping_Zone( $raw_zone );
        }

        $zones[] = new WC_Shipping_Zone( 0 );

        foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

            $options[ $method->get_method_title() ] = array();

            // Translators: %1$s shipping method name.
            $options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'woocommerce' ), $method->get_method_title() );

            foreach ( $zones as $zone ) {

                $shipping_method_instances = $zone->get_shipping_methods();

                foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

                    if ( $shipping_method_instance->id !== $method->id ) {
                        continue;
                    }

                    $option_id = $shipping_method_instance->get_rate_id();

                    // Translators: %1$s shipping method title, %2$s shipping method id.
                    $option_instance_title = sprintf( __( '%1$s (#%2$s)', 'woocommerce' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

                    // Translators: %1$s zone name, %2$s shipping method instance name.
                    $option_title = sprintf( __( '%1$s &ndash; %2$s', 'woocommerce' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'woocommerce' ), $option_instance_title );

                    $options[ $method->get_method_title() ][ $option_id ] = $option_title;
                }
            }
        }

        return $options;
    }
    
    
    
    
    
    
    
       /* Enqueue JS script for showing fields as per the changes made in the settings.
        *
        * @version 1.3.0
        * @since   1.3.0
        */
            public static function alg_custom_order_numbers_setting_script() {
                $plugin_url       = plugins_url() . '/Plugin_ePayco_WooCommerce';
                $numbers_instance = alg_wc_custom_order_numbers();
                wp_enqueue_script(
                    'con_dismiss_notice',
                    $plugin_url . '/includes/js/con-dismiss-notice.js',
                    '',
                    $numbers_instance->version,
                    false
                );
                wp_localize_script(
                    'con_dismiss_notice',
                    'con_dismiss_param',
                    array(
                        'ajax_url' => admin_url( 'admin-ajax.php' ),
                    )
                );
            }
            /**
             * Check if HPOS is enabled or not.
             *
             * @since 1.8.0
             * return boolean true if enabled else false
             */
            public function con_wc_hpos_enabled() {
                if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
                    if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
                        return true;
                    }
                }
                return false;
            }

            /**
             * Function to show the admin notice to update the old CON meta key in the database when the plugin is updated.
             *
             * @version 1.3.0
             * @since   1.3.0
             */
            public static function alg_custom_order_numbers_update_admin_notice() {
                global $current_screen;
                $ts_current_screen = get_current_screen();
                // Return when we're on any edit screen, as notices are distracting in there.
                if ( ( method_exists( $ts_current_screen, 'is_block_editor' ) && $ts_current_screen->is_block_editor() ) || ( function_exists( 'is_gutenberg_page' ) && is_gutenberg_page() ) ) {
                    return;
                }
                if ( 'yes' === get_option( 'alg_custom_order_numbers_show_admin_notice', '' ) ) {
                    if ( '' === get_option( 'alg_custom_order_numbers_update_database', '' ) ) {
                        ?>
                        <div class=''>
                            <div class="con-lite-message notice notice-info" style="position: relative;">
                                <p style="margin: 10px 0 10px 10px; font-size: medium;">
                                    <?php
                                    echo esc_html_e( 'From version 1.3.0, you can now search the orders by custom order numbers on the Orders page. In order to make the previous orders with custom order numbers searchable on Orders page, we need to update the database. Please click the "Update Now" button to do this. The database update process will run in the background.', 'epayco_woocommerce' );
                                    ?>
                                </p>
                                <p class="submit" style="margin: -10px 0 10px 10px;">
                                    <a class="button-primary button button-large" id="con-lite-update" href="edit.php?post_type=shop_order&action=alg_custom_order_numbers_update_old_con_in_database"><?php esc_html_e( 'Update Now', 'epayco_woocommerce' ); ?></a>
                                </p>
                            </div>
                        </div>
                        <?php
                    }
                }
                if ( 'yes' !== get_option( 'alg_custom_order_numbers_no_meta_admin_notice', '' ) ) {
                    if ( 'yes' === get_option( 'alg_custom_order_number_old_orders_to_update_meta_key', '' ) ) {
                        if ( '' === get_option( 'alg_custom_order_numbers_update_meta_key_in_database', '' ) ) {
                            ?>
                            <div class=''>
                                <div class="con-lite-message notice notice-info" style="position: relative;">
                                    <p style="margin: 10px 0 10px 10px; font-size: medium;">
                                        <?php
                                        echo esc_html_e( 'In order to make the previous orders searchable on Orders page where meta key of the custom order number is not present, we need to update the database. Please click the "Update Now" button to do this. The database update process will run in the background.', 'epayco_woocommerce' );
                                        ?>
                                    </p>
                                    <p class="submit" style="margin: -10px 0 10px 10px;">
                                        <a class="button-primary button button-large" id="con-lite-update" href="edit.php?post_type=shop_order&action=alg_custom_order_numbers_update_old_con_with_meta_key"><?php esc_html_e( 'Update Now', 'epayco_woocommerce' ); ?></a>
                                    </p>
                                </div>
                            </div>
                            <?php
                        }
                    }
                }
            }

            /**
             * Function to add a scheduled action when Update now button is clicked in admin notice.AS will run every 5 mins and will run the script to update the CON meta value in old orders.
             *
             * @version 1.3.0
             * @since   1.3.0
             */
            public function alg_custom_order_numbers_add_recurring_action() {
                if ( isset( $_REQUEST['action'] ) && 'alg_custom_order_numbers_update_old_con_in_database' === $_REQUEST['action'] ) { // phpcs:ignore
                    update_option( 'alg_custom_order_numbers_update_database', 'yes' );
                    $current_time = current_time( 'timestamp' ); // phpcs:ignore
                    update_option( 'alg_custom_order_numbers_time_of_update_now', $current_time );
                    if ( function_exists( 'as_next_scheduled_action' ) ) { // Indicates that the AS library is present.
                        as_schedule_recurring_action( time(), 300, 'alg_custom_order_numbers_update_old_custom_order_numbers' );
                    }
                    wp_safe_redirect( admin_url( 'edit.php?post_type=shop_order' ) );
                    exit;
                }
            }

            /**
             * Function to add a scheduled action when Update now button is clicked in admin notice.AS will run every 5 mins and will run the script to add the meta key of CON in old orders where it is missing.
             *
             * @version 1.3.0
             * @since   1.3.0
             */
            public function alg_custom_order_numbers_add_recurring_action_to_add_meta_key() {
                if ( isset( $_REQUEST['action'] ) && 'alg_custom_order_numbers_update_old_con_with_meta_key' === $_REQUEST['action'] ) { // phpcs:ignore
                    update_option( 'alg_custom_order_numbers_update_meta_key_in_database', 'yes' );
                    $current_time = current_time( 'timestamp' ); // phpcs:ignore
                    update_option( 'alg_custom_order_numbers_meta_key_time_of_update_now', $current_time );
                    if ( function_exists( 'as_next_scheduled_action' ) ) { // Indicates that the AS library is present.
                        as_schedule_recurring_action( time(), 300, 'alg_custom_order_numbers_update_meta_key_in_old_con' );
                    }
                    wp_safe_redirect( admin_url( 'edit.php?post_type=shop_order' ) );
                    exit;
                }
            }

            /**
             * Callback function for the AS to run the script to update the CON meta value for the old orders.
             *
             * @version 1.3.0
             * @since   1.3.0
             */
            public function alg_custom_order_numbers_update_old_custom_order_numbers_callback() {
                $args        = array(
                    'post_type'      => 'shop_order',
                    'posts_per_page' => 10000, // phpcs:ignore
                    'post_status'    => 'any',
                    'meta_query'     => array( // phpcs:ignore
                        'relation' => 'AND',
                        array(
                            'key'     => '_alg_wc_custom_order_number',
                            'compare' => 'EXISTS',
                        ),
                        array(
                            'key'     => '_alg_wc_custom_order_number_updated',
                            'compare' => 'NOT EXISTS',
                        ),
                    ),
                );
                $loop_orders = new WP_Query( $args );
                if ( ! $loop_orders->have_posts() ) {
                    update_option( 'alg_custom_order_numbers_no_old_orders_to_update', 'yes' );
                    return;
                }
                foreach ( $loop_orders->posts as $order_ids ) {
                    $order_id = $order_ids->ID;
                    if ( $this->con_wc_hpos_enabled() ) {
                        $order_number_meta = get_meta( '_alg_wc_custom_order_number' );
                    } else {
                        $order_number_meta = get_post_meta( $order_id, '_alg_wc_custom_order_number', true );
                    }
                    if ( '' === $order_number_meta ) {
                        $order_number_meta = $order_id;
                    }
                    $is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
                    $order                 = wc_get_order( $order_id );
                    $order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
                    $time                  = get_option( 'alg_custom_order_numbers_time_of_update_now', '' );
                    if ( $order_timestamp > $time ) {
                        return;
                    }
                    $custom_order_numbers_prefix = $this->alg_wc_custom_order_numbers_prefix;
                    if ( '' === $custom_order_numbers_prefix ) {
                        $custom_order_numbers_prefix = get_bloginfo( 'name' )." : ".$this->alg_wc_custom_order_numbers_prefix;
                    }
                    $con_order_number = apply_filters(
                        'alg_wc_custom_order_numbers',
                        sprintf( '%s%s', do_shortcode( $custom_order_numbers_prefix ), $order_number_meta ),
                        'value',
                        array(
                            'order_timestamp'   => $order_timestamp,
                            'order_number_meta' => $order_number_meta,
                        )
                    );
                    if ( $this->con_wc_hpos_enabled() ) {
                        $order->update_meta_data( '_alg_wc_full_custom_order_number', $con_order_number );
                        $order->update_meta_data( '_alg_wc_custom_order_number_updated', 1 );
                        $order->save();
                    } else {
                        update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $con_order_number );
                        update_post_meta( $order_id, '_alg_wc_custom_order_number_updated', 1 );
                    }
                }
                $loop_old_orders = $this->alg_custom_order_number_old_orders_without_meta_key_data();
                if ( '' === $loop_old_orders ) {
                    update_option( 'alg_custom_order_numbers_no_old_orders_to_update', 'yes' );
                    return;
                }
                foreach ( $loop_old_orders->posts as $order_ids ) {
                    $order_id              = $order_ids->ID;
                    $order_number_meta     = $order_id;
                    $is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
                    $order                 = wc_get_order( $order_id );
                    $order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
                    $time                  = get_option( 'alg_custom_order_numbers_meta_key_time_of_update_now', '' );
                    if ( $order_timestamp > $time ) {
                        return;
                    }
                    $con_order_number = apply_filters(
                        'alg_wc_custom_order_numbers',
                        sprintf( '%s%s', do_shortcode( $custom_order_numbers_prefix ), $order_number_meta ),
                        'value',
                        array(
                            'order_timestamp'   => $order_timestamp,
                            'order_number_meta' => $order_number_meta,
                        )
                    );
                    if ( $this->con_wc_hpos_enabled() ) {
                        $order->update_meta_data( '_alg_wc_full_custom_order_number', $con_order_number );
                        $order->update_meta_data( '_alg_wc_custom_order_number_meta_key_updated', 1 );
                        $order->save();
                    } else {
                        update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $con_order_number );
                        update_post_meta( $order_id, '_alg_wc_custom_order_number_meta_key_updated', 1 );
                    }
                }
                if ( 10000 > count( $loop_orders->posts ) && 500 > count( $loop_old_orders->posts ) ) {
                    update_option( 'alg_custom_order_numbers_no_old_orders_to_update', 'yes' );
                }
            }

            /**
             * Callback function for the AS to run the script to add the CON meta key for the old orders where it is missing.
             */
            public function alg_custom_order_numbers_update_meta_key_in_old_con_callback() {
                $loop_orders = $this->alg_custom_order_number_old_orders_without_meta_key_data();
                if ( '' === $loop_orders ) {
                    update_option( 'alg_custom_order_number_no_old_con_without_meta_key', 'yes' );
                    return;
                }
                $custom_order_numbers_prefix = $this->alg_wc_custom_order_numbers_prefix;
                if ( '' === $custom_order_numbers_prefix ) {
                    $custom_order_numbers_prefix = get_bloginfo( 'name' )." : ".$this->alg_wc_custom_order_numbers_prefix;
                }
                foreach ( $loop_orders->posts as $order_ids ) {
                    $order_id              = $order_ids->ID;
                    $order_number_meta     = $order_id;
                    $is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
                    $order                 = wc_get_order( $order_id );
                    $order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
                    $time                  = get_option( 'alg_custom_order_numbers_meta_key_time_of_update_now', '' );
                    if ( $order_timestamp > $time ) {
                        return;
                    }
                    $con_order_number = apply_filters(
                        'alg_wc_custom_order_numbers',
                        sprintf( '%s%s', do_shortcode( $custom_order_numbers_prefix ), $order_number_meta ),
                        'value',
                        array(
                            'order_timestamp'   => $order_timestamp,
                            'order_number_meta' => $order_number_meta,
                        )
                    );
                    if ( $this->con_wc_hpos_enabled() ) {
                        $order->update_meta_data( '_alg_wc_full_custom_order_number', $con_order_number );
                        $order->update_meta_data( '_alg_wc_custom_order_number_meta_key_updated', 1 );
                        $order->save();
                    } else {
                        update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $con_order_number );
                        update_post_meta( $order_id, '_alg_wc_custom_order_number_meta_key_updated', 1 );
                    }
                }
                if ( 500 > count( $loop_orders->posts ) ) {
                    update_option( 'alg_custom_order_number_no_old_con_without_meta_key', 'yes' );
                }
            }

            /**
             * Function to get the old orders where CON meta key is missing.
             */
            public function alg_custom_order_number_old_orders_without_meta_key() {
                if ( 'yes' !== get_option( 'alg_custom_order_number_no_old_con_without_meta_key', '' ) && 'yes' !== get_option( 'alg_custom_order_number_no_old_orders_to_update_meta_key', '' ) ) {
                    $args        = array(
                        'post_type'      => 'shop_order',
                        'posts_per_page' => 1, // phpcs:ignore
                        'post_status'    => 'any',
                        'meta_query'     => array( // phpcs:ignore
                            'relation' => 'AND',
                            array(
                                'key'     => '_alg_wc_custom_order_number',
                                'compare' => 'NOT EXISTS',
                            ),
                            array(
                                'key'     => '_alg_wc_custom_order_number_meta_key_updated',
                                'compare' => 'NOT EXISTS',
                            ),
                        ),
                    );
                    $loop_orders = new WP_Query( $args );
                    update_option( 'alg_custom_order_number_no_old_orders_to_update_meta_key', 'yes' );
                    if ( ! $loop_orders->have_posts() ) {
                        return '';
                    } else {
                        update_option( 'alg_custom_order_number_old_orders_to_update_meta_key', 'yes' );
                        return $loop_orders;
                    }
                }
            }

            /**
             * Function to get the old orders data where CON meta key is missing.
             */
            public function alg_custom_order_number_old_orders_without_meta_key_data() {
                $args        = array(
                    'post_type'      => 'shop_order',
                    'posts_per_page' => 500, // phpcs:ignore
                    'post_status'    => 'any',
                    'meta_query'     => array( // phpcs:ignore
                        'relation' => 'AND',
                        array(
                            'key'     => '_alg_wc_custom_order_number',
                            'compare' => 'NOT EXISTS',
                        ),
                        array(
                            'key'     => '_alg_wc_custom_order_number_meta_key_updated',
                            'compare' => 'NOT EXISTS',
                        ),
                    ),
                );
                $loop_orders = new WP_Query( $args );
                if ( ! $loop_orders->have_posts() ) {
                    return '';
                } else {
                    return $loop_orders;
                }
            }

            /**
             * Stop AS when there are no old orders left to update the CON meta key.
             *
             * @version 1.3.0
             * @since   1.3.0
             */
            public static function alg_custom_order_numbers_stop_recurring_action() {
                if ( 'yes' === get_option( 'alg_custom_order_numbers_no_old_orders_to_update', '' ) ) {
                    as_unschedule_all_actions( 'alg_custom_order_numbers_update_old_custom_order_numbers' );
                }
                if ( 'yes' === get_option( 'alg_custom_order_number_no_old_con_without_meta_key', '' ) ) {
                    as_unschedule_all_actions( 'alg_custom_order_numbers_update_meta_key_in_old_con' );
                }
            }

            /**
             * Function to show the Success Notice when all the old orders CON meta value are updated.
             *
             * @version 1.3.0
             * @since   1.3.0
             */
            public function alg_custom_order_numbers_update_success_notice() {
                if ( 'yes' === get_option( 'alg_custom_order_numbers_no_old_orders_to_update', '' ) ) {
                    if ( 'dismissed' !== get_option( 'alg_custom_order_numbers_success_notice', '' ) ) {
                        ?>
                        <div>
                            <div class="con-lite-message con-lite-success-message notice notice-success is-dismissible" style="position: relative;">
                                <p>
                                    <?php
                                    echo esc_html_e( 'Database updated successfully. In addition to new orders henceforth, you can now also search the old orders on Orders page with the custom order numbers.', 'epayco_woocommerce' );
                                    ?>
                                </p>
                            </div>
                        </div>
                        <?php
                    }
                }
                if ( 'yes' !== get_option( 'alg_custom_order_numbers_no_meta_admin_notice', '' ) ) {
                    if ( 'yes' === get_option( 'alg_custom_order_number_no_old_con_without_meta_key', '' ) ) {
                        if ( 'dismissed' !== get_option( 'alg_custom_order_numbers_success_notice_for_meta_key', '' ) ) {
                            ?>
                            <div>
                                <div class="con-lite-message con-lite-meta-key-success-message notice notice-success is-dismissible" style="position: relative;">
                                    <p>
                                        <?php
                                        echo esc_html_e( 'Database updated successfully. In addition to new orders henceforth, you can now also search the old orders on Orders page with the custom order numbers.', 'epayco_woocommerce' );
                                        ?>
                                    </p>
                                </div>
                            </div>
                            <?php
                        }
                    }
                }
            }

            /**
             * Function to dismiss the admin notice.
             *
             * @version 1.3.0
             * @since   1.3.0
             */
            public function alg_custom_order_numbers_admin_notice_dismiss() {
                $admin_choice = isset( $_POST['admin_choice'] ) ? sanitize_text_field( wp_unslash( $_POST['admin_choice'] ) ) : ''; // phpcs:ignore
                update_option( 'alg_custom_order_numbers_success_notice', $admin_choice );
            }

            /**
             * Function to dismiss the admin notice.
             */
            public function alg_custom_order_numbers_admin_meta_key_notice_dismiss() {
                $admin_choice = isset( $_POST['alg_admin_choice'] ) ? sanitize_text_field( wp_unslash( $_POST['alg_admin_choice'] ) ) : ''; // phpcs:ignore
                update_option( 'alg_custom_order_numbers_success_notice_for_meta_key', $admin_choice );
            }

            /**
             * Function to update the prefix in the databse when settings are saved.
             *
             * @version 1.3.0
             * @since   1.3.0
             */
            public function woocommerce_settings_save_alg_wc_custom_order_numbers_callback() {
                if ( '1' === get_option( 'alg_wc_custom_order_numbers_prefix_suffix_changed' ) ) {
                    $args        = array(
                        'post_type'      => 'shop_order',
                        'post_status'    => 'any',
                        'posts_per_page' => -1,
                    );
                    $loop_orders = new WP_Query( $args );
                    if ( ! $loop_orders->have_posts() ) {
                        update_option( 'alg_wc_custom_order_numbers_prefix_suffix_changed', '' );
                        return;
                    }
                    foreach ( $loop_orders->posts as $order_ids ) {
                        $order_id = $order_ids->ID;
                        $order    = wc_get_order( $order_id );
                        if ( $this->con_wc_hpos_enabled() ) {
                            $order_number_meta = $order->get_meta( '_alg_wc_custom_order_number' );
                        } else {
                            $order_number_meta = get_post_meta( $order_id, '_alg_wc_custom_order_number', true );
                        }
                        if ( '' === $order_number_meta ) {
                            $order_number_meta = $order_id;
                        }
                        
                        $is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
                        $order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
                        $custom_order_numbers_prefix = $this->alg_wc_custom_order_numbers_prefix;
                        if ( '' === $custom_order_numbers_prefix ) {
                            $custom_order_numbers_prefix = get_bloginfo( 'name' )." : ".$this->alg_wc_custom_order_numbers_prefix;
                        }
                        $full_order_number     = apply_filters(
                            'alg_wc_custom_order_numbers',
                            sprintf( '%s%s', do_shortcode( $custom_order_numbers_prefix ), $order_number_meta ),
                            'value',
                            array(
                                'order_timestamp'   => $order_timestamp,
                                'order_number_meta' => $order_number_meta,
                            )
                        );
                        if ( $this->con_wc_hpos_enabled() ) {
                            $order->update_meta_data( '_alg_wc_full_custom_order_number', $full_order_number );
                            $order->save();
                        } else {
                            update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $full_order_number );
                        }
                        update_option( 'alg_wc_custom_order_numbers_prefix_suffix_changed', '' );
                    }
                }
            }

            /**
             * Maybe_reset_sequential_counter.
             *
             * @param string $current_order_number - Current custom Order Number.
             * @param int    $order_id - WC Order ID.
             *
             * @version 1.2.2
             * @since   1.1.2
             * @todo    [dev] use MySQL transaction
             */
            public function maybe_reset_sequential_counter( $current_order_number, $order_id ) {
                return $current_order_number;
            }

            /**
             * Save_order_number_meta_box.
             *
             * @param int      $post_id - Order ID.
             * @param WC_Order $post - Post Object.
             * @version 1.1.1
             * @since   1.1.1
             */
            public function save_order_number_meta_box( $post_id, $post ) {
                if ( ! isset( $_POST['alg_wc_custom_order_numbers_meta_box'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
                    return;
                }

                if ( isset( $_POST['alg_wc_custom_order_number'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
                    $is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
                    $order                 = wc_get_order( $post_id );
                    $order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
                    $current_order_number  = '';
                    if ( isset( $_POST['alg_wc_custom_order_number'] ) ) { // phpcs:ignore
                        $current_order_number = sanitize_text_field( wp_unslash( $_POST['alg_wc_custom_order_number'] ) ); // phpcs:ignore
                    }
                    $custom_order_numbers_prefix = $this->alg_wc_custom_order_numbers_prefix;
                    if ( '' === $custom_order_numbers_prefix ) {
                        $custom_order_numbers_prefix = get_bloginfo( 'name' )." : ".$this->alg_wc_custom_order_numbers_prefix;
                    }
                    $full_custom_order_number = apply_filters(
                        'alg_wc_custom_order_numbers',
                        sprintf( '%s%s', do_shortcode( $custom_order_numbers_prefix ), $current_order_number ),
                        'value',
                        array(
                            'order_timestamp'   => $order_timestamp,
                            'order_number_meta' => $current_order_number,
                        )
                    );
                    if ( $this->con_wc_hpos_enabled() ) {
                        $order->update_meta_data( '_alg_wc_custom_order_number', $current_order_number );
                        $order->update_meta_data( '_alg_wc_full_custom_order_number', $full_custom_order_number );
                        $order->save();
                    } else {
                        update_post_meta( $post_id, '_alg_wc_custom_order_number', $current_order_number );
                        update_post_meta( $post_id, '_alg_wc_full_custom_order_number', $full_custom_order_number );
                    }
                }
            }

            /**
             * Add_order_number_meta_box.
             *
             * @version 1.1.1
             * @since   1.1.1
             */
            public function add_order_number_meta_box() {
                if ( $this->con_wc_hpos_enabled() ) {
                    add_meta_box(
                        'alg-wc-custom-order-numbers-meta-box',
                        __( 'Order Number', 'epayco_woocommerce' ),
                        array( $this, 'create_order_number_meta_box' ),
                        wc_get_page_screen_id( 'shop-order' ),
                        'side',
                        'low'
                    );

                } else {
                    add_meta_box(
                        'alg-wc-custom-order-numbers-meta-box',
                        __( 'Order Number', 'epayco_woocommerce' ),
                        array( $this, 'create_order_number_meta_box' ),
                        'shop_order',
                        'side',
                        'low'
                    );
                }
            }

            /**
             * Create_order_number_meta_box.
             *
             * @version 1.1.1
             * @since   1.1.1
             */
            public function create_order_number_meta_box() {
                if ( $this->con_wc_hpos_enabled() ) {
                    $order = wc_get_order( get_the_ID() );
                    $meta  = $order->get_meta( '_alg_wc_custom_order_number' );
                } else {
                    $meta = get_post_meta( get_the_ID(), '_alg_wc_custom_order_number', true );
                }
                ?>
                <input type="number" name="alg_wc_custom_order_number" style="width:100%;" value="<?php echo esc_attr( $meta ); ?>">
                <input type="hidden" name="alg_wc_custom_order_numbers_meta_box">
                <?php
            }

            /**
             * Renumerate orders function.
             *
             * @version 1.1.2
             * @since   1.0.0
             */
            public function renumerate_orders() {
                $total_renumerated = 0;
                $last_renumerated  = 0;
                $offset            = 0;
                $block_size        = 512;
                while ( true ) {
                    $args        = array(
                        'type'    => array( 'shop_order', 'shop_subscription' ),
                        'status'  => 'any',
                        'limit'   => $block_size,
                        'orderby' => 'date',
                        'order'   => 'ASC',
                        'offset'  => $offset,
                        'return'  => 'ids',
                    );
                    $loop_orders = wc_get_orders( $args );
                    if ( count( $loop_orders ) <= 0 ) {
                        break;
                    }
                    foreach ( $loop_orders as $order_id ) {
                        $last_renumerated = $this->add_order_number_meta( $order_id, true );
                        $total_renumerated++;
                    }
                    $offset += $block_size;
                }
                return array( $total_renumerated, $last_renumerated );
            }

            /**
             * Function search_by_custom_number.
             *
             * @param array $metakeys Array of the metakeys to search order numbers on shop order page.
             * @version 1.3.0
             * @since   1.3.0
             */
            public function search_by_custom_number( $metakeys ) {
                $metakeys[] = '_alg_wc_full_custom_order_number';
                $metakeys[] = '_alg_wc_custom_order_number';
                return $metakeys;
            }

            /**
             * Display order number.
             *
             * @param string $order_number - Custom Order Number.
             * @param object $order - WC_Order object.
             *
             * @version 1.2.1
             * @since   1.0.0
             */
            public function display_order_number( $order_number, $order ) {
                $is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
                $order_id              = ( $is_wc_version_below_3 ? $order->id : $order->get_id() );
                $order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
                $con_wc_hpos_enabled   = $this->con_wc_hpos_enabled();
                if ( 'yes' !== get_option( 'alg_custom_order_numbers_show_admin_notice', '' ) || 'yes' === get_option( 'alg_custom_order_numbers_no_old_orders_to_update', '' ) ) {
                    // This code of block is added to update the meta key '_alg_wc_full_custom_order_number' in the subscription orders as the order numbers were getting changed after the database update.
                    if ( $con_wc_hpos_enabled ) {
                        $subscription_orders_updated = $order->get_meta( 'subscription_orders_updated' );
                    } else {
                        $subscription_orders_updated = get_post_meta( $order_id, 'subscription_orders_updated', true );
                    }
                    if ( 'yes' !== $subscription_orders_updated ) {
                        if ( $con_wc_hpos_enabled ) {
                            $post_type = OrderUtil::get_order_type( $order_id );
                        } else {
                            $post_type = get_post_type( $order_id );
                        }
                        if ( 'shop_subscription' === $post_type ) {
                            if ( $con_wc_hpos_enabled ) {
                                $order_number_meta = $order->get_meta( '_alg_wc_custom_order_number' );
                            } else {
                                $order_number_meta = get_post_meta( $order_id, '_alg_wc_custom_order_number', true );
                            }
                            if ( '' === $order_number_meta ) {
                                $order_number_meta = $order_id;
                            }
                            $custom_order_numbers_prefix = $this->alg_wc_custom_order_numbers_prefix;
                            if ( '' === $custom_order_numbers_prefix ) {
                                $custom_order_numbers_prefix = get_bloginfo( 'name' )." : ".$this->alg_wc_custom_order_numbers_prefix;
                            }
                            $order_number = apply_filters(
                                'alg_wc_custom_order_numbers',
                                sprintf( '%s%s', do_shortcode( $custom_order_numbers_prefix ), $order_number_meta ),
                                'value',
                                array(
                                    'order_timestamp'   => $order_timestamp,
                                    'order_number_meta' => $order_number_meta,
                                )
                            );
                            if ( $con_wc_hpos_enabled ) {
                                $order->update_meta_data( '_alg_wc_full_custom_order_number', $order_number );
                                $order->update_meta_data( 'subscription_orders_updated', 'yes' );
                                $order->save();
                            } else {
                                update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $order_number );
                                update_post_meta( $order_id, 'subscription_orders_updated', 'yes' );
                            }
                            return $order_number;
                        }
                    }
                    if ( $con_wc_hpos_enabled ) {
                        $order_number_meta = $order->get_meta( '_alg_wc_full_custom_order_number' );
                    } else {
                        $order_number_meta = get_post_meta( $order_id, '_alg_wc_full_custom_order_number', true );
                    }
                    // This code of block is added to update the meta key '_alg_wc_full_custom_order_number' in new orders which were placed after the update of v1.3.0 where counter type is set to order id.
                    if ( $con_wc_hpos_enabled ) {
                        $new_orders_updated = $order->get_meta( 'new_orders_updated' );
                    } else {
                        $new_orders_updated = get_post_meta( $order_id, 'new_orders_updated', true );
                    }
                    
                    $custom_order_numbers_prefix = $this->alg_wc_custom_order_numbers_prefix;
                    if ( '' === $custom_order_numbers_prefix ) {
                        $custom_order_numbers_prefix = get_bloginfo( 'name' )." : ".$this->alg_wc_custom_order_numbers_prefix;
                    }
                    
                    if ( 'yes' !== $new_orders_updated ) {
                        $counter_type = 'sequential';
                        if ( 'order_id' === $counter_type ) {
                            $order_number_meta = $order_id;
                            $order_number      = apply_filters(
                                'alg_wc_custom_order_numbers',
                                sprintf( '%s%s', do_shortcode( $custom_order_numbers_prefix ), $order_number_meta ),
                                'value',
                                array(
                                    'order_timestamp'   => $order_timestamp,
                                    'order_number_meta' => $order_number_meta,
                                )
                            );
                            if ( $con_wc_hpos_enabled ) {
                                $order->update_meta_data( '_alg_wc_full_custom_order_number', $order_number );
                                $order->update_meta_data( 'new_orders_updated', 'yes' );
                                $order->save();
                            } else {
                                update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $order_number );
                                update_post_meta( $order_id, 'new_orders_updated', 'yes' );
                            }
                            return $order_number;
                        }
                    }
                    
                    $custom_order_numbers_prefix = $this->alg_wc_custom_order_numbers_prefix;
                    if ( '' === $custom_order_numbers_prefix ) {
                        $custom_order_numbers_prefix = get_bloginfo( 'name' )." : ".$this->alg_wc_custom_order_numbers_prefix;
                    }
                    
                    if ( '' === $order_number_meta ) {
                        $order_number_meta = $order_id;
                        $order_number_meta = apply_filters(
                            'alg_wc_custom_order_numbers',
                            sprintf( '%s%s', do_shortcode( $custom_order_numbers_prefix ), $order_number_meta ),
                            'value',
                            array(
                                'order_timestamp'   => $order_timestamp,
                                'order_number_meta' => $order_number_meta,
                            )
                        );
                    }
                    return $order_number_meta;
                } else {
                    if ( $con_wc_hpos_enabled ) {
                        $order_number_meta = $order->get_meta( '_alg_wc_custom_order_number' );
                    } else {
                        $order_number_meta = get_post_meta( $order_id, '_alg_wc_custom_order_number', true );
                    }
                    if ( '' === $order_number_meta ) {
                        $order_number_meta = $order_id;
                    }
                     
                    $custom_order_numbers_prefix = $this->alg_wc_custom_order_numbers_prefix;
                    if ( '' === $custom_order_numbers_prefix ) {
                        $custom_order_numbers_prefix = get_bloginfo( 'name' )." : ".$this->alg_wc_custom_order_numbers_prefix;
                    }
                    
                    $order_number = apply_filters(
                        'alg_wc_custom_order_numbers',
                        sprintf( '%s%s', do_shortcode( $custom_order_numbers_prefix ), $order_number_meta ),
                        'value',
                        array(
                            'order_timestamp'   => $order_timestamp,
                            'order_number_meta' => $order_number_meta,
                        )
                    );
                    return $order_number;
                }
                return $order_number;
            }

            /**
             * Add_new_order_number.
             *
             * @param int $order_id - Order ID.
             *
             * @version 1.0.0
             * @since   1.0.0
             */
            public function add_new_order_number( $order_id ) {
                $this->add_order_number_meta( $order_id, false );
            }

            /**
             * Add/update order_number meta to order.
             *
             * @param int  $order_id - Order ID.
             * @param bool $do_overwrite - Change the order number to a custom number.
             *
             * @version 1.2.0
             * @since   1.0.0
             */
            public function add_order_number_meta( $order_id, $do_overwrite ) {
                $con_wc_hpos_enabled = $this->con_wc_hpos_enabled();
                if ( $con_wc_hpos_enabled ) {
                    if ( ! in_array( OrderUtil::get_order_type( $order_id ), array( 'shop_order', 'shop_subscription' ), true ) ) {
                        return false;
                    }
                }
                if ( ! $con_wc_hpos_enabled ) {
                    if ( ! in_array( get_post_type( $order_id ), array( 'shop_order', 'shop_subscription' ), true ) ) {
                        return false;
                    }
                }
                $order = wc_get_order( $order_id );
                if ( true === $do_overwrite || '' ==  ( $con_wc_hpos_enabled ? $order->get_meta( '_alg_wc_custom_order_number' ) : get_post_meta( $order_id, '_alg_wc_custom_order_number', true ) ) ) { // phpcs:ignore
                    $is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
                    $order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
                    $counter_type          = 'sequential';
                    if ( 'sequential' === $counter_type ) {
                        // Using MySQL transaction, so in case of a lot of simultaneous orders in the shop - prevent duplicate sequential order numbers.
                        global $wpdb;
                        $wpdb->query( 'START TRANSACTION' ); //phpcs:ignore
                        $wp_options_table = $wpdb->prefix . 'options';
                        $result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . $wpdb->prefix . 'options` WHERE option_name = %s', 'alg_wc_custom_order_numbers_counter' ) ); //phpcs:ignore
                        if ( null !== $result_select ) {
                            $current_order_number     = $this->maybe_reset_sequential_counter( $result_select->option_value, $order_id );
                            $result_update            = $wpdb->update( // phpcs:ignore
                                $wp_options_table,
                                array( 'option_value' => ( $current_order_number + 1 ) ),
                                array( 'option_name' => 'alg_wc_custom_order_numbers_counter' )
                            );
                            
                            $custom_order_numbers_prefix = $this->alg_wc_custom_order_numbers_prefix;
                            if ( '' === $custom_order_numbers_prefix ) {
                                $custom_order_numbers_prefix = get_bloginfo( 'name' )." : ".$this->alg_wc_custom_order_numbers_prefix;
                            }
                            
                            $current_order_number_new = $current_order_number + 1;
                            if ( null !== $result_update || $current_order_number_new === $result_select->option_value ) {
                                $full_custom_order_number = apply_filters(
                                    'alg_wc_custom_order_numbers',
                                    sprintf( '%s%s', do_shortcode( $custom_order_numbers_prefix ), $current_order_number ),
                                    'value',
                                    array(
                                        'order_timestamp'   => $order_timestamp,
                                        'order_number_meta' => $current_order_number,
                                    )
                                );
                                // all ok.
                                $wpdb->query( 'COMMIT' ); //phpcs:ignore
                                if ( $con_wc_hpos_enabled ) {
                                    $order->update_meta_data( '_alg_wc_custom_order_number', $current_order_number );
                                    $order->update_meta_data( '_alg_wc_full_custom_order_number', $full_custom_order_number );
                                    $order->save();
                                } else {
                                    update_post_meta( $order_id, '_alg_wc_custom_order_number', $current_order_number );
                                    update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $full_custom_order_number );
                                }
                            } else {
                                // something went wrong, Rollback.
                                $wpdb->query( 'ROLLBACK' ); //phpcs:ignore
                                return false;
                            }
                        } else {
                            // something went wrong, Rollback.
                            $wpdb->query( 'ROLLBACK' ); //phpcs:ignore
                            return false;
                        }
                    }
                    return $current_order_number;
                }
                return false;
            }

            /**
             * Updates the custom order number for a renewal order created
             * using WC Subscriptions
             *
             * @param WC_Order $renewal_order - Order Object of the renewed order.
             * @param object   $subscription - Subscription for which the order has been created.
             * @return WC_Order $renewal_order
             * @since 1.2.6
             */
            public function remove_order_meta_renewal( $renewal_order, $subscription ) {
                $new_order_id = $renewal_order->get_id();
                // update the custom order number.
                $this->add_order_number_meta( $new_order_id, true );
                return $renewal_order;
            }

            /**
             * Updates the custom order number for the WC Subscription
             *
             * @param object $subscription - Subscription for which the order has been created.
             * @since 1.2.6
             */
            public function update_custom_order_meta( $subscription ) {

                $subscription_id = $subscription->get_id();
                // update the custom order number.
                $this->add_order_number_meta( $subscription_id, true );

            }

            /**
             * Remove the WooCommerc filter which convers the order numbers to integers by removing the * * characters.
             */
            public function alg_remove_tracking_filter() {
                remove_filter( 'woocommerce_shortcode_order_tracking_order_id', 'wc_sanitize_order_id' );
            }

            /**
             * Function to unset the CON meta key at the time of renewal of subscription.
             *
             * @param Array  $meta Array of a meta key present in the subscription.
             * @param Object $to_order  Order object.
             * @param Objec  $from_order Subscription object.
             */
            public function remove_con_metakey_in_wcs_order_meta( $meta, $to_order, $from_order ) {
                $to_order_id = $to_order->get_id();
                if ( $this->con_wc_hpos_enabled() ) {
                    $from_order_type = OrderUtil::get_order_type( $from_order->get_id() );
                } else {
                    $from_order_type = get_post_type( $from_order->get_id() );
                }
                if ( 0 === $to_order_id && 'shop_subscription' === $from_order_type ) {
                    foreach ( $meta as $key => $value ) {
                        if ( '_alg_wc_custom_order_number' === $value['meta_key'] ) {
                            unset( $meta[ $key ] );
                        }
                        if ( '_alg_wc_full_custom_order_number' === $value['meta_key'] ) {
                            unset( $meta[ $key ] );
                        }
                    }
                }
                return $meta;
            }

            /**
             * Function to see if prefix value is changed or not.
             *
             * @param string $new_value New setting value which is selected.
             * @param string $old_value Old setting value which is saved in the database.
             */
            public function pre_alg_wc_custom_order_numbers_prefix( $new_value, $old_value ) {
                if ( $new_value !== $old_value ) {
                    update_option( 'alg_wc_custom_order_numbers_prefix_suffix_changed', '1' );
                }
                return $new_value;
            }
    
    
    
    
    
    
    
}









if ( ! class_exists( 'Alg_WC_Custom_Order_Numbers' ) ) :

    /**
     * Main Alg_WC_Custom_Order_Numbers Class
     *
     * @class   Alg_WC_Custom_Order_Numbers
     * @version 1.2.3
     * @since   1.0.0
     */
    final class Alg_WC_Custom_Order_Numbers {

        /**
         * Plugin version.
         *
         * @var   string
         * @since 1.0.0
         */
        public $version = '1.4.0';

        /**
         * The single instance of the class
         *
         * @var   Alg_WC_Custom_Order_Numbers The single instance of the class
         * @since 1.0.0
         */
        protected static $instance = null;

        /**
         * Main Alg_WC_Custom_Order_Numbers Instance
         *
         * Ensures only one instance of Alg_WC_Custom_Order_Numbers is loaded or can be loaded.
         *
         * @version 1.0.0
         * @since   1.0.0
         * @static
         * @return  Alg_WC_Custom_Order_Numbers - Main instance
         */
        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Alg_WC_Custom_Order_Numbers Constructor.
         *
         * @version 1.0.0
         * @since   1.0.0
         * @access  public
         */
        public function __construct() {

            // Set up localisation.
            load_plugin_textdomain( 'epayco_woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );

            // Include required files.
            $this->includes();

            // Settings & Scripts.
            if ( is_admin() ) {
                add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_woocommerce_settings_tab' ) );
            }
        }

        /**
         * Include required core files used in admin and on the frontend.
         *
         * @version 1.2.0
         * @since   1.0.0
         */
        public function includes() {
            // Settings.
            //require_once 'includes/admin/class-alg-wc-custom-order-numbers-settings-section.php';
            $this->settings            = array();
            //$this->settings['general'] = require_once 'includes/admin/class-alg-wc-custom-order-numbers-settings-general.php';
            if ( is_admin() && get_option( 'alg_custom_order_numbers_version', '' ) !== $this->version ) {
                foreach ( $this->settings as $section ) {
                    foreach ( $section->get_settings() as $value ) {
                        if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
                            $autoload = isset( $value['autoload'] ) ? (bool) $value['autoload'] : true;
                            add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
                        }
                    }
                }
                if ( '' !== get_option( 'alg_custom_order_numbers_version', '' ) ) {
                    update_option( 'alg_custom_order_numbers_show_admin_notice', 'yes' );
                }
                if ( '' !== get_option( 'alg_custom_order_numbers_version', '' ) && '1.3.0' > get_option( 'alg_custom_order_numbers_version', '' ) ) {
                    update_option( 'alg_custom_order_numbers_no_meta_admin_notice', 'yes' );
                }
                update_option( 'alg_custom_order_numbers_version', $this->version );
            }
            // Core file needed.
            //require_once 'includes/class-alg-wc-custom-order-numbers-core.php';
        }

        /**
         * Add Custom Order Numbers settings tab to WooCommerce settings.
         *
         * @param array $settings - List containing all the plugin files which will be displayed in the Settings.
         * @return array $settings
         *
         * @version 1.2.2
         * @since   1.0.0
         */
        public function add_woocommerce_settings_tab( $settings ) {
            $settings[] = include 'admin/class-alg-wc-settings-custom-order-numbers.php';
            return $settings;
        }

        /**
         * Get the plugin url.
         *
         * @version 1.0.0
         * @since   1.0.0
         * @return  string
         */
        public function plugin_url() {
            return untrailingslashit( plugin_dir_url( __FILE__ ) );
        }

        /**
         * Get the plugin path.
         *
         * @version 1.0.0
         * @since   1.0.0
         * @return  string
         */
        public function plugin_path() {
            return untrailingslashit( plugin_dir_path( __FILE__ ) );
        }

    }

endif;

if ( ! function_exists( 'alg_wc_custom_order_numbers' ) ) {
    /**
     * Returns the main instance of Alg_WC_Custom_Order_Numbers to prevent the need to use globals.
     *
     * @version 1.0.0
     * @since   1.0.0
     * @return  Alg_WC_Custom_Order_Numbers
     */
    function alg_wc_custom_order_numbers() {
        return Alg_WC_Custom_Order_Numbers::instance();
    }
}

alg_wc_custom_order_numbers();
