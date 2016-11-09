<?php

/**
 * Shopping Flux   Block for category page to assiocate products.
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author Vincent Enjalbert @ Web-cooking.net
 */

class Profileolabs_Shoppingflux_Block_Export_Adminhtml_Catalog_Category_Edit_Form extends Mage_Adminhtml_Block_Template {

    public function __construct() {
        parent::__construct();
        $this->setTemplate('profileolabs/shoppingflux/export/category/edit.phtml');
    }

    public function getCategory() {
        return Mage::registry('category');
    }

    public function getProductsJson() {
        $products = array();
        $collection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToFilter('shoppingflux_default_category', $this->getCategory()->getId())
                ->addStoreFilter($this->getRequest()->getParam('store'));
        foreach ($collection as $_product) {
            $products[$_product->getId()] = 1;
        }
        if(!empty($products)) {
            $currentVersion = Mage::getVersion();
            if (version_compare($currentVersion, '1.4.0') < 0) {
                return Zend_Json::encode($products);
            }
            return Mage::helper('core')->jsonEncode($products);
        }
        return '{}';
    }

}