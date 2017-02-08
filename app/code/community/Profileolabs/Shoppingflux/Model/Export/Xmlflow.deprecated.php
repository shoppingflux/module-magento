<?php
class Profileolabs_Shoppingflux_Model_Export_Xmlflow
{
    protected $_xmlArray = array();
    
    protected $_backendCacheAttributes = array();
    
    protected $_memoryManager = null;
    
    public function __construct()
    {
    	$backendAttributes = array(
    			'cache_dir'                 => Mage::getBaseDir('cache'),
    			'hashed_directory_level'    => 1,
    			'hashed_directory_umask'    => 0777,
    			'file_name_prefix'          => 'mage',
    	);
    }
    
    public function getMemoryManager()
    {
    	if(is_null($this->_memoryManager))
    	{
    		$this->_memoryManager = Zend_Memory::factory('File',$this->_backendCacheAttributes);
    	}
    	 
    	return $this->_memoryManager;
    }
    
    public function _addEntries($entries)
    {
        $this->_xmlArray = $entries;
        return $this;
    }
    
    public function _addEntry($entry)
    {
        $this->_xmlArray[] = $entry;
        return $this;
    }

    public function getXmlArray()
    {
        return $this->_xmlArray;
    }

    
    public function getVersion()
    {
    	return Mage::getConfig()->getModuleConfig("Profileolabs_Shoppingflux")->version;
    }
    
    public function createXml()
    {
        try {
        	if($this->useZendMemory())
        	{
        		$xmlFlow = "";
        		$mem =  $this->getMemoryManager()->create($xmlFlow);
        		
        		$mem->value = '<?xml version="1.0" encoding="utf-8"?>'.chr(10);
        		$mem->value .= '<products count="'.count($this->_xmlArray).'" version="'.$this->getVersion().'">'.chr(10);
        		
        		 
        		foreach ($this->_xmlArray as $key=>$entry)
        		{
        			
        			$mem->value .= "<product>".chr(10);
        				
        			$mem->value .= $this->arrayToNode($entry);
        		
        			$mem->value .= "</product>".chr(10);
        			//unset($this->_xmlArray[$key]);
        		}
        		
        		$mem->value .= "</products>".chr(10);
        		
        		return $mem->value;
        	}
        	else
        	{
        		
       			$xmlFlow = '<?xml version="1.0" encoding="utf-8"?>'.chr(10);
    			$xmlFlow .= '<products count="'.count($this->_xmlArray).'" version="'.$this->getVersion().'">'.chr(10);	

    			$i= 0;
    			foreach ($this->_xmlArray as $key=>$entry)
    			{
    				//Mage::log("i = ".$i++,null,"test_sf.log");
    				$xmlFlow .= "<product>".chr(10);
					
    				$xmlFlow .= $this->arrayToNode($entry);

    				$xmlFlow .= "</product>".chr(10);	
    				//unset($this->_xmlArray[$key]);
    			}

      			$xmlFlow .= "</products>".chr(10); 

      			return $xmlFlow;
        	}
      				
        } catch (Exception $e) {
            return $this->getHelper()->__('Error in processing xml. %s',$e->getMessage());
        }
    }
    
    

   
    
    public function extractData($nameNode,$attributeCode,$product)
    {
    	
    		$_helper = Mage::helper('catalog/output');
    	
    		$data = $product->getData($attributeCode);
			
			$attribute = $product->getResource()->getAttribute($attributeCode);
			if($attribute)
			{				
				$data = $attribute->getFrontend()->getValue($product);
				$data = $_helper->productAttribute($product, $data, $attributeCode);
				
				if($nameNode == 'ecotaxe' && $attribute->getFrontendInput() == 'weee')
				{
					$weeeAttributes = Mage::getSingleton('weee/tax')->getProductWeeeAttributes($product);
					
					foreach ($weeeAttributes as $wa)
					{
						if($wa->getCode() == $attributeCode)
						{
							$data = round($wa->getAmount(),2);
							break;
						}
					}				
				}
			}
			
			
			

			//$_helper = Mage::helper('catalog/output');
			//if($nameNode == 'description' || $nameNode == 'short_description')
				//$data = $_helper->productAttribute($product, $data, $attributeCode);	
			
			//Synthetize it
			/*$method = "get".ucfirst($attributeCode);
			if(method_exists($product,$method))
				$data = $product->$method();*/

			//TODO remove this
			if($data== "No" || $data == "Non")
				$data = "";	

			//Exceptions data
			if($nameNode == 'shipping_delay' && empty($data))
				$data = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_delay');
			
			if($nameNode == 'quantity')
				$data = round($data);
			
			return $data;
    }
    
     /**
     * Get singleton config for Export
     * @return Profileolabs_Shoppingflux_Model_Export_Config
     */
    public function getConfig()
    {
    	return Mage::getSingleton('profileolabs_shoppingflux/config');
    }
    
    protected function arrayToNode($entry)
    {
    	$node = "";
    	
    	if($this->useZendMemory())
    	{
    		$mem =  $this->getMemoryManager()->create($node);
    		foreach ($entry as $key=>$value) {
    			 
    			if(is_array($value))
    			{
    				if(is_string($key))
    					$mem->value.= $this->getNode($key, $this->arrayToNode($value),0);
    				elseif(is_string(($subKey =current($value))))
    				$mem->value.= $this->getNode($subKey, $this->arrayToNode($value),0);
    				else
    					$mem->value.= $this->arrayToNode($value);
    			}
    			else
    				$mem->value .= $this->getNode($key, $value);
    		}
    		
    		
    		return $mem->value;
    	}
    	else
    	{
    		
	    	foreach ($entry as $key=>$value) {
	    		
	    		if(is_array($value))
	    		{
	    			if(is_string($key))
	    				$node.= $this->getNode($key, $this->arrayToNode($value),0);
	    			elseif(is_string(($subKey =current($value))))
	    				$node.= $this->getNode($subKey, $this->arrayToNode($value),0);
	    			else
	    				$node.= $this->arrayToNode($value);
	    		}
	    		else 
	    			$node .= $this->getNode($key, $value);
	    	}

	    	
	    	return $node;
    	}
    }
    
    protected function useZendMemory()
    {
    	return false;
    }
    
    protected function getNode($name,$value,$withCDATA = 1)
	{
		$value = $this->getHelper()->cleanNotUtf8($value);
		$openCDATA = "";
		$closeCDATA = "";
		if($withCDATA)
		{
			$openCDATA = "<![CDATA[";
			$closeCDATA = "]]>";
		}
		return "<{$name}>{$openCDATA}{$value}{$closeCDATA}</{$name}>".chr(10);
	}
	
	/**
     * Return Shoppingflu Helper
     * @return Profileolabs_Shoppingflux_Helper_Data
     */
    protected function getHelper()
    {
    	return Mage::helper('profileolabs_shoppingflux');
    }
    
    
}