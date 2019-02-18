<?php

class Profileolabs_Shoppingflux_Block_Export_Adminhtml_Catalog_Category_Edit_Form extends Mage_Adminhtml_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('profileolabs/shoppingflux/export/category/edit.phtml');
    }

    /**
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory()
    {
        return Mage::registry('category');
    }

    public function getProductsJson()
    {
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->addAttributeToFilter('shoppingflux_default_category', $this->getCategory()->getId());
        $collection->addStoreFilter($this->getRequest()->getParam('store'));
        $products = array_fill_keys($collection->getAllIds(), 1);

        if (!empty($products)) {
            $currentVersion = Mage::getVersion();

            if (version_compare($currentVersion, '1.4.0') < 0) {
                return Zend_Json::encode($products);
            }

            /** @var Mage_Core_Helper_Data $coreHelper */
            $coreHelper = Mage::helper('core');
            return $coreHelper->jsonEncode($products);
        }

        return '{}';
    }
}
