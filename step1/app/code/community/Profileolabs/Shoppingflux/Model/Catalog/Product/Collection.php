<?php

/**
 * Shopping Flux Service
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author vincent enjalbert @ web-cooking.net
 */
class Profileolabs_Shoppingflux_Model_Catalog_Product_Collection extends Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection {

    /* WebCooking Fix to rewrite Varien_Data_Collection_Db::getSize() in order to solve pagination error caused by the groupBy clause */
    public function getSize() {
        if (is_null($this->_totalRecords)) {
            $sql = $this->getSelectCountSql();

            $result = $this->getConnection()->fetchAll($sql, $this->_bindParams);
            

            foreach ($result as $row) {
                $this->_totalRecords += reset($row);
            }
        }
        return intval($this->_totalRecords);
    }

}
