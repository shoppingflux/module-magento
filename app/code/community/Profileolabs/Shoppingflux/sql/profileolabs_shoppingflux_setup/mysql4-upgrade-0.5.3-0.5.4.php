<?php

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');
$installer->startSetup();

$installer->run(
    "
    CREATE TABLE IF NOT EXISTS `{$this->getTable('profileolabs_shoppingflux/manageorders_shipping_method')}` (
    `id` int(11) NOT NULL auto_increment,
    `shipping_method` varchar(255) NOT NULL default '',
    `marketplace` varchar(127) NOT NULL default '',
    `last_seen_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT SF_S_M_UNIQUE UNIQUE (`shipping_method`, `marketplace`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    "
);

$installer->endSetup();
