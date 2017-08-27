<?php

/**
 * Class Shaun_Csv_Model_Plantform
 */
class Shaun_Csv_Model_Plantform extends Shaun_Csv_Model_Abstract
{
    const CSV_FILE = 'plant-form.csv';

    protected function getSource()
    {
        $default = 'www.agrumi.co.uk/inventory/form-lookup.csv';
        $configValue = Mage::getStoreConfig('csv_settings/external_files/plant_form_file');

        return ($configValue) ? $configValue : $default;
    }
}
