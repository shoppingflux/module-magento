<?php

/** @var Mage_Eav_Model_Config $eavConfig */
$eavConfig = Mage::getSingleton('eav/config');

/** @var Mage_Customer_Model_Resource_Setup $customerInstaller */
$customerInstaller = Mage::getResourceModel('customer/setup', 'profileolabs_shoppingflux_setup');
$customerInstaller->startSetup();
$entityTypeId = $customerInstaller->getEntityTypeId('customer');

if (!$attribute = $customerInstaller->getAttribute($entityTypeId, 'from_shoppingflux')) {
    $customerInstaller->addAttribute(
        'customer',
        'from_shoppingflux',
        array(
            'type' => 'int',
            'label' => 'From ShoppingFlux',
            'visible' => true,
            'required' => false,
            'unique' => false,
            'sort_order' => 700,
            'default' => 0,
            'input' => 'select',
            'source' => 'eav/entity_attribute_source_boolean',
        )
    );

    $attribute = $eavConfig->getAttribute('customer', 'from_shoppingflux');
    $attribute->setData('used_in_forms', array('adminhtml_customer'));
    $attribute->setData('sort_order', 700);
    $attribute->save();
}

$customerInstaller->endSetup();

/** @var Mage_Sales_Model_Resource_Setup $salesInstaller */
$salesInstaller = Mage::getResourceModel('sales/setup', 'profileolabs_shoppingflux_setup');
$salesInstaller->startSetup();
$entityTypeId = $salesInstaller->getEntityTypeId('order');

if (!$attribute = $salesInstaller->getAttribute($entityTypeId, 'from_shoppingflux')) {
    $salesInstaller->addAttribute(
        'order',
        'from_shoppingflux',
        array(
            'type' => 'int',
            'label' => 'From ShoppingFlux',
            'visible' => true,
            'required' => false,
            'unique' => false,
            'sort_order' => 700,
            'default' => 0,
            'input' => 'select',
            'source' => 'eav/entity_attribute_source_boolean',
            'grid' => true,
        )
    );
}

if (!$salesInstaller->getAttribute($entityTypeId, 'order_id_shoppingflux')) {
    $salesInstaller->addAttribute(
        'order',
        'order_id_shoppingflux',
        array(
            'type' => 'varchar',
            'label' => 'ID Order ShoppingFlux',
            'visible' => true,
            'required' => false,
            'unique' => false,
            'sort_order' => 705,
            'input' => 'text',
            'grid' => true,
        )
    );
}

if (!$attribute = $salesInstaller->getAttribute($entityTypeId, 'marketplace_shoppingflux')) {
    $salesInstaller->addAttribute(
        'order',
        'marketplace_shoppingflux',
        array(
            'type' => 'varchar',
            'label' => 'Marketplace ShoppingFlux',
            'visible' => true,
            'required' => false,
            'unique' => false,
            'sort_order' => 710,
            'input' => 'text',
            'grid' => true,
        )
    );
}

if (!$attribute = $salesInstaller->getAttribute($entityTypeId, 'fees_shoppingflux')) {
    $salesInstaller->addAttribute(
        'order',
        'fees_shoppingflux',
        array(
            'type' => 'decimal',
            'label' => 'Fees ShoppingFlux',
            'visible' => true,
            'required' => false,
            'unique' => false,
            'sort_order' => 720,
            'input' => 'text',
            'grid' => true,
        )
    );
}

if (!$attribute = $salesInstaller->getAttribute($entityTypeId, 'other_shoppingflux')) {
    $salesInstaller->addAttribute(
        'order',
        'other_shoppingflux',
        array(
            'type' => 'varchar',
            'label' => 'ShoppingFlux Note',
            'visible' => true,
            'required' => false,
            'unique' => false,
            'sort_order' => 710,
            'input' => 'text',
            'grid' => true,
        )
    );
}

if (!$attribute = $salesInstaller->getAttribute($entityTypeId, 'shoppingflux_shipment_flag')) {
    $salesInstaller->addAttribute(
        'order',
        'shoppingflux_shipment_flag',
        array(
            'type' => 'int',
            'label' => 'Is shipped in ShoppingFlux',
            'visible' => true,
            'required' => false,
            'unique' => false,
            'sort_order' => 705,
            'default' => 0,
            'input' => 'select',
            'source' => 'eav/entity_attribute_source_boolean',
            'grid' => true,
        )
    );
}

$salesInstaller->endSetup();

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
            'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
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
            'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
            'visible' => true,
            'used_in_product_listing' => true,
            'frontend_class' => '',
            'required' => false,
            'user_defined' => true,
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

