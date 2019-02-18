<?php

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');
$installer->startSetup();
$installer->run("UPDATE `{$this->getTable('profileolabs_shoppingflux/export_flux')}` SET update_needed = 1, should_export = 1");
$installer->endSetup();
