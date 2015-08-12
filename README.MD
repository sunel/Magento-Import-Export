## Magento Export & Import Script

Move the files to shell directory of your magento installation.

In the export_attribute_set.php file

```php
	$attributeSetNames = array(
		'Default'
	);
```

Add the attribute set that u need to export

To Export run the script 

```shell
	
	php export_attribute_set.php

```

It will create three csv files "importAttributeSet.csv" "importAttribute.csv" "product.csv" 


To Import Arrtibute and Attribute set 


```shell
	
	php import.php

```


To Import Product use [magmi](http://wiki.magmi.org/)


To increase the import performace and also to fully support magmi run


```shell
	
	php split_products.php

```


This will split the product csv into smaller chunks of csv (count of 500 per file) and then modifies the files to support magmi

