<?php

/**
 * Class Shaun_Csv_Model_Descriptions
 */
class Shaun_Csv_Model_Descriptions extends Shaun_Csv_Model_Abstract
{
    const CSV_FILE = 'description.csv';

    protected function getSource()
    {
        $default = 'http://www.agrumi.co.uk/inventory/descriptions.csv';
        $configValue = Mage::getStoreConfig('csv_settings/external_files/description_file');

        return ($configValue) ? $configValue : $default;
    }
}
