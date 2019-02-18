<?php

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');
$installer->startSetup();
$entityTypeId = $installer->getEntityTypeId('catalog_product');

if (!$attribute = $installer->getAttribute($entityTypeId, 'shoppingflux_default_category')) {
    $installer->addAttribute(
        'catalog_product',
        'shoppingflux_default_category',
        array(
            'group' => 'General',
            'type' => 'int',
            'backend' => '',
            'frontend_input' => '',
            'frontend' => '',
            'label' => 'Default Shoppingflux Category',
            'input' => 'select',
            'class' => '',
            'source' => 'profileolabs_shoppingflux/attribute_source_category',
            'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
            'visible' => true,
            'used_in_product_listing' => true,
            'frontend_class' => '',
            'required' => false,
            'user_defined' => false,
            'default' => '',
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'unique' => false,
            'position' => 60,
        )
    );
}

$installer->endSetup();
