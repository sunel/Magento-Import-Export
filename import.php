<?php

require_once 'bootstrap.php';
require_once 'import_arrtibute.php';
require_once 'import_arrtibute_set.php';


if (!Arguments::hasArgs()) {
    echo "No arugmets given !!!! [--set  --attr ] \n";
    exit(0);
}

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

if (Arguments::getArg('attr')) {
    echo "Creating Attribute. \n";
    
    $fileName = MAGENTO . '/var/importAttribute.csv';
    
    $importer = new ArrtibuteImporter();
    
    $importer->import($fileName);
    
    unset($importer);
    
    echo "\nMEMORY USED : ".convert(memory_get_usage(true)) . "\n\n";
}

/*
|--------------------------------------------------------------------------
| Attribute Set Import
|--------------------------------------------------------------------------
*/

if (Arguments::getArg('set')) {
    echo "Creating Attribute Sets. \n";
    
    
    $fileName = MAGENTO . '/var/importAttributeSet.csv';
    
    $importer = new ArrtibuteSetImporter();
    
    $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');
    
    $writeConnection->query("SET FOREIGN_KEY_CHECKS = 0");
    
    $importer->import($fileName);
    
    $writeConnection->query("SET FOREIGN_KEY_CHECKS = 1");
    
    unset($importer);
    
    echo "\nMEMORY USED : ".convert(memory_get_usage(true)) . "\n\n";
}
