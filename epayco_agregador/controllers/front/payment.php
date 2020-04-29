<?php
/**
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2017 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class Epayco_agregadorPaymentModuleFrontController extends ModuleFrontController
{
        public $ssl = true;
        public $display_column_left = false;
        public $display_column_right = false;

        /**
	 * @see FrontController::initContent()
	 */
        public function initContent()
        { 
                parent::initContent();

                $cart = $this->context->cart;
		if (!$this->module->checkCurrency($cart))
			Tools::redirect('index.php?controller=order');

                $this->context->smarty->assign(array(
                        'nbProducts' => $cart->nbProducts(),
                        'cust_currency' => $cart->id_currency,
                        'currencies' => $this->module->getCurrency((int)$cart->id_currency),
			'total' => $cart->getOrderTotal(true, Cart::BOTH),
                        'iva' => $cart->getOrderTotal(true, Cart::BOTH) - $cart->getOrderTotal(false, Cart::BOTH),
                        'baseDevolucionIva' => $cart->getOrderTotal(false, Cart::BOTH),
                        'isoCode' => $this->context->language->iso_code,
                        'merchandid' => $this->module->p_cust_id_cliente,
                        'merchanttest' => $this->module->p_test_request,
                        'p_key'=>$this->module->p_key,
                        'this_path' => $this->module->getPathUri(),
                        'this_path_bw' => $this->module->getPathUri(),
                        'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
                ));

                $this->setTemplate('payment_execution.tpl');
        }
}
?>