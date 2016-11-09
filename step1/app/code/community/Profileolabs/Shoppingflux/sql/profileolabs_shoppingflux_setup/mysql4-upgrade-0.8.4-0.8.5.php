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
    UPDATE `{$this->getTable('shoppingflux_export_flux')}`  SET update_needed = 1, should_export = 1;
    ALTER TABLE  `{$this->getTable('shoppingflux_export_flux')}` ADD  `product_id` int( 11 ) NOT NULL default 0 AFTER  `id`;
");
    
$installer->endSetup();