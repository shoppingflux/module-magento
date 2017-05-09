<?php

/**
 * @category    ShoppingFlux
 * @author 	vincent enjalbert
 */


/* CUSTOMER */


$installerCustomer = new Mage_Customer_Model_Entity_Setup('profileolabs_shoppingflux_setup');
/* @var $installerCustomer Mage_Customer_Model_Entity_Setup */

$installerCustomer->startSetup();
Mage::app()->getCacheInstance()->flush(); 
//$attribute   = Mage::getModel('eav/config')->getAttribute('customer', 'from_shoppingflux');
$entityId = $installerCustomer->getEntityTypeId('customer');
$attribute = $installerCustomer->getAttribute($entityId, 'from_shoppingflux');
if (!$attribute) {

    $installerCustomer->addAttribute('customer', 'from_shoppingflux', array(
        'type' => 'int',
        'label' => 'From ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 700,
        'default' => 0,
        'input' => 'select',
        'source' => 'eav/entity_attribute_source_boolean',
        'comment' => 'From ShoppingFeed ?'
    ));

    $usedInForms = array(
        'adminhtml_customer',
    );

    $attribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'from_shoppingflux');
    $attribute->setData('used_in_forms', $usedInForms);
    $attribute->setData('sort_order', 700);

    $attribute->save();
}

$installerCustomer->endSetup();


/* SALES */

$installerSales = new Mage_Sales_Model_Mysql4_Setup('profileolabs_shoppingflux_setup');
/* @var $installerSales Mage_Sales_Model_Mysql4_Setup */

$installerSales->startSetup();

