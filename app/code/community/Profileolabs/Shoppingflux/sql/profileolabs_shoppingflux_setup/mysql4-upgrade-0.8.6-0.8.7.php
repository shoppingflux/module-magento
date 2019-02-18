<?php

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');
$installer->startSetup();

$installer->run("ALTER TABLE  `{$this->getTable('profileolabs_shoppingflux/export_flux')}` ADD INDEX (`product_id`)");

$installer->endSetup();
Mage::app()->cleanCache();
