<?php

/**
 * Shoppinflux
 * 
 * @category    Profileolabs
 * @package     Profileolabs_Shoppingflux
 * @author		Vincent Enjalbert - web-cooking.net
 */
/* @var $installer Mage_Catalog_Model_Resource_Eav_Mysql4_Setup */



$installerSales = new Mage_Sales_Model_Mysql4_Setup('profileolabs_shoppingflux_setup');
/* @var $installerSales Mage_Sales_Model_Mysql4_Setup */

$installerSales->startSetup();

$entityId = $installerSales->getEntityTypeId('order');

$attribute = $installerSales->getAttribute($entityId, 'shoppingflux_shipment_flag');
if (!$attribute) {
    $installerSales->addAttribute('order', 'shoppingflux_shipment_flag', array(
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
    ));
}

$installerSales->endSetup();


$installer = $this;

$installer->startSetup();

$installer->endSetup();
