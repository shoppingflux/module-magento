<?php

/**
 * Shoppinflux
 * 
 * @category    Profileolabs
 * @package     Profileolabs_Shoppingflux
 * @author		Vincent Enjalbert - web-cooking.net
 */
/* @var $installer Mage_Catalog_Model_Resource_Eav_Mysql4_Setup */
$installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');

$installer->startSetup();

$installer->updateAttribute('catalog_product', 'shoppingflux_default_category', 'is_global', Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE);
$installer->updateAttribute('catalog_product', 'shoppingflux_product', 'is_global', Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE);



$installer->endSetup();