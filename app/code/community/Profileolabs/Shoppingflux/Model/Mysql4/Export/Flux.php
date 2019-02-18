<?php

class Profileolabs_Shoppingflux_Model_Mysql4_Export_Flux extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init('profileolabs_shoppingflux/export_flux', 'id');
    }

    /**
     * @param int|null $storeId
     */
    public function markExportableAsUpdatable($storeId = null)
    {
        $adapter = $this->_getWriteAdapter();
        $conditions = array($adapter->quoteInto('should_export = ?', 1));

        if ($storeId !== null) {
            $conditions[] = $adapter->quoteInto('store_id = ?', $storeId);
        }

        $adapter->update(
            $this->getMainTable(),
            array('update_needed' => 1),
            implode(' AND ', $conditions)
        );
    }

    /**
     * @param int|null $storeId
     */
    public function markAllAsUpdatable($storeId = null)
    {
        $adapter = $this->_getWriteAdapter();
        $conditions = array();

        if (!is_null($storeId)) {
            $conditions[] = $adapter->quoteInto('store_id = ?', $storeId);
        }

        $adapter->update(
            $this->getMainTable(),
            array('update_needed' => 1),
            implode(' AND ', $conditions)
        );
    }

    /**
     * @param int $storeId
     * @return array
     */
    public function getStoreState($storeId)
    {
        $adapter = $this->_getReadAdapter();

        $state = $adapter->fetchRow(
            $adapter->select()
                ->from(
                    $this->getMainTable(),
                    array(
                        'total_updatable' => new Zend_Db_Expr('SUM(update_needed)'),
                        'total_exportable' => new Zend_Db_Expr('SUM(should_export)'),
                        'total' => new Zend_Db_Expr('COUNT(*)'),
                    )
                )
                ->where('store_id = ?', $storeId)
        );

        $state['total_not_exportable'] = $state['total'] - $state['total_exportable'];
        return $state;
    }
}
