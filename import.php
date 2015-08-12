<?php
    // Uncomment to enable debugging
    // ini_set('display_errors', '1');
    // ini_set('error_reporting', E_ALL);

    // Increase memory limit
    ini_set('memory_limit', '2048M');
    // Increase maximum execution time to 4 hours
    //ini_set('max_execution_time', 14400);

    define('MAGENTO', realpath(dirname(__FILE__)).'/..');
    require_once MAGENTO . '/app/Mage.php';

    // Set working directory to magento root folder
    chdir(MAGENTO);

    // Make files written by the profile world-writable/readable
    umask(0);
    
    function convert($size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024, ($i=floor(log($size, 1024)))), 2).' '.$unit[$i];
    }
    
    echo "MEMORY USED : ".convert(memory_get_usage(true)) . "\n\n";
    // Initialize the admin application
    Mage::app('admin');
    
    $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');
    /*
    |--------------------------------------------------------------------------
    | Attribute set Delete
    |--------------------------------------------------------------------------
    */
    
    /*
    $resource = Mage::getSingleton('core/resource');
    $db_read = $resource->getConnection('core_read');
     
    $attribute_sets = $db_read->fetchCol("SELECT attribute_set_id FROM " . $resource->getTableName("eav_attribute_set") . " WHERE attribute_set_id<> 4 AND entity_type_id=4");
    foreach ($attribute_sets as $attribute_set_id) {
        try {
            Mage::getModel("eav/entity_attribute_set")->load($attribute_set_id)->delete();
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }
    */
    
    /*
    |--------------------------------------------------------------------------
    | Attribute Delete
    |--------------------------------------------------------------------------
    | To delete attributes just create a csv file with name oldAttributes and
    | uncomment the below section and run it.
    |		
    */
    
    /*
    $fileName = MAGENTO . '/var/oldAttributes.csv';
    $file = fopen($fileName,"r");
    $oldCode = array();
    while(!feof($file)){
        $tmpCode = fgetcsv($file, 0, ',');
        if(is_array($tmpCode)){
            $oldCode[] = $tmpCode[0];	
        }  
    }
    fclose($file);
    
    $attrCollection = Mage::getResourceModel('catalog/product_attribute_collection')
                                ->addFilter('is_user_defined','1')
                                ->addFieldToFilter('main_table.attribute_code', array('in' => $oldCode));
    foreach($attrCollection as $_attibute) {
        if ($_attibute->getIsUserDefined()) {
            try {
                $_attibute->delete();
            } catch (Exception $e) {
                echo  $_attibute->getAttributeCode()." -- ".$e->getMessage() .'\n';
                
            }
        }	
    }
    */
    
    /*
    |--------------------------------------------------------------------------
    | Attribute Import
    |--------------------------------------------------------------------------
    */
    
    echo "Creating Attribute. \n";
    
    $fileName = MAGENTO . '/var/importAttribute.csv';
    
    $importer = new ArrtibuteImporter();
    
    $importer->getAttributeCsv($fileName);
    
    unset($importer);
    
    echo "\nMEMORY USED : ".convert(memory_get_usage(true)) . "\n\n";
    
    /*
    |--------------------------------------------------------------------------
    | Attribute Set Import
    |--------------------------------------------------------------------------
    */
    echo "Creating Attribute Sets. \n";
    
    
    $fileName = MAGENTO . '/var/importAttributeSet.csv';
    
    $importer = new ArrtibuteSetImporter();
    
    $writeConnection->query("SET FOREIGN_KEY_CHECKS = 0");
    
    $importer->getAttributeSetCsv($fileName);
    
    $writeConnection->query("SET FOREIGN_KEY_CHECKS = 1");
    
    unset($importer);
    
    echo "\nMEMORY USED : ".convert(memory_get_usage(true)) . "\n\n";
    
    
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
        
        public function getAttributeSetCsv($fileName)
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
                        echo "   ->Adding []".$attribute[0]."]\n";
                        $this->_attributeSetModel->addAttributeToSet($this->_entityTypeId, $setId, $_group['name'], $attribute[0]);
                    }
                }
            }
        }
    }
    
    /**
     * Class for Arrtibute Import
     */
    class ArrtibuteImporter
    {
        private $_storeIds = array();
        
        public function __construct()
        {
            $allStores = Mage::app()->getStores();
            foreach ($allStores as $_eachStoreId => $val) {
                $this->_storeIds[] = Mage::app()->getStore($_eachStoreId)->getId();
            }
        }
        
        public function getAttributeCsv($fileName)
        {
            // $csv = array_map("str_getcsv", file($fileName,FILE_SKIP_EMPTY_LINES));
            echo "Reading $fileName.\n";
            $file = fopen($fileName, "r");
            while (!feof($file)) {
                $csv[] = fgetcsv($file, 0, ',');
            }
            $keys = array_shift($csv);
            foreach ($csv as $i=>$row) {
                $csv[$i] = array_combine($keys, $row);
            }
            foreach ($csv as $row) {
                $labelText = $row['frontend_label'];
                $attributeCode = $row['attribute_code'];
                if ($row['_options'] != "") {
                    $options = array_unique(explode(";", $row['_options']));
                } // add this to createAttribute parameters and call "addAttributeValue" function.
                else {
                    $options = -1;
                }
                if ($row['apply_to'] != "") {
                    $productTypes = explode(",", $row['apply_to']);
                } else {
                    $productTypes = -1;
                }
                unset($row['category_ids'], $row['frontend_label'], $row['attribute_code'], $row['_options'], $row['apply_to'], $row['attribute_id'], $row['entity_type_id'], $row['search_weight']);
                $this->createAttribute($labelText, $attributeCode, $row, $productTypes, -1, $options);
                echo "MEMORY USED : ".convert(memory_get_usage(true)) . "\n";
            }
            fclose($file);
            unset($csv, $keys, $file);
        }
        
        
        /**
         * Create an attribute.
         *
         * For reference, see Mage_Adminhtml_Catalog_Product_AttributeController::saveAction().
         *
         * @return int|false
         */
        public function createAttribute($labelText, $attributeCode, $values = -1, $productTypes = -1, $setInfo = -1, $options = -1)
        {
            $labelText = trim($labelText);
            $attributeCode = trim($attributeCode);
        
            if ($labelText == '' || $attributeCode == '') {
                echo "Can't import the attribute with an empty label or code.  LABEL= [$labelText]  CODE= [$attributeCode]"." \n";
                return false;
            }
        
            if ($values === -1) {
                $values = array();
            }
        
            if ($productTypes === -1) {
                $productTypes = array();
            }
        
            if ($setInfo !== -1 && (isset($setInfo['SetID']) == false || isset($setInfo['GroupID']) == false)) {
                echo "Please provide both the set-ID and the group-ID of the attribute-set if you'd like to subscribe to one."." \n";
                return false;
            }
        
            echo "Creating attribute [$labelText] with code [$attributeCode]."." \n";
        
            //>>>> Build the data structure that will define the attribute. See
            //     Mage_Adminhtml_Catalog_Product_AttributeController::saveAction().

            $data = array(
                            'is_global'                     => '0',
                            'frontend_input'                => 'text',
                            'default_value_text'            => '',
                            'default_value_yesno'           => '0',
                            'default_value_date'            => '',
                            'default_value_textarea'        => '',
                            'is_unique'                     => '0',
                            'is_required'                   => '0',
                            'frontend_class'                => '',
                            'is_searchable'                 => '1',
                            'is_visible_in_advanced_search' => '1',
                            'is_comparable'                 => '1',
                            'is_used_for_promo_rules'       => '0',
                            'is_html_allowed_on_front'      => '1',
                            'is_visible_on_front'           => '0',
                            'used_in_product_listing'       => '0',
                            'used_for_sort_by'              => '0',
                            'is_configurable'               => '0',
                            'is_filterable'                 => '0',
                            'is_filterable_in_search'       => '0',
                            'backend_type'                  => 'varchar',
                            'default_value'                 => '',
                            'is_user_defined'               => '0',
                            'is_visible'                    => '1',
                            'is_used_for_price_rules'       => '0',
                            'position'                      => '0',
                            'is_wysiwyg_enabled'            => '0',
                            'backend_model'                 => '',
                            'attribute_model'               => '',
                            'backend_table'                 => '',
                            'frontend_model'                => '',
                            'source_model'                  => '',
                            'note'                          => '',
                            'frontend_input_renderer'       => '',
                            'position_in_key_features'        => '',
                            'use_in_key_features'            => '0',
                            'use_in_key_features'            => '0',
                            'is_used_for_customer_segment'    => '0',
                            'is_used_for_target_rules'        => '0',
                        );
        
            
        
            // Valid product types: simple, grouped, configurable, virtual, bundle, downloadable, giftcard
            $data['apply_to']       = $productTypes;
            $data['attribute_code'] = $attributeCode;
            $data['frontend_label'] = array(0 => $labelText);
            
            $arrtibuteFrontname = explode(':', $values['arrtibute_frontname']);
            unset($values['arrtibute_frontname']);
            $arrtibuteStoreFrontname = array();
            foreach ($arrtibuteFrontname as $_names) {
                list($name, $storeId) = explode('|', $_names);
                $arrtibuteStoreFrontname[$storeId] = $name;
            }
            
            foreach ($this->_storeIds as $_store) {
                $data['frontend_label'][$_store] = $arrtibuteStoreFrontname[$_store];
            }

            // Now, overlay the incoming values on to the defaults.
            foreach ($values as $key => $newValue) {
                if (isset($data[$key]) == false) {
                    echo "Attribute feature [$key] is not valid."." \n";
                    //return false;
                } else {
                    $data[$key] = $newValue;
                }
            }
            
            // Build the model.		
            $model = Mage::getModel('catalog/resource_eav_attribute');
        
            $model->addData($data);
        
            if ($setInfo !== -1) {
                $model->setAttributeSetId($setInfo['SetID']);
                $model->setAttributeGroupId($setInfo['GroupID']);
            }
        
            $entityTypeID = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
            $model->setEntityTypeId($entityTypeID);
        
            $model->setIsUserDefined(1);
        
            //<<<<

            // Save.

            try {
                $model->save();
            } catch (Exception $ex) {
                echo "Attribute [$labelText] could not be saved: " . $ex->getMessage()." \n";
                return false;
            }
            
            if (is_array($options)) {
                echo "Adding (".count($options).") attribute value for [$attributeCode]."." \n";
                foreach ($options as $_opt) {
                    $this->addAttributeValue($attributeCode, $_opt);
                    echo "*";
                }
                echo "\n";
            }
        
            $id = $model->getId();
        
            echo "Attribute [$labelText] has been saved as ID ($id). \n";
        
            // return $id;
        }
        
        public function addAttributeValue($arg_attribute, $arg_value)
        {
            $attribute_model        = Mage::getModel('eav/entity_attribute');
        
            $attribute_code         = $attribute_model->getIdByCode('catalog_product', $arg_attribute);
            $attribute              = $attribute_model->load($attribute_code);
            
            $optionFrontname = explode(':', $arg_value);
            $optionStoreFrontname = array();
            foreach ($optionFrontname as $_names) {
                list($name, $storeId) = explode('|', $_names);
                $optionStoreFrontname[$storeId] = $name;
            }
            $data = array();
            if (!$this->attributeValueExists($arg_attribute, $optionStoreFrontname[0])) {
                $data[0] = $optionStoreFrontname[0];
                foreach ($this->_storeIds as $_store) {
                    $data[$_store] = $optionStoreFrontname[$_store];
                }
                
                $value['option'] = $data;
                $result = array('value' => $value);
                $attribute->setData('option', $result);
                
                $attribute->save();
            }
        }

        public function attributeValueExists($arg_attribute, $arg_value)
        {
            $attribute_model        = Mage::getModel('eav/entity_attribute');
            $attribute_options_model= Mage::getModel('eav/entity_attribute_source_table') ;
        
            $attribute_code         = $attribute_model->getIdByCode('catalog_product', $arg_attribute);
            $attribute              = $attribute_model->load($attribute_code);
        
            $attribute_table        = $attribute_options_model->setAttribute($attribute);
            $options                = $attribute_options_model->getAllOptions(false);
        
            foreach ($options as $option) {
                if ($option['label'] == $arg_value) {
                    return $option['value'];
                }
            }
        
            return false;
        }
    }
