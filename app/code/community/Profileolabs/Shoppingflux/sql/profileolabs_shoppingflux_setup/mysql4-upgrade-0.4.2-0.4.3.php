<?php

/** @var Mage_Sales_Model_Resource_Setup $salesInstaller */
$salesInstaller = Mage::getResourceModel('sales/setup', 'profileolabs_shoppingflux_setup');
$salesInstaller->startSetup();
$entityTypeId = $salesInstaller->getEntityTypeId('order');

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
