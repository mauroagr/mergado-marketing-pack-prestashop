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
 * @author    www.mergado.cz
 * @copyright 2016 Mergado technologies, s. r. o.
 * @license   LICENSE.txt
 */

// Do not use USE statements because of PS 1.6.1.12 - error during installation

require_once _PS_MODULE_DIR_ . 'mergado/classes/services/Biano/BianoClass.php';
require_once _PS_MODULE_DIR_ . 'mergado/classes/services/Google/GoogleClass.php';
require_once _PS_MODULE_DIR_ . 'mergado/classes/services/Kelkoo/KelkooClass.php';
require_once _PS_MODULE_DIR_ . 'mergado/classes/services/Glami/GlamiClass.php';
require_once _PS_MODULE_DIR_ . 'mergado/classes/services/Heureka/HeurekaClass.php';
require_once _PS_MODULE_DIR_ . 'mergado/classes/services/Zbozi/ZboziClass.php';
require_once _PS_MODULE_DIR_ . 'mergado/classes/services/NajNakup/NajNakupClass.php';
require_once _PS_MODULE_DIR_ . 'mergado/classes/services/Pricemania/PricemaniaClass.php';
require_once _PS_MODULE_DIR_ . 'mergado/classes/tools/RssClass.php';
require_once _PS_MODULE_DIR_ . 'mergado/classes/tools/ImportPricesClass.php';
require_once _PS_MODULE_DIR_ . 'mergado/classes/tools/HelperClass.php';
require_once _PS_MODULE_DIR_ . 'mergado/classes/tools/SettingsClass.php';

if (!defined('_PS_VERSION_')) {
    exit;
}


class Mergado extends Module
{
    protected $controllerClass;
    public $shopId;

    const MERGADO_LATEST_RELEASE = "https://api.github.com/repos/mergado/mergado-marketing-pack-prestashop/releases/latest";
    const MERGADO_UPDATE = 'https://raw.githubusercontent.com/mergado/mergado-marketing-pack-prestashop/master/mergado/config/mergado_update.xml';
    const MERGADO_UPDATE_CACHE_ID = 'mergado_remote_version';

    // Languages
    const LANG_CS = 'cs';
    const LANG_SK = 'sk';
    const LANG_EN = 'en';
    const LANG_PL = 'pl';

    const LANG_AVAILABLE = array(
        self::LANG_EN,
        self::LANG_CS,
        self::LANG_SK,
        self::LANG_PL,
    );

    // Prestashop versions
    const PS_V_16 = 1.6;
    const PS_V_17 = 1.7;

    // Mergado
    const MERGADO = [
        'MODULE_NAME' => 'mergado',
        'TABLE_NAME' => 'mergado',
        'TABLE_NEWS_NAME' => 'mergado_news',
        'VERSION' => '2.3.34',
    ];

    public function __construct()
    {
        $this->name = self::MERGADO['MODULE_NAME'];
        $this->tab = 'export';
        $this->version = self::MERGADO['VERSION'];
        $this->author = 'www.mergado.cz';
        $this->need_instance = 0;
        $this->module_key = '12cdb75588bb090637655d626c01c351';
        $this->controllerClass = 'AdminMergado';

        /*
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.7)
         */
        if (_PS_VERSION_ >= self::PS_V_16 && _PS_VERSION_ < self::PS_V_17) {
            $this->bootstrap = false;
        } else {
            $this->bootstrap = true;
        }

        $this->shopID = self::getShopId();

        parent::__construct();

        $this->displayName = $this->l('Mergado marketing pack');
        $this->description = $this->l('Mergado marketing pack module helps you to export your products information to Mergado services.');

        $this->confirmUninstall = $this->l('Are you sure to uninstall Mergado marketing pack module?');

        $this->ps_versions_compliancy = array('min' => self::PS_V_16, 'max' => '1.7.9.99');

        try {
            $cronRss = new Mergado\Tools\RssClass();
            $cronRss->getFeed();
        } catch (Exception $ex) {
            // Error during installation
        }
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update.
     */
    public function install()
    {
        include dirname(__FILE__) . '/sql/install.php';

        $this->addTab();

        return parent::install()
            && $this->installUpdates()
            && $this->registerHook('backOfficeHeader')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('orderConfirmation')
            && $this->registerHook('displayFooter')
//            && $this->registerHook('displayProductFooter') // Probably not used
            && $this->registerHook('displayFooterProduct')
            && $this->registerHook('displayShoppingCart')
            && $this->registerHook('displayShoppingCartFooter')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('displayAfterBodyOpeningTag') // only for PS 1.7
            && $this->registerHook('displayBeforeBodyClosingTag') // only for PS 1.7
            && $this->mergadoEnableAll(true);
    }

    public function uninstall()
    {
        include dirname(__FILE__) . '/sql/uninstall.php';

        $this->removeTab();

        return parent::uninstall();
    }

    public function installUpdates()
    {
        include __DIR__ . "/sql/update-1.2.2.php";
        include __DIR__ . "/sql/update-1.6.5.php";
        include __DIR__ . "/sql/update-2.0.0.php"; // 2.0.1 not added (because of version missmatch fix)
        include __DIR__ . "/sql/update-2.3.0.php";

        return true;
    }

    public static function getRepo()
    {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
            CURLOPT_URL => self::MERGADO_LATEST_RELEASE,
        ));
        $response = json_decode(curl_exec($ch));
        curl_close($ch);

