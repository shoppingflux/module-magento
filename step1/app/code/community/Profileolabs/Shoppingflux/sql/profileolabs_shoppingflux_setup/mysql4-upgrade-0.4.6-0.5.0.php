<?php

/**
 * Shoppinflux
 * 
 * @category    Profileolabs
 * @package     Profileolabs_Shoppingflux
 * @author		Vincent Enjalbert - web-cooking.net
 */
/* @var $installer Mage_Catalog_Model_Resource_Eav_Mysql4_Setup */


$installer = $this;

$installer->startSetup();
$installer->run(
        "CREATE TABLE IF NOT EXISTS `{$this->getTable('shoppingflux_export_flux')}` (
			`id` int(11) NOT NULL auto_increment,
			`sku` varchar(255) NOT NULL default '',
                        `store_id` smallint(5) NOT NULL default 1,
			`xml` MEDIUMTEXT NOT NULL,
			`is_in_stock` tinyint(1) NOT NULL,
			`is_in_flux` tinyint(1) NOT NULL,
			`type` varchar(50) NOT NULL,
			`visibility` varchar(50) NOT NULL,
			`update_needed` tinyint(1) NOT NULL,
			`should_export` tinyint(1) NOT NULL,
                        `updated_at` datetime NOT NULL,
			PRIMARY KEY  (`id`),
                        CONSTRAINT SF_E_F_UNIQUE UNIQUE (`sku`, `store_id`),
                        INDEX (`update_needed`),
                        INDEX (`is_in_stock`),
                        INDEX (`is_in_flux`),
                        INDEX (`type`),
                        INDEX (`visibility`),
                        INDEX (`should_export`),
                        INDEX (`type`, `is_in_stock`, `is_in_flux`, `visibility`, `store_id`, `should_export`),
                        INDEX (`type`, `is_in_flux`, `visibility`, `store_id`,`should_export`),
                        INDEX (`type`, `is_in_stock`, `visibility`, `store_id`, `should_export`),
                        INDEX (`type`, `visibility`, `store_id`, `should_export`),
                        INDEX (`sku`),
                        INDEX (`store_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

$installer->endSetup();

