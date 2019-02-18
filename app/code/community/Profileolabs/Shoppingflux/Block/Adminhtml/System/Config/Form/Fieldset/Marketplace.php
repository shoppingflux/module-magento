<?php

abstract class Profileolabs_Shoppingflux_Block_Adminhtml_System_Config_Form_Fieldset_Marketplace extends Profileolabs_Shoppingflux_Block_Adminhtml_System_Config_Form_Fieldset_Abstract
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        if ($this->shouldRenderUnregistered()) {
            return parent::render($element);
        }

        $html = $this->_getHeaderHtml($element);

        $marketplaceCsvFile = Mage::getModuleDir('', 'Profileolabs_Shoppingflux')
            . DS
            . 'etc'
            . DS
            . 'marketplaces.csv';

        $marketplaces = file($marketplaceCsvFile);
        $i = 1;

        foreach ($marketplaces as $marketplace) {
            $this->_addMarketplaceField($element, $marketplace, 10 * $i++);
        }

        foreach ($element->getSortedElements() as $field) {
            $html .= $field->toHtml();
        }

        $html .= $this->_getFooterHtml($element);
        return $html;
    }

    /**
     * @return string
     */
    abstract protected function _getConfigSectionKey();

    /**
     * @return string
     */
    abstract protected function _getConfigGroupKey();

    /**
     * @return string
     */
    abstract protected function _getFieldSuffix();

    /**
     * @return string
     */
    abstract protected function _getFieldType();

    /**
     * @param string $marketplace
     * @return array
     */
    abstract protected function _getFieldConfig($marketplace);

    /**
     * @param string $name
     * @return string
     */
    protected function _beautifyMarketplaceName($name)
    {
        return ucwords(str_replace('_', ' ', $name));
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $fieldset
     * @param string $marketplace
     * @param int $sortOrder
     */
    protected function _addMarketplaceField(Varien_Data_Form_Element_Abstract $fieldset, $marketplace, $sortOrder)
    {
        $fieldKey = strtolower(preg_replace('%[^a-zA-Z0-9_]%', '', $marketplace)) . '_' . $this->_getFieldSuffix();
        $configPath = $this->_getConfigSectionKey() . '/' . $this->_getConfigGroupKey() . '/' . $fieldKey;
        $configData = $this->getConfigData();
        $fieldValue = '';
        $isInheriting = false;

        if (isset($configData[$configPath])) {
            $fieldValue = $configData[$configPath];
            $isInheriting = false;
        } elseif ($this->getForm()->getConfigRoot()) {
            $fieldValue = (string) $this->getForm()->getConfigRoot()->descend($configPath);
            $isInheriting = true;
        }

        /** @var Mage_Adminhtml_Block_System_Config_Form $form */
        $form = $this->getData('form');

        $field = $fieldset->addField(
            $fieldKey,
            $this->_getFieldType(),
            array_merge(
                $this->_getFieldConfig($marketplace),
                array(
                    'name' => 'groups[' . $this->_getConfigGroupKey() . '][fields][' . $fieldKey . '][value]',
                    'value' => $fieldValue,
                    'sort_order' => $sortOrder,
                    'inherit' => $isInheriting,
                    'scope' => $form->getScope(),
                    'scope_id' => $form->getScopeId(),
                    'scope_label' => $form->getScopeLabel($this->_getDummyElement()),
                    'can_use_default_value' => 0,
                    'can_use_website_value' => 0,
                )
            )
        );

        $field->setRenderer($this->_getFieldRenderer());
    }
}
