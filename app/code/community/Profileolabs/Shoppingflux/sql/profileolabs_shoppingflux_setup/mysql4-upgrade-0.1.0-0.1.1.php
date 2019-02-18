<?php

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');
$installer->startSetup();
$entityTypeId = $installer->getEntityTypeId('catalog_product');

if (!$attribute = $installer->getAttribute($entityTypeId, 'shoppingflux_product')) {
    $installer->addAttribute(
        'catalog_product',
        'shoppingflux_product',
        array(
            'type' => 'int',
            'backend' => '',
            'frontend' => '',
            'label' => 'Filtrer la présence dans le flux',
            'input' => 'boolean',
            'global' => 1,
            'visible' => 1,
            'required' => 0,
            'user_defined' => 0,
            'default' => 1,
            'searchable' => 0,
            'filterable' => 0,
            'comparable' => 0,
            'visible_on_front' => 0,
            'unique' => 0,
            'used_in_product_listing' => 1,
        )
    );
}

$installer->endSetup();
