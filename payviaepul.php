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
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2017 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PayViaEpul extends PaymentModule
{
    const V15 = '15';
    const V16 = '16';
    const V17 = '17';
    const MODULE_NAME = 'payviaepul';
    protected $supported_currencies = array('AZN');

    /**
     * Costructor
     *
     * @return PayViaEpul
     */
    public function __construct()
    {
        $this->name = PayViaEpul::MODULE_NAME;
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'E-PUL';
        $this->controllers = array(/*'payment', */
            'validation');
        $this->bootstrap = true;
        //$this->module_key = '336225a5988ad434b782f2d868d7bfcd';
        $this->currencies = true;
        $this->currencies_mode = 'radio';
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);

        parent::__construct();

        $this->displayName = $this->l('Pay Via E-PUL');
        $this->description = $this->l('Accept payments with E-PUL');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');

        $username = Configuration::get('EPUL_USERNAME');
        $password = Configuration::get('EPUL_PASSWORD');

        if (!isset($username) || !$username) {
            $this->warning = $this->l('Pay via E-PUL username doens\'t set');
        }

        if (!isset($password) || !$password) {
            $this->warning = $this->l('Pay via E-PUL password doens\'t set');
        }
    }


    /**
     * Return module web path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    #region Install/Uninstall

    /**
     * Install module
     *
     * @return bool
     */
    public function install()
    {
        // Install default
        if (!parent::install()) {
            return false;
        }

        if (!$this->registrationHook()) {
            return false;
        }

        if (!Configuration::updateValue('EPUL_USERNAME', '')
            || !Configuration::updateValue('EPUL_PASSWORD', '')
        ) {
            return false;
        }

        return true;
    }

    private function registrationHook()
    {
        if (!$this->registerHook('paymentReturn') /*
            || !$this->registerHook('actionValidateOrder')*/
        ) {
            return false;
        }
        if ($this->getPsVersion() === $this::V17) {
            if (!$this->registerHook('paymentOptions')) {
                return false;
            }
        }

        if (!$this->installSQL()) {
            return false;
        }

        return true;
    }

    public function registerHook($hook_name, $shop_list = null)
    {
        return Hook::registerHook($this, $hook_name, $shop_list);
    }

    private function installSQL()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . PayViaEpul::MODULE_NAME . '_transactions` (
              `id_order` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `cart_id` INT(11) UNSIGNED NOT NULL,
              `transaction_id` VARCHAR(50) NOT NULL,
              `date_add` DATETIME NOT NULL,
              `date_upd` DATETIME NOT NULL,
              `status` INT(1) UNSIGNED NOT NULL
        ) ENGINE = ' . _MYSQL_ENGINE_;


        if (!Db::getInstance()->Execute($sql)) {
            return false;
        }

        return true;
    }

    /**
     * Uninstall DataBase table
     * @return boolean if install was successfull
     */
    private function uninstallSQL()
    {
        $sql = array();
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . PayViaEpul::MODULE_NAME . '_transactions`';

        foreach ($sql as $query) {
            if (!DB::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Uninstall module
     *
     * @return bool
     */
    public function uninstall()
    {
        $config = array(
            'EPUL_USERNAME',
            'EPUL_PASSWORD'
        );
        foreach ($config as $var) {
            Configuration::deleteByName($var);
        }

        if (!$this->uninstallSQL()) {
            return false;
        }

        // Uninstall default
        if (!parent::uninstall()) {
            return false;
        }
        return true;
    }

    #endregion

    #region SQL

    /**
     * Add the transaction to the database
     *
     * @param int $orderId
     * @param string $transactionId
     * @return bool
     */
    public function dbUpdateTransactionId($orderId, $transactionId)
    {
        $query = 'UPDATE ' . _DB_PREFIX_ . PayViaEpul::MODULE_NAME . '_transactions
        SET `transaction_id` = \'' . pSQL($transactionId) . '\', `date_upd` = NOW()
                WHERE `id_order` = ' . pSQL($orderId);

        return $this->executeDbQuery($query);
    }

    /**
     * Add the transaction to the database
     *
     * @param int $cartId
     * @return int
     */
    public function dbAddTransaction($cartId)
    {
        $query = 'INSERT INTO ' . _DB_PREFIX_ . PayViaEpul::MODULE_NAME . '_transactions
                (`transaction_id`, `cart_id`, `date_add`, `date_upd`, `status`)
                VALUES
                (\'0\', ' . pSQL($cartId) . ',NOW(), NOW(), 0 )';

        if ($this->executeDbQuery($query)) {
            return (int)Db::getInstance()->Insert_ID();
        } else {
            return 0;
        }
    }

    /**
     * Execute database query
     *
     * @param mixed $query
     * @return boolean
     */
    private function executeDbQuery($query)
    {
        try {
            if (!Db::getInstance()->Execute($query)) {
                return false;
            }
        } catch (Exception $e) {
            $this->addToLog($e->getMessage(), 3);
            return false;
        }

        return true;
    }

    /**
     * Update status
     *
     * @param mixed $transactionId
     * @param mixed $status
     * @return boolean
     */
    public function dbUpdateOrderStatus($transactionId, $status)
    {
        if (!$transactionId || !$status) {
            return false;
        }

        $query = 'UPDATE ' . _DB_PREFIX_ . PayViaEpul::MODULE_NAME . '_transactions SET `status`='
            . pSQL($status) . ', `date_upd` = NOW() WHERE `transaction_id`=\'' . pSQL($transactionId) . '\'';
        return $this->executeDbQuery($query);
    }

    /**
     * Get transactions from the database with order id.
     *
     * @param mixed $idOrder
     * @return mixed
     */
    private function getDbTransactionsByOrderId($idOrder)
    {

        $query = 'SELECT * FROM ' . _DB_PREFIX_ . PayViaEpul::MODULE_NAME . '_transactions WHERE id_order = ' . pSQL($idOrder);
        $result = Db::getInstance()->executeS($query);

        if (!isset($result) || count($result) === 0 || !isset($result[0])) {
            return null;
        }

        return $result[0];
    }

    /**
     * Get transactions from the database with cart id.
     *
     * @param mixed $transactionId
     * @return mixed
     */
    public function dbGetTransactionsByTransactionId($transactionId)
    {

        $query = 'SELECT * FROM ' . _DB_PREFIX_ . PayViaEpul::MODULE_NAME . '_transactions WHERE transaction_id = \'' . pSQL($transactionId) . '\'';
        $result = Db::getInstance()->executeS($query);

        if (!isset($result) || count($result) === 0 || !isset($result[0])) {
            return null;
        }

        return $result[0];
    }

    #endregion

    #region Hooks

    /**
     * Hook payment
     *
     * @param array $params
     *
     * @return string
     */
    /*public function hookPayment($params)
    {
        $this->addToLog('hookPayment', 1);
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($this->context->cart)) {
            return;
        }

        $response = $this->createPaymentRequest();

        if (isset($response) && (!empty($response_body['success']) && ($response_body['success'] == 'true') || ($response_body['success'] == '1'))) {
            $this->context->smarty->assign($response);
            return $this->display(__FILE__, 'payment.tpl');
        } else {
            return $this->display(__FILE__, 'error_creating.tpl');
        }
    }*/

    /**
     * Hook payment options for Prestashop 1.7
     *
     * @param mixed $params
     * @return PrestaShop\PrestaShop\Core\Payment\PaymentOption[]
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        $this->addToLog('hookPaymentOptions', 1);

        $cart = $params['cart'];
        if (!$this->checkCurrency($cart)) {
            $this->addToLog('checkCurrency failed!', 2);
            return;
        }

        $paymentOption = new PaymentOption();
        $paymentOption->setCallToActionText($this->l('Pay Via E-PUL'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:' . PayViaEpul::MODULE_NAME . '/views/templates/front/payment_infos.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png'));

        $options = [
            $paymentOption
        ];
        return $options;
    }

    /*
    public function hookActionValidateOrder($params)
    {
        if (!$this->active) {
            return;
        }
        $this->addToLog('hookActionValidateOrder', 1);
        $order = $params['order'];
        $amount_paid = (float)$order->getOrdersTotalPaid();
        if (isset($amount_paid) && $amount_paid != 0 && $order->total_paid != $amount_paid) {
            $order->total_paid = $amount_paid;
            $order->total_paid_real = $amount_paid;
            $order->total_paid_tax_incl = $amount_paid;
            $order->update();

            $sql = 'UPDATE `' . _DB_PREFIX_ . 'order_payment`
		    SET `amount` = ' . (float)$amount_paid . '
		    WHERE  `order_reference` = "' . pSQL($order->reference) . '"';
            Db::getInstance()->execute($sql);
        }
    }*/

    #endregion

    /**
     * Check currency
     *
     * @param  Cart $cart
     *
     * @return bool
     */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency((int)($cart->id_currency));
        if ($currency_order->iso_code == 'AZN') {
            return true;
        }

        return false;
    }


    /**
     * Get a configuration page
     *
     * @return string
     */
    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submit' . $this->name)) {
            $payviaepul_username = strval(Tools::getValue('EPUL_USERNAME'));
            $payviaepul_password = strval(Tools::getValue('EPUL_PASSWORD'));

            $err = !$payviaepul_username || empty($payviaepul_username) || !Validate::isGenericName($payviaepul_username) ||
                !$payviaepul_password || empty($payviaepul_password) || !Validate::isGenericName($payviaepul_password);

            if ($err) {
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            } else {
                Configuration::updateValue('EPUL_USERNAME', $payviaepul_username);
                Configuration::updateValue('EPUL_PASSWORD', $payviaepul_password);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        return $output . $this->displayForm();
    }

    /**
     * Generate form
     *
     * @return string
     */
    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Username'),
                    'name' => 'EPUL_USERNAME',
                    'size' => 50,
                    'required' => true
                ),
                array(
                    'type' => 'password',
                    'label' => $this->l('Password'),
                    'name' => 'EPUL_PASSWORD',
                    'size' => 50,
                    'required' => true
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );
        $helper->fields_value['EPUL_USERNAME'] = Configuration::get('EPUL_USERNAME');
        $helper->fields_value['EPUL_PASSWORD'] = Configuration::get('EPUL_PASSWORD');
        return $helper->generateForm($fields_form);
    }

    public function getPsVersion()
    {
        if (_PS_VERSION_ < "1.6.0.0") {
            return $this::V15;
        } elseif (_PS_VERSION_ >= "1.6.0.0" && _PS_VERSION_ < "1.7.0.0") {
            return $this::V16;
        } else {
            return $this::V17;
        }
    }

    public function createPaymentRequest()
    {
        $this->addToLog('createPaymentRequest', 1);
        $response = array();
        try {
            $orderId = $this->dbAddTransaction($this->context->cart->id);
            if ($orderId == 0) {
                throw new Exception('Can\'t obtain insert id! Something wrong with DB INSERT!');
            }
            $parameters = array();
            $parameters['username'] = Configuration::get('EPUL_USERNAME');
            $parameters['password'] = Configuration::get('EPUL_PASSWORD');
            $parameters['description'] = '';
            $parameters['amount'] = intval(round($this->context->cart->getOrderTotal() * 100.0, 0));
            $parameters['transactionId'] = 'Epul_' . $orderId;
            $parameters['errorUrl'] = $this->context->link->getPageLink('order', true, null, 'step=3');
            $parameters['backUrl'] = $this->context->link->getModuleLink(PayViaEpul::MODULE_NAME, 'validation', array(), true);

            $this->addToLog(
                "Param list: \n" . print_r($parameters, true),
                1
            );
            $curl = curl_init();

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_URL, 'https://www.e-pul.az/epay/pay_via_epul/register_transaction');
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $parameters);

            $response = json_decode(curl_exec($curl), true);

            if (curl_errno($curl)) {
                $this->addToLog(
                    'Error occured during curl exec. Additional info: ' . curl_errno($curl) . ':' . curl_error($curl)
                );
            }
            if (isset($response)) {
                $this->addToLog(print_r($response, TRUE), 1);
                if ((!empty($response['success']) && ($response['success'] == 'true') || ($response['success'] == '1'))) {
                    $this->dbUpdateTransactionId($orderId, $response['orderId']);
                } else {
                    $this->addToLog('Not success!', 2);
                }
            } else {
                $this->addToLog(
                    'NULL response!',
                    3
                );
            }
        } catch (Exception $exp) {
            $this->addToLog($exp->getMessage());
        }

        return $response;
    }

    public function checkOrder($transactionId)
    {
        $this->addToLog('checkOrder', 1);
        try {
            $parameters = array();
            $parameters['username'] = Configuration::get('EPUL_USERNAME');
            $parameters['password'] = Configuration::get('EPUL_PASSWORD');
            $parameters['orderId'] = $transactionId;

            $this->addToLog(
                "Param list: \n" . print_r($parameters, true),
                1
            );

            $curl = curl_init();

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_URL, 'https://www.e-pul.az/epay/pay_via_epul/check_transaction');
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $parameters);

            $response = json_decode(curl_exec($curl), true);

            if (curl_errno($curl)) {
                $this->addToLog(
                    'Error occured during curl exec. Additional info: ' . curl_errno($curl) . ':' . curl_error($curl)
                );
            }
            if (isset($response)) {
                $this->addToLog(print_r($response, TRUE), 1);
                if ((!empty($response['success']) && ($response['success'] == 'true') || ($response['success'] == '1'))) {
                    return true;
                } else {
                    $this->addToLog('Not success!', 2);
                }
            } else {
                $this->addToLog(
                    'NULL response!',
                    3
                );
            }
        } catch (Exception $exp) {
            $this->addToLog($exp->getMessage(), 3);
        }

        return false;
    }

    public function addToLog($message, $severity = 3)
    {
        if ($this->getPsVersion() === PayViaEpul::V15) {
            Logger::addLog($message, $severity);
        } else {
            PrestaShopLogger::addLog($message, $severity);
        }
    }
}