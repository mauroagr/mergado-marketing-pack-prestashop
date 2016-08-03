<?php
/**
 * NOTICE OF LICENSE.
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 *  @author    www.mergado.cz
 *  @copyright 2016 Mergado technologies, s. r. o.
 *  @license   LICENSE.txt
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_.'mergado/classes/MergadoClass.php';

class Mergado extends Module
{
    protected $controllerClass;

    public function __construct()
    {
        $this->name = 'mergado';
        $this->tab = 'export';
        $this->version = '1.0.0';
        $this->author = 'www.mergado.cz';
        $this->need_instance = 0;
        $this->module_key = '12cdb75588bb090637655d626c01c351';
        $this->controllerClass = 'AdminMergado';
        //$this->module_key = '';

        /*
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Mergado marketing pack');
        $this->description = $this->l('Mergado marketing pack module helps you to export your products information to Mergado services.');

        $this->confirmUninstall = $this->l('Are you sure to uninstall Mergado marketing pack module?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update.
     */
    public function install()
    {
        include dirname(__FILE__).'/sql/install.php';

        $this->addTab();

        return parent::install() && $this->registerHook('backOfficeHeader')
                && $this->registerHook('actionValidateOrder') && $this->registerHook('orderConfirmation');
    }

    public function uninstall()
    {
        include dirname(__FILE__).'/sql/uninstall.php';

        $this->removeTab();

        return parent::uninstall();
    }

    /**
     * Load the configuration form.
     */
    public function getContent()
    {
        return $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $id = Tab::getIdFromClassName($this->controllerClass);
        $token = Tools::getAdminToken($this->controllerClass.$id.(int) $this->context->employee->id);
        Tools::redirectAdmin('index.php?controller='.$this->controllerClass.'&token='.$token);
        die;
    }

    /**
     * Add item into menu.
     */
    protected function addTab()
    {
        $id_parent = Tab::getIdFromClassName('AdminCatalog');
        if (!$id_parent) {
            throw new RuntimeException(
                sprintf($this->l('Failed to add the module into the main BO menu.')).' : '
                .Db::getInstance()->getMsgError()
            );
        }

        $tabNames = array();
        foreach (Language::getLanguages(false) as $lang) {
            $tabNames[$lang['id_lang']] = $this->displayName;
        }

        $tab = new Tab();
        $tab->class_name = $this->controllerClass;
        $tab->name = $tabNames;
        $tab->module = $this->name;
        $tab->id_parent = $id_parent;

        if (!$tab->save()) {
            throw new RuntimeException($this->l('Failed to add the module into the main BO menu.'));
        }
    }

    protected function removeTab()
    {
        if (!Tab::getInstanceFromClassName($this->controllerClass)->delete()) {
            throw new RuntimeException($this->l('Failed to remove the module from the main BO menu.'));
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('controller') == $this->controllerClass) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Verified by users.
     */
    public function hookActionValidateOrder($params)
    {
        $verifiedCz = MergadoClass::getSettings('mergado_heureka_overeno_zakazniky_cz');
        $verifiedSk = MergadoClass::getSettings('mergado_heureka_overeno_zakazniky_sk');
        $mergado = new MergadoClass();

        /* Heureka verified by users */
        if ($verifiedCz && $verifiedCz['value'] === '1') {
            $verifiedCzCode = MergadoClass::getSettings('mergado_heureka_overeno_zakazniky_kod_cz');

            if ($verifiedCzCode && $verifiedCzCode['value'] != '') {
                $mergado->heurekaVerify($verifiedCzCode['value'], $params, 'cs');
            }
        }

        if ($verifiedSk && $verifiedSk['value'] === '1') {
            $verifiedCzCode = MergadoClass::getSettings('mergado_heureka_overeno_zakazniky_kod_sk');

            if ($verifiedCzCode && $verifiedCzCode['value'] != '') {
                $mergado->heurekaVerify($verifiedCzCode['value'], $params, 'sk');
            }
        }

        /* Zbozi conversion */
        $mergado->sendZboziKonverze($params, 'cs');
    }

    public function hookOrderConfirmation($params)
    {
        $zboziActive = MergadoClass::getSettings('mergado_zbozi_konverze');
        $zboziId = MergadoClass::getSettings('mergado_zbozi_shop_id');
        $heurekaCzActive = MergadoClass::getSettings('mergado_heureka_konverze_cz');
        $heurekaCzCode = MergadoClass::getSettings('mergado_heureka_konverze_cz_kod');
        $heurekaSkActive = MergadoClass::getSettings('mergado_heureka_konverze_sk');
        $heurekaSkCode = MergadoClass::getSettings('mergado_heureka_konverze_sk');
        $cartCz = new CartCore($params['objOrder']->id_cart, LanguageCore::getIdByIso('cs'));
        $cartSk = new CartCore($params['objOrder']->id_cart, LanguageCore::getIdByIso('sk'));
        $heurekaCzProducts = array();
        $heurekaSkProducts = array();

        if ($cartCz && $heurekaCzActive) {
            foreach ($cartCz->getProducts() as $product) {
                $exactName = $product['name'];

                if (array_key_exists('attributes_small', $product) && $product['attributes_small'] != '') {
                    $tmpName = array_reverse(explode(', ', $product['attributes_small']));
                    $exactName .= ': '.implode(' ', $tmpName);
                }

                $heurekaCzProducts[] = array(
                    'name' => $exactName,
                    'qty' => $product['quantity'],
                    'unitPrice' => Tools::ps_round(
                        $product['price_wt'],
                        Configuration::get('PS_PRICE_DISPLAY_PRECISION')
                    ),
                );
            }
        }

        if ($cartSk && $heurekaSkActive) {
            foreach ($cartSk->getProducts() as $product) {
                $exactName = $product['name'];

                if (array_key_exists('attributes_small', $product) && $product['attributes_small'] != '') {
                    $tmpName = array_reverse(explode(', ', $product['attributes_small']));
                    $exactName .= ': '.implode(' ', $tmpName);
                }

                $heurekaSkProducts[] = array(
                    'name' => $exactName,
                    'qty' => $product['quantity'],
                    'unitPrice' => Tools::ps_round(
                        $product['price_wt'],
                        Configuration::get('PS_PRICE_DISPLAY_PRECISION')
                    ),
                );
            }
        }

        $this->smarty->assign(array(
            'conversionZboziShopId' => $zboziId['value'],
            'conversionZboziActive' => $zboziActive['value'],
            'conversionZboziTotal' => number_format(
                $params['objOrder']->total_paid,
                Configuration::get('PS_PRICE_DISPLAY_PRECISION')
            ),
            'conversionOrderId' => $params['objOrder']->id,
            'heurekaCzActive' => $heurekaCzActive,
            'heurekaCzCode' => $heurekaCzCode,
            'heurekaSkActive' => $heurekaSkActive,
            'heurekaSkCode' => $heurekaSkCode,
            'heurekaCzProducts' => $heurekaCzProducts,
            'heurekaSkProducts' => $heurekaSkProducts,
        ));

        return $this->display(__FILE__, '/views/templates/front/tracking.tpl');
    }
}