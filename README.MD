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

It will create three csv files "importAttriSet.csv" "importAttrib.csv" "product.csv" 


To Import Arrtibute and Attribute set 


```shell
	
	php import.php

```

To Import Product user [magmi](http://wiki.magmi.org/)


