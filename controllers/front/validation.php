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

class PayViaEpulValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $this->module->addToLog('PayViaEpulValidationModuleFrontController::postProcess', 1);
        $message = '';
        $responseCode = '400';
        $cart = null;
        $message = $this->processAction($cart, $responseCode);
        if ($responseCode != 200) {
            $this->handleError($message, $cart);
        } else {
            $this->redirectToAccept($cart);
        }
    }

    /**
     * Redirect To Accept
     *
     * @param Cart $cart
     */
    private function redirectToAccept($cart)
    {
        Tools::redirectLink(__PS_BASE_URI__ . 'order-confirmation.php?key='
            . $cart->secure_key . '&id_cart=' . (int)$cart->id . '&id_module='
            . (int)$this->module->id . '&id_order=' . (int)Order::getIdByCartId($cart->id));
    }

    private function handleError($message, $cart)
    {
        $this->module->addToLog($message, 3, $cart);
        Context::getContext()->smarty->assign('paymenterror', $message);
        if ($this->module->getPsVersion() === PayViaEpul::V17) {
            $this->setTemplate('module:' . PayViaEpul::MODULE_NAME . '/views/templates/front/error_payment17.tpl');
        } else {
            $this->setTemplate('error_payment.tpl');
        }
    }

    /**
     * Process Action
     *
     * @param Cart $cart
     * @param int $responseCode
     * @return string
     */
    protected function processAction(&$cart, &$responseCode)
    {
        $message = '';
        try {
            $transactionId = null;

            if (!Tools::getIsset('orderId')) {
                $this->module->addToLog('No GET(orderId) was supplied to the system!', 2);
                $message = $this->module->l('No GET(orderId) was supplied to the system!', PayViaEpul::MODULE_NAME);
                $responseCode = 500;
                return $message;
            }
            $transactionId = Tools::getValue('orderId');
            $this->module->addToLog('orderId: ' . $transactionId, 1);

            $savedOrder = $this->module->dbGetTransactionsByTransactionId($transactionId);

            if (!$savedOrder) {
                $this->module->addToLog('Can\'t obtain order from DB', 3);
                $message = $this->module->l('Can\'t obtain order from DB', PayViaEpul::MODULE_NAME);
                $responseCode = 500;
                return $message;
            }

            $cart = new Cart($savedOrder['cart_id']);

            if (!isset($cart->id)) {
                $this->module->addToLog('Please provide a valid orderid or cartid', 2);
                $message = $this->module->l('Please provide a valid orderid or cartid', PayViaEpul::MODULE_NAME);
                $responseCode = 500;
                return $message;
            }

            if (!$cart->orderExists()) {
                $paymentMethod = $this->module->displayName;
                $mailVars = array(
                    'TransactionId' => $transactionId,
                    'PaymentType' => $paymentMethod);

                $this->module->addToLog('Order Total: ' . $cart->getOrderTotal(), 1);
                $this->module->validateOrder(
                    (int)$cart->id,
                    Configuration::get('PS_OS_PAYMENT'),
                    $cart->getOrderTotal(),
                    $this->module->displayName,
                    null,
                    $mailVars,
                    null,
                    false,
                    $cart->secure_key);


                $orderId = Order::getIdByCartId($cart->id);

                if (empty($orderId) || $orderId == 0) {
                    $this->module->addToLog('Can\'t obtain order ID!', 2);
                    $message = $this->module->l('Can\'t obtain order ID!', PayViaEpul::MODULE_NAME);
                    $responseCode = 500;
                    return $message;
                }

                $order = new Order($orderId);

                if (!is_object($order)) {
                    $this->module->addToLog('Can\'t obtain order!', 2);
                    $message = $this->module->l('Can\'t obtain order!', PayViaEpul::MODULE_NAME);
                    $responseCode = 500;
                    return $message;
                }

                $this->module->addToLog('PrestaShop order id: ' . $order->id, 1);

                if ($this->module->checkOrder($transactionId)) {
                    $this->module->addToLog('Transaction OK!', 1);
                    $this->module->dbUpdateOrderStatus($transactionId, 1);
                    $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
                    $responseCode = 200;
                } else {
                    $this->module->addToLog('Transaction failed!', 2);
                    $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                    $responseCode = 500;
                    $message = $this->module->l('Transaction failed!', PayViaEpul::MODULE_NAME);
                }
                $order->save();
            } else {
                $this->module->addToLog('Order was already Created', 2);
                $message = $this->module->l('Order was already Created', PayViaEpul::MODULE_NAME);
                $responseCode = 500;
            }
        } catch (Exception $e) {
            $responseCode = 500;
            $message = $this->module->l('Process order failed with an exception: ', PayViaEpul::MODULE_NAME) . $e->getMessage();
            $this->module->addToLog($message, 3);
        }

        return $message;
    }
}