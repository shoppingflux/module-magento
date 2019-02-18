<?php

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');
$installer->startSetup();
$installer->updateAttribute('catalog_product', 'shoppingflux_default_category', 'is_user_defined', 1);
$installer->updateAttribute('catalog_product', 'shoppingflux_product', 'is_user_defined', 1);
$installer->endSetup();
