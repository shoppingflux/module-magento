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

$installer->endSetup();

