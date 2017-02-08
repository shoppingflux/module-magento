<?php

/**
 * Vincent Enjalbert
 *
 * Version Française :
 * *****************************************************************************
 *
 * Notification de la Licence
 *
 * Ce fichier source est sujet au CLUF
 * qui est fourni avec ce module dans le fichier LICENSE-FR.txt.
 * Il est également disponible sur le web à l'adresse suivante:
 * http://www.enjalbert.net/licences/magento/LICENSE-FR.txt
 *
 * =============================================================================
 *        NOTIFICATION SUR L'UTILISATION DE L'EDITION MAGENTO
 * =============================================================================
 * Ce module est conçu pour l'édition COMMUNITY de Magento
 * WebCooking ne garantit pas le fonctionnement correct de cette extension
 * sur une autre édition de Magento excepté l'édition COMMUNITY de Magento.
 * WebCooking ne fournit pas de support d'extension en cas
 * d'utilisation incorrecte de l'édition.
 * =============================================================================
 *
 * English Version :
 * *****************************************************************************
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE-EN.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.web-cooking.net/licences/magento/LICENSE-EN.txt
 *
 * =============================================================================
 *                 MAGENTO EDITION USAGE NOTICE
 * =============================================================================
 * This package designed for Magento COMMUNITY edition
 * WebCooking does not guarantee correct work of this extension
 * on any other Magento edition except Magento COMMUNITY edition.
 * WebCooking does not provide extension support in case of
 * incorrect edition usage.
 * =============================================================================
 *
 * @category   Webcooking
 * @package    Webcooking_All
 * @copyright  Copyright (c) 2011-2014 Vincent René Lucien Enjalbert
 * @license    http://www.web-cooking.net/licences/magento/LICENSE-EN.txt
 */

class Varien_Data_Form_Element_Wcmultiselect extends Varien_Data_Form_Element_Abstract
{
     public function __construct($attributes=array())
    {
        parent::__construct($attributes);
        $this->setType('select');
        $this->setExtType('multiple');
    }

    public function getName()
    {
        $name = parent::getName();
        if (strpos($name, '[]') === false) {
            $name.= '[]';
        }
        return $name;
    }

    public function getElementHtml()
    {
        $this->addClass('select wcmultiselect');
        $html = '';
        if ($this->getCanBeEmpty() && empty($this->_data['disabled'])) {
            $html .= '<input type="hidden" name="' . parent::getName() . '" value="" />';
        }
        
        $html .= '<a href="javascript:void(0);" onclick="javascript:newelem=$(\''.$this->getHtmlId().'\').cloneNode(true);newelem.removeAttribute(\'id\');newelem.setValue(\'\');$(this).next().insert({after:newelem});">';
        $html .= '<img src="' . Mage::getDesign()->getSkinUrl('images/icon_btn_add.gif') . '" alt="Add Row"/>';
        $html .= '</a>' . "\n";
        $html .= '<br/>' . "\n";
        
        
        
        $value = $this->getValue();
        if (!is_array($value)) {
            $value = explode(',', $value);
        }
        $value = array_unique($value);
        $value = array_filter($value);
        array_unshift($value, '');
        foreach($value as $val) {
            $html .= '<select id="' . $this->getHtmlId() . '" name="' . $this->getName() . '" ' .
            $this->serialize($this->getHtmlAttributes()) . '>' . "\n";


            if ($values = $this->getValues()) {
                foreach ($values as $option) {
                    $html .= $this->_optionToHtml($option, $val);
                }
            }

            $html .= '</select>' . "\n";
            $html .= '<a href="javascript:void(0);" onclick="javascript:$(this).previous().remove();$(this).remove()">';
            $html .= '<img src="' . Mage::getDesign()->getSkinUrl('images/icon_btn_delete.gif') . '" alt="Remove Row"/>';
            $html .= '</a>' . "\n";

        }
        
        
        $html .= $this->getAfterElementHtml();

        return $html;
    }

    public function getHtmlAttributes()
    {
        return array('title', 'class', 'style', 'onclick', 'onchange', 'disabled', 'tabindex');
    }

    public function getDefaultHtml()
    {
        $result = ( $this->getNoSpan() === true ) ? '' : '<span class="field-row">'."\n";
        $result.= $this->getLabelHtml();
        $result.= $this->getElementHtml();

        $result.= ( $this->getNoSpan() === true ) ? '' : '</span>'."\n";

        return $result;
    }

    public function getJsObjectName() {
         return $this->getHtmlId() . 'ElementControl';
    }

    protected function _optionToHtml($option, $selected)
    {
        $html = '<option value="'.$this->_escape($option['value']).'"';
        $html.= isset($option['title']) ? 'title="'.$this->_escape($option['title']).'"' : '';
        $html.= isset($option['style']) ? 'style="'.$option['style'].'"' : '';
        if ((string)$option['value'] == $selected) {
            $html.= ' selected="selected"';
        }
        $html.= '>'.$this->_escape($option['label']). '</option>'."\n";
        return $html;
    }
}
