<?php

/**
 * Class Shaun_Csv_Model_Abstract
 */
class Shaun_Csv_Model_Abstract
{
    const CSV_FILE = '';

    /**
     * Get data from external file
     *
     * @return array
     */
    public function getData()
    {
         $this->getFileFromSource();

        return $this->getCsvData();
    }

    protected function getFileFromSource()
    {
        $csvDirectory = Mage::getBaseDir('var') . DS . 'import' . DS . 'external-csv-files';
        if(is_dir($csvDirectory) == false) {
            $file = new Varien_Io_File();
            if ($file->mkdir($csvDirectory, 0777, true) == false) {
                Mage::log('Directory external-csv-files doesn\'t exist and automatic creation failed.', null, 'process_attributes_cron.log');
            }
        }

         $this->downloadCsv($csvDirectory . DS . static::CSV_FILE);
    }

    protected function downloadCsv($destination)
    {
        $source = $this->getSource();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $source);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSLVERSION,3);
        $data = curl_exec($ch);
        $data = rtrim($data);
        $error = curl_error($ch);
        curl_close ($ch);

        if ($error) {
            Mage::log($error, null, 'process_attributes_cron.log');
        }

        $file = fopen($destination, "w+");
        fputs($file, $data);
        fclose($file);
    }

    protected function getSource() {}

    protected function getCsvData()
    {
        $file = Mage::getBaseDir('var') . DS . 'import' . DS . 'external-csv-files' . DS . static::CSV_FILE;
        $csvData = [];

        if (($handle = fopen($file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
                $csvData[] = $data;
            }

            fclose($handle);
        } else {
            Mage::log('Error reading the .asc file.', null, 'process_attribute_cron.log');
        }

        return $csvData;
    }
}
