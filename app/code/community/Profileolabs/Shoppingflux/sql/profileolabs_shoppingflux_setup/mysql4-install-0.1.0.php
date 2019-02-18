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

$salesInstaller->endSetup();

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

$installer->endSetup();
