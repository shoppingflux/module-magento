<?php

class Profileolabs_Shoppingflux_Helper_Lock extends Mage_Core_Helper_Abstract
{
    /**
     * @var string[]
     */
    private $_activeLocks = array();

    /**
     * @var resource[]
     */
    private $_lockFiles = array();

    public function __construct()
    {
        register_shutdown_function(array($this, 'unlockAll'));
    }

    /**
     * @param string $code
     * @return resource
     * @throws Mage_Core_Exception
     */
    private function _getLockFile($code)
    {
        if (!isset($this->_lockFiles[$code])) {
            $varDir = Mage::getConfig()->getVarDir('locks');
            $file = $varDir . DS . $code . '.lock';

            if (is_file($file)) {
                $this->_lockFiles[$code] = fopen($file, 'w');
            } else {
                $this->_lockFiles[$code] = fopen($file, 'x');
            }

            if (false === $file) {
                Mage::throwException('Could not open lock file: "' . $code . '.lock"');
            }

            fwrite($this->_lockFiles[$code], date('r'));
        }

        return $this->_lockFiles[$code];
    }

    /**
     * @param string $code
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function lock($code)
    {
        $this->_activeLocks[] = $code;
        flock($this->_getLockFile($code), LOCK_EX | LOCK_NB);
        return $this;
    }

    /**
     * @param string $code
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function lockAndBlock($code)
    {
        $this->_activeLocks[] = $code;
        flock($this->_getLockFile($code), LOCK_EX);
        return $this;
    }

    /**
     * @param string $code
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function unlock($code)
    {
        $this->_activeLocks = array_diff($this->_activeLocks, array($code));
        flock($this->_getLockFile($code), LOCK_UN);
        return $this;
    }

    /**
     * @param string $code
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function isLocked($code)
    {
        if (in_array($code, $this->_activeLocks, true)) {
            return true;
        } else {
            $file = $this->_getLockFile($code);

            if (flock($file, LOCK_EX | LOCK_NB)) {
                flock($file, LOCK_UN);
                return false;
            }

            return true;
        }
    }

    public function unlockAll()
    {
        $this->_activeLocks = array();

        foreach ($this->_lockFiles as $key => $file) {
            @flock($file, LOCK_UN);
            @fclose($file);
            unset($this->_lockFiles[$key]);
        }
    }

    public function __destruct()
    {
        $this->unlockAll();
    }
}