$attributeSetIds = $installer->getAllAttributeSetIds($entityTypeId);

foreach ($attributeSetIds as $attributeSetId) {
    $group = $installer->getAttributeGroup($entityTypeId, $attributeSetId, 'Shopping Flux');
    
    if (!$group) {
        $installer->addAttributeGroup(Mage_Catalog_Model_Product::ENTITY, $attributeSetId, 'Shopping Flux');
    }
    
    $groupId = $installer->getAttributeGroupId(Mage_Catalog_Model_Product::ENTITY, $attributeSetId, 'Shopping Flux');
    $attributeId = $installer->getAttributeId($entityTypeId, 'shoppingflux_product');
    $installer->addAttributeToGroup($entityTypeId, $attributeSetId, $groupId, $attributeId);
    $attributeId = $installer->getAttributeId($entityTypeId, 'shoppingflux_default_category');
    $installer->addAttributeToGroup($entityTypeId, $attributeSetId, $groupId, $attributeId);
}

$installer->endSetup();

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$installer->run(
    "
    CREATE TABLE IF NOT EXISTS `{$this->getTable('profileolabs_shoppingflux/manageorders_log')}` (
    `id` int(11) NOT NULL auto_increment,
    `date` timestamp NOT NULL default CURRENT_TIMESTAMP,
    `message` text NOT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    "
);

$installer->run(
    "
    CREATE TABLE IF NOT EXISTS `{$this->getTable('profileolabs_shoppingflux/export_updates')}` (
    `update_id` int(11) NOT NULL auto_increment,
    `store_id` int(11) NOT NULL,
    `product_sku` varchar(255) NOT NULL,
    `stock_value` int(11) NOT NULL,
    `price_value` decimal(12,4) NOT NULL,
    `old_price_value` decimal(12,4) NOT NULL,
    `updated_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
    PRIMARY KEY (`update_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    "
);

$installer->run(
    "
    CREATE TABLE IF NOT EXISTS `{$this->getTable('profileolabs_shoppingflux/export_flux')}` (
    `id` int(11) NOT NULL auto_increment,
    `product_id` int(11) NOT NULL default 0,
    `sku` varchar(255) NOT NULL default '',
    `store_id` smallint(5) NOT NULL default 1,
    `xml` MEDIUMTEXT NOT NULL,
    `stock_value` INT( 11 ) NOT NULL,
    `price_value` DECIMAL( 12,4 ) NOT NULL,
    `is_in_stock` tinyint(1) NOT NULL,
    `salable` tinyint(1) NOT NULL,
    `is_in_flux` tinyint(1) NOT NULL,
    `type` varchar(50) NOT NULL,
    `visibility` varchar(50) NOT NULL,
    `update_needed` tinyint(1) NOT NULL,
    `should_export` tinyint(1) NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT SF_E_F_UNIQUE UNIQUE (`sku`, `store_id`),
    INDEX (`update_needed`),
    INDEX (`is_in_stock`),
    INDEX (`is_in_flux`),
    INDEX (`type`),
    INDEX (`visibility`),
    INDEX (`should_export`),
    INDEX (`type`, `is_in_stock`, `is_in_flux`, `visibility`, `store_id`, `should_export`),
    INDEX (`type`, `is_in_flux`, `visibility`, `store_id`,`should_export`),
    INDEX (`type`, `is_in_stock`, `visibility`, `store_id`, `should_export`),
    INDEX (`type`, `visibility`, `store_id`, `should_export`),
    INDEX (`sku`),
    INDEX (`store_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    "
);

$installer->run(
    "
    CREATE TABLE IF NOT EXISTS `{$this->getTable('profileolabs_shoppingflux/manageorders_shipping_method')}` (
    `id` int(11) NOT NULL auto_increment,
    `shipping_method` varchar(255) NOT NULL default '',
    `marketplace` varchar(127) NOT NULL default '',
    `last_seen_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT SF_S_M_UNIQUE UNIQUE (`shipping_method`, `marketplace`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    "
);

$installer->run(
    "
    CREATE TABLE IF NOT EXISTS `{$this->getTable('profileolabs_shoppingflux/manageorders_export_shipments')}` (
    `update_id` int(11) NOT NULL auto_increment,
    `shipment_id` int(11) NOT NULL,
    `updated_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
    PRIMARY KEY (`update_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    "
);

$installer->endSetup();

/** @var Profileolabs_Shoppingflux_Helper_Data $helper */
$helper = Mage::helper('profileolabs_shoppingflux');
$helper->generateTokens();
$helper->newInstallation();
