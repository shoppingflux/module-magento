<?php

require_once('abstract.php');

class Profileolabs_Shoppingflux_Shell_Feed_Exporter extends Mage_Shell_Abstract
{
    public function appendProductNode(array $args)
    {
        fwrite($args['file'], $args['row']['xml']);
    }

    /**
     * @return void
     */
    public function run()
    {
        if ($this->getArg('help')) {
            echo $this->usageHelp();
            return;
        }

        $storeId = (int) $this->getArg('store-id');

        if (empty($storeId)) {
            echo 'The store ID for which to export the feed must be specified.' . "\n";
            return;
        }

        /** @var Mage_Core_Model_App_Emulation $appEmulation */
        $appEmulation = Mage::getSingleton('core/app_emulation');
        $appEmulation->startEnvironmentEmulation($storeId);

        // Force the initialization of the customer session,
        // which could otherwise get initialized after output is started.
        Mage::getSingleton('customer/session');

        $feedFileDir = ltrim(trim($this->getArg('file-dir')), DS . '/');

        if (empty($feedFileDir)) {
            $feedFileDir = 'media';
        }

        $feedFileDir = dirname(Mage::getRoot()) . DS . $feedFileDir;

        /** @var Mage_Core_Model_Config_Options $storageModel */
        $storageModel = Mage::getSingleton('core/config_options');

        try {
            if (false === $storageModel->createDirIfNotExists($feedFileDir)) {
                throw new Exception('The path is not writable or does not point to a directory.');
            }
        } catch (Exception $e) {
            echo 'Could not initialize the "' . $feedFileDir . '" directory:' . "\n";
            echo $e->getMessage() . "\n";
            return;
        }

        $feedFileName = trim($this->getArg('file-name'));

        if (empty($feedFileName)) {
            $feedFileName = 'feed_' . $storeId . '.xml';
        }

        /** @var Profileolabs_Shoppingflux_Model_Config $config */
        $config = Mage::getSingleton('profileolabs_shoppingflux/config');

        error_reporting(-1);
        ini_set('display_errors', 1);
        set_time_limit(0);
        ini_set('memory_limit', $config->getMemoryLimit() . 'M');

        Profileolabs_Shoppingflux_Model_Export_Observer::checkStock();
        $useAllStores = $config->getUseAllStoreProducts();

        /** @var Profileolabs_Shoppingflux_Model_Export_Flux $fluxModel */
        $fluxModel = Mage::getModel('profileolabs_shoppingflux/export_flux');

        $maxImportLimit = 1000;
        $memoryLimit = ini_get('memory_limit');

        if (preg_match('%M$%', $memoryLimit)) {
            $memoryLimit = (int) $memoryLimit * 1024 * 1024;
        } elseif (preg_match('%G$%', $memoryLimit)) {
            $memoryLimit = (int) $memoryLimit * 1024 * 1024 * 1024;
        } else {
            $memoryLimit = false;
        }

        if ($memoryLimit > 0) {
            if ($memoryLimit <= 128 * 1024 * 1024) {
                $maxImportLimit = 100;
            } elseif ($memoryLimit <= 256 * 1024 * 1024) {
                $maxImportLimit = 500;
            } elseif ($memoryLimit >= 1024 * 1024 * 1024) {
                $maxImportLimit = 3000;
            } elseif ($memoryLimit >= 2048 * 1024 * 1024) {
                $maxImportLimit = 6000;
            }
        }

        if (!$this->getArg('no-update')) {
            echo 'Updating refreshable products...' . "\n";
            $fluxModel->updateFlux($useAllStores ? false : $storeId, $maxImportLimit);
            echo 'The refreshable products have been updated.' . "\n";
        }

        /** @var Profileolabs_Shoppingflux_Model_Mysql4_Export_Flux_Collection $collection */
        $collection = Mage::getResourceModel('profileolabs_shoppingflux/export_flux_collection');
        $collection->addFieldToFilter('should_export', 1);
        $withNotSalableRetention = $config->isNotSalableRetentionEnabled();

        if ($useAllStores) {
            $collection->getSelect()->group(array('sku'));
        } else {
            $collection->addFieldToFilter('store_id', $storeId);
        }

        $totalSize = $collection->getSize();
        $collection->clear();

        if (!$config->isExportNotSalable() && !$withNotSalableRetention) {
            $collection->addFieldToFilter('salable', 1);
        }

        if (!$config->isExportSoldout() && !$withNotSalableRetention) {
            $collection->addFieldToFilter('is_in_stock', 1);
        }

        if ($config->isExportFilteredByAttribute()) {
            $collection->addFieldToFilter('is_in_flux', 1);
        }

        $visibilities = $config->getVisibilitiesToExport();
        $visibilities = array_filter($visibilities);
        $collection->getSelect()->where('FIND_IN_SET(visibility, ?)', implode(',', $visibilities));


        /** @var Profileolabs_Shoppingflux_Model_Export_Xml $xmlObject */
        $xmlObject = Mage::getModel('profileolabs_shoppingflux/export_xml');

        /** @var Mage_Core_Model_Date $dateModel */
        $dateModel = Mage::getModel('core/date');

        $feedContent = $xmlObject->startXml(
            array(
                'store_id' => $storeId,
                'generated-at' => date('d/m/Y H:i:s', $dateModel->timestamp(time())),
                'size-exportable' => $totalSize,
                'size-xml' => $collection->count(),
                'with-out-of-stock' => (int) $config->isExportSoldout(),
                'with-not-salable' => (int) $config->isExportNotSalable(),
                'selected-only' => (int) $config->isExportFilteredByAttribute(),
                'visibilities' => implode(',', $visibilities),
            )
        );

        $feedFilePath = $feedFileDir . DS . $feedFileName;
        $feedTempFilePath = $feedFileDir . DS . $feedFileName . '.tmp';
        $feedTempFile = fopen($feedTempFilePath, 'w');

        if (false === $feedTempFile) {
            echo 'Could not open temporary export file "' . $feedTempFilePath . '".' . "\n";
            return;
        }

        echo 'Exporting the feed in "' . $feedTempFilePath . '"...' . "\n";

        fwrite($feedTempFile, $feedContent);

        try {
            /** @var Mage_Core_Model_Resource_Iterator $iterator */
            $iterator = Mage::getSingleton('core/resource_iterator');

            $iterator->walk(
                $collection->getSelect(),
                array(array($this, 'appendProductNode')),
                array('file' => $feedTempFile)
            );

            fwrite($feedTempFile, $xmlObject->endXml());

            echo 'The feed has been successfully exported.' . "\n";

            if (false === rename($feedTempFilePath, $feedFilePath)) {
                throw new Exception('Could not copy "' . $feedTempFilePath . '" to "' . $feedFilePath . '".');
            }

            echo 'The feed has been successfully copied to "' . $feedFilePath . '".' . "\n";

        } catch (Exception $e) {
            echo 'Could not export the feed:' . "\n";
            echo $e->getMessage() . "\n";
        }

        if (false !== $feedTempFile) {
            fclose($feedTempFile);
        }
    }

    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f shoppingfeedExporter.php -- [options]

  --store-id  <store_id>     Set the ID of the store view for which to export the feed
  --file-dir  <dir>          Set the directory where to export the feed
  --file-name <name>         Set the name of the exported feed file
  --no-update                Disable the update of refreshable products
  help                       Display this help

USAGE;
    }
}

$shell = new Profileolabs_Shoppingflux_Shell_Feed_Exporter();
$shell->run();
