<?php

class Profileolabs_Shoppingflux_Model_System_Config_Backend_Category extends Profileolabs_Shoppingflux_Model_System_Config_Backend_Refresh
{
    protected function _afterSave()
    {
        try {
            if ($this->isValueChanged()) {
                /** @var Mage_Eav_Model_Config $eavConfig */
                $eavConfig = Mage::getSingleton('eav/config');
                $attribute = $eavConfig->getAttribute('catalog_product', 'shoppingflux_default_category');
                $attributeId = $attribute->getId();

                /** @var Mage_Core_Model_Resource $resource */
                $resource = Mage::getSingleton('core/resource');
                $write = $resource->getConnection('core_write');
                $write->beginTransaction();

                try {
                    if ($this->getValue()) {
                        $write->update(
                            $resource->getTableName('eav/attribute'),
                            array('source_model' => 'profileolabs_shoppingflux/attribute_source_category'),
                            $write->quoteInto('attribute_id = ?', $attributeId)
                        );

                        $write->update(
                            $resource->getTableName('catalog/eav_attribute'),
                            array('is_visible' => 1),
                            $write->quoteInto('attribute_id = ?', $attributeId)
                        );
                    } else {
                        $write->update(
                            $resource->getTableName('eav/attribute'),
                            array('source_model' => ''),
                            $write->quoteInto('attribute_id = ?', $attributeId)
                        );

                        $write->update(
                            $resource->getTableName('catalog/eav_attribute'),
                            array('is_visible' => 0),
                            $write->quoteInto('attribute_id = ?', $attributeId)
                        );
                    }

                    $write->commit();
                } catch (Exception $e) {
                    $write->rollback();
                }
            }
        } catch (Exception $e) {
        }

        parent::_afterSave();
    }
}
