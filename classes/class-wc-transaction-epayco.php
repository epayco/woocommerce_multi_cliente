<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Epayco_Transaction_Handler
{
    public static function handle_transaction($order, $data, $settings)
    {
        $order_id = $order->get_id();
        $current_state = $order->get_status();
        $modo = $settings['test_mode'] === 'true' ? 'pruebas' : 'Producción';

        self::save_epayco_metadata($order, $modo, $data);

        $estado_final_exitoso = self::get_success_status($settings);
        $estado_cancelado = self::get_cancel_status($settings);

        switch ((int) $data['x_cod_transaction_state']) {
            case 1:
                self::handle_approved($order, $order_id, $current_state, $settings, $estado_final_exitoso);
                echo '1';
                break;
            case 2:
            case 4:
            case 10:
            case 11:
                self::handle_failed($order, $current_state, $estado_cancelado, $data['is_confirmation'], $settings);
                echo '2';
                break;
            case 3:
            case 7:
                self::handle_pending($order, $order_id, $current_state, $settings);
                echo '3';
                break;
            case 6:
                self::handle_reversed($order);
                echo '6';
                break;
            default:
                self::handle_default($order, $current_state);
                echo 'default';
                break;
        }
    }

    private static function save_epayco_metadata($order, $modo, $data)
    {
        $order->update_meta_data('refDavipay', esc_attr($data['x_ref_payco']));
        $order->update_meta_data('modo', esc_attr($modo));
        $order->update_meta_data('fecha', esc_attr($data['x_fecha_transaccion']));
        $order->update_meta_data('franquicia', esc_attr($data['x_franchise']));
        $order->update_meta_data('autorizacion', esc_attr($data['x_approval_code']));
        $order->save();
    }

    public static function get_payment_reference($order)
    {
        $reference = $order->get_meta('refDavipay');
        if ($reference === '' || $reference === null) {
            $reference = $order->get_meta('refPayco');
        }
        return $reference;
    }

    private static function get_success_status($settings)
    {
        if ($settings['test_mode'] === 'true') {
            return ($settings['end_order_state'] === 'processing') ? 'processing_test' :
                (($settings['end_order_state'] === 'completed') ? 'completed_test' :
                    (($settings['end_order_state'] === 'epayco-processing') ? 'epayco_processing' :
                        (($settings['end_order_state'] === 'epayco-completed') ? 'epayco_completed' : $settings['end_order_state'])));
        }
        return $settings['end_order_state'];
    }

    private static function get_cancel_status($settings)
    {
        if ($settings['test_mode'] === 'true') {
            return ($settings['cancel_order_state'] === 'cancelled') ? 'cancelled' :
                (($settings['cancel_order_state'] === 'epayco-cancelled') ? 'epayco_cancelled' :
                    (($settings['cancel_order_state'] === 'epayco-failed') ? 'epayco_failed' : 'failed'));
        }
        return $settings['cancel_order_state'];
    }

    private static function success_states()
    {
        return array('processing', 'completed', 'processing_test', 'completed_test', 'epayco-processing', 'epayco-completed', 'epayco_processing', 'epayco_completed');
    }

    private static function handle_approved($order, $order_id, $current_state, $settings, $estado_final_exitoso)
    {
        $logger = new WC_Logger();
        try {
            $reduce_pending = ($settings['reduce_stock_pending'] ?? null) === 'yes';
            if ($current_state === 'pending') {
                $order->update_status('on-hold');
                $order->save();
                if ($reduce_pending) {
                    self::reduce_stock($order_id);
                }
                return;
            }

            if (! in_array($current_state, self::success_states(), true)) {
                $order->payment_complete(self::get_payment_reference($order));
                $order->update_status($estado_final_exitoso);
                $order->save();
            }

            // Use WooCommerce's native idempotent order stock reduction.
            self::reduce_stock($order_id);
        } catch (\Exception $ex) {
            $error_message = "handle_approved got error: {$ex->getMessage()}";
            $logger->add('handle_approved', $error_message);
            throw new \Exception($error_message);
        }
    }

    private static function handle_failed($order, $current_state, $estado_cancelado, $isConfirmation, $settings)
    {
        $logger = new WC_Logger();
        try {
            // Do not let a late failed webhook cancel an already-paid order.
            if (in_array($current_state, self::success_states(), true)) {
                return;
            }

            $order->update_status($estado_cancelado);
            $order->save();
            self::restore_stock_once($order->get_id());

            if (($settings['reduce_stock_pending'] ?? null) === 'yes' && $current_state === 'pending' && ! $isConfirmation) {
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }
        } catch (\Exception $ex) {
            $error_message = "handle_failed got error: {$ex->getMessage()}";
            $logger->add('handle_failed', $error_message);
            throw new \Exception($error_message);
        }
    }

    private static function handle_pending($order, $order_id, $current_state, $settings)
    {
        if (in_array($current_state, self::success_states(), true)) {
            return;
        }

        if ($current_state !== 'on-hold') {
            $order->update_status('on-hold');
            $order->save();
        }

        if (($settings['reduce_stock_pending'] ?? null) === 'yes') {
            self::reduce_stock($order_id);
        }
    }

    private static function handle_reversed($order)
    {
        $order->update_status('refunded');
        $order->add_order_note('Pago Reversado');
        self::restore_stock_once($order->get_id());
    }

    private static function handle_default($order, $current_state)
    {
        if (! in_array($current_state, self::success_states(), true)) {
            $order->update_status('epayco-failed');
            $order->add_order_note('Pago fallido o abandonado');
            self::restore_stock_once($order->get_id());
        }
    }

    public static function reduce_stock($order_id)
    {
        if (function_exists('wc_reduce_stock_levels')) {
            wc_reduce_stock_levels($order_id);
        }
    }

    public static function restore_stock_once($order_id)
    {
        if (function_exists('wc_increase_stock_levels')) {
            wc_increase_stock_levels($order_id);
        }
    }

    // Preserve compatibility with callers that still use this method.
    public static function restore_stock($order_id, $direction = 'increase')
    {
        if ($direction === 'decrease') {
            self::reduce_stock($order_id);
        } else {
            self::restore_stock_once($order_id);
        }
    }
}
