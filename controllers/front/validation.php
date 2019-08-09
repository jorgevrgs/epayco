<?php
/**
* 2007-2019 Jorge Vargas
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
*  @author    Jorge Vargas <https://github.com/jorgevrgs>
*  @copyright 2007-2019 Jorge Vargas
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

class EpaycoValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if (Tools::getIsset('cart_id')
        && (Tools::getIsset('secure_key'))) {
            // Validate order
            $cart_id = Tools::getValue('cart_id');
            $secure_key = Tools::getValue('secure_key');

            $cart = new Cart((int) $cart_id);
            $customer = new Customer((int) $cart->id_customer);

            /**
             * Since it's an example we are validating the order right here,
             * You should not do it this way in your own module.
             */
            $payment_status = Configuration::get('EPAYCO_OS_PENDING'); // Default value for a payment that succeed.
            $message = null; // You can add a comment directly into the order so the merchant will see it in the BO.

            /**
             * Converting cart into a valid order
             */
            $module_name = $this->module->displayName;
            $currency_id = (int) Context::getContext()->currency->id;

            $this->module->validateOrder(
                $cart_id,
                $payment_status,
                0.00,
                $module_name,
                $message,
                array(),
                $currency_id,
                false,
                $secure_key
            );

            $order_id = Order::getOrderByCartId((int) $cart->id);

            if ($order_id && ($secure_key == $customer->secure_key)) {
                /**
                 * The order has been placed so we redirect the customer on the confirmation page.
                 */
                //$module_id = $this->module->id;

                Tools::redirect(
                    $this->context->link->getModuleLink(
                        $this->module->name,
                        'payments',
                        [
                            'order_id' => $order_id,
                            'secure_key' => $secure_key,
                        ],
                        true
                    )
                );
            } else {
                /*
                 * An error occured and is shown on a new page.
                 */
                $this->errors[] = $this->module->l('An error occured. Please contact the merchant to have more informations');
            }
        } else {
            $this->errors[] = $this->module->l('There are missing parameters to validate cart.');
        }

        return $this->setTemplate('error.tpl');
    }
}
