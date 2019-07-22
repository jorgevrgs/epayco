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

use PrestaShop\Modules\Epayco\Service\EpaycoService;

class EpaycoUpdateModuleFrontController extends ModuleFrontController
{
    /**
     * @see parent::$conten_only
     */
    public $content_only = true;
    public function initContent()
    {
        parent::initContent();

        header('Content-Type: application/json');

        try {
            if ($this->module->processPayment()) {
                http_response_code(200);
                die(json_encode('OK'));
            }
        } catch (PrestaShopException $e) {
            http_response_code(400);
            die(json_encode([
                'error' => '400',
                'message' => $e->getMessage(),
            ]));
        }        
    }
}