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

$installer->updateAttribute('catalog_product', 'shoppingflux_default_category', 'is_user_defined', 0);
$installer->updateAttribute('catalog_product', 'shoppingflux_product', 'is_user_defined', 0);



$installer->endSetup();

