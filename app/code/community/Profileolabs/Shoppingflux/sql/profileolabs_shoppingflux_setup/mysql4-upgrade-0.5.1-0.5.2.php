<?php

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');
$installer->startSetup();
$entityTypeId = $installer->getEntityTypeId(Mage_Catalog_Model_Product::ENTITY);
$attributeSetIds = $installer->getAllAttributeSetIds($entityTypeId);

foreach ($attributeSetIds as $attributeSetId) {
    $group = $installer->getAttributeGroup($entityTypeId, $attributeSetId, 'Shopping Flux');

    if (!$group) {
        $installer->addAttributeGroup(Mage_Catalog_Model_Product::ENTITY, $attributeSetId, 'Shopping Flux');
    }

    $groupId = $installer->getAttributeGroupId(Mage_Catalog_Model_Product::ENTITY, $attributeSetId, 'Shopping Flux');
    $attributeId = $installer->getAttributeId($entityTypeId, 'shoppingflux_product');
    $installer->addAttributeToGroup($entityTypeId, $attributeSetId, $groupId, $attributeId);
    $attributeId = $installer->getAttributeId($entityTypeId, 'shoppingflux_default_category');
    $installer->addAttributeToGroup($entityTypeId, $attributeSetId, $groupId, $attributeId);
}

$installer->updateAttribute('catalog_product', 'shoppingflux_default_category', 'is_user_defined', 0);
$installer->updateAttribute('catalog_product', 'shoppingflux_product', 'is_user_defined', 0);

$installer->endSetup();
