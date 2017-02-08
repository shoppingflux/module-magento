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

$installer->run("
    UPDATE `{$this->getTable('shoppingflux_export_flux')}`  SET update_needed = 1, should_export = 1;
    ALTER TABLE  `{$this->getTable('shoppingflux_export_flux')}` ADD  `price_value` decimal( 12, 4 ) NOT NULL AFTER  `stock_value`;
    ALTER TABLE  `{$this->getTable('shoppingflux_export_flux')}` ADD  `salable` tinyint( 1 ) NOT NULL AFTER  `is_in_stock`;
        ");
   
 
//Mage::helper('profileolabs_shoppingflux')->generateTokens();
    
foreach (array(
    'shoppingflux_export/attributes_unknow/ean'                 => 'shoppingflux_export/attributes_mapping/ean',
    'shoppingflux_export/attributes_unknow/isbn'                => 'shoppingflux_export/attributes_mapping/isbn',
    'shoppingflux_export/attributes_unknow/ref_manufacturer'    => 'shoppingflux_export/attributes_mapping/ref_manufacturer',
    'shoppingflux_export/attributes_unknow/ref_wholesaler'      => 'shoppingflux_export/attributes_mapping/ref_wholesaler',
    'shoppingflux_export/attributes_unknow/shipping_delay'      => 'shoppingflux_export/attributes_mapping/shipping_delay',
    'shoppingflux_export/attributes_unknow/shipping_send_delay' => 'shoppingflux_export/attributes_mapping/shipping_send_delay',
    'shoppingflux_export/attributes_unknow/brand'               => 'shoppingflux_export/attributes_mapping/brand',
    'shoppingflux_export/attributes_unknow/brand_page_url'      => 'shoppingflux_export/attributes_mapping/brand_page_url',
    'shoppingflux_export/attributes_unknow/ecotaxe'             => 'shoppingflux_export/attributes_mapping/ecotaxe',
    'shoppingflux_export/attributes_unknow/short_name'          => 'shoppingflux_export/attributes_mapping/short_name',
    'shoppingflux_export/attributes_unknow/characteristics'     => 'shoppingflux_export/attributes_mapping/characteristics',
    'shoppingflux_export/attributes_unknow/warranty'            => 'shoppingflux_export/attributes_mapping/warranty',
    'shoppingflux_export/attributes_unknow/kind'                => 'shoppingflux_export/attributes_mapping/kind',
    'shoppingflux_export/attributes_unknow/matter'              => 'shoppingflux_export/attributes_mapping/matter',
    'shoppingflux_export/attributes_unknow/size'                => 'shoppingflux_export/attributes_mapping/size',
    'shoppingflux_export/attributes_unknow/shoe_size'           => 'shoppingflux_export/attributes_mapping/shoe_size',
    'shoppingflux_export/attributes_unknow/dimension'           => 'shoppingflux_export/attributes_mapping/dimension',
    'shoppingflux_export/attributes_know/sku'                   => 'shoppingflux_export/attributes_mapping/sku',
    'shoppingflux_export/attributes_know/name'                  => 'shoppingflux_export/attributes_mapping/name',
    'shoppingflux_export/attributes_know/description'           => 'shoppingflux_export/attributes_mapping/description',
    'shoppingflux_export/attributes_know/short_description'     => 'shoppingflux_export/attributes_mapping/short_description',
    'shoppingflux_export/attributes_know/meta_title'            => 'shoppingflux_export/attributes_mapping/meta_title',
    'shoppingflux_export/attributes_know/meta_description'      => 'shoppingflux_export/attributes_mapping/meta_description',
    'shoppingflux_export/attributes_know/meta_keyword'          => 'shoppingflux_export/attributes_mapping/meta_keyword',
    'shoppingflux_export/attributes_know/weight'                => 'shoppingflux_export/attributes_mapping/weight',
    'shoppingflux_export/attributes_know/color'                 => 'shoppingflux_export/attributes_mapping/color',
    'shoppingflux_export/specific_prices/price'                 => 'shoppingflux_export/attributes_mapping/price',
    'shoppingflux_export/specific_prices/special_price'         => 'shoppingflux_export/attributes_mapping/special_price',
    'shoppingflux_export/attributes_additionnal/list'           => 'shoppingflux_export/attributes_mapping/additional',
    ) as $was => $become) {
    $installer->run(sprintf("UPDATE `%s` SET `path` = '%s' WHERE `path` = '%s'",
        $this->getTable('core/config_data'), $become, $was
    ));
}

    
                        
$installer->endSetup();

$installerSales = new Mage_Sales_Model_Mysql4_Setup('profileolabs_shoppingflux_setup');

$installerSales->startSetup();
$entityId = $installerSales->getEntityTypeId('order');

$attribute = $installerSales->getAttribute($entityId, 'other_shoppingflux');
if (!$attribute)
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

$installerSales->endSetup();