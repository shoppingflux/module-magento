<?php

/**
 * @category    ShoppingFlux
 * @package     ShoppingFLux_ManageOrders
 * @author 		kassim belghait
 */
$installerCustomer = new Mage_Customer_Model_Entity_Setup('profileolabs_shoppingflux_setup');
/* @var $installerCustomer Mage_Customer_Model_Entity_Setup */

$installerCustomer->startSetup();

//$attribute   = Mage::getModel('eav/config')->getAttribute('customer', 'from_shoppingflux');
$entityId = $installerCustomer->getEntityTypeId('customer');
$attribute = $installerCustomer->getAttribute($entityId, 'from_shoppingflux');
if (!$attribute) {

    $installerCustomer->addAttribute('customer', 'from_shoppingflux', array(
        'type' => 'int',
        'label' => 'From ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 700,
        'default' => 0,
        'input' => 'select',
        'source' => 'eav/entity_attribute_source_boolean',
    ));

    $usedInForms = array(
        'adminhtml_customer',
    );

    $attribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'from_shoppingflux');
    $attribute->setData('used_in_forms', $usedInForms);
    $attribute->setData('sort_order', 700);

    $attribute->save();
}

$installerCustomer->endSetup();

$installerSales = new Mage_Sales_Model_Mysql4_Setup('profileolabs_shoppingflux_setup');
/* @var $installerSales Mage_Sales_Model_Mysql4_Setup */

$installerSales->startSetup();
//if(!Mage::getSingleton('eav/config')->getAttribute('order', 'from_shoppingflux')->getId())
$entityId = $installerSales->getEntityTypeId('order');
$attribute = $installerSales->getAttribute($entityId, 'from_shoppingflux');
if (!$attribute)
    $installerSales->addAttribute('order', 'from_shoppingflux', array(
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
    ));

//if(!Mage::getSingleton('eav/config')->getAttribute('order', 'order_id_shoppingflux')->getId())
$attribute = $installerSales->getAttribute($entityId, 'order_id_shoppingflux');
if (!$attribute)
    $installerSales->addAttribute('order', 'order_id_shoppingflux', array(
        'type' => 'varchar',
        'label' => 'ID Order ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 705,
        'input' => 'text',
        'grid' => true,
    ));

//if(!Mage::getSingleton('eav/config')->getAttribute('order', 'marketplace_shoppingflux')->getId())
$attribute = $installerSales->getAttribute($entityId, 'marketplace_shoppingflux');
if (!$attribute)
    $installerSales->addAttribute('order', 'marketplace_shoppingflux', array(
        'type' => 'varchar',
        'label' => 'Marketplace ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 710,
        'input' => 'text',
        'grid' => true,
    ));

//if(!Mage::getSingleton('eav/config')->getAttribute('order', 'fees_shoppingflux')->getId())
$attribute = $installerSales->getAttribute($entityId, 'fees_shoppingflux');
if (!$attribute)
    $installerSales->addAttribute('order', 'fees_shoppingflux', array(
        'type' => 'decimal',
        'label' => 'Fees ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 720,
        'input' => 'text',
        'grid' => true,
    ));

$installerSales->endSetup();

$installer = $this;

$installer->startSetup();

$installer->run(
        "CREATE TABLE IF NOT EXISTS `{$this->getTable('shoppingflux_log')}` (
			`id` int(11) NOT NULL auto_increment,
			`date` timestamp NOT NULL default CURRENT_TIMESTAMP,
			`message` text NOT NULL,
			PRIMARY KEY  (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

$installer->endSetup();

