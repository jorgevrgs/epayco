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
    public $epaycoResponse;

    public function __construct()
    {
        $this->name = 'epayco';
        $this->tab = 'payments_gateways';
        $this->version = '0.0.1';
        $this->author = 'Jorge Vargas';
        $this->need_instance = 1;
        $this->controllers = array('payment', 'validation', 'update');
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('ePayco');
        $this->description = $this->l('ePayco payment gateway');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall ePayco module?');

        $this->limited_countries = array('CO');
        $this->limited_currencies = array('COP', 'USD');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->epaycoResponse = [
            'x_ref_payco' => $this->l('Payco Reference'),
            //'x_id_invoice' => $this->l('Id Invoice'),
            //'x_description' => $this->l('Description'),
            'x_amount' => $this->l('Amount'),
            //'x_amount_country' => $this->l('Amount Country'),
            //'x_amount_ok' => $this->l('Amount OK'),
            'x_tax' => $this->l('Tax'),
            'x_amount_base' => $this->l('Amount Base'),
            'x_currency_code' => $this->l('Currency Code'),
            'x_bank_name' => $this->l('Bank Name'),
            'x_cardnumber' => $this->l('Card Number'),
            'x_quotas' => $this->l('Quotas'),
            'x_response' => $this->l('Response'),
            'x_approval_code' => $this->l('Approval Code'),
            //'x_transaction_id' => $this->l('Transaction ID'),
            'x_transaction_date' => $this->l('Date'),
            //'x_cod_response' => $this->l('Code Response'),
            'x_response_reason_text' => $this->l('Response Reason Text'),
            'x_cod_transaction_state' => $this->l('Code Transaction State'),
            'x_transaction_state' => $this->l('Transaction State'),
            'x_errorcode' => $this->l('Error Code'),
            'x_franchise' => $this->l('Franchise'),
            'x_business' => $this->l('Business'),
            //'x_customer_doctype' => $this->l('Customer Doc Type'),
            //'x_customer_document' => $this->l('Customer Doc'),
            //'x_customer_name' => $this->l('Customer First Name'),
            //'x_customer_lastname' => $this->l('Customer Last Name'),
            //'x_customer_email' => $this->l('Customer Email'),
            //'x_customer_phone' => $this->l('Customer Phone'),
            //'x_customer_movil' => $this->l('Customer Mobile'),
            //'x_customer_country' => $this->l('Customer Country'),
            //'x_customer_city' => $this->l('Customer City'),
            //'x_customer_address' => $this->l('Customer Address'),
            //'x_customer_ip' => $this->l('Customer IP'),
            'x_test_request' => $this->l('Test Request'),
            'x_type_payment' => $this->l('Type Payment'),
        ];
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (in_array($iso_code, $this->limited_countries) == false) {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }

        Configuration::updateValue('EPAYCO_LIVE_MODE', false);

        //include(dirname(__FILE__).'/sql/install.php');
        $this->installOrderState();

        return parent::install() &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('actionPaymentCCAdd') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('displayPayment') &&
            $this->registerHook('displayPaymentReturn') &&
            $this->registerHook('displayPaymentTop') &&
            $this->registerHook('paymentOptions');
    }

    public function installOrderState()
    {
        if (!Configuration::get('EPAYCO_OS_PENDING')) {
            $order_state = new OrderState();
    		$order_state->name = array();
    		foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'ePayco Awaiting Payment';
    		}

    		$order_state->send_email = false;
    		$order_state->color = '#d5a933';
    		$order_state->hidden = false;
    		$order_state->delivery = false;
    		$order_state->logable = false;
    		$order_state->invoice = false;

            if ($order_state->add()) {
                Configuration::updateValue('EPAYCO_OS_PENDING', (int)$order_state->id);
                $epaycoOs = Configuration::get('EPAYCO_OS_PENDING');
                Tools::copy(
                    $this->getLocalPath().'views/img/payment_icon.gif',
                    _PS_IMG_DIR_.'os/'.(int)$epaycoOs.'.gif'
                );
            } else {
                $this->errors[] = $this->l('Error when trying to add new order state');
            }
        }

        return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName('EPAYCO_LIVE_MODE');

        //include(dirname(__FILE__).'/sql/uninstall.php');
        $this->uninstallOrderState();

        return parent::uninstall();
    }

    public function uninstallOrderState()
    {
        $orderState = new OrderState(Configuration::get('EPAYCO_OS_PENDING'));
        $orderState->delete();
        Configuration::deleteByName('EPAYCO_OS_PENDING');

        return true;
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
                        'desc' => $this->l('You can find it at the top right of the page in your admin account.'),
                        'name' => 'EPAYCO_CLIENT_ID',
                        'label' => $this->l('Client Id'),
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
                    array(
                        //'col' => 3,
                        'type' => 'textarea',
                        //'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Show help information to customer before payment methods list.'),
                        'name' => 'EPAYCO_PAYMENT_TOP',
                        'label' => $this->l('Payment Top Message'),
                        'autoload_rte' => true,
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
            'EPAYCO_CLIENT_ID' => Configuration::get('EPAYCO_CLIENT_ID', false),
            'EPAYCO_PUBLIC_KEY' => Configuration::get('EPAYCO_PUBLIC_KEY', null),
            'EPAYCO_PRIVATE_KEY' => Configuration::get('EPAYCO_PRIVATE_KEY', null),
            'EPAYCO_ONPAGE_CHECKOUT' => Configuration::get('EPAYCO_ONPAGE_CHECKOUT', true),
            'EPAYCO_PAYMENT_TOP' => Configuration::get('EPAYCO_PAYMENT_TOP', ''),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            if ($key != 'EPAYCO_PAYMENT_TOP') {
                Configuration::updateValue($key, Tools::getValue($key));
            }
        }

        Configuration::updateValue('EPAYCO_PAYMENT_TOP', Tools::getValue($key), true);
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookDisplayHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookDisplayPaymentReturn($params)
    {
        if ($this->active == false
        || Tools::getValue('id_module') != $this->id) {
            return;
        }

        $order = $params['order'];
        $epayco = [];

        if (Tools::getIsset('ref_payco')) {
            $response = $this->getPaycoResponse(Tools::getValue('ref_payco'));

            if (!empty($response['data'])) {
                $this->processPayment($response['data']);
            } else {
                $this->_errors[] = $this->l('Error when processing ref_payco response');
            }
        } else {
            $order->setCurrentState(Configuration::get('PS_OS_CANCELED'));
        }

        $orderState = new OrderState($order->getCurrentOrderState()->id, $this->context->language->id);

        $this->smarty->assign([
            'epayco' => [
                'id_order' => $order->id,
                'reference' => $order->reference,
                'total' => Tools::displayPrice($order->getTotalPaid(), new Currency($order->id_currency), false),
                'errors' => $this->_errors,
                'status' => $orderState->name,
                'lang' => $this->epaycoResponse,
                'data' => !empty($response['data']) ? $response['data'] : [],
                'franchise' => self::FRANCHISE,
            ],
        ]);

        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
    }

    /**
     * @param string $ref_payco
     */
    protected function getPaycoResponse($ref_payco)
    {
        $return = Tools::file_get_contents('https://secure.epayco.co/validation/v1/reference/'.$ref_payco);

        if (false === $return) {
            return false;
        }

        return json_decode($return, true);
    }

    public function hookActionPaymentCCAdd()
    {
        /* Place your code here. */
    }

    /**
     * @param array $params [
     *   @var int 'id_order'
     * ]
     */
    public function hookActionPaymentConfirmation($params)
    {
        /* Place your code here. */
    }

    /**
     * @param array $params [
     *   @var OrderState $newOrderStatus
     *   @var int $id_order
     * ]
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $orderState = $params['newOrderStatus'];
        $response = $this->getPaycoResponse(Tools::getValue('ref_payco'));

        if (Tools::getIsset('ref_payco') &&
        !empty($response['data'])) {
            $transaction = $response['data'];
            $orderState->transaction_id = $transaction['x_ref_payco'];
            $orderState->card_number = $transaction['x_cardnumber'];
            $orderState->card_brand = !empty(self::FRANCHISE[$transaction['x_franchise']])
                ? self::FRANCHISE[$transaction['x_franchise']]
                : $transaction['x_franchise'];
            $orderState->card_holder = $transaction['x_franchise'];
            $orderState->update();
        }
    }

    /*
    public function hookDisplayPayment()
    {
        // TODO
    }
    */

    public function hookDisplayPaymentTop()
    {
        $this->smarty->assign([
            'epayco_payment_top' => Configuration::get('EPAYCO_PAYMENT_TOP'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/payment_top.tpl');
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active
        || !$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
            $this->getExternalPaymentOption(),
        ];

        return $payment_options;
    }

    /**
     * @param array $params [
     *   @var 'x_cust_id_cliente',
     *   @var 'x_key',
     *   @var 'x_ref_payco',
     *   @var 'x_transaction_id',
     *   @var 'x_amount',
     *   @var 'x_currency_code',
     * ]
     */
    public function getSignature($params) : string
    {
        if (!is_array($params)
        && empty($params)) {
            return false;
        }

        $data = implode('^', $params);

        return hash('sha256', $data);
    }

    /**
     * @param array $params
     */
    public function processPayment(array $params)
    {
        $neededVars = [
            // Understand transaction response
            'x_cod_response',
            'x_response',
            'x_response_reason_text',
            // Response code
            'x_cod_transaction_state',
            'x_transaction_state',
            // Signature
            'x_signature',
            'x_cust_id_cliente',
            'x_ref_payco',
            'x_transaction_id',
            'x_amount',
            'x_currency_code',
            // Invoice
            'x_id_invoice',
            'x_extra1',
            'x_extra2',
        ];

        $epaycoVars = [];
        foreach ($_POST as $key => $value) {
            if (Tools::strpos($key, 'x_') == 0) {
                $epaycoVars[$key] = pSQL($value);
            }
        }

        if (!count($epaycoVars)
        && count($params)) {
            $epaycoVars = $params;
        } else {
            throw new PrestaShopException($this->l('There are not exist vars needed to process the payment'));
        }

        foreach ($neededVars as $neededVar) {
            if (!array_key_exists($neededVar, $epaycoVars)) {
                throw new PrestaShopException(sprintf(
                    $this->l('Missing var needed to update payment info %s'),
                    $neededVar
                ));
            }
        }

        $signature = $this->getSignature([
            $epaycoVars['x_cust_id_cliente'],
            Configuration::get('EPAYCO_PRIVATE_KEY'),
            $epaycoVars['x_ref_payco'],
            $epaycoVars['x_transaction_id'],
            $epaycoVars['x_amount'],
            $epaycoVars['x_currency_code'],
        ]);

        if ($signature != $epaycoVars['x_signature']) {
            throw new PrestaShopException(sprintf(
                $this->l('Error when trying to validate signature local %s && received %s'),
                $signature,
                $epaycoVars['x_signature']
            ));
        }

        $order_id = (int) $epaycoVars['x_id_invoice'];
        $order = new Order($order_id);
        if (!Validate::isLoadedObject($order)) {
            throw new PrestaShopException(sprintf($this->l('Error when trying to upload Order %d'), $order_id));
        }

        $customer = new Customer($order->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            throw new PrestaShopException(sprintf($this->l('Error when trying to upload Customer %d'), $order->id_customer));
        }

        return $this->updateOrderTransaction($epaycoVars, $order);
    }

    /**
     * @param array $transaction
     * @param Order $order
     */
    public function updateOrderTransaction(array $transaction, Order $order)
    {
        $idOrderState = (int) self::getOrderStatusId($transaction['x_cod_response']);

        // Update order state
        if (!empty($transaction['x_cod_response'])
        && $idOrderState != $order->getCurrentState()
        && !$order->hasBeenPaid()) {
            $order->setCurrentState($idOrderState);
        }

        return true;
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
    public static function getOrderStatusId($code) : int
    {
        switch ($code) {
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
                $idOrderStatus = Configuration::get('EPAYCO_OS_PENDING');
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

    public function getExternalPaymentOption()
    {
        $externalOption = new PaymentOption();
        $externalOption
            //->setCallToActionText($this->l('ePayco'))
            // TODO dynamic show title or not
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setInputs([
                'cart_id' => [
                    'name' => 'cart_id',
                    'type' => 'hidden',
                    'value' => (int) $this->context->cart->id,
                ],
                'secure_key' => [
                    'name' => 'secure_key',
                    'type' => 'hidden',
                    'value' => $this->context->customer->secure_key,
                ],
            ])
            ->setAdditionalInformation(
                $this->context->smarty->fetch('module:epayco/views/templates/hook/information.tpl')
            )
            ->setLogo('https://369969691f476073508a-60bf0867add971908d4f26a64519c2aa.ssl.cf5.rackcdn.com/btns/epayco/boton_de_cobro_epayco.png');
            //->setLogo(Media::getMediaPath($this->getPathUri().'/logo.png'));
            // TODO dynamic image

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
