<?php

/**
 * Shopping Flux
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait
 * @deprecated deprecated since 0.1.1
 */
class Profileolabs_Shoppingflux_Adminhtml_Shoppingfeed_ExportController extends Mage_Adminhtml_Controller_Action {

    protected $_flowModel = null;

    /**
     * Get Singleton Flow
     * @return Profileolabs_Shoppingflux_Model_Export_Flow
     */
    protected function _getFlow() {
        $storeId = $this->getRequest()->getParam('store', 0);
        return Mage::getSingleton('profileolabs_shoppingflux/export_flow')->setStoreId($storeId);
    }

    public function indexAction() {
        $this->_redirect('*/*/edit');
    }

    public function editAction() {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function updateAction() {
        $this->loadLayout();
        $this->renderLayout();

        return $this;
    }

    public function showAction() {

        $this->loadLayout();
        $this->renderLayout();

        return $this;
    }

    /**
     * Product grid for AJAX request
     */
    public function gridAction() {
        $this->loadLayout();
        $this->getResponse()->setBody(
                $this->getLayout()->createBlock('profileolabs_shoppingflux/export_adminhtml_product_grid')->toHtml()
        );
    }

    public function massPublishAction() {
        $productIds = (array) $this->getRequest()->getParam('product');
        $storeId = (int) $this->getRequest()->getParam('store', 0);
        $publish = (int) $this->getRequest()->getParam('publish');
        // die('P: '.$publish);
        //$entityTypeId = Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getEntiyTypeId();
        $resource = Mage::getResourceModel('catalog/product');
        $entityTypeId = $resource->getEntityType()->getId();

        try {
            foreach ($productIds as $productId) {
                $product = new Varien_Object(array('entity_id' => $productId,
                            'id' => $productId,
                            'entity_type_id' => $entityTypeId,
                            'store_id' => $storeId,
                            'shoppingflux_product' => $publish));
                Mage::dispatchEvent('shoppingflux_mass_publish_save_item', array('product_id' => $productId, 'shoppingflux_product'=>$publish));
                $resource->saveAttribute($product, 'shoppingflux_product');
            }
            $this->_getSession()->addSuccess(
                    $this->__('Total of %d record(s) were successfully updated', count($productIds))
            );
        } catch (Mage_Core_Model_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $e->getMessage() . $this->__('There was an error while updating product(s) publication'));
        }

        $this->_redirect('*/*/update', array('store' => $storeId));
    }

    public function runAction() {
        $this->loadLayout();
        $this->renderLayout();

        return $this;
    }

    public function flowRunAction() {
        if ($this->getRequest()->isPost()) {

            $offset = $this->getRequest()->getPost('offset', 1);
            $errors = array();
            $saved = 0;
            try {

                $this->_getFlow()->generateProductsNodes($offset);
                $saved = $this->_getFlow()->getLastCount();
                ;
                $errors = $this->_getFlow()->getErrors();
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }

            $result = array(
                'savedRows' => $saved,
                'errors' => $errors,
                'offset' => $offset,
            );
            $this->getResponse()->setBody(Zend_Json::encode($result));
        }
    }

    protected function _getSession() {
        return Mage::getSingleton('adminhtml/session');
    }

    public function flowFinishAction() {
        $result = array();
        $storeCode = Mage::app()->getStore($this->getRequest()->getParam('store'))->getCode();
        $result['filename'] = Mage::getBaseUrl('media') . "shoppingflux/" . $storeCode . "/flow.xml";
        $this->getResponse()->setBody(Zend_Json::encode($result));
    }

     protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('shoppingflux/export');
    }
}