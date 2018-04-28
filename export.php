<?php

require_once 'bootstrap.php';
require_once 'export_product.php';
require_once 'export_attribute.php';
require_once 'export_attribute_set.php';

$attributeSetNames = array(
    'Default'
);


if (!Arguments::hasArgs()) {
    echo "No arugmets given !!!! [--set  --attr  --products ] \n";
    exit(0);
}


/*
|--------------------------------------------------------------------------
| Attribute Set Export
|--------------------------------------------------------------------------
*/
if (Arguments::getArg('set')) {
    echo "Collecting all attribute set..... \n";

    $fileName = MAGENTO . '/var/importAttributeSet.csv';

    $attrSetExporter = new ArrtibuteSetExporter();

    $result = $attrSetExporter->export($attributeSetNames, $fileName, Arguments::getArg('products'));

    unset($attrSetExporter);

    echo "\nMEMORY USED : ".convert(memory_get_usage(true)) . "\n\n";
}


/*
|--------------------------------------------------------------------------
| Attribute Export
|--------------------------------------------------------------------------
*/

if (Arguments::getArg('attr')) {
    echo "Collecting all attribute..... \n";

    $fileName = MAGENTO . '/var/importAttribute.csv';

    $attrExporter = new ArrtibuteExporter();

    $attrExporter->export(array_unique($result[0]), $fileName);

    unset($attrExporter);

    echo "\nMEMORY USED : ".convert(memory_get_usage(true)) . "\n\n";
}

/*
|--------------------------------------------------------------------------
| Product Export
|--------------------------------------------------------------------------
*/


if (Arguments::getArg('products')) {
    echo "Collecting all product..... \n";

    $fileName = MAGENTO . '/var/product.csv';

    // Execute the product export
    $exporter = new ProductExporter();

    $exporter->export(array_unique($result[1]), $fileName);

    echo "\n";
    echo "$fileName \n\n";

    unset($exporter);

    echo "MEMORY USED : ".convert(memory_get_usage(true)) . "\n";
}