        return $response;
    }

    /**
     * @param $url
     * @param $zipPath
     * @return bool
     */
    public function getZipFile($url, $zipPath)
    {

        mkdir($zipPath);
        $zipFile = $zipPath . 'update.zip'; // Local Zip File Path
        $zipResource = fopen($zipFile, "w+");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FILE, $zipResource);
        $page = curl_exec($ch);

        if (!$page) {
            echo "Error :- " . curl_error($ch);
        }
        curl_close($ch);

        $zip = new ZipArchive;
        $extractPath = $zipPath;
        if (!$zip->open($zipFile)) {
            echo "Error :- Unable to open the Zip File";
        }

        $result = $zip->extractTo($extractPath);
        $zip->close();

        return $result;
    }

    public static function checkUpdate()
    {
        $response = self::getRepo();
        $version = $response->tag_name;

        if ($version > self::MERGADO['VERSION']) {
            Mergado\Tools\SettingsClass::saveSetting(Mergado\Tools\SettingsClass::NEW_MODULE_VERSION_AVAILABLE, $version, 0);
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return array|bool
     */
    public function updateModule()
    {
        $response = $this->getRepo();
        $version = $response->tag_name;
        $zipUrl = $response->zipball_url;
        $zipPath = _PS_MODULE_DIR_ . $this->name . '/upgrade/tmp/';

        if ($version > $this->version) {
            if ($this->getZipFile($zipUrl, $zipPath)) {
                $dirname = '';
                foreach (glob($zipPath . '*', GLOB_ONLYDIR) as $dir) {
                    $dirname = basename($dir);
                    break;
                }

                if ($dirname !== '') {
                    $from = $zipPath . $dirname . '/' . $this->name;
                    $to = _PS_MODULE_DIR_ . $this->name;

                    AdminController::mergadoCopyFiles($from, $to);

                    return true;
                }
            }
        } else {
            Mergado\Tools\SettingsClass::saveSetting(Mergado\Tools\SettingsClass::NEW_MODULE_VERSION_AVAILABLE, $version, 0);
            return false;
        }

        return false;
    }

    /**
     * @param null $addons
     * @return string
     */
    public function updateVersionXml($addons = null)
    {
        if (_PS_VERSION_ < Mergado::PS_V_17) {
            $addons = Tools::addonsRequest('must-have');
        }

        $mergadoXml = Tools::file_get_contents(self::MERGADO_UPDATE);

        try {
            if ($addons && $mergadoXml) {
                $psXml = new \SimpleXMLElement($addons);
                $mXml = new \SimpleXMLElement($mergadoXml);

                $doc = new DOMDocument();
                $doc->loadXML($psXml->asXml());

                $mDoc = new DOMDocument();
                $mDoc->loadXML($mXml->asXml());

                $node = $doc->importNode($mDoc->documentElement, true);
                $doc->documentElement->appendChild($node);

                $updateXml = $doc->saveXml();

                if (_PS_VERSION_ >= Mergado::PS_V_17) {
                    return $updateXml;
                }
                //            @file_put_contents(_PS_ROOT_DIR_ . ModuleCore::CACHE_FILE_MUST_HAVE_MODULES_LIST, $updateXml);
            }
        } catch(Exception $e) {
            //xml in presta addons not correct or xml in mergado not correct
        }
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
        $id = TabCore::getIdFromClassName($this->controllerClass);
        $token = Tools::getAdminToken($this->controllerClass . $id . (int)$this->context->employee->id);
        Tools::redirectAdmin('index.php?controller=' . $this->controllerClass . '&token=' . $token);
        die;
    }

    /**
     * Add item into menu.
     */
    protected function addTab()
    {
        $id_parent = TabCore::getIdFromClassName('AdminCatalog');
        if (!$id_parent) {
            throw new RuntimeException(
                sprintf($this->l('Failed to add the module into the main BO menu.')) . ' : '
                . Db::getInstance()->getMsgError()
            );
        }

        $tabNames = array();
        foreach (LanguageCore::getLanguages(false) as $lang) {
            $tabNames[$lang['id_lang']] = $this->displayName;
        }

        $tab = new TabCore();
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
        if (!TabCore::getInstanceFromClassName($this->controllerClass)->delete()) {
            throw new RuntimeException($this->l('Failed to remove the module from the main BO menu.'));
        }
    }


    public function hookDisplayAfterBodyOpeningTag() {
        if(_PS_VERSION_ >= self::PS_V_17) { // Just check cause of custom hook in ps16
            if(Mergado\Google\GoogleClass::isGTMActive($this->shopId)):
                $code = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GOOGLE_TAG_MANAGER['CODE'], self::getShopId());
                ?>
                <!-- Google Tag Manager (noscript) -->
                    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= $code ?>"
                    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
                <!-- End Google Tag Manager (noscript) -->
            <?php
            endif;
        }
    }

    public function hookDisplayProductAdditionalInfo($product) {
        //Modal first
        $this->addToCart();
    }

    public function addToCart() {
        $lang = Mergado\Tools\SettingsClass::getLangIso();

        $this->shopId = self::getShopId();

        $glami = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GLAMI['ACTIVE'], self::getShopId());
        $glamiLangActive = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GLAMI_LANGUAGES[$lang], self::getShopId());

        if($glami === Mergado\Tools\SettingsClass::ENABLED && $glamiLangActive === Mergado\Tools\SettingsClass::ENABLED) {
            ?>
            <script>
                if(typeof $ !== 'undefined') {
                    $('.add-to-cart').on('click', function () {
                        var $_currency = $('.product-price').find('[itemprop="priceCurrency"]').attr('content');
                        var $_id = $(this).closest('form').find('#product_page_product_id').val();
                        var $_name = $('h1[itemprop="name"]').text();
                        var $_price = $('.product-price').find('[itemprop="price"]').attr('content');

                        if ($_name === '') {
                            $_name = $('.modal-body h1').text();
                        }

                        if ($(this).closest('form').find('#idCombination').length > 0) {
                            $_id = $_id + '-' + $(this).closest('form').find('#idCombination').val();
                        }

                        glami('track', 'AddToCart', {
                            item_ids: [$_id],
                            product_names: [$_name],
                            value: $_price,
                            currency: $_currency
                        });
                    });
                }
            </script>
            <?php
        }

        $facebook = Mergado\Tools\SettingsClass::getSettings('fb_pixel', $this->shopID);

        if($facebook === Mergado\Tools\SettingsClass::ENABLED) {
            ?>
            <script>
                // In product detail and modal in PS1.7
                if(typeof $ !== 'undefined') {
                    $('.add-to-cart').on('click', function () {
                        var $_currency = $('.product-price').find('[itemprop="priceCurrency"]').attr('content');
                        var $_id = $(this).closest('form').find('#product_page_product_id').val();
                        var $_name = $('h1[itemprop="name"]').text();
                        var $_price = $('.product-price').find('[itemprop="price"]').attr('content');

                        if($_name === '') {
                            $_name = $('.modal-body h1').text();
                        }

                        if($(this).closest('form').find('#idCombination').length > 0) {
                            $_id = $_id + '-' + $(this).closest('form').find('#idCombination').val();
                        }

                        fbq('track', 'AddToCart', {
                            content_name: $_name,
                            content_ids: [$_id],
                            content_type: 'product',
                            value: $_price,
                            currency: $_currency,
                        });
                    });
                }
            </script>
            <?php
        }
    }


    /**
     * HOOK - BACKOFFICE HEADER
     */

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {
        $this->shopId = self::getShopId();

        if (Tools::getValue('controller') == $this->controllerClass) {
            $this->context->controller->addJquery();
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addJS($this->_path . 'views/vendors/iframe-resizer/js/iframeResizer.min.js');
            $this->context->controller->addJS($this->_path . 'views/js/iframe-resizer.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        } else {
            $this->context->controller->addJquery();
        }

        $this->context->controller->addJS($this->_path . 'views/js/notifications.js');

        if(_PS_VERSION_ < Mergado::PS_V_17) {
            $this->context->controller->addCSS($this->_path . 'views/css/notifications16.css');
        } else {
            $this->context->controller->addCSS($this->_path . 'views/css/notifications17.css');
        }

        $lang = Mergado\Tools\SettingsClass::getLangIso();
        $this->smarty->assign(array(
            'langCode' => $lang,
        ));


        if (!ModuleCore::isEnabled($this->name)) {
            return false;
        }

        if(_PS_VERSION_ == self::PS_V_17) {
            $psv_new = 1;
        } else {
            $psv_new = 0;
        }

        $logoPath = '"' . __PS_BASE_URI__ . "modules/" . self::MERGADO["MODULE_NAME"] . '/logo.gif"';

        return '<script>
                var admin_mergado_ajax_url = ' . (string) json_encode($this->context->link->getAdminLink('AdminMergado')) . ';
                var admin_mergado_show_more_message = "' . $this->l('Show all messages') . '";
                var admin_mergado_read_more = "' . $this->l('Read more') . '";
                var admin_mergado_show_messages = "' . $this->l('Mergado messages') . '";
                var admin_mergado_news = "' . $this->l('NEWS') . '";
                var admin_mergado_no_new = "' . $this->l('No new messages.') . '";
                var admin_mergado_all_messages_url = ' . (string) json_encode($this->context->link->getAdminLink('AdminMergado')) . ';
                var admin_mergado_all_messages_id_tab = 7;
                
                var admin_mergado_prices_imported = "' . $this->l('Prices successfully imported.') . '";
                var admin_mergado_back_running = "' . $this->l('Error generate XML. Selected cron already running.') . '";
                var admin_mergado_back_merged = "' . $this->l('File merged and ready for review in XML feeds section!') . '";
                var admin_mergado_back_success = "' . $this->l('File successfully generated.') . '";
                var admin_mergado_back_error = "' . $this->l('Mergado feed generate ERROR. Try to change number of temporary files and repeat the process.') . '";
                var admin_mergado_back_process = "' . $this->l('Generating') . '";
                
                var psv_new = ' . $psv_new . ';
                var m_logoPath = ' . $logoPath . ';
            </script>';
    }


    /**
     * HOOK - DISPLAY FOOTER PRODUCT
     */

    /**
     * @param array $params
     * @return mixed
     */
    public function hookDisplayFooterProduct($params)
    {
        $display = "";
        $this->shopId = self::getShopId();

        //TODO: ADS
//        $googleAdsRemarketing = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GOOGLE_ADS['REMARKETING'], $this->shopID);
//        if ($googleAdsRemarketing === Mergado\Tools\SettingsClass::ENABLED) {
//            $googleAdsRemarketingId = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GOOGLE_ADS['REMARKETING_ID'], $this->shopID);
//
//            if ($googleAdsRemarketingId !== '') {
//                $this->smarty->assign(array(
//                    'googleAds_remarketing_id' => $googleAdsRemarketingId,
//                    'page_type' => 'product',
//                    'prodid' => $params['product']->id
//                ));
//
//                $display .= $this->display(__FILE__, '/views/templates/front/remarketingtag.tpl');
//            }
//        }

        if(\Mergado\Biano\BianoClass::isActive($this->shopId)) {
            $langCode = Mergado\Tools\SettingsClass::getLangIso(strtoupper($this->context->language->iso_code));

            if(\Mergado\Biano\BianoClass::isLanguageActive($langCode, $this->shopId)) {
                $prodId = \Mergado\Tools\HelperClass::getProductId($params['product']);

                $this->smarty->assign(array(
                        'productId' => $prodId,
                ));

                $display .= $this->display(__FILE__, 'views/templates/front/productDetail/biano/bianoViewProductDetail.tpl');
            }
        }

        return $display;
    }

    /**
     * HOOK - DISPLAY SHOPPING CART FOOTER
     * @param $params
     * @return bool
     */

    public function hookDisplayBeforeBodyClosingTag($params)
    {
        return $this->cartDataPs17($params);
    }

    /**
     * TODO: ADS - DELETE NOT USED
     * @param array $params
     * @return mixed
     */
    public function hookDisplayShoppingCartFooter($params)
    {
//        $this->shopId = self::getShopId();
//
//        $googleAdsRemarketing = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GOOGLE_ADS['REMARKETING'], $this->shopId);
//        $prodid = "";
//
//        foreach ($params['cart']->getProducts() as $product) {
//            $prodid .= "'" . $product['id_product'];
//
//            if (isset($product['id_product_attribute']) && $product['id_product_attribute'] !== "") {
//                $prodid .= '-';
//                $prodid .= $product['id_product_attribute'];
//            }
//
//            $prodid .= "',";
//        }
//
//        $prodid = "[" . substr($prodid, 0, -1) . "]";
//
//        if ($googleAdsRemarketing === Mergado\Tools\SettingsClass::ENABLED) {
//            $googleAdsRemarketingId = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GOOGLE_ADS['REMARKETING_ID'], $this->shopId);
//
//            if ($googleAdsRemarketingId !== '') {
//                $this->smarty->assign(array(
//                    'googleAds_remarketing_id' => $googleAdsRemarketingId,
//                    'page_type' => 'cart',
//                    'prodid' => $prodid
//                ));
//
//                return $this->display(__FILE__, '/views/templates/front/remarketingtag.tpl');
//            }
//        }
//
        return false;
    }


    /**
     * HOOK - ACTION VALIDATE ORDER
     */

    /**
     * Verified by users.
     * @param $params
     * @throws Exception
     */
    public function hookActionValidateOrder($params)
    {

        $this->shopId = Mergado::getShopId();

        $verifiedCz = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::HEUREKA['VERIFIED_CZ'], $this->shopID);
        $verifiedSk = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::HEUREKA['VERIFIED_SK'], $this->shopID);

        /* Heureka verified by users */
        if ($verifiedCz && $verifiedCz === Mergado\Tools\SettingsClass::ENABLED) {
            $verifiedCzCode = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::HEUREKA['VERIFIED_CODE_CZ'], $this->shopID);

            if ($verifiedCzCode && $verifiedCzCode !== '') {
                Mergado\Heureka\HeurekaClass::heurekaVerify($verifiedCzCode, $params, self::LANG_CS);
            }
        }

        if ($verifiedSk && $verifiedSk === Mergado\Tools\SettingsClass::ENABLED) {
            $verifiedCzCode = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::HEUREKA['VERIFIED_CODE_SK'], $this->shopID);

            if ($verifiedCzCode && $verifiedCzCode !== '') {
                Mergado\Heureka\HeurekaClass::heurekaVerify($verifiedCzCode, $params, self::LANG_SK);
            }
        }

        $zboziSent = Mergado\Zbozi\ZboziClass::sendZbozi($params, $this->shopId);
        $najNakupSent = Mergado\NajNakup\NajNakupClass::sendNajnakupValuation($params, self::LANG_SK, $this->shopId);

        try {
            $pricemaniaSent = Mergado\Pricemania\PricemaniaClass::sendPricemaniaOverenyObchod($params, self::LANG_SK, $this->shopId);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }

        Mergado\Tools\LogClass::log("Validate order:\n" . json_encode(array('verifiedCz' => $verifiedCz, 'verifiedSk' => $verifiedSk, 'conversionSent_Zbozi' => $zboziSent, 'conversionSent_NajNakup' => $najNakupSent, 'conversionSent_Pricemania' => $pricemaniaSent)) . "\n");
    }


    /**
     * HOOK - DISPLAY FOOTER
     */

    /**
     * @return string
     */
    public function hookDisplayFooter($params)
    {
        global $cookie;

        $this->shopId = self::getShopId();

        $iso_code = LanguageCore::getIsoById((int)$cookie->id_lang);
        $codeCz = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::HEUREKA['WIDGET_CZ'], $this->shopID);
        $codeSk = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::HEUREKA['WIDGET_SK'], $this->shopID);
        $googleAdsRemarketing = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GOOGLE_ADS['REMARKETING'], $this->shopID);
        $sklikRetargeting = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::SKLIK['RETARGETING'], $this->shopID);
        $etarget = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::ETARGET['ACTIVE'], $this->shopID);

        $display = "";

        if ($iso_code === self::LANG_CS && $codeCz === Mergado\Tools\SettingsClass::ENABLED) {
            $verifiedCodeCZ = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::HEUREKA['VERIFIED_CODE_CZ'], $this->shopID);
            $verifiedCZ = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::HEUREKA['VERIFIED_CZ'], $this->shopID);

            if ($verifiedCodeCZ !== '' && $verifiedCZ == Mergado\Tools\SettingsClass::ENABLED) {

                $this->smarty->assign(array(
                    'conversionKey' => $verifiedCodeCZ
                ));

                $display .= $this->display(__FILE__, '/views/templates/front/footer/partials/heureka_widget_cz.tpl');
            }
        }

        if ($iso_code === self::LANG_SK && $codeSk === Mergado\Tools\SettingsClass::ENABLED) {
            $verifiedCodeSK = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::HEUREKA['VERIFIED_CODE_SK'], $this->shopID);
            $verifiedSK = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::HEUREKA['VERIFIED_CZ'], $this->shopID);

            if ($verifiedCodeSK !== '' && $verifiedSK == Mergado\Tools\SettingsClass::ENABLED) {
                $this->smarty->assign(array(
                    'conversionKey' => $verifiedCodeSK
                ));

                $display .= $this->display(__FILE__, '/views/templates/front/footer/partials/heureka_widget_sk.tpl');
            }
        }

