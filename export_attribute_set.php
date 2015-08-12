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
    require_once MAGENTO . '/shell/export_product.php';
    
    // Set working directory to magento root folder
    chdir(MAGENTO);

    // Make files written by the profile world-writable/readable
    umask(0);
    echo "MEMORY USED : ".convert(memory_get_usage(true)) . "\n\n";
    // Initialize the admin application
    Mage::app('admin');
    
    $attributeSetNames = array(
        'Default'
    );
    
    $exportProducts = array();
    $exportAttributes = array();
    
    $entityType = Mage::getModel('catalog/product')->getResource()->getEntityType();
    $collection = Mage::getResourceModel('eav/entity_attribute_set_collection')
        ->setEntityTypeFilter($entityType->getId())
        ->addFieldToFilter('attribute_set_name', array('in', $attributeSetNames));
        
        
    $attibute_set = array();
    $i = 0;
    
    echo "Collecting all attribute set..... \n";
    foreach ($collection as $attributeSet) {
        $defaultGroupId = $attributeSet->getDefaultGroupId();
        $defaultGroup = Mage::getModel('eav/entity_attribute_group')->load($attributeSet->getDefaultGroupId());
        
        $attibute_set[$i]['ID'] = $attributeSet->getId();
        $attibute_set[$i]['NAME'] = $attributeSet->getAttributeSetName();
        $attibute_set[$i]['Group ID'] = $defaultGroupId;
        $attibute_set[$i]['DEFAULT GROUP'] = $defaultGroup->getAttributeGroupName();
        
        $products = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->addAttributeToSelect('sku')
                    ->addFieldToFilter('attribute_set_id', $attributeSet->getId());
        
        $productsSku = array();
        foreach ($products as $p) {
            $productsSku[] = $p->getSku();
            $exportProducts[] = $p->getSku();
        }
        unset($products);
        
        $attibute_set[$i]['PRODUCT SKU'] = join(';', $productsSku);
        
        /** @var $groupCollection Mage_Eav_Model_Resource_Entity_Attribute_Group_Collection */
        $groupCollection    = Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter($attributeSet->getId())
            ->addOrder('sort_order', 'ASC')
            ->load();
        foreach ($groupCollection as $group) {
            $i++;
            /* @var $group Mage_Eav_Model_Entity_Attribute_Group */
            $attrs = Mage::getResourceModel('catalog/product_attribute_collection')
                ->setAttributeGroupFilter($group->getId())
                ->addVisibleFilter()
                ->checkConfigurableProducts();
            $attrCodes = array();
            foreach ($attrs as $attr) {
                $attrCodes[] = $attr->getAttributeCode() .'/'. $attr->getSortOrder();
                $exportAttributes[] = '"'.$attr->getAttributeCode().'"';
            }

            $attibute_set[$i]['ID'] = ' ';
            $attibute_set[$i]['NAME'] = $group->getAttributeGroupName();
            $attibute_set[$i]['Group ID'] = ' ';
            $attibute_set[$i]['DEFAULT GROUP'] = join(';', $attrCodes);
            $attibute_set[$i]['PRODUCT SKU'] = ' ';
        }
        unset($productsSku, $attrCodes, $attrs, $groupCollection);
        $i++;
    }
    unset($collection);

    echo "preparing csv.... \n";
    prepareCsv($attibute_set, "importAttributeSet.csv", ',');
    unset($attibute_set);
    
    
    $entity_type_id = Mage::getModel('catalog/product')->getResource()->getTypeId();
    
    echo "Collecting all attribute..... \n";
    prepareCollection($entity_type_id, array_unique($exportAttributes));
    
    function prepareCollection($ent_type_id, $exportAttributes)
    {
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        
        // Increase maximium length for group_concat
        $connection->query("SET SESSION group_concat_max_len = 1000000;");
        
        $select_attribs = $connection
                            ->select()
                            ->from(array('ea' => $resource->getTableName('eav/attribute')),
                                   array('ea.*', 'c_ea.*',
                                   "(SELECT GROUP_CONCAT(CONCAT_WS('|',l_ea.value,l_ea.store_id) SEPARATOR ':') FROM ".$resource->getTableName('eav/attribute_label')." as l_ea WHERE ea.attribute_id = l_ea.attribute_id GROUP BY l_ea.attribute_id) as arrtibute_frontname"));
                            
        $select_attribs->join(array('c_ea' => $resource->getTableName('catalog/eav_attribute')),
                                    'ea.attribute_id = c_ea.attribute_id');
                                    
        if (!empty($exportAttributes)) {
            $select_attribs = $select_attribs->where('ea.attribute_code in ( '.implode(',', $exportAttributes) .' )');
        }
        $select_prod_attribs = $select_attribs->where('ea.entity_type_id = ' . $ent_type_id)->order('ea.attribute_id ASC');
        
        $product_attributes = $connection->fetchAll($select_prod_attribs);
        
            
        $select_attrib_option = $select_attribs
                                    ->join(
                                        array('e_ao' => $resource->getTableName('eav/attribute_option'), array('option_id')),
                                        'c_ea.attribute_id = e_ao.attribute_id'
                                     )
                                    ->columns("(SELECT GROUP_CONCAT(CONCAT_WS('|',e_aov.value,e_aov.store_id) SEPARATOR ':') FROM ".$resource->getTableName('eav/attribute_option_value')." as e_aov WHERE e_ao.option_id = e_aov.option_id GROUP BY e_aov.option_id) as value")
                                    ->join(
                                        array('e_aov' => $resource->getTableName('eav/attribute_option_value'), array('value')),
                                        'e_ao.option_id = e_aov.option_id ',
                                        array()
                                     )
                                    ->order('e_ao.attribute_id ASC');
                                    
        
        $product_attribute_options = $connection->fetchAll($select_attrib_option);
    
        $attributesCollection = mergeCollections($product_attributes, $product_attribute_options);
        
        unset($product_attributes, $product_attribute_options);
        
        echo "preparing csv.... \n";
        prepareCsv($attributesCollection, 'importAttribute.csv', ',');
        unset($attributesCollection);
    }

    
    echo "Collecting all product..... \n";
    // Execute the product export
    $exporter = new ProductExporter();
    
    $allStores = Mage::app()->getStores();
    
    $prouctCollection = $exporter->runMain(array_unique($exportProducts));
    
    echo "MEMORY USED : ".convert(memory_get_usage(true)) . "\n";
    
    function mergeCollections($product_attributes, $product_attribute_options)
    {
        foreach ($product_attributes as $key => $_prodAttrib) {
            $values = array();
            $attribId = $_prodAttrib['attribute_id'];
            foreach ($product_attribute_options as $pao) {
                if ($pao['attribute_id'] == $attribId) {
                    $values[] = $pao['value'];
                }
            }
            $values = array_unique($values);
            if (count($values) > 0) {
                $values = implode(";", $values);
                $product_attributes[$key]['_options'] = $values;
            } else {
                $product_attributes[$key]['_options'] = "";
            }
            /*
             temp
             */
            $product_attributes[$key]['attribute_code'] = $product_attributes[$key]['attribute_code'];
        }
    
        return $product_attributes;
    }
    
    function prepareCsv($attributesCollection, $filename = "import.csv", $delimiter = '|', $enclosure = '"')
    {
        $f = fopen(MAGENTO.'/var/'.$filename, "w") or die("Unable to open file!");
        $first = true;
        foreach ($attributesCollection as $line) {
            if ($first) {
                $titles = array();
                foreach ($line as $field => $val) {
                    $titles[] = $field;
                }
                fputcsv($f, $titles, $delimiter, $enclosure);
                $first = false;
            }
            fputcsv($f, $line, $delimiter, $enclosure);
        }
        fclose($f);
        echo "MEMORY USED : ".convert(memory_get_usage(true)) . "\n";
        echo "csv dumped to ".MAGENTO."/var/$filename \n\n";
    }
    
    function convert($size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024, ($i=floor(log($size, 1024)))), 2).' '.$unit[$i];
    }
