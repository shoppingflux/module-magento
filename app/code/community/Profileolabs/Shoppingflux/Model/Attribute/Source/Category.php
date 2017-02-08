<?php

/**
 * Shopping Flux Categories source for product attribute
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author Vincent Enjalbert @ Web-cooking.net
 */
class Profileolabs_Shoppingflux_Model_Attribute_Source_Category extends Mage_Eav_Model_Entity_Attribute_Source_Abstract {

    protected $_options = null;

   

    public function getAllOptions($withEmpty = false) {
        if (is_null($this->_options)) {
            $this->_options = array();
            $categories = Mage::helper('profileolabs_shoppingflux')->getCategoriesWithParents('name', Mage::app()->getRequest()->getParam('store', null));
            foreach($categories as $categoryId=>$categoryName)
                $this->_options[] = array('label'=>$categoryName, 'value'=>$categoryId);
        }
        $options = $this->_options;
        if ($withEmpty) {
            array_unshift($options, array('value' => '', 'label' => ''));
        }
        return $options;
    }

    public function getOptionText($value) {
        $options = $this->getAllOptions(false);

        foreach ($options as $item) {
            if ($item['value'] == $value) {
                return $item['label'];
            }
        }
        return false;
    }

    public function getFlatColums() {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $column = array(
            'unsigned' => false,
            'default' => null,
            'extra' => null
        );

        $currentVersion = Mage::getVersion();
        if (version_compare($currentVersion, '1.6.0') < 0 || Mage::helper('core')->useDbCompatibleMode()) {
            $column['type'] = 'int(10)';
            $column['is_null'] = true;
        } else {
            $column['type'] = Varien_Db_Ddl_Table::TYPE_SMALLINT;
            $column['length'] = 10;
            $column['nullable'] = true;
            $column['comment'] = $attributeCode . ' column';
        }

        return array($attributeCode => $column);
    }

    public function getFlatUpdateSelect($store) {
        return Mage::getResourceModel('eav/entity_attribute')
                        ->getFlatUpdateSelect($this->getAttribute(), $store);
    }

}