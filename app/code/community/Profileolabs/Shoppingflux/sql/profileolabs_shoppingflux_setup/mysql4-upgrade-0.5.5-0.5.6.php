<?php

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');
$installer->startSetup();

$installer->run(
    "
    CREATE TABLE IF NOT EXISTS `{$this->getTable('profileolabs_shoppingflux/manageorders_export_shipments')}` (
    `update_id` int(11) NOT NULL auto_increment,
    `shipment_id` int(11) NOT NULL,
    `updated_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
    PRIMARY KEY (`update_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    "
);

$installer->endSetup();
