<?php

/**
 * Shoppinflux
 * 
 * @category    Profileolabs
 * @package     Profileolabs_Shoppingflux
 * @author		Vincent Enjalbert - web-cooking.net
 */
/* @var $installer Mage_Catalog_Model_Resource_Eav_Mysql4_Setup */


//$installer = $this;


$installerSales = new Mage_Sales_Model_Mysql4_Setup('profileolabs_shoppingflux_setup');
/* @var $installerSales Mage_Sales_Model_Mysql4_Setup */

$installerSales->startSetup();

$entityId = $installerSales->getEntityTypeId('invoice');
$attribute = $installerSales->getAttribute($entityId, 'fees_shoppingflux');
if (!$attribute) {
    $installerSales->addAttribute('invoice', 'fees_shoppingflux', array(
        'type' => 'decimal',
        'label' => 'Fees ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 720,
        'input' => 'text',
        'grid' => true,
    ));
}


$entityId = $installerSales->getEntityTypeId('creditmemo');
$attribute = $installerSales->getAttribute($entityId, 'fees_shoppingflux');
if (!$attribute) {
    $installerSales->addAttribute('creditmemo', 'fees_shoppingflux', array(
        'type' => 'decimal',
        'label' => 'Fees ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 720,
        'input' => 'text',
        'grid' => true,
    ));
}

$installerSales->endSetup();