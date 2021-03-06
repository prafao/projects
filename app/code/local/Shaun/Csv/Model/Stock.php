<?php

/**
 * Class Shaun_Csv_Model_Stock
 */
class Shaun_Csv_Model_Stock extends Shaun_Csv_Model_Abstract
{
    const CSV_FILE = 'stock.asc';

    protected function getSource()
    {
        $default = 'http://www.agrumi.co.uk/AL/WWW_STOC.ASC';
        $configValue = $configValue = Mage::getStoreConfig('csv_settings/external_files/stock_file');

        return ($configValue) ? $configValue : $default;
    }

    protected function getCsvData()
    {
        $file = Mage::getBaseDir('var') . DS . 'import' . DS . 'external-csv-files' . DS . static::CSV_FILE;
        $csvData = [];

        if (($handle = fopen($file, "r")) !== FALSE) {
            while (($line = fgets($handle)) !== false) {
                $line = str_replace("\r\n", '', $line);
                $lineData = explode(',', $line);
                foreach ($lineData as &$item) {
                    $item = str_replace('"', '', $item);
                }
                $csvData[] = $lineData;
            }
            fclose($handle);
        } else {
            Mage::log('Error reading the .asc file.', null, 'process_attribute_cron.log');
        }

        return $csvData;
    }

    /**
     * Process cron
     */
    public function processStock()
    {
        $stockArray = $this->getData();

        $outputArray = $this->formatData($stockArray);

        $this->writeCsv($outputArray);
    }

    /**
     * Format stock data array
     *
     * @param $stockArray
     * @return array
     */
    private function formatData($stockArray)
    {
        $gradeA = array("Grade AA" => 10, "Grade A+" => 9, "Grade A" => 8, "Grade A-" => 7);
        $gradeB = array("Grade B" => 3, "Cultured" => 2, "" => 1);
        $outputArray = array();
        $stockRanks = array();

        foreach ($stockArray as $stockItem) {
            // don't take into account the items with stock 0
            if ($stockItem[1] == 0) {
                continue;
            }
            if (array_key_exists($stockItem[2], $gradeA)) {
                if (!isset($outputArray[$stockItem[0]])) {
                    $outputArray[$stockItem[0]] = array($stockItem[0], (int)$stockItem[1], $stockItem[2], 1);
                    $stockRanks[$stockItem[0]] = $gradeA[$stockItem[2]];
                } else {
                    $outputArray[$stockItem[0]][1] += $stockItem[1];
                    if ($gradeA[$stockItem[2]] > $stockRanks[$stockItem[0]]) {
                        $stockRanks[$stockItem[0]] = $gradeA[$stockItem[2]];
                        $outputArray[$stockItem[0]][2] = $stockItem[2];
                    }
                }
            } elseif (array_key_exists($stockItem[2], $gradeB)) {
                if (!isset($outputArray[$stockItem[0]])) {
                    $outputArray[$stockItem[0]] = array($stockItem[0], 0, $stockItem[2], 1);
                    $stockRanks[$stockItem[0]] = $gradeB[$stockItem[2]];
                } else {
                    if ($gradeB[$stockItem[2]] > $stockRanks[$stockItem[0]]) {
                        $stockRanks[$stockItem[0]] = $gradeB[$stockItem[2]];
                        $outputArray[$stockItem[0]][2] = $stockItem[2];
                    }
                }
            }
        }

        return $outputArray;
    }

    /**
     * Write stock data to csv
     *
     * @param $data
     */
    private function writeCsv($data)
    {
        $csvDirectory = Mage::getBaseDir('var') . DS . 'import';
        if(is_dir($csvDirectory) == false) {
            $file = new Varien_Io_File();
            if ($file->mkdir($csvDirectory, 0777, true) == false) {
                Mage::log('Directory var/import doesn\'t exist and automatic creation failed.', null, 'process_stock_cron.log');
            }
        }
        $filePath = $csvDirectory . DS . 'stock.csv';
        $csvHeaders = array('sku', 'qty', 'plant_grade', 'manage_stock');

        $csvFile = fopen($filePath, 'w');
        fputcsv($csvFile, $csvHeaders);

        foreach ($data as $item) {
            fputcsv($csvFile, $item);
        }

        fclose($csvFile);
    }
}
