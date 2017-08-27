<?php

/**
 * Class Shaun_Csv_Model_Pricepoints
 */
class Shaun_Csv_Model_Pricepoints extends Shaun_Csv_Model_Abstract
{
    const CSV_FILE = 'price-point.csv';

    protected function getSource()
    {
        $default = 'http://www.agrumi.co.uk/inventory/price-points.csv';
        $configValue = Mage::getStoreConfig('csv_settings/external_files/price_points_file');

        return ($configValue) ? $configValue : $default;
    }
}
