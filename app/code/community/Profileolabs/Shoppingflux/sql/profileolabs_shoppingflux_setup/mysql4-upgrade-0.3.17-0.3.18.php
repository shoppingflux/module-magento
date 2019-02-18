<?php

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');
$installer->startSetup();

$installer->run(
    "
    CREATE TABLE IF NOT EXISTS `{$this->getTable('profileolabs_shoppingflux/export_updates')}` (
    `update_id` int(11) NOT NULL auto_increment,
    `store_id` int(11) NOT NULL,
    `product_sku` varchar(255) NOT NULL,
    `stock_value` int(11) NOT NULL,
    `price_value` decimal(12,4) NOT NULL,
    `old_price_value` decimal(12,4) NOT NULL,
    `updated_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
    PRIMARY KEY (`update_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    "
);

$installer->run(
    "
    UPDATE `{$this->getTable('core/config_data')}`
    SET path = 'shoppingflux/configuration/api_key'
    WHERE path = 'shoppingflux_mo/manageorders/api_key'
    "
);

$installer->run(
    "
    UPDATE `{$this->getTable('core/config_data')}`
    SET path = 'shoppingflux/configuration/login'
    WHERE path = 'shoppingflux_export/general/login'
    "
);

$installer->endSetup();
