<?php
/*
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
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2013 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class PayViaEpulPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $this->module->addToLog('PayViaEpulPaymentModuleFrontController::postProcess', 1);
        try {
            $cart = $this->context->cart;
            if ($cart->id_customer == 0 ||
                $cart->id_address_delivery == 0 ||
                $cart->id_address_invoice == 0 ||
                !$this->module->active) {
                throw new Exception('Can\'t obtain full info');
            }

            $authorized = false;
            foreach (Module::getPaymentModules() as $module) {
                if ($module['name'] == PayViaEpul::MODULE_NAME) {
                    $authorized = true;
                    break;
                }
            }

            if (!$authorized) {
                throw new Exception('This payment method is not available.');
            }

            $response = $this->module->createPaymentRequest();

            if (isset($response) &&
                (!empty($response['success']) && ($response['success'] == 'true') || ($response['success'] == '1'))) {
                $this->context->smarty->assign($response);
                Tools::redirect($response['forwardUrl']);
            } /* else {
                Tools::redirect('index.php?controller=order&step=1');
            }*/
        } catch (Exception $exp) {
            $this->module->addToLog($exp->getMessage(), 4);
        }

        Tools::displayError($this->module->l(
            'Sorry, can\'t make payment! Please contact to the shop administrator',
            PayViaEpul::MODULE_NAME));
    }
}