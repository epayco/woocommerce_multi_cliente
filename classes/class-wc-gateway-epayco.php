<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once EPAYCO_PLUGIN_CLASS_PATH . 'class-wc-transaction-epayco.php';

class WC_Gateway_Epayco extends WC_Payment_Gateway
{
