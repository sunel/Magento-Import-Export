<?php

class ArrtibuteExporter
{
    private $_entityTypeId;
    
    public function __construct()
    {
        $this->_entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
    }

    public function export($exportAttributes, $filename)
    {
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        
        // Increase maximium length for group_concat
        $connection->query("SET SESSION group_concat_max_len = 1000000;");
        
        $select_attribs = $connection
                            ->select()
                            ->from(
                                array('ea' => $resource->getTableName('eav/attribute')),
                                   array('ea.*', 'c_ea.*',
                                   "(SELECT GROUP_CONCAT(CONCAT_WS('|',l_ea.value,l_ea.store_id) SEPARATOR ':') FROM ".$resource->getTableName('eav/attribute_label')." as l_ea WHERE ea.attribute_id = l_ea.attribute_id GROUP BY l_ea.attribute_id) as arrtibute_frontname")
                            );
                            
        $select_attribs->join(
                            
            array('c_ea' => $resource->getTableName('catalog/eav_attribute')),
                                    'ea.attribute_id = c_ea.attribute_id'
                            
        );
                                    
        if (!empty($exportAttributes)) {
            $select_attribs = $select_attribs->where('ea.attribute_code in ( '.implode(',', $exportAttributes) .' )');
        }
        $select_prod_attribs = $select_attribs->where('ea.entity_type_id = ' . $this->_entityTypeId)->order('ea.attribute_id ASC');
        
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
    
        $attributesCollection = $this->mergeCollections($product_attributes, $product_attribute_options);
        
        unset($product_attributes, $product_attribute_options);
        
        echo "preparing csv.... \n";
        writeCsv($attributesCollection, $filename, ',');
        unset($attributesCollection);
    }

    protected function mergeCollections($product_attributes, $product_attribute_options)
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
}