$entityId = $installerSales->getEntityTypeId('order');
$attribute = $installerSales->getAttribute($entityId, 'from_shoppingflux');
if (!$attribute) {
    $installerSales->addAttribute('order', 'from_shoppingflux', array(
        'type' => 'int',
        'label' => 'From ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 700,
        'default' => 0,
        'input' => 'select',
        'source' => 'eav/entity_attribute_source_boolean',
        'grid' => true,
        'comment' => 'From ShoppingFeed ?'
    ));
    try{
        if($this->getTable('enterprise_salesarchive/order_grid')) {
            $installerSales->run("
            ALTER TABLE  `" . $this->getTable('enterprise_salesarchive/order_grid') . "` ADD  `from_shoppingflux` INTEGER(11) NULL;
            ");
        }
    }catch(Exception $e) {}
}

$attribute = $installerSales->getAttribute($entityId, 'order_id_shoppingflux');
if (!$attribute) {
    $installerSales->addAttribute('order', 'order_id_shoppingflux', array(
        'type' => 'varchar',
        'label' => 'ID Order ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 705,
        'input' => 'text',
        'grid' => true,
    ));
    
    try {
        if($this->getTable('enterprise_salesarchive/order_grid')) {
            $installerSales->run("
            ALTER TABLE  `" . $this->getTable('enterprise_salesarchive/order_grid') . "` ADD  `order_id_shoppingflux` varchar(255) NULL;
            ");
        }
    }catch(Exception $e) {}
}

$attribute = $installerSales->getAttribute($entityId, 'marketplace_shoppingflux');
if (!$attribute) {
    $installerSales->addAttribute('order', 'marketplace_shoppingflux', array(
        'type' => 'varchar',
        'label' => 'Marketplace ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 710,
        'input' => 'text',
        'grid' => true,
    ));
    
    try {
        if($this->getTable('enterprise_salesarchive/order_grid')) {
            $installerSales->run("
            ALTER TABLE  `" . $this->getTable('enterprise_salesarchive/order_grid') . "` ADD  `marketplace_shoppingflux` varchar(255) NULL;
            ");
        }
    }catch(Exception $e) {}
}

$attribute = $installerSales->getAttribute($entityId, 'fees_shoppingflux');
if (!$attribute) {
    $installerSales->addAttribute('order', 'fees_shoppingflux', array(
        'type' => 'decimal',
        'label' => 'Fees ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 720,
        'input' => 'text',
        'grid' => true,
    ));
    
    
    try {
        if($this->getTable('enterprise_salesarchive/order_grid')) {
            $installerSales->run("
            ALTER TABLE  `" . $this->getTable('enterprise_salesarchive/order_grid') . "` ADD  `fees_shoppingflux` decimal(12,4) NULL;
            ");
        }
    }catch(Exception $e) {}
}

$attribute = $installerSales->getAttribute($entityId, 'other_shoppingflux');
if (!$attribute) {
    $installerSales->addAttribute('order', 'other_shoppingflux', array(
        'type' => 'varchar',
        'label' => 'ShoppingFlux Note',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 710,
        'input' => 'text',
        'grid' => true,
    ));
    
    
    try {
        if($this->getTable('enterprise_salesarchive/order_grid')) {
            $installerSales->run("
            ALTER TABLE  `" . $this->getTable('enterprise_salesarchive/order_grid') . "` ADD  `other_shoppingflux` varchar(255) NULL;
            ");
        }
    }catch(Exception $e) {}
}


$attribute = $installerSales->getAttribute($entityId, 'shoppingflux_shipment_flag');
if (!$attribute) {
    $installerSales->addAttribute('order', 'shoppingflux_shipment_flag', array(
        'type' => 'int',
        'label' => 'Is shipped in ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 705,
        'default' => 0,
        'input' => 'select',
        'source' => 'eav/entity_attribute_source_boolean',
        'grid' => true,
    ));
    
    
    
    try {
        if($this->getTable('enterprise_salesarchive/order_grid')) {
            $installerSales->run("
            ALTER TABLE  `" . $this->getTable('enterprise_salesarchive/order_grid') . "` ADD  `shoppingflux_shipment_flag` INTEGER(11) NULL;
            ");
        }
    }catch(Exception $e) {}
}


$entityId = $installerSales->getEntityTypeId('invoice');
$attribute = $installerSales->getAttribute($entityId, 'fees_shoppingflux');
if (!$attribute) {
    $installerSales->addAttribute('invoice', 'fees_shoppingflux', array(
        'type' => 'decimal',
        'label' => 'Fees ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 720,
        'input' => 'text',
        'grid' => true,
    ));
    
    
    
    
    try {
        if($this->getTable('enterprise_salesarchive/invoice_grid')) {
            $installerSales->run("
            ALTER TABLE  `" . $this->getTable('enterprise_salesarchive/invoice_grid') . "` ADD  `fees_shoppingflux` decimal(12,4) NULL;
            ");
        }
    }catch(Exception $e) {}
    
}


$entityId = $installerSales->getEntityTypeId('creditmemo');
$attribute = $installerSales->getAttribute($entityId, 'fees_shoppingflux');
if (!$attribute) {
    $installerSales->addAttribute('creditmemo', 'fees_shoppingflux', array(
        'type' => 'decimal',
        'label' => 'Fees ShoppingFlux',
        'visible' => true,
        'required' => false,
        'unique' => false,
        'sort_order' => 720,
        'input' => 'text',
        'grid' => true,
    ));
    
    
    
    
    try {
        if($this->getTable('enterprise_salesarchive/creditmemo_grid')) {
            $installerSales->run("
            ALTER TABLE  `" . $this->getTable('enterprise_salesarchive/creditmemo_grid') . "` ADD  `fees_shoppingflux` decimal(12,4) NULL;
            ");
        }
    }catch(Exception $e) {}
}


$installerSales->endSetup();


//CATALOG
$installer = Mage::getResourceModel('catalog/setup','profileolabs_shoppingflux_setup');

$installer->startSetup();


$entityId = $installer->getEntityTypeId('catalog_category');

$attribute = $installer->getAttribute($entityId,'sf_exclude');
if(!$attribute)
$installer->addAttribute('catalog_category', 'sf_exclude', array(
        'type'              => 'int',
        'group'         => 'General Information',
	'backend'           => '',
	'frontend'          => '',
	'label'        		=> 'Do not export this category in ShoppingFlux',
	'input'             => 'select',
	'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
	'visible'           => 1,
	'required'          => 0,
	'user_defined'      => 0,
	'default'           => 0,
        'source' => 'eav/entity_attribute_source_boolean',
	'unique'            => 0,
));





$entityId = $installer->getEntityTypeId('catalog_product');

$attribute = $installer->getAttribute($entityId,'shoppingflux_product');
if(!$attribute)
$installer->addAttribute('catalog_product', 'shoppingflux_product', array(
    'type'              => 'int',
	'backend'           => '',
	'frontend'          => '',
	'label'        		=> 'Filtrer la présence dans le flux',
	'input'             => 'boolean',
	'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
	'visible'           => 1,
	'required'          => 0,
	'user_defined'      => 1,
	'default'           => 1,
	'searchable'        => 0,
	'filterable'        => 0,
	'comparable'        => 0,
	'visible_on_front'  => 0,
	'unique'            => 0,
        'used_in_product_listing' => 1
));

$attribute = $installer->getAttribute($entityId, 'shoppingflux_default_category');

if (!$attribute) {

    $installer->addAttribute('catalog_product', 'shoppingflux_default_category', array(
        'group' => 'General',
        'type' => 'int',
        'backend' => '',
        'frontend_input' => '',
        'frontend' => '',
        'label' => 'Default Shoppingflux Category',
        'input' => 'select',
        'class' => '',
     //   'source' => 'profileolabs_shoppingflux/attribute_source_category', Optional. Needs to be added manually. To avoid having an error on uninstall if attribute is still there.
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible' => false,// Optional. Needs to be added manually. To avoid having an error on uninstall if attribute is still there.
        'used_in_product_listing' => true,
        'frontend_class' => '',
        'required' => false,
        'user_defined' => true,
        'default' => '',
        'searchable' => false,
        'filterable' => false,
        'comparable' => false,
        'visible_on_front' => false,
        'unique' => false,
        'position' => 60,
    ));
}

$entityTypeId = $installer->getEntityTypeId(Mage_Catalog_Model_Product::ENTITY);
$attrSetIds = $installer->getAllAttributeSetIds($entityTypeId);
foreach ($attrSetIds as $attrSetId) {
    $group = $installer->getAttributeGroup($entityTypeId, $attrSetId, 'Shopping Flux');
    if (!$group) {
        $installer->addAttributeGroup(Mage_Catalog_Model_Product::ENTITY, $attrSetId, 'Shopping Flux');
    }
    $groupId = $installer->getAttributeGroupId(Mage_Catalog_Model_Product::ENTITY, $attrSetId, 'Shopping Flux');


    $attributeId = $installer->getAttributeId($entityTypeId, 'shoppingflux_product');
    $installer->addAttributeToGroup($entityTypeId, $attrSetId, $groupId, $attributeId);
    $attributeId = $installer->getAttributeId($entityTypeId, 'shoppingflux_default_category');
    $installer->addAttributeToGroup($entityTypeId, $attrSetId, $groupId, $attributeId);
}

$installer->endSetup();







// DEFAULT

$installer = $this;

$installer->startSetup();

$installer->run(
        "CREATE TABLE IF NOT EXISTS `{$this->getTable('shoppingflux_log')}` (
			`id` int(11) NOT NULL auto_increment,
			`date` timestamp NOT NULL default CURRENT_TIMESTAMP,
			`message` text NOT NULL,
			PRIMARY KEY  (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
$installer->run(
			"CREATE TABLE IF NOT EXISTS `{$this->getTable('shoppingflux_export_updates')}` (
			`update_id` int(11) NOT NULL auto_increment,
                        `store_id` int(11) NOT NULL,
                        `product_sku` varchar(255) NOT NULL,
                        `stock_value` int(11) NOT NULL,
                        `price_value` decimal(12,4) NOT NULL,
                        `old_price_value` decimal(12,4) NOT NULL,
                        `updated_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
			PRIMARY KEY  (`update_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        
$installer->run(
        "CREATE TABLE IF NOT EXISTS `{$this->getTable('shoppingflux_export_flux')}` (
			`id` int(11) NOT NULL auto_increment,
			`product_id` int(11) NOT NULL default 0,
			`sku` varchar(255) NOT NULL default '',
                        `store_id` smallint(5) NOT NULL default 1,
			`xml` MEDIUMTEXT NOT NULL,
                        `stock_value` INT( 11 ) NOT NULL,
                        `price_value` DECIMAL( 12,4 ) NOT NULL,
			`is_in_stock` tinyint(1) NOT NULL,
			`salable` tinyint(1) NOT NULL,
			`is_in_flux` tinyint(1) NOT NULL,
			`type` varchar(50) NOT NULL,
			`visibility` varchar(50) NOT NULL,
			`update_needed` tinyint(1) NOT NULL,
			`should_export` tinyint(1) NOT NULL,
                        `updated_at` datetime NOT NULL,
			PRIMARY KEY  (`id`),
                        CONSTRAINT SF_E_F_UNIQUE UNIQUE (`sku`, `store_id`),
                        INDEX (`update_needed`),
                        INDEX (`product_id`),
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

$installer->run(
        "CREATE TABLE IF NOT EXISTS `{$this->getTable('shoppingflux_shipping_methods')}` (
			`id` int(11) NOT NULL auto_increment,
			`shipping_method` varchar(255) NOT NULL default '',
                        `marketplace` varchar(127) NOT NULL default '',
			`last_seen_at` datetime NOT NULL,
			PRIMARY KEY  (`id`),
                        CONSTRAINT SF_S_M_UNIQUE UNIQUE (`shipping_method`, `marketplace`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        
$installer->run(
			"CREATE TABLE IF NOT EXISTS `{$this->getTable('shoppingflux_manageorders_export_shipments')}` (
			`update_id` int(11) NOT NULL auto_increment,
                        `shipment_id` int(11) NOT NULL,
                        `updated_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
			PRIMARY KEY  (`update_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
$installer->endSetup();
Mage::app()->getCacheInstance()->flush(); 
Mage::helper('profileolabs_shoppingflux')->generateTokens();
Mage::helper('profileolabs_shoppingflux')->newInstallation();