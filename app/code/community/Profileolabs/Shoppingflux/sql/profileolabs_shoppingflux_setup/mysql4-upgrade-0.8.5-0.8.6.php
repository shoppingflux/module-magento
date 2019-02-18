<?php

/** @var Mage_Sales_Model_Resource_Setup $salesInstaller */
$salesInstaller = Mage::getResourceModel('sales/setup', 'profileolabs_shoppingflux_setup');
$salesInstaller->startSetup();
$entityTypeId = $salesInstaller->getEntityTypeId('invoice');

if (!$attribute = $salesInstaller->getAttribute($entityTypeId, 'fees_shoppingflux')) {
    $salesInstaller->addAttribute(
        'invoice',
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

$entityTypeId = $salesInstaller->getEntityTypeId('creditmemo');

if (!$attribute = $salesInstaller->getAttribute($entityTypeId, 'fees_shoppingflux')) {
    $salesInstaller->addAttribute(
        'creditmemo',
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
