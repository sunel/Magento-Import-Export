## Magento Export & Import Script

Move the files to shell directory of your magento installation.

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

It will create three csv files "importAttriSet.csv" "importAttrib.csv" "product.csv" 


To Import 


```shell
	
	php import.php

```
