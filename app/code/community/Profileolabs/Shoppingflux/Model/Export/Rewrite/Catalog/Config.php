<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2008 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
if (file_exists(BP . '/app/code/local/Amasty/Improved/Model/Rewrite/Config.php')) {

    class Profileolabs_Shoppingflux_Model_Export_Rewrite_Catalog_Config_Compatibility extends Amasty_ImprovedSorting_Model_Rewrite_Config {
        
    }

} else {

    class Profileolabs_Shoppingflux_Model_Export_Rewrite_Catalog_Config_Compatibility extends Mage_Catalog_Model_Config {
        
    }

}

class Profileolabs_Shoppingflux_Model_Export_Rewrite_Catalog_Config extends Profileolabs_Shoppingflux_Model_Export_Rewrite_Catalog_Config_Compatibility {

    /**
     * Get attribute by code for entity type
     *
     * @param   mixed $entityType
     * @param   mixed $code
     * @return  Mage_Eav_Model_Entity_Attribute_Abstract
     */
    public function getAttribute($entityType, $code) {
        $attribute = parent::getAttribute($entityType, $code);
        if (is_object($attribute) && $attribute->getAttributeCode() == "") {
            $attribute->setAttributeCode($code);
        }
        return $attribute;
    }

}
