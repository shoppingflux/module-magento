<?php

class Profileolabs_Shoppingflux_Adminhtml_Shoppingfeed_ExportController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->_redirect('*/*/edit');
    }

    public function updateAction()
    {
        $this->loadLayout()->renderLayout();
    }

    public function showAction()
    {
        $this->loadLayout()->renderLayout();
    }

    public function gridAction()
    {
        /** @var Profileolabs_Shoppingflux_Block_Export_Adminhtml_Product_Grid $gridBlock */
        $gridBlock = $this->getLayout()->createBlock('profileolabs_shoppingflux/export_adminhtml_product_grid');
        $this->loadLayout();
        $this->getResponse()->setBody($gridBlock->toHtml());
    }

    public function massPublishAction()
    {
        $productIds = (array) $this->getRequest()->getParam('product');
        $storeId = (int) $this->getRequest()->getParam('store', 0);
        $isExported = (int) $this->getRequest()->getParam('publish');

        /** @var Mage_Catalog_Model_Resource_Product $productResource */
        $productResource = Mage::getResourceModel('catalog/product');
        $entityTypeId = $productResource->getEntityType()->getId();

        try {
            foreach ($productIds as $productId) {
                $product = new Varien_Object(
                    array(
                        'entity_id' => $productId,
                        'id' => $productId,
                        'entity_type_id' => $entityTypeId,
                        'store_id' => $storeId,
                        'shoppingflux_product' => $isExported,
                    )
                );

                Mage::dispatchEvent(
                    'shoppingflux_mass_publish_save_item',
                    array('product_id' => $productId, 'shoppingflux_product' => $isExported)
                );

                $productResource->saveAttribute($product, 'shoppingflux_product');
            }

            $this->_getSession()
                ->addSuccess($this->__('Total of %d record(s) were successfully updated', count($productIds)));
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                $e->getMessage() . $this->__('There was an error while updating product(s) publication')
            );
        }

        $this->_redirect('*/*/update', array('store' => $storeId));
    }

    protected function _isAllowed()
    {
        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');
        return $session->isAllowed('shoppingflux/export');
    }
}
