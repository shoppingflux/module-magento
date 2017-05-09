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
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Mage
 * @package     Mage_Core
 * @copyright  Copyright (c) 2006-2016 X.commerce, Inc. and affiliates (http://www.magento.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Shopping Flux String Helper
 *
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author     Shopping Feed
 */
class Profileolabs_Shoppingflux_Helper_String extends Mage_Core_Helper_String
{
    /**
     * Parse query string to array
     *
     * @param string $str
     * @return array
     */
    public function parseQueryStr($str)
    {
        /** @var Mage_Core_Helper_String $baseHelper */
        $baseHelper = Mage::helper('core/string');

        if (is_callable(array($baseHelper, 'parseQueryStr'))) {
            $result = $baseHelper->parseQueryStr($str);
        } else {
            $result = array();
            $argSeparator = '&';
            $partsQueryStr = explode($argSeparator, $str);

            foreach ($partsQueryStr as $partQueryStr) {
                if ($this->_validateQueryStr($partQueryStr)) {
                    $param = $this->_explodeAndDecodeParam($partQueryStr);
                    $param = $this->_handleRecursiveParamForQueryStr($param);
                    $result = $this->_appendParam($result, $param);
                }
            }
        }

        return $result;
    }

    /**
     * Validate query pair string
     *
     * @param string $str
     * @return bool
     */
    protected function _validateQueryStr($str)
    {
        if (!$str || (strpos($str, '=') === false)) {
            return false;
        }
        return true;
    }

    /**
     * Prepare param
     *
     * @param string $str
     * @return array
     */
    protected function _explodeAndDecodeParam($str)
    {
        $preparedParam = array();
        $param = explode('=', $str);
        $preparedParam['key'] = urldecode(array_shift($param));
        $preparedParam['value'] = urldecode(array_shift($param));
        return $preparedParam;
    }

    /**
     * Merge array recursive without overwrite keys.
     * PHP function array_merge_recursive merge array
     * with overwrite num keys
     *
     * @param array $baseArray
     * @param array $mergeArray
     * @return array
     */
    protected function _mergeRecursiveWithoutOverwriteNumKeys(array $baseArray, array $mergeArray)
    {
        foreach ($mergeArray as $key => $value) {
            if (is_array($value)) {
                if (array_key_exists($key, $baseArray)) {
                    $baseArray[$key] = $this->_mergeRecursiveWithoutOverwriteNumKeys($baseArray[$key], $value);
                } else {
                    $baseArray[$key] = $value;
                }
            } else {
                if ($key) {
                    $baseArray[$key] = $value;
                } else {
                    $baseArray[] = $value;
                }
            }
        }
        return $baseArray;
    }

    /**
     * Append param to general result
     *
     * @param array $result
     * @param array $param
     * @return array
     */
    protected function _appendParam(array $result, array $param)
    {
        $key = $param['key'];
        $value = $param['value'];

        if ($key) {
            if (is_array($value) && array_key_exists($key, $result)) {
                $result[$key] = $this->_mergeRecursiveWithoutOverwriteNumKeys($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Handle param recursively
     *
     * @param array $param
     * @return array
     */
    protected function _handleRecursiveParamForQueryStr(array $param)
    {
        $value = $param['value'];
        $key = $param['key'];

        $subKeyBrackets = $this->_getLastSubkey($key);
        $subKey = $this->_getLastSubkey($key, false);

        if ($subKeyBrackets) {
            if ($subKey) {
                $param['value'] = array($subKey => $value);
            } else {
                $param['value'] = array($value);
            }

            $param['key'] = $this->_removeSubkeyPartFromKey($key, $subKeyBrackets);
            $param = $this->_handleRecursiveParamForQueryStr($param);
        }

        return $param;
    }

    /**
     * Remove subkey part from key
     *
     * @param string $key
     * @param string $subKeyBrackets
     * @return string
     */
    protected function _removeSubkeyPartFromKey($key, $subKeyBrackets)
    {
        return substr($key, 0, strrpos($key, $subKeyBrackets));
    }

    /**
     * Get last part key from query array
     *
     * @param string $key
     * @param bool $withBrackets
     * @return string
     */
    protected function _getLastSubkey($key, $withBrackets = true)
    {
        $subKey = '';
        $leftBracketSymbol = '[';
        $rightBracketSymbol = ']';

        $firstPos = strrpos($key, $leftBracketSymbol);
        $lastPos = strrpos($key, $rightBracketSymbol);

        if (($firstPos !== false || $lastPos !== false)
            && ($firstPos < $lastPos)
        ) {
            $keyLength = $lastPos - $firstPos + 1;
            $subKey = substr($key, $firstPos, $keyLength);

            if (!$withBrackets) {
                $subKey = ltrim($subKey, $leftBracketSymbol);
                $subKey = rtrim($subKey, $rightBracketSymbol);
            }
        }

        return $subKey;
    }
}
