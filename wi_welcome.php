<?php
/**
 * 2007-2021 PrestaShop
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
 *  @copyright 2007-2021 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Wi_welcome extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'wi_welcome';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Jesús Abades';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Webimpacto Welcome Message');
        $this->description = $this->l('Shows a welcome message to your customers.');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        include dirname(__FILE__) . '/sql/install.php';
        $res = parent::install() &&
        $this->registerHook('header') &&
        $this->registerHook('backOfficeHeader');
        $hooks = $this->getAvailableHooks();
        foreach ($hooks as $hook => $desc) {
            $res &= $this->registerHook($hook);
        }
        return $res;
    }

    public function uninstall()
    {
        include dirname(__FILE__) . '/sql/uninstall.php';
        Configuration::deleteByName('WI_WELCOME_LIVE_MODE');

        return parent::uninstall();
    }

    public function getAvailableHooks()
    {
        return array(
            'displayHome' => $this->l('Home'),
            'displayFooter' => $this->l('Footer'),
            'displayFooterBefore' => $this->l('Footer Before'),
        );
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {        
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool) Tools::isSubmit('submitWi_welcomeModule')) == true) {
            $this->postProcess();
        }
        switch (Tools::getValue('tab_sec')) {
            case 'list':
                $html = $this->renderList();
                break;
            case 'help':
                $html = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/help.tpl');
                break;
            default:
                $html = $this->renderForm();
                break;
        }
        $params = array(
            'wi_welcome' => array(
                'module_dir' => $this->_path,
                'module_name' => $this->name,
                'base_url' => _MODULE_DIR_ . $this->name . '/',
                'iso_code' => $this->context->language->iso_code,
                'menu' => $this->getMenu(),
                'html' => $html,
                'errors' => empty($this->errors) ? array() : $this->errors,
            ),
        );

        $this->context->smarty->assign($params);

        $header = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/header.tpl');
        $body = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/body.tpl');
        $footer = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/footer.tpl');

        return $header . $body . $footer;
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
        $helper->submit_action = 'submitWi_welcomeModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
        . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    public function renderList()
    {
        $list = array();
        if (Tools::getValue('WI_WELCOME_ID') && Tools::getValue('wi_delete')) {
            $sql = 'DELETE FROM `' . _DB_PREFIX_ . 'wi_welcome` 
                WHERE `id_wi_welcome` = ' . (int)Tools::getValue('WI_WELCOME_ID');
            Db::getInstance()->execute($sql);
            $sql = 'DELETE FROM `' . _DB_PREFIX_ . 'wi_welcome_lang` 
                WHERE `id_wi_welcome` = ' . (int)Tools::getValue('WI_WELCOME_ID');
            Db::getInstance()->execute($sql);
        }
        $link = $this->context->link->getAdminLink('AdminModules', true) .
            '&configure=' . $this->name . '&tab_module=' . $this->tab .
            '&module_name=' . $this->name;
        $sql = 'SELECT `wiw`.`id_wi_welcome` AS `id`, `wiw`.`name`, `wiw`.`hooks` 
            FROM `' . _DB_PREFIX_ . 'wi_welcome` `wiw` ORDER BY `id_wi_welcome` DESC';
        if ($rows = Db::getInstance()->executeS($sql)) {
            foreach ($rows as $k => $row) {
                $list[$k] = $row;
                $list[$k]['hooks'] = preg_replace('[\[|\]|"]', ' ', $row['hooks']);
            }
        }
        $params = array(
            'wi_welcome' => array(
                'list' => $list,
                'link' => $link
            )
        );
        $this->context->smarty->assign($params);
        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/list.tpl');
    }

    protected function getMenu()
    {
        $tab = Tools::getValue('tab_sec');
        $tab_link = $this->context->link->getAdminLink('AdminModules', true)
        . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name . '&tab_sec=';
        return array(
            array(
                'label' => $this->l('Edit / Create message'),
                'link' => $tab_link . 'edit',
                'active' => ($tab == 'edit' || empty($tab) ? 1 : 0),
            ),
            array(
                'label' => $this->l('Messages list'),
                'link' => $tab_link . 'list',
                'active' => ($tab == 'list' ? 1 : 0),
            ),
            array(
                'label' => $this->l('Help'),
                'link' => $tab_link . 'help',
                'active' => ($tab == 'help' ? 1 : 0),
            ),
        );
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
                        'col' => 6,
                        'type' => 'text',
                        'desc' => $this->l('Enter a welcome message for your customers.'),
                        'name' => 'WI_WELCOME_NAME',
                        'label' => $this->l('Descrition'),
                        'required' => true,
                    ),
                    array(
                        'col' => 9,
                        'type' => 'textarea',
                        'desc' => $this->l('Enter a welcome message for your customers.'),
                        'name' => 'WI_WELCOME_MESSAGE',
                        'label' => $this->l('Message'),
                        'class' => 'rte',
                        'autoload_rte' => true,
                        'lang' => true,
                        'required' => true,
                    ),
                    array(
                        'col' => 6,
                        'type' => 'hooks',
                        'desc' => $this->l('Check the positions which you want to enable this message.'),
                        'name' => 'WI_WELCOME_HOOKS',
                        'label' => $this->l('Enable in positions'),
                        'options' => $this->getAvailableHooks(),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'identifier',
                        'name' => 'WI_WELCOME_ID',
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
        $languages = $this->context->controller->getLanguages();
        if (Tools::getValue('submitWi_welcomeModule') || !Tools::getValue('WI_WELCOME_ID')) {
            $formValues = array(
                'WI_WELCOME_ID' => Tools::getValue('WI_WELCOME_ID', ''),
                'WI_WELCOME_NAME' => Tools::getValue('WI_WELCOME_NAME', ''),                
                'WI_WELCOME_HOOKS' => Tools::getValue('WI_WELCOME_HOOKS', array()),
            );            
            foreach ($languages as $language) {
                $formValues['WI_WELCOME_MESSAGE'][(int) $language['id_lang']]
                    = Tools::getValue('WI_WELCOME_MESSAGE_' . (int) $language['id_lang']);
            }
        } else {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'wi_welcome` `wiw` 
                JOIN `' . _DB_PREFIX_ . 'wi_welcome_lang` `wiwl` 
                    ON `wiw`.`id_wi_welcome` = `wiwl`.`id_wi_welcome` 
                WHERE `wiw`.`id_wi_welcome` = ' . (int) Tools::getValue('WI_WELCOME_ID');            
            if ($rows = Db::getInstance()->executeS($sql)) {
                $row = current($rows);
                $formValues = array(
                    'WI_WELCOME_ID' => $row['id_wi_welcome'],
                    'WI_WELCOME_NAME' => $row['name'],
                    'WI_WELCOME_HOOKS' => Tools::jsonDecode($row['hooks']),                    
                );
                foreach ($languages as $language) {
                    foreach ($rows as $row) {
                        if ($row['id_lang'] == $language['id_lang']) {
                            $formValues['WI_WELCOME_MESSAGE'][(int) $language['id_lang']] = $row['message'];
                        }
                    }
                }
            }
        }
        return $formValues;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        if (Tools::getValue('submitWi_welcomeModule')) {
            $languages = $this->context->controller->getLanguages();
            $id_wi_welcome = Tools::getValue('WI_WELCOME_ID', '');            
            $name = Tools::getValue('WI_WELCOME_NAME', '');
            $hooks = Tools::jsonEncode(
                Tools::getValue('WI_WELCOME_HOOKS', '')
            );
            if (empty($name)) {
                $this->errors[] = $this->l('Please set a descriptive name.');
            }                        
            if (empty($this->errors)) {
                if (empty($id_wi_welcome)) {
                    $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'wi_welcome`
                        (`name`,`hooks`) VALUES
                        ("' . pSQL($name) . '", "' . pSQL($hooks) . '")';
                    Db::getInstance()->execute($sql);
                    $id_wi_welcome = Db::getInstance()->Insert_ID();
                } else {
                    $sql = 'UPDATE `' . _DB_PREFIX_ . 'wi_welcome`
                        SET `name` = "' . pSQL($name) . '", `hooks` = "' . pSQL($hooks) . '"
                        WHERE `id_wi_welcome` = ' . (int) $id_wi_welcome;
                    Db::getInstance()->execute($sql);
                }
                $values = array();
                foreach ($languages as $language) {
                    $message = Tools::getValue('WI_WELCOME_MESSAGE_' . (int) $language['id_lang'], '');
                    if (empty($message)) {
                        $this->errors[] = $this->l('Please complete the message in laguage:') . ' ' . $language['iso_code'];
                    }
                    $values[] = 
                        '(' . (int) $id_wi_welcome . ',' . (int) $language['id_lang'] . ',"' . pSQL($message, true) . '")';
                }
                $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'wi_welcome_lang` (`id_wi_welcome`,`id_lang`,`message`)
                    VALUES ' . implode(',', $values) . '
                    ON DUPLICATE KEY UPDATE `message` = VALUES(`message`)';
                Db::getInstance()->execute($sql);
                $link = $this->context->link->getAdminLink('AdminModules', true) .
                    '&configure=' . $this->name . '&tab_module=list&module_name=' . $this->name . '&tab_sec=list';
                Tools::redirectAdmin($link);
            }
        }
        return null;
    }

    public function getTextBlock($hook = null)
    {
        $id_lang = $this->context->language->id;
        $sql = 'SELECT `wiwl`.`message` FROM `' . _DB_PREFIX_ . 'wi_welcome_lang` `wiwl` 
            JOIN `' . _DB_PREFIX_ . 'wi_welcome` `wiw` ON `wiw`.`id_wi_welcome` = `wiwl`.`id_wi_welcome`
            WHERE `wiwl`.`id_lang` = ' . (int) $id_lang . ' AND `wiw`.`hooks` LIKE "%\"' . $hook . '\"%"';
        if ($text = Db::getInstance()->getValue($sql)) {
            $params = array(
                'wi_welcome' => array(
                    'text' => $text
                )
            );
            $this->context->smarty->assign($params);
            return $this->context->smarty->fetch($this->local_path . 'views/templates/front/block.tpl');
        }
        return null;
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader($params)
    {
        if (Tools::getValue('module_name') == $this->name || Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader($params)
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    public function hookDisplayFooter($params)
    {
        return $this->getTextBlock('displayFooter');
    }

    public function hookDisplayFooterBefore($params)
    {
        return $this->getTextBlock('displayFooterBefore');
    }

    public function hookDisplayHome($params)
    {
        return $this->getTextBlock('displayHome');
    }
}
