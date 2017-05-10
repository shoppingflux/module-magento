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

$installer = Mage::getResourceModel('catalog/setup','profileolabs_shoppingflux_setup');

$installer->startSetup();
$installer->run("
ALTER TABLE  `{$this->getTable('shoppingflux_export_flux')}` ADD INDEX (  `product_id` )
");
$installer->endSetup();

Mage::app()->cleanCache();