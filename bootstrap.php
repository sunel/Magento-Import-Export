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

// Set working directory to magento root folder
chdir(MAGENTO);

// Make files written by the profile world-writable/readable
umask(0);
echo "MEMORY USED : ".convert(memory_get_usage(true)) . "\n\n";
// Initialize the admin application
Mage::app('admin');



class Arguments
{
    public static $_args;

    public function __construct()
    {
        $this->_parseArgs();
    }

    /**
     * Parse input arguments
     *
     * @return Mage_Shell_Abstract
     */
    protected function _parseArgs()
    {
        $current = null;
        foreach ($_SERVER['argv'] as $arg) {
            $match = array();
            if (preg_match('#^--([\w\d_-]{1,})$#', $arg, $match) || preg_match('#^-([\w\d_]{1,})$#', $arg, $match)) {
                $current = $match[1];
                static::$_args[$current] = true;
            } else {
                if ($current) {
                    static::$_args[$current] = $arg;
                } elseif (preg_match('#^([\w\d_]{1,})$#', $arg, $match)) {
                    static::$_args[$match[1]] = true;
                }
            }
        }
        return $this;
    }

    /**
     * Retrieve argument value by name or false
     *
     * @param string $name the argument name
     * @return mixed
     */
    public static function getArg($name)
    {
        if (isset(static::$_args[$name])) {
            return static::$_args[$name];
        }
        return false;
    }


    /**
     * Check if argumnets are given
     *
     * @return bool
     */
    public static function hasArgs()
    {
        return count(static::$_args) !== 0;
    }
}


new Arguments();

function convert($size)
{
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024, ($i=floor(log($size, 1024)))), 2).' '.$unit[$i];
}


function writeCsv($attributesCollection, $filename = "import.csv", $delimiter = '|', $enclosure = '"')
{
    $f = fopen($filename, "w") or die("Unable to open file!");
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
    echo "csv dumped to $filename \n\n";
}
