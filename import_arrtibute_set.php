<?php

class ArrtibuteSetImporter
{
    private $_storeIds = array();
    
    private $_groups = array();
    
    private $_attributeSetModel;
    
    private $_entityTypeId;
    
    public function __construct()
    {
        $allStores = Mage::app()->getStores();
        foreach ($allStores as $_eachStoreId => $val) {
            $this->_storeIds[] = Mage::app()->getStore($_eachStoreId)->getId();
        }
        $this->_attributeSetModel = Mage::getModel('eav/entity_setup', 'core_write');
        $this->_entityTypeId = Mage::getModel('catalog/product')->getResource()->getTypeId();
    }
    
    public function import($fileName)
    {
        echo "Reading $fileName.\n";
        $file = fopen($fileName, "r");
        while (!feof($file)) {
            $csv[] = fgetcsv($file, 0, ',');
        }
        $keys = array_shift($csv);
        foreach ($csv as $i=>$row) {
            $csv[$i] = array_combine($keys, $row);
        }
        
        $currentSet = null;
        foreach ($csv as $row) {
            if (trim($row['ID']) != '') {
                $currentSet = $row['NAME'];
                $this->_groups[$currentSet] = array('sku'=>explode(';', $row['PRODUCT SKU']));
                
                echo "Creating Attribute Set [$currentSet].\n";
                $this->_attributeSetModel->addAttributeSet($this->_entityTypeId, $currentSet);
            } else {
                $this->_groups[$currentSet]['groups'][] = array(
                    'name'=>$row['NAME'],
                    'attributes'=>explode(';', $row['DEFAULT GROUP'])
                );
            }
        }
        echo "MEMORY USED : ".convert(memory_get_usage(true)) . "\n";
        
        $this->processAtributeGroup($this->_groups);
        fclose($file);
        unset($csv, $keys, $file);
        
        echo "MEMORY USED : ".convert(memory_get_usage(true)) . "\n";
    }


    protected function processAtributeGroup($sets)
    {
        foreach ($sets as $setId => $setInfo) {
            echo "Adding Group to Attribute Set [$setId].\n";
            foreach ($setInfo['groups'] as $_group) {
                $this->_attributeSetModel->addAttributeGroup($this->_entityTypeId, $setId, $_group['name']);
                echo "  Adding Attribute to Group [".$_group['name']."].\n";
                foreach ($_group['attributes'] as $_attribute) {
                    $attribute = explode('/', $_attribute);
                    echo "   ->Adding [".$attribute[0]."]\n";
                    $this->_attributeSetModel->addAttributeToSet($this->_entityTypeId, $setId, $_group['name'], $attribute[0]);
                }
            }
        }
    }
}
