<?php

class ArrtibuteSetExporter
{
    private $_storeIds = array();

    private $_entityTypeId;
    
    public function __construct()
    {
        $allStores = Mage::app()->getStores();
        foreach ($allStores as $_eachStoreId => $val) {
            $this->_storeIds[] = Mage::app()->getStore($_eachStoreId)->getId();
        }

        $this->_entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
    }

    public function export($attributeSetNames, $filename, $pullProducts = false)
    {
        $exportProducts = array();
        $exportAttributes = array();

        $collection = Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->setEntityTypeFilter($this->_entityTypeId)
            ->addFieldToFilter('attribute_set_name', array('in', $attributeSetNames));

        $attibute_set = array();
        $i = 0;
        
        foreach ($collection as $attributeSet) {
            $defaultGroupId = $attributeSet->getDefaultGroupId();
            $defaultGroup = Mage::getModel('eav/entity_attribute_group')->load($attributeSet->getDefaultGroupId());
            
            $attibute_set[$i]['ID'] = $attributeSet->getId();
            $attibute_set[$i]['NAME'] = $attributeSet->getAttributeSetName();
            $attibute_set[$i]['Group ID'] = $defaultGroupId;
            $attibute_set[$i]['DEFAULT GROUP'] = $defaultGroup->getAttributeGroupName();
            
            if ($pullProducts) {
                $products = Mage::getModel('catalog/product')
                        ->getCollection()
                        ->addAttributeToSelect('sku')
                        ->addFieldToFilter('attribute_set_id', $attributeSet->getId());
            
                $productsSku = array();
                foreach ($products as $p) {
                    $productsSku[] = $p->getSku();
                    $exportProducts[] = $p->getSku();
                }
                unset($productsSku, $products);
                
                $attibute_set[$i]['PRODUCT SKU'] = join(';', $productsSku);
            } else {
                $attibute_set[$i]['PRODUCT SKU'] = ' ';
            }

            
            
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
            unset($attrCodes, $attrs, $groupCollection);
            $i++;
        }
        unset($collection);

        echo "preparing csv.... \n";
        writeCsv($attibute_set, $filename, ',');
        unset($attibute_set);

        return [ $exportAttributes, $exportProducts ];
    }
}
