<?php
    // Uncomment to enable debugging
    // ini_set('display_errors', '1');
    // ini_set('error_reporting', E_ALL);

    // Increase memory limit
    ini_set('memory_limit', '-1');
    // Increase maximum execution time to 4 hours
    //ini_set('max_execution_time', 14400);

    define('MAGENTO', realpath(dirname(__FILE__)).'/..');
    require_once MAGENTO . '/app/Mage.php';
    

    // Set working directory to magento root folder
    chdir(MAGENTO);

    // Make files written by the profile world-writable/readable
    umask(0);
    if (!function_exists('prepareCsv')) {
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
    }
    if (!function_exists('convert')) {
        function convert($size)
        {
            $unit=array('b','kb','mb','gb','tb','pb');
            return @round($size/pow(1024, ($i=floor(log($size, 1024)))), 2).' '.$unit[$i];
        }
    }
    
    echo "MEMORY USED : ".convert(memory_get_usage(true)) . "\n\n";
    // Initialize the admin application
    Mage::app('admin');
    
    $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');
    
    
    /*
    |--------------------------------------------------------------------------
    | Product Delete script
    |--------------------------------------------------------------------------
    */
    
    //$writeConnection->query("DELETE FROM `catalog_product_entity`");

    //$writeConnection->query("TRUNCATE TABLE `catalog_product_entity`");

    
    /*
    |--------------------------------------------------------------------------
    | Prepare for Product Import ( works with magmi)
    |--------------------------------------------------------------------------
    */
    
    echo "Creating Products files. \n";
    
    if (!is_dir(MAGENTO.'/var/split/old')) {
        mkdir(MAGENTO.'/var/split/old', 0777, true);
    }
    
    if (!is_dir(MAGENTO.'/var/split/new')) {
        mkdir(MAGENTO.'/var/split/new', 0777, true);
    }
        
    /*
    |--------------------------------------------------------------------------
    | Slpitting the Product
    |--------------------------------------------------------------------------
    */
    
    $handle = fopen(MAGENTO . "/var/product.csv", 'r');
    $maxRange = 500;
    $count = 0;
    $prefix = 1;
    $line = 1;
    while (($data = fgetcsv($handle)) !== false) {
        if ($line == 1) {
            $headers = $data;
        }
    
        if ($data[0] != null) {
            $count++;
            if ($count > $maxRange) {
                $prefix++;
                $count = 0;
                if ($prefix != 1) {
                    $filename = MAGENTO.'/var/split/old/product-' . $prefix . '.csv';
                    $write = fopen($filename, 'a');
                    fputcsv($write, $headers);
                    fclose($write);
                }
                echo "Count prefix [" . $prefix . "]\n";
            }
    
            $filename = MAGENTO.'/var/split/old/product-'. $prefix . '.csv';
            $write = fopen($filename, 'a');
            fputcsv($write, $data);
            fclose($write);
        } else {
            $filename = MAGENTO.'/var/split/old/product-' . $prefix . '.csv';
            $write = fopen($filename, 'a');
            fputcsv($write, $data);
            fclose($write);
        }
        $line++;
    }
    
    echo "\nMEMORY USED : ".convert(memory_get_usage(true)) . "\n\n";
    unset($handle, $write, $data);
    
    //$writeConnection->query('TRUNCATE log_url; TRUNCATE log_url_info; TRUNCATE log_visitor; TRUNCATE log_visitor_info;  TRUNCATE dataflow_batch_import; TRUNCATE dataflow_batch_export; TRUNCATE index_event;   TRUNCATE report_event;');

    function array_equal($a, $b)
    {
        return (is_array($a) && is_array($b) && array_diff($a, $b) === array_diff($b, $a));
    }
    
    for ($i=1; $i <=$prefix ; $i++) {
        $fileName = 'product-'.$i.'.csv';
        $importer = new PrepareProductImporter();
    
        $importer->run($fileName);
        
        unset($importer);
    }
    
    
    echo "\nMEMORY USED : ".convert(memory_get_usage(true)) . "\n\n";
    
    class PrepareProductImporter
    {
        private $_storeIds = array();
        
        private $_groups = array();
        
        private $_entityTypeId;
        
        private $_missedProduct = array();
        
        public function __construct()
        {
            $allStores = Mage::app()->getStores();
            foreach ($allStores as $_eachStoreId => $val) {
                $this->_storeIds[] = Mage::app()->getStore($_eachStoreId)->getId();
            }
            $this->_entityTypeId = Mage::getModel('catalog/product')->getResource()->getTypeId();
        }
        
        public static function checkEmpty($value)
        {
            if ($value === '') {
                return false;
            }
            return true;
        }
        
        public function run($name)
        {
            $fileName = MAGENTO . '/var/split/old/'.$name;
            
            echo "Reading $fileName.\n";
            $file = fopen($fileName, "r");
            while (!feof($file)) {
                $csv[] = fgetcsv($file, 0, ',');
            }
            $keys = array_shift($csv);
            
            foreach ($csv as $i=>$row) {
                $csv[$i] = array_combine($keys, $row);
                $csv[$i] = $csv[$i];//array_filter($csv[$i],'self::checkEmpty');
            }
            $i = 0;
            $currentSet = null;
            foreach ($csv as $row) {
                $row['sku_type'] = 1;
                $row['price_type'] = 1;
                $row['weight_type'] = 1;
                
                $row = $this->removeInvalidData($row);
                $row = $this->convertBool($row);
                
                if (trim($row['sku']) != '') {
                    $currentSet = $row['sku'];
                    $this->_groups[$currentSet][] = $row;
                    $i++;
                } else {
                    $processedChild = array_filter($row, 'self::checkEmpty');
                    if (!empty($processedChild)) {
                        $newExtRow = array_merge($this->_groups[$currentSet][0], $processedChild);
                        if (array_equal($this->_groups[$currentSet][0], $newExtRow)) {
                            continue;
                        }
                        $newExtRow['bundle_skus'] = '';
                        $newExtRow['bundle_options'] = '';
                        $this->_groups[$currentSet][] = $newExtRow;
                    }
                    unset($processedChild, $newExtRow);
                }
            }
            echo "MEMORY USED : ".convert(memory_get_usage(true)) . "\n";
            
            fclose($file);
            unset($csv, $keys, $file, $row);
            $finalArray = array();
            foreach ($this->_groups as $sku => $_product) {
                foreach ($_product as $__product) {
                    $finalArray[] = $__product;
                }
            }
            unset($this->_groups);
            
            prepareCsv($finalArray, 'split/new/'.$name, ',');
            
            echo "MEMORY USED : ".convert(memory_get_usage(true)) . "\n";
        }
        
        /**
        * To convert string into boll value (1 or 0)
        * 
        * @param Array $row 
        * @return  Array
        */
        protected function convertBool($row)
        {
            /*
                if($row['cpsp_enable'] == 'Yes') {
                    $row['cpsp_enable'] = 1;
                } else {
                    $row['cpsp_enable'] = 0;
                }
            
            */
            return $row;
        }
        
        /**
        * To remove invalid data form the row
        * 
        * @param Array $row 
        * @return  Array
        */
        protected function removeInvalidData($row)
        {
             
             // Add Values
             $invalid = array(
             
             );
             
            foreach ($invalid as $_invlaid) {
                unset($row[$_invlaid]);
            }
            
            return $row;
        }
    }
