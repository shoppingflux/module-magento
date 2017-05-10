<?php

/* @var $installer Mage_Catalog_Model_Resource_Eav_Mysql4_Setup */
$installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');
$installer->startSetup();

$installer->run(
"
CREATE TABLE IF NOT EXISTS `{$installer->getTable('profileolabs_shoppingflux/not_salable_product')}` (
`product_id` int(10) unsigned NOT NULL,
`store_id` int(10) unsigned NOT NULL,
`not_salable_from` timestamp NOT NULL default '0000-00-00 00:00:00',
PRIMARY KEY (`product_id`),
CONSTRAINT `FK_SHOPPINGFLUX_NOT_SALABLE_PRODUCT_PRODUCT` FOREIGN KEY (`product_id`)
    REFERENCES `{$this->getTable('catalog/product')}` (`entity_id`) ON UPDATE CASCADE ON DELETE CASCADE,
CONSTRAINT `FK_SHOPPINGFLUX_NOT_SALABLE_PRODUCT_STORE` FOREIGN KEY (`store_id`)
    REFERENCES `{$this->getTable('core/store')}` (`store_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{$installer->getTable('profileolabs_shoppingflux/updated_not_salable_product')}` (
`product_id` int(10) unsigned NOT NULL,
`store_id` int(10) unsigned NOT NULL,
PRIMARY KEY (`product_id`, `store_id`),
CONSTRAINT `FK_SHOPPINGFLUX_UPDATED_NOT_SALABLE_PRODUCT_NS_PRODUCT` FOREIGN KEY (`product_id`)
    REFERENCES `{$this->getTable('profileolabs_shoppingflux/not_salable_product')}` (`product_id`) ON UPDATE CASCADE ON DELETE CASCADE,
CONSTRAINT `FK_SHOPPINGFLUX_UPDATED_NOT_SALABLE_PRODUCT_STORE` FOREIGN KEY (`store_id`)
    REFERENCES `{$this->getTable('core/store')}` (`store_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
"
);


// Assume all products to be not salable from the start.
// This will only affect products that are actually not salable, ensuring that they won't be temporarily re-exported.

$connection = $installer->getConnection();
$outOfStockFrom = $connection->quote(date('Y-m-d h:i:s', time() - 86400 * 14));

$oosBaseSelect = $connection->select()
    ->from(
        array('_cpe' => $installer->getTable('catalog/product')),
        array('entity_id')
    )
    ->joinInner(
        array('_cpw' => $installer->getTable('catalog/product_website')),
        '_cpe.entity_id = _cpw.product_id',
        array()
    )
    ->joinInner(
        array('_store' => $installer->getTable('core/store')),
        '_cpw.website_id = _store.website_id',
        array('store_id')
    );

$oosSelect = clone $oosBaseSelect;
$oosSelect->columns(array('not_salable_from' => new Zend_Db_Expr($outOfStockFrom)));

$connection->query(
    $oosSelect->insertIgnoreFromSelect(
        $installer->getTable('profileolabs_shoppingflux/not_salable_product'),
        array('product_id', 'store_id', 'not_salable_from')
    )
);

$connection->query(
    $oosBaseSelect->insertIgnoreFromSelect(
        $installer->getTable('profileolabs_shoppingflux/updated_not_salable_product'),
        array('product_id', 'store_id')
    )
);

$installer->endSetup();
