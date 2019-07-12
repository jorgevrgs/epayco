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

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Epayco extends PaymentModule
{
    protected $config_form = false;
    const FRANCHISE = [
        'AM' => 'Amex',
        'BA' => 'Baloto',
        'CR' => 'Credencial',
        'DC' => 'Diners Club',
        'EF' => 'Efecty',
        'GA' => 'Gana',
        'PR' => 'Punto Red',
        'RS' => 'Red Servi',
        'MC' => 'Mastercard',
        'PSE' => 'PSE',
        'SP' => 'SafetyPay',
        'VS' => 'Visa',
    ];

    public function __construct()
    {
        $this->name = 'epayco';
        $this->tab = 'payments_gateways';
        $this->version = '0.0.1';
        $this->author = 'Jorge Vargas';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('ePayco');
        $this->description = $this->l('ePayco payment gateway');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall ePayco module?');

        $this->limited_countries = array('CO');

        $this->limited_currencies = array('COP', 'USD');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (in_array($iso_code, $this->limited_countries) == false)
        {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }

        Configuration::updateValue('EPAYCO_LIVE_MODE', false);

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('payment') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('actionPaymentCCAdd') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('displayPayment') &&
            $this->registerHook('displayPaymentReturn') &&
            $this->registerHook('displayPaymentTop') &&
            $this->registerHook('paymentOptions');
    }

    public function uninstall()
    {
        Configuration::deleteByName('EPAYCO_LIVE_MODE');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitEpaycoModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitEpaycoModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'EPAYCO_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Open "Integraciones" --> "Llaves API" and search "Llaves secretas"'),
                        'name' => 'EPAYCO_PUBLIC_KEY',
                        'label' => $this->l('Public Key'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Open "Integraciones" --> "Llaves API" and search "Llaves secretas"'),
                        'name' => 'EPAYCO_PRIVATE_KEY',
                        'label' => $this->l('Private Key'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('OnPage Checkout'),
                        'name' => 'EPAYCO_ONPAGE_CHECKOUT',
                        'is_bool' => true,
                        'desc' => $this->l('If you set No, you will use standard redirect payment process,
                        else, set Yes to use Onpage Checkout'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'EPAYCO_LIVE_MODE' => Configuration::get('EPAYCO_LIVE_MODE', false),
            'EPAYCO_PUBLIC_KEY' => Configuration::get('EPAYCO_PUBLIC_KEY', null),
            'EPAYCO_PRIVATE_KEY' => Configuration::get('EPAYCO_PRIVATE_KEY', null),
            'EPAYCO_ONPAGE_CHECKOUT' => Configuration::get('EPAYCO_ONPAGE_CHECKOUT', true),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * This method is used to render the payment button,
     * Take care if the button should be displayed or not.
     */
    /*
    public function hookPayment($params)
    {
        $currency_id = $params['cart']->id_currency;
        $currency = new Currency((int)$currency_id);

        if (in_array($currency->iso_code, $this->limited_currencies) == false)
            return false;

        $this->smarty->assign('module_dir', $this->_path);

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }
    */

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false)
            return;

        $order = $params['objOrder'];

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR'))
            $this->smarty->assign('status', 'ok');

        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,
            'params' => $params,
            'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
        ));

        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
    }

    public function hookActionPaymentCCAdd()
    {
        /* Place your code here. */
    }

    public function hookActionPaymentConfirmation()
    {
        /* Place your code here. */
    }

    public function hookDisplayPayment()
    {
        /* Place your code here. */
    }

    public function hookDisplayPaymentReturn()
    {
        /* Place your code here. */
    }

    public function hookDisplayPaymentTop()
    {
        /* Place your code here. */
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active
        || !(Configuration::get('EPAYCO_LIVE_MODE'))
        || !$this->checkCurrency($params['cart'])) {
            return;
        }

        if (Configuration::get('EPAYCO_ONPAGE_CHECKOUT')) {
            $payment_options = [
                $this->getEmbeddedPaymentOption(),
            ];
        } else {
            $payment_options = [
                $this->getExternalPaymentOption(),
            ];
        }

        return $payment_options;
    }

    /**
     * @param array $params [
     *   'x_cust_id_cliente',
     *   'x_key',
     *   'x_ref_payco',
     *   'x_transaction_id',
     *   'x_amount',
     *   'x_currency_code',
     * ]
     */
    public function getSignature($params) : string
    {
        if (!is_array($params)
        && empty($params)) {
            return false;
        }

        $count = count($params);
        $data = '';
        for ($i = 0; $i < $count - 1; $i++) {
            if (!empty($params[$i + 1])) {
                $data .= $params[$i].'^';
            } else {
                $data .= $params[$i];
            }
        }

        return hash('sha256', $data);
    }

    /**
     * @param int $code
     *
     * x_cod_transaction_state - x_transaction_state
     * 1	Aceptada
     * 2	Rechazada
     * 3	Pendiente
     * 4	Fallida
     * 6	Reversada
     * 7	Retenida
     * 8	Iniciada
     * 9	Exprirada
     * 10	Abandonada
     * 11	Cancelada
     * 12	Antifraude
     *
     */
    public function getOrderStatusId($code) : int
    {
        switch $code {
            case 1:
                $idOrderStatus = Configuration::get('PS_OS_PAYMENT');
                break;

            case 9:
            case 10:
            case 11:
                $idOrderStatus = Configuration::get('PS_OS_CANCELED');
                break;

            case 3:
            case 8:
                $idOrderStatus = Configuration::get('PS_OS_WS_PAYMENT');
                break;

            case 6:
                $idOrderStatus = Configuration::get('PS_OS_REFUND');
                break;

            case 2:
            case 4:
            case 7:
            case 12:
            default:
                $idOrderStatus = Configuration::get('PS_OS_ERROR');
        };

        return $idOrderStatus;
    }

    /**
     * Use for OnPage Checkout
     */
    public function getEmbeddedPaymentOption()
    {
        $embeddedOption = new PaymentOption();
        $embeddedOption
            ->setCallToActionText($this->l('ePayco'))
            ->setAction($this->context->link->getModuleLink($this->name, 'confirmation', array(), true))
            ->setInputs([
                'external' => [
                    'name' => 'external',
                    'type' => 'hidden',
                    'value' => true,
                ],
            ])
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/logo.png'));

        return $externalOption;
    }

    /**
     * Use for Standard Checkout
     */
    public function getExternalPaymentOption()
    {
        $externalOption = new PaymentOption();
        $externalOption
            ->setCallToActionText($this->l('ePayco'))
            ->setAction($this->context->link->getModuleLink($this->name, 'confirmation', array(), true))
            ->setInputs([
                'external' => [
                    'name' => 'external',
                    'type' => 'hidden',
                    'value' => false,
                ],
            ])
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/logo.png'));

        return $externalOption;
    }

    public function checkCurrency(Cart $cart)
    {
        $currency_cart = new Currency($cart->id_currency);

        if (in_array($currency_cart->iso_code, $this->limited_currencies)) {
            return true;
        }

        return false;
    }
}
