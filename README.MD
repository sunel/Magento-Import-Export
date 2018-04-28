## Magento Export & Import Script

### Note

!!! DONT NOT USE IN PRODUCTION DIRECTLY !!!

Please go through each script files and modify the code according to you needs. The code is written based on assumptions.

Read through the file, you will find comments that will help you understand the code.

Improvements are welcomed.

### Setup

Move the files to shell directory of your magento installation.

In the export.php file

```php
$attributeSetNames = array(
	'Default'
);
```

Add the attribute set that you need to export


### Export

Run the script 

```bash	
$ php export.php --set --attr --products
```

The above will export Attribute Set, Attribute, Product.

The options will let the script what to export

`--set` will export Attribute Set

`--attr` will export Attribute

`--products` will export Product


> It will create three csv files "importAttributeSet.csv" "importAttribute.csv" "product.csv" by default. You can change the file name if you need.


### Import

Run the script

```bash	
$ php import.php --set --attr
```

The above will import Attribute Set, Attribute


To Import Product use [magmi](http://wiki.magmi.org/) `RECOMMENDED`


To increase the import performace and also to fully support magmi run


```bash	
$ php prepare_products.php
```


This will split the product csv into smaller chunks of csv (count of 500 per file) and then modifies the files to support magmi