//        if ($googleAdsRemarketing === Mergado\Tools\SettingsClass::ENABLED) {
//            $googleAdsRemarketingId = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GOOGLE_ADS['REMARKETING_ID'], $this->shopID);
//
//            if ($googleAdsRemarketingId !== '') {
//                $this->smarty->assign(array(
//                    'googleAds_remarketing_id' => $googleAdsRemarketingId,
//                ));
//
//                $display .= $this->display(__FILE__, '/views/templates/front/footer/partials/googleAds.tpl');
//            }
//        }

        if ($sklikRetargeting === Mergado\Tools\SettingsClass::ENABLED) {
            $sklikRetargetingId = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::SKLIK['RETARGETING_ID'], $this->shopID);

            if ($sklikRetargetingId !== '') {
                $this->smarty->assign(array(
                    'seznam_retargeting_id' => $sklikRetargetingId,
                ));

                $display .= $this->display(__FILE__, '/views/templates/front/footer/partials/sklik.tpl');
            }
        }

        if ($etarget === Mergado\Tools\SettingsClass::ENABLED) {
            $etargetId = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::ETARGET['ID'], $this->shopID);
            $etargetHash = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::ETARGET['HASH'], $this->shopID);

            if ($etargetId !== '') {
                $this->smarty->assign(array(
                    'etarget_id' => $etargetId,
                    'etarget_hash' => $etargetHash,
                ));

                $display .= $this->display(__FILE__, '/views/templates/front/footer/partials/etarget.tpl');
            }
        }

        $currency = new CurrencyCore($cookie->id_currency);
        $this->smarty->assign(array(
            'currencySign' => $currency->sign,
        ));

        $display .= $this->display(__FILE__, '/views/templates/front/footer/base.tpl');

        $display .= $this->cartDataPs16($params);

        //BIANO
        if (\Mergado\Biano\BianoClass::isActive($this->shopId)) {
            $langCode = Mergado\Tools\SettingsClass::getLangIso(strtoupper($this->context->language->iso_code));

            $display .= $this->display(__FILE__, 'views/templates/front/header/biano/bianoView.tpl');
            $this->context->controller->addJS($this->_path . 'views/js/biano.js');
        }

        return $display;
    }

    public function cartDataPs17($params) {
        //Data for checkout in ps 1.7 ..

        if(_PS_VERSION_ > self::PS_V_16) {
            $langId = (int)ContextCore::getContext()->language->id;

            $cart = $params['cart'];
            $cartProducts = $cart->getProducts(true);

            $exportProducts = array();

            foreach ($cartProducts as $i => $product) {
                $category = new CategoryCore((int)$product['id_category_default'], (int)$langId);
                $manufacturer = new ManufacturerCore($product['id_manufacturer'], (int)$langId);
                $variant = Mergado\Tools\HelperClass::getProductAttributeName($product['id_product_attribute'], (int)$langId);

                $exportProducts[] = array(
                    "id" => \Mergado\Tools\HelperClass::getProductId($product),
                    "name" => $product['name'],
                    "brand" => $manufacturer->name,
                    "category" => $category->name,
                    "variant" => $variant,
                    "list_position" => $i,
                    "quantity" => $product['cart_quantity'],
                    "price" => $product['total_wt'] / $product['cart_quantity'],
                );
            }

            if (_PS_VERSION_ < self::PS_V_17) {
                $this->smarty->assign(array(
                    'data' => htmlspecialchars(json_encode($exportProducts), ENT_QUOTES, 'UTF-8'),
                    'cart_id' => $cart->id,
                ));
            } else {
                $this->smarty->assign(array(
                    'data' => json_encode($exportProducts),
                    'cart_id' => $cart->id,
                ));
            }

            $discounts = [];

            foreach ($cart->getDiscounts() as $item) {
                $discounts[] = $item['name'];
            }

            global $smarty;
            $url = $smarty->tpl_vars['urls']->value['pages']['order'];

            $this->smarty->assign(array(
                'orderUrl' => $url,
                'coupons' => join(', ', $discounts),
            ));

            return $this->display(__FILE__, '/views/templates/front/shoppingCart/cart_data.tpl');
        }

        return false;
    }

    public function cartDataPs16($params) {
        //For checkout in ps 1.6
        if(_PS_VERSION_ < self::PS_V_17) {
            $langId = (int)ContextCore::getContext()->language->id;

            $cart = $params['cart'];
            $cartProducts = $cart->getProducts(true);

            $exportProducts = array();

            foreach ($cartProducts as $i => $product) {
                $category = new CategoryCore((int)$product['id_category_default'], (int)$langId);
                $manufacturer = new ManufacturerCore($product['id_manufacturer'], (int)$langId);
                $variant = Mergado\Tools\HelperClass::getProductAttributeName($product['id_product_attribute'], (int)$langId);

                $exportProducts[] = array(
                    "id" => \Mergado\Tools\HelperClass::getProductId($product),
                    "name" => $product['name'],
                    "brand" => $manufacturer->name,
                    "category" => $category->name,
                    "variant" => $variant,
                    "list_position" => $i,
                    "quantity" => $product['cart_quantity'],
                    "price" => $product['total_wt'] / $product['cart_quantity'],
                );
            }

            if (_PS_VERSION_ < self::PS_V_17) {
                $this->smarty->assign(array(
                    'data' => htmlspecialchars(json_encode($exportProducts), ENT_QUOTES, 'UTF-8'),
                    'cart_id' => $cart->id,
                ));
            } else {
                $this->smarty->assign(array(
                    'data' => json_encode($exportProducts),
                    'cart_id' => $cart->id,
                ));
            }

            $discounts = [];

            foreach ($cart->getDiscounts() as $item) {
                $discounts[] = $item['name'];
            }

            global $smarty;
            $url = array_key_exists('urls', $smarty->tpl_vars) ? $smarty->tpl_vars['urls']->value['pages']['order'] : null;

            $this->smarty->assign(array(
                'orderUrl' => $url,
                'coupons' => join(', ', $discounts),
            ));

            return $this->display(__FILE__, '/views/templates/front/shoppingCart/cart_data.tpl');
        }
    }

    /**
     * HOOK - DISPLAY HEADER
     */

    /**
     * @return string
     */
    public function hookDisplayHeader()
    {
        $lang = Mergado\Tools\SettingsClass::getLangIso();

        $this->shopId = self::getShopId();

        $display = "";
        $glami = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GLAMI['ACTIVE'], self::getShopId());
        $glamiLangActive = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GLAMI_LANGUAGES[$lang], self::getShopId());
        $categoryId = Tools::getValue('id_category');
        $productId = Tools::getValue('id_product');
        $fbPixel = Mergado\Tools\SettingsClass::getSettings('fb_pixel', $this->shopID);

        if ($categoryId) {
            $category = new CategoryCore($categoryId, (int)ContextCore::getContext()->language->id);
            $nb = 10;
            $products_tmp = $category->getProducts((int)ContextCore::getContext()->language->id, 1, ($nb ? $nb : 10));
            $products = array();

            foreach ($products_tmp as $product) {
                $products['ids'][] = $product['id_product'] . '-' . $product['id_product_attribute'];
                $products['name'][] = $product['name'];
            }

            $this->smarty->assign(array(
                'glami_pixel_category' => $category,
                'glami_pixel_productIds' => json_encode($products['ids']),
                'glami_pixel_productNames' => json_encode($products['name'])
            ));
        }

        if ($productId) {
            $product = new ProductCore($productId, false, (int)ContextCore::getContext()->language->id);

            $this->smarty->assign(array(
                'glami_pixel_product' => $product,
                'productId' => $productId
            ));
        }

        //GLAMI
        if ($glami === Mergado\Tools\SettingsClass::ENABLED && $glamiLangActive === Mergado\Tools\SettingsClass::ENABLED) {
            $glamiPixel = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GLAMI['CODE'] . '-' . $lang, $this->shopID);

            if ($glamiPixel !== '') {
                $this->smarty->assign(array(
                    'glami_pixel_code' => $glamiPixel,
                    'glami_lang' => strtolower($lang),
                ));

                $display .= $this->display(__FILE__, '/views/templates/front/header/glami.tpl');
            }
        }

        //KELKOO
        $kelkooActive = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::KELKOO['ACTIVE'], self::getShopId());
        if ($kelkooActive === Mergado\Tools\SettingsClass::ENABLED) {
            $display .= $this->display(__FILE__, '/views/templates/front/header/kelkoo.tpl');
        }

        //GTAG
        if (Mergado\Google\GoogleClass::isGtagjsActive($this->shopId) || Mergado\Google\GoogleClass::isGAdsConversionsActive($this->shopId) || Mergado\Google\GoogleClass::isGAdsRemarketingActive($this->shopId)) {
            $smartyParams = array();
            $gtagMainCode = '';

            //Add google analytics code
            if (Mergado\Google\GoogleClass::isGtagjsActive($this->shopId)) {
                $smartyParams['googleAnalyticsCode'] = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GOOGLE_GTAGJS['CODE'], self::getShopId());
                $gtagMainCode = $smartyParams['googleAnalyticsCode'];
            }

            // Add conversion code
            if (Mergado\Google\GoogleClass::isGAdsConversionsActive($this->shopID) || \Mergado\Google\GoogleClass::isGAdsRemarketingActive($this->shopID)) {
                $gAdsConversionCode = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GOOGLE_ADS['CONVERSIONS_CODE'], $this->shopID);;

                if (preg_match("/^[A-Z]{2}-/i", substr($gAdsConversionCode, 0, 3))) {
                    $smartyParams['gAdsConversionCode'] = substr($gAdsConversionCode, 3);
                } else {
                    $smartyParams['gAdsConversionCode'] = $gAdsConversionCode;
                }

                if ($gtagMainCode == '') {
                    $gtagMainCode = 'AW-' . $smartyParams['gAdsConversionCode'];
                }
            }

            //Does remarketing code exist ??
            $smartyParams['gAdsRemarketingActive'] = \Mergado\Google\GoogleClass::isGAdsRemarketingActive($this->shopID);

            //Add main code to template
            $smartyParams['gtagMainCode'] = $gtagMainCode;

            $this->smarty->assign($smartyParams);

            $display .= $this->display(__FILE__, '/views/templates/front/header/gtagjs.tpl');
        }

        //GTAG - ecommerce enhanced
        if(Mergado\Google\GoogleClass::isGtagjsEcommerceEnhancedActive($this->shopId)) {
            $this->context->controller->addJS($this->_path . 'views/js/gtag.js');
        }

        //Google Tag Manager
        if (Mergado\Google\GoogleClass::isGTMActive($this->shopId)) {
            $googleAnalyticsCode = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GOOGLE_TAG_MANAGER['CODE'], self::getShopId());
            $this->smarty->assign(array(
                'gtm_analytics_id' => $googleAnalyticsCode,
            ));

            $display .= $this->display(__FILE__, '/views/templates/front/header/gtm.tpl');
        }

        //Google Tag Manager - ecommerce enhanced
        if(Mergado\Google\GoogleClass::isGTMEcommerceEnhancedActive($this->shopId)) {
            $this->context->controller->addJS($this->_path . 'views/js/gtm.js');
        }

        //GTAG + Google Tag Manager
        //If user come from my url === clicked on product url
        if(isset($_SERVER["HTTP_REFERER"])) {
            if($_SERVER["HTTP_REFERER"]) {
                global $smarty;

                if(_PS_VERSION_ < self::PS_V_17) {
                    $shopUrl = $smarty->tpl_vars['base_dir']->value;
                } else {
                    $shopUrl = $smarty->tpl_vars['urls']->value['shop_domain_url'];
                }

                if(strpos($_SERVER["HTTP_REFERER"], $shopUrl) !== false) {
                    if(Mergado\Google\GoogleClass::isGtagjsEcommerceEnhancedActive($this->shopId)) {
                        $this->context->controller->addJS($this->_path . 'views/js/gtagProductClick.js');
                    }

                    if (Mergado\Google\GoogleClass::isGTMEcommerceEnhancedActive($this->shopId)) {
                        $this->context->controller->addJS($this->_path . 'views/js/gtmProductClick.js');
                    }
                }
            }
        }

        //BIANO
        if (\Mergado\Biano\BianoClass::isActive($this->shopId)) {
            $langCode = Mergado\Tools\SettingsClass::getLangIso(strtoupper($this->context->language->iso_code));

            if(\Mergado\Biano\BianoClass::isLanguageActive($langCode, $this->shopId)) {
                $this->smarty->assign(array(
                    'merchantId' => Mergado\Biano\BianoClass::getMerchantIdField($langCode, $this->shopId),
                    'langCode' => $langCode,
                ));

                $display .= $this->display(__FILE__, 'views/templates/front/header/biano/biano.tpl');
            } else {
                $this->smarty->assign(array(
                    'langCode' => $langCode,
                ));

                $display .= $this->display(__FILE__, 'views/templates/front/header/biano/bianoDefault.tpl');
            }

            $this->context->controller->addJS($this->_path . 'views/js/biano.js');
        }

        //FB PIXEL
        if ($fbPixel === Mergado\Tools\SettingsClass::ENABLED) {
            $fbPixelCode = Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::FB_PIXEL['CODE'], $this->shopID);

            if ($fbPixelCode !== '') {
                $this->smarty->assign(array(
                    'fbPixelCode' => $fbPixelCode,
                    'searchQuery' => Tools::getValue('search_query'),
                ));

                $display .= $this->display(__FILE__, '/views/templates/front/footer/partials/fbpixel.tpl');
            }
        }

        $this->context->controller->addJS($this->_path . 'views/js/glami.js');
        $this->context->controller->addJS($this->_path . 'views/js/fbpixel.js');

        return $display;
    }

    /**
     * HOOK - DISPLAY ORDER CONFIRMATION
     */

    /**
     * @param $params
     * @return string
     */
    public function hookDisplayOrderConfirmation($params)
    {
        $display = "";
//        $this->context->controller->addCSS($this->_path . 'views/css/popup.css');
        $this->shopId = self::getShopId();

        $heurekaCzProducts = array();
        $heurekaSkProducts = array();

        $options = $this->getOrderConfirmationOptions();
        $context = ContextCore::getContext();

        $orderId = \Mergado\Tools\HelperClass::getOrderId($params);
        $orderCartId = \Mergado\Tools\HelperClass::getOrderCartId($params);

        $this->smarty->assign(array(
            'useSandbox' => Mergado\Zbozi\ZboziClass::ZBOZI_SANDBOX === true ? 1 : 0,
            'lang' => strtolower(substr($context->language->language_code, strpos($context->language->language_code, "-") + 1)), // CZ/SK
            'langIsoCode' => $context->language->iso_code, // CS,SK
        ));

        $order = new OrderCore($orderId);
        $products_tmp = $order->getProducts();

        $glamiProducts = array();
        foreach ($products_tmp as $product) {
            $glamiProducts['full'] = ['id' => $product['product_id'] . '-' . $product['product_attribute_id'], 'name' => $product['product_name']];
            $glamiProducts['ids'][] = $product['product_id'] . '-' . $product['product_attribute_id'];
            $glamiProducts['names'][] = $product['product_name'];
        }
        $customer = new Customer($order->id_customer);

        if (_PS_VERSION_ < Mergado::PS_V_17) {
            $this->assignGlami($orderId,
                $params['objOrder']->total_products,
                $params['currencyObj']->iso_code,
                $glamiProducts,
                $customer->email
            );
        } else {
            $this->assignGlami(
                $orderId,
                $params['order']->total_products,
                CurrencyCore::getCurrency($params['order']->id_currency),
                $glamiProducts,
                $customer->email
            );
        }

        $cart = new CartCore($orderCartId);
        $cartCz = new CartCore($orderCartId, LanguageCore::getIdByIso(self::LANG_CS));
        $cartSk = new CartCore($orderCartId, LanguageCore::getIdByIso(self::LANG_SK));

        if($options['sklikValue'] == '') {
            $sklikValue = false;
        } else {
            $sklikValue = $options['sklikValue'];
        }

        if ($cartCz && $options['heurekaCzActive']) {
            $heurekaCzProducts = $this->getOrderConfirmationHeurekaProducts($cartCz->getProducts());
        }

        if ($cartSk && $options['heurekaSkActive']) {
            $heurekaSkProducts = $this->getOrderConfirmationHeurekaProducts($cartSk->getProducts());
        }

        $fbProducts = array();

        if ($options['fbPixel']) {
            foreach ($cart->getProducts() as $product) {
                $fbProducts[] = \Mergado\Tools\HelperClass::getProductId($product);
            }
        }

        $baseData = $this->getOrderConfirmationBaseData($options, $params, $context, $heurekaSkProducts, $heurekaCzProducts, $fbProducts);

        if (_PS_VERSION_ < Mergado::PS_V_17) {
            $specialData = array(
                'sklikValue' => $sklikValue,
                'conversionOrderId' => $orderId,
                'total' => $params['objOrder']->total_products,
                'currency' => $params['currencyObj'],
                'totalWithoutShippingAndVat' => $params['objOrder']->total_products,
            );

        } else {
            $specialData = array(
                'sklikValue' => $sklikValue,
                'conversionOrderId' => $orderId,
                'total' => $params['order']->total_products,
                'currency' => CurrencyCore::getCurrency($params['order']->id_currency),
                'totalWithoutShippingAndVat' => $params['order']->total_products,
            );
        }

        //Kelkoo
        if(\Mergado\Kelkoo\KelkooClass::isKelkooActive(self::getShopId())) {
            if (_PS_VERSION_ < Mergado::PS_V_17) {
                $kelkooData = Mergado\Kelkoo\KelkooClass::getKelkooOrderData($orderId, $order, $products_tmp, $this->shopId);
            } else {
                $kelkooData = Mergado\Kelkoo\KelkooClass::getKelkooOrderData($orderId, $order, $products_tmp, $this->shopId);
            }

            $this->smarty->assign(
                $kelkooData
            );

            $display .= $this->display(__FILE__, '/views/templates/front/orderConfirmation/partials/kelkoo.tpl');
        }

        //Gtag.js - Google analytics
        if(Mergado\Google\GoogleClass::isGtagjsEcommerceActive(self::getShopId())) {
            $this->smarty->assign(array(
                'gtag_purchase_data' => Mergado\Google\GoogleClass::getGtagjsPurchaseData($orderId, $order, $products_tmp, (int) $context->language->id),
            ));

            $display .= $this->display(__FILE__, '/views/templates/front/orderConfirmation/partials/gtagjs.tpl');
        }

        //GoogleTagManager - Google analytics
        if(Mergado\Google\GoogleClass::isGTMEcommerceActive(self::getShopId())) {
            $currency = new CurrencyCore($order->id_currency);

            $this->smarty->assign(array(
                'gtm_purchase_data' => Mergado\Google\GoogleClass::getGTMPurchaseData($orderId, $order, $products_tmp, (int) $context->language->id),
                'gtm_currencyCode' => $currency->iso_code,
            ));

            $display .= $this->display(__FILE__, '/views/templates/front/orderConfirmation/partials/gtm.tpl');
        }

        //Biano
        if(\Mergado\Biano\BianoClass::isActive(self::getShopId())) {
            $this->smarty->assign(array(
                'bianoPurchaseData' => \Mergado\Biano\BianoClass::getPurchaseData($orderId, $order, $products_tmp, (int) $context->language->id),
            ));

            $display .= $this->display(__FILE__, '/views/templates/front/orderConfirmation/partials/biano.tpl');
        }

        // All smarty assign merged and assigned
        $data = array_merge($baseData + $specialData + array('PS_VERSION' => _PS_VERSION_));
        $this->smarty->assign($data);

        Mergado\Tools\LogClass::log("Order confirmation:\n" . json_encode($data) . "\n");

        $display .= $this->display(__FILE__, '/views/templates/front/orderConfirmation/base.tpl');
        return $display;
    }

    /**
     * HOOK - DISPLAY SHOPING CART
     */

    /**
     * @param $params
     * @return string
     */
    public function hookDisplayShoppingCart($params)
    {
        //For checkout in ps 1.6
        if(_PS_VERSION_ < self::PS_V_17) {
            $display = "";
            $langId = (int)ContextCore::getContext()->language->id;

            $cart = $params['cart'];
            $cartProducts = $cart->getProducts(true);

            $exportProducts = array();

            foreach ($cartProducts as $i => $product) {
                $category = new CategoryCore((int)$product['id_category_default'], (int)$langId);
                $manufacturer = new ManufacturerCore($product['id_manufacturer'], (int)$langId);
                $variant = Mergado\Tools\HelperClass::getProductAttributeName($product['id_product_attribute'], (int)$langId);

                $exportProducts[] = array(
                    "id" => \Mergado\Tools\HelperClass::getProductId($product),
                    "name" => $product['name'],
                    "brand" => $manufacturer->name,
                    "category" => $category->name,
                    "variant" => $variant,
                    "list_position" => $i,
                    "quantity" => $product['cart_quantity'],
                    "price" => $product['total_wt'] / $product['cart_quantity'],
                );
            }

            if (_PS_VERSION_ < self::PS_V_17) {
                $this->smarty->assign(array(
                    'data' => htmlspecialchars(json_encode($exportProducts), ENT_QUOTES, 'UTF-8'),
                    'cart_id' => $cart->id,
                ));
            } else {
                $this->smarty->assign(array(
                    'data' => json_encode($exportProducts),
                    'cart_id' => $cart->id,
                ));
            }

            $discounts = [];

            foreach ($cart->getDiscounts() as $item) {
                $discounts[] = $item['name'];
            }

            global $smarty;
            $url = $smarty->tpl_vars['urls']->value['pages']['order'];

            $this->smarty->assign(array(
                'orderUrl' => $url,
                'coupons' => join(', ', $discounts),
            ));

            $display .= $this->display(__FILE__, '/views/templates/front/shoppingCart/cart_data.tpl');

            return $display;
        }
    }

    /**
     * @param $orderId
     * @param $value
     * @param $currency
     * @param $glamiProducts
     * @param $customerEmail
     */
    public function assignGlami($orderId, $value, $currency, $glamiProducts, $customerEmail)
    {
        $lang = Mergado\Tools\SettingsClass::getLangIso();
        $shopID = self::getShopId();

        $glamiTOPLanguageValues = Mergado\Glami\GlamiClass::getGlamiTOPActiveDomain($shopID);

        $this->smarty->assign(array(
            'glami_active' => Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GLAMI['ACTIVE'], $shopID),
            'glami_top_active' => Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GLAMI['ACTIVE_TOP'], $shopID),
            'glami_top_lang_active'=> $glamiTOPLanguageValues['type_code'],
            'glami_top_url_active'=> $glamiTOPLanguageValues['name'],
            'glami_top_code' => Mergado\Tools\SettingsClass::getSettings(Mergado\Tools\SettingsClass::GLAMI['CODE_TOP'], $shopID),
            'glami_orderId' => $orderId,
            'glami_value' => $value,
            'glami_currency' => $currency,
            'glami_productIds' => json_encode($glamiProducts['ids']),
            'glami_productNames' => json_encode($glamiProducts['names']),
            'glami_products' => json_encode($glamiProducts['full']),
            'glami_email' => $customerEmail,
        ));
    }

    /**
     * @return array
     */
    public function getOrderConfirmationOptions()
    {
        $shopId = self::getShopId();
        $settings = Mergado\Tools\SettingsClass::getWholeSettings($shopId);

        // @TODO Rewrite if only php 7+ support - like: isset($settings[Mergado\Tools\SettingsClass::ZBOZI['CONVERSION']]) ?? '',

        $sorted = array();

        foreach($settings as $item) {
            $sorted[$item['key']] = $item['value'];
        }

        $googleAdsCodeSanitized = isset($sorted[Mergado\Tools\SettingsClass::GOOGLE_ADS['CONVERSIONS_CODE']]) ? $sorted[Mergado\Tools\SettingsClass::GOOGLE_ADS['CONVERSIONS_CODE']] : '';
        if (preg_match("/^[A-Z]{2}-/i", substr($googleAdsCodeSanitized, 0, 3))) {
            $googleAdsCodeSanitized = substr($googleAdsCodeSanitized, 3);
        }

        return array(
            'zboziActive' => isset($sorted[Mergado\Tools\SettingsClass::ZBOZI['CONVERSIONS']]) ? $sorted[Mergado\Tools\SettingsClass::ZBOZI['CONVERSIONS']] : '',
            'zboziAdvancedActive' => isset($sorted[Mergado\Tools\SettingsClass::ZBOZI['CONVERSIONS_ADVANCED']]) ? $sorted[Mergado\Tools\SettingsClass::ZBOZI['CONVERSIONS_ADVANCED']] : '',
            'zboziId' => isset($sorted[Mergado\Tools\SettingsClass::ZBOZI['SHOP_ID']]) ? $sorted[Mergado\Tools\SettingsClass::ZBOZI['SHOP_ID']] : '',
            'heurekaCzActive' => isset($sorted[Mergado\Tools\SettingsClass::HEUREKA['CONVERSIONS_CZ']]) ? $sorted[Mergado\Tools\SettingsClass::HEUREKA['CONVERSIONS_CZ']] : '',
            'heurekaCzCode' => isset($sorted[Mergado\Tools\SettingsClass::HEUREKA['CONVERSIONS_CODE_CZ']]) ? $sorted[Mergado\Tools\SettingsClass::HEUREKA['CONVERSIONS_CODE_CZ']] : '',
            'heurekaSkActive' => isset($sorted[Mergado\Tools\SettingsClass::HEUREKA['CONVERSIONS_SK']]) ? $sorted[Mergado\Tools\SettingsClass::HEUREKA['CONVERSIONS_SK']] : '',
            'heurekaSkCode' => isset($sorted[Mergado\Tools\SettingsClass::HEUREKA['CONVERSIONS_SK']]) ? $sorted[Mergado\Tools\SettingsClass::HEUREKA['CONVERSIONS_SK']] : '',
            'sklik' => isset($sorted[Mergado\Tools\SettingsClass::SKLIK['CONVERSIONS']]) ? $sorted[Mergado\Tools\SettingsClass::SKLIK['CONVERSIONS']] : '',
            'sklikCode' => isset($sorted[Mergado\Tools\SettingsClass::SKLIK['CONVERSIONS_CODE']]) ? $sorted[Mergado\Tools\SettingsClass::SKLIK['CONVERSIONS_CODE']] : '',
            'sklikValue' => isset($sorted[Mergado\Tools\SettingsClass::SKLIK['CONVERSIONS_VALUE']]) ? $sorted[Mergado\Tools\SettingsClass::SKLIK['CONVERSIONS_VALUE']] : '',
            'googleAds' => isset($sorted[Mergado\Tools\SettingsClass::GOOGLE_ADS['CONVERSIONS']]) ? $sorted[Mergado\Tools\SettingsClass::GOOGLE_ADS['CONVERSIONS']] : '',
            'googleAdsCode' => $googleAdsCodeSanitized,
            'googleAdsLabel' => isset($sorted[Mergado\Tools\SettingsClass::GOOGLE_ADS['CONVERSIONS_LABEL']]) ? $sorted[Mergado\Tools\SettingsClass::GOOGLE_ADS['CONVERSIONS_LABEL']] : '',
            'fbPixel' => isset($sorted[Mergado\Tools\SettingsClass::FB_PIXEL['ACTIVE']]) ? $sorted[Mergado\Tools\SettingsClass::FB_PIXEL['ACTIVE']] : '',
        );
    }

    /**
     * @param array $options
     * @param array $params
     * @param $context
     * @param array $heurekaSkProducts
     * @param array $heurekaCzProducts
     * @param array $fbProducts
     * @return array
     */
    public function getOrderConfirmationBaseData(array $options, array $params, $context, array $heurekaSkProducts, array $heurekaCzProducts, array $fbProducts)
    {
        return array(
            'conversionZboziShopId' => $options['zboziId'],
            'conversionZboziActive' => $options['zboziActive'],
            'conversionZboziAdvancedActive' => $options['zboziAdvancedActive'],
            'conversionZboziTotal' => number_format(
                $params['order']->total_paid, ConfigurationCore::get('PS_PRICE_DISPLAY_PRECISION')
            ),
            'heurekaCzActive' => $options['heurekaCzActive'],
            'heurekaCzCode' => $options['heurekaCzCode'],
            'heurekaSkActive' => $options['heurekaSkActive'],
            'heurekaSkCode' => $options['heurekaSkCode'],
            'heurekaCzProducts' => $heurekaCzProducts,
            'heurekaSkProducts' => $heurekaSkProducts,
            'sklik' => $options['sklik'],
            'sklikCode' => $options['sklikCode'],
            'googleAds' => $options['googleAds'],
            'googleAdsCode' => $options['googleAdsCode'],
            'googleAdsLabel' => $options['googleAdsLabel'],
            'languageCode' => str_replace('-', '_', $context->language->language_code),
            'fbPixel' => $options['fbPixel'],
            'fbPixelProducts' => $fbProducts,
        );
    }

    /**
     * @param array $products
     * @return array
     */
    public function getOrderConfirmationHeurekaProducts(array $products)
    {
        $query = [];

        foreach ($products as $product) {
            $exactName = $product['name'];

            if (array_key_exists('attributes_small', $product) && $product['attributes_small'] !== '') {
                $tmpName = array_reverse(explode(', ', $product['attributes_small']));
                $exactName .= ': ' . implode(' ', $tmpName);
            }

            $query[] = array(
                'name' => $exactName,
                'qty' => $product['quantity'],
                'unitPrice' => Tools::ps_round(
                    $product['price_wt'], ConfigurationCore::get('PS_PRICE_DISPLAY_PRECISION')
                ),
            );
        }

        return $query;
    }

    public static function getShopId()
    {
        if (ShopCore::isFeatureActive()) {
            $shopID = ContextCore::getContext()->shop->id;
        } else {
            $shopID = 0;
        }

        return $shopID;
    }

    public function mergadoEnableAll($force_all = false)
    {
        // Retrieve all shops where the module is enabled
        $list = ShopCore::getShops(true, null, true);
        if (!$this->id || !is_array($list)) {
            return false;
        }
        $sql = 'SELECT `id_shop` FROM `' . _DB_PREFIX_ . 'module_shop`
                WHERE `id_module` = ' . (int) $this->id .
            ((!$force_all) ? ' AND `id_shop` IN(' . implode(', ', $list) . ')' : '');

        // Store the results in an array
        $items = array();
        if ($results = Db::getInstance($sql)->executeS($sql)) {
            foreach ($results as $row) {
                $items[] = $row['id_shop'];
            }
        }

        // Enable module in the shop where it is not enabled yet
        foreach ($list as $id) {
            if (!in_array($id, $items)) {
                Db::getInstance()->insert('module_shop', array(
                    'id_module' => $this->id,
                    'id_shop' => $id,
                ));
            }
        }

        return true;
    }
}
