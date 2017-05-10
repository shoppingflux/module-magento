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

$installer->run(
        "CREATE TABLE IF NOT EXISTS `{$this->getTable('shoppingflux_shipping_methods')}` (
			`id` int(11) NOT NULL auto_increment,
			`shipping_method` varchar(255) NOT NULL default '',
                        `marketplace` varchar(127) NOT NULL default '',
			`last_seen_at` datetime NOT NULL,
			PRIMARY KEY  (`id`),
                        CONSTRAINT SF_S_M_UNIQUE UNIQUE (`shipping_method`, `marketplace`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

$installer->endSetup();

