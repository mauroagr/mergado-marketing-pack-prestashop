<?php

use Mergado\Tools\SettingsClass;

$fields_form[0]['form'] = array(
    'legend' => array(
        'title' => $this->l('Kelkoo'),
        'icon' => 'icon-cogs',
    ),
    'input' => array(),
    'submit' => array(
        'title' => $this->l('Save'),
        'name' => 'submit' . $this->name
    )
);

$fields_form[0]['form']['input'][] = array(
    'name' => SettingsClass::KELKOO['ACTIVE'],
    'label' => $this->l('Module active'),
    'validation' => 'isBool',
    'cast' => 'intval',
    'type' => (version_compare(_PS_VERSION_, Mergado::PS_V_16) < 0) ? 'radio' : 'switch',
//    'desc' => $this->l('1. Your website must have HTTPS protocol at least on order confirmation page. 2. You have to set your DNS before use. More informations on: https://www.glami.cz/info/reviews/implementation/') . '<br><span class="mmp-tag mmp-tag--question"></span>' . $this->l('Váš API klíč pro Glami TOP naleznete v administraci Glami na stránce Glami TOP > Implementace > Průvodce implementace pro vývojáře > sekce Integrace pomocí Javascriptu.'),
    'class' => 'switch15',
    'values' => array(
        array(
            'id' => 'kelkoo_active_on',
            'value' => 1,
            'label' => $this->l('Yes')
        ),
        array(
            'id' => 'kelkoo_active_off',
            'value' => 0,
            'label' => $this->l('No')
        )
    ),
    'visibility' => Shop::CONTEXT_ALL,
);

$fields_form[0]['form']['input'][] = array(
    'name' => SettingsClass::KELKOO['COUNTRY'],
    'label' => $this->l('Kelkoo country'),
    'type' => 'select',
    'options' => array(
        'query' => SettingsClass::KELKOO_COUNTRIES,
        'id' => 'id_option',
        'name' => 'name'
    )
);

$fields_form[0]['form']['input'][] = array(
    'name' => SettingsClass::KELKOO['COM_ID'],
    'label' => $this->l('Kelkoo merchant id'),
    'type' => 'text',
    'visibility' => Shop::CONTEXT_ALL,
);

include __DIR__ . '/partials/helperForm.php';
