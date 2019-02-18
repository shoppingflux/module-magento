<?php

class Profileolabs_Shoppingflux_Model_Export_Xml
{
    public function __construct()
    {
    }

    /**
     * @return Profileolabs_Shoppingflux_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('profileolabs_shoppingflux');
    }

    /**
     * @param array $entry
     * @return string
     */
    public function addEntry(array $entry)
    {
        return '<product>' . chr(10) . $this->_arrayToNode($entry) . '</product>' . chr(10);
    }

    /**
     * @param array $params
     * @return string
     */
    public function startXml($params = array())
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . chr(10)
            . '<products version="' . $this->getHelper()->getModuleVersion() . '"';

        foreach ($params as $attrName => $attrValue) {
            $xml .= ' ' . $attrName . '="' . $attrValue . '"';
        }

        $xml .= '>' . chr(10);
        return $xml;
    }

    /**
     * @return string
     */
    public function endXml()
    {
        return '</products>' . chr(10);
    }

    /**
     * @param array $entry
     * @return string
     */
    protected function _arrayToNode(array $entry)
    {
        $node = '';

        foreach ($entry as $key => $value) {
            if (is_array($value)) {
                if (is_string($key)) {
                    $node .= $this->_getNode($key, $this->_arrayToNode($value), false);
                } elseif (is_string(($subKey = current($value)))) {
                    $node .= $this->_getNode($subKey, $this->_arrayToNode($value), false);
                } else {
                    $node .= $this->_arrayToNode($value);
                }
            } else {
                $node .= $this->_getNode($key, $value);
            }
        }

        return $node;
    }

    /**
     * @param string $name
     * @param string $value
     * @param bool $withCData
     * @return string
     */
    protected function _getNode($name, $value, $withCData = true)
    {
        return '<' . $name . '>'
            . ($withCData ? '<![CDATA[' : '')
            . $this->getHelper()->cleanString($value)
            . ($withCData ? ']]>' : '')
            . '</' . $name . '>'
            . chr(10);
    }
}
