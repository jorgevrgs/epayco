<?php
/**
* 2007-2019 PrestaShop
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
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/
class EpaycoPaymentModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        Media::addJsDef([
            'epayco_public_key' => Configuration::get('EPAYCO_PUBLIC_KEY'),
            'epayco_test' => Configuration::get('EPAYCO_LIVE_MODE') ? 'false' : 'true',
        ]);

        parent::initContent();
    }

    public function postProcess()
    {
        if (Tools::getIsset('order_id')
        && (Tools::getIsset('secure_key'))) {
            // TODO generate form
            $order_id = (int) Tools::getValue('order_id');
            $order = new Order($order_id);

            if ($order->id_customer != $this->context->customer->id) {
                throw new PrestaShopException($this->module->l('Error in authenticated customer'));
            }

            $cart_id = $order->id_cart;
            $secure_key = $order->secure_key;

            if ($secure_key != Tools::getValue('secure_key')) {
                throw new PrestaShopException($this->module->l('Error in secure key'));
            }

            $customer = new Customer((int) $order->id_customer);

            if ($order_id && ($secure_key == $customer->secure_key)) {
                /**
                 * The order has been placed so we redirect the customer on the confirmation page.
                 */
                $module_id = $this->module->id;
                $query = [
                    'id_cart' => $cart_id,
                    'id_module' => $module_id,
                    'id_order' => $order_id,
                    'key' => $secure_key,
                ];

                $this->context->smarty->assign([
                    'order_confirmation' => $this->context->link->getPageLink(
                        'order-confirmation',
                        true,
                        null,
                        http_build_query($query)
                    ),
                    'epayco_form' => $this->getFormParams($order),
                ]);
            } else {
                /*
                 * An error occured and is shown on a new page.
                 */
                $this->errors[] = $this->module->l('An error occured. Please contact the merchant to have more informations');
            }
        } elseif (Tools::getIsset('order_id')) {
            // TODO retry
        }

        return $this->setTemplate('module:epayco/views/templates/front/payment.tpl');
    }

    public function getCurrency(Order $order) : string
    {
        return 'COP'; // TODO COP, USD
    }

    public function getCountry(Order $order) : string
    {
        return 'CO'; // TODO CO
    }

    public function getLang(Order $order) : string
    {
        return 'es'; // TODO es, en
    }

    public function getFormParams(Order $order) : array
    {
        $cart_id = $order->id_cart;
        $module_id = $this->module->id;
        $order_id = $order->id;
        $secure_key = $order->secure_key;
        $customer = new Customer($order->id_customer);

        $query = [
            'id_cart' => $cart_id,
            'id_module' => $module_id,
            'id_order' => $order_id,
            'key' => $secure_key,
        ];
        $confirmation = $this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            http_build_query($query)
        );

        $return = [
            'external' => Configuration::get('EPAYCO_ONPAGE_CHECKOUT') ? 'false' : 'true',
            'key' => Configuration::get('EPAYCO_PUBLIC_KEY'), // Required
            'amount' => number_format((float) $order->total_paid_tax_incl, Configuration::get('PS_PRICE_DISPLAY_PRECISION'), '.', ''), // Required
            'tax' => number_format((float) $order->total_paid_tax_incl - $order->total_paid_tax_excl, Configuration::get('PS_PRICE_DISPLAY_PRECISION'), '.', ''),
            'tax-base' => number_format((float) $order->total_paid_tax_excl, Configuration::get('PS_PRICE_DISPLAY_PRECISION'), '.', ''),
            'name' => sprintf($this->module->l('Order #%06d'), $order->id), // Required
            'description' => sprintf($this->module->l('Order with reference %s'), $order->reference), // Required
            'currency' => $this->getCurrency($order), // Required
            'country' => $this->getCountry($order),
            'lang' => $this->getLang($order),
            'test' => Configuration::get('EPAYCO_LIVE_MODE') ? 'false' : 'true', // Required
            'invoice' => $order_id,
            'extra1' => $module_id,
            'extra2' => $secure_key,
            'extra3' => '',
            'button' => 'https://369969691f476073508a-60bf0867add971908d4f26a64519c2aa.ssl.cf5.rackcdn.com/btns/epayco/boton_de_cobro_epayco4.png',
            'confirmation' => $this->context->link->getModuleLink(
                $this->module->name,
                'update'
            ),
            'autoclick' => 'true',
            'email-billing' => $customer->email,
            'name-billing' => $customer->firstname.' '.$customer->lastname,
        ];

        if (Configuration::get('EPAYCO_ONPAGE_CHECKOUT')) {
            $return['response'] = $confirmation;
        } else {
            $return['acepted'] = $confirmation;
            $return['rejected'] = $confirmation;
            $return['pending'] = $confirmation;
        }

        return $return;
    }

    /**
     * @see parent::setMedia()
     */
    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->registerJavascript('epayco-checkout', 'https://checkout.epayco.co/checkout.js');
        
    }
}
