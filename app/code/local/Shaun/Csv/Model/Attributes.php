<?php

/**
 * Class Shaun_Csv_Model_Attributes
 */
class Shaun_Csv_Model_Attributes extends Shaun_Csv_Model_Abstract
{
    const CSV_FILE = 'attributes.ASC';

    protected $header = [
        'A',
        'B',
        'C',
        'D',
        'E',
        'F',
        'G',
        'H',
        'I',
        'J',
        'K',
        'L',
        'M',
        'N',
        'O',
        'P',
        'Q'
    ];

    protected function getSource()
    {
        $default = 'http://agrumi.co.uk/inventory/WWW_INV.ASC';
        $configValue = $configValue = Mage::getStoreConfig('csv_settings/external_files/attributes_file');

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
    public function processAttributes()
    {
        $attributeArray = $this->getData();

        $outputArray = $this->formatData($attributeArray);

        $this->writeCsv($outputArray);
    }

    /**
     * Format attribute data array
     *
     * @param $attributeArray
     * @return array
     */
    private function formatData($attributeArray)
    {
        $header = $this->header;
        $outputArray = array();
        $plantForm = Mage::getModel('csv/plantform');
        $plantFormData = $plantForm->getData();
        $pricePoints = Mage::getModel('csv/pricepoints');
        $pricePointsData = $pricePoints->getData();
        $descriptions = Mage::getModel('csv/descriptions');
        $descriptionsData = $descriptions->getData();

        foreach ($attributeArray as $line => $row) {
            $outputItem = array();
            $data = array();
            foreach ($row as $key => $value) {
                $data[$header[$key]] = $value;
            }

            $outputItem['sku'] = $data['A'];
            $outputItem['common_name'] = $data['P'];
            $outputItem['latin_name'] = $data['Q'];
            $outputItem['pot_size'] = $data['D'];
            $outputItem['plant_size'] = $data['E'];
            $outputItem['plant_min_height'] = $this->formatMinHeight($data['E']);
            $outputItem['plant_max_height'] = $this->formatMaxHeight($data['E']);

            $outputItem['plant_form'] = $this->formatPlantForm($data, $plantFormData);

            $outputItem['plant_secondary_size'] = $data['G'];
            $outputItem['plant_trunk_size'] = $data['H'];
            $outputItem['plant_trunk_width'] = $data['I'];
            $outputItem['pot_information'] = $data['J'];
            $outputItem['name'] = '';
            if (!empty($outputItem['latin_name'])) {
                $outputItem['name'] .= $outputItem['latin_name'] . '/';
            }
            if (!empty($outputItem['common_name'])) {
                $outputItem['name'] .= $outputItem['common_name'] . ':';
            }
            if (!empty($outputItem['pot_size'])) {
                $outputItem['name'] .= $outputItem['pot_size'] . ':';
            }
            if (!empty($outputItem['plant_size'])) {
                $outputItem['name'] .= $outputItem['plant_size'] . ' High (exc pot)';
            }
            $outputItem['cost'] = $data['L'];

            $outputItem['msrp'] = $this->formatMsrp($data, $pricePointsData);

            $outputItem['price'] = $this->formatPrice($data, $pricePointsData);

            $outputItem['tax_class_id'] = ($data['O'] == 'T1') ? 2 : 6;
            $outputItem['weight'] = $this->formatWeight($data['D']);

            $this->fillDescription($outputItem, $data, $descriptionsData);

            $outputItem['Image_label'] = $outputItem['name'];
            $outputItem['Small_image_label'] = $outputItem['name'];
            $outputItem['Thumbnail_label'] = $outputItem['name'];
            $outputItem['_media_label'] = $outputItem['name'];

            $outputItem['Ebay_spec_table'] = $this->buildEbaySpecTable($outputItem);
            $outputItem['Ebay_care_table'] = $this->buildEbayCareTable($outputItem);

            $outputArray[] = $outputItem;
        }

        return $outputArray;
    }

    /**
     * Write attribute data to csv
     *
     * @param $data
     */
    private function writeCsv($data)
    {
        $csvDirectory = Mage::getBaseDir('var') . DS . 'import';
        if(is_dir($csvDirectory) == false) {
            $file = new Varien_Io_File();
            if ($file->mkdir($csvDirectory, 0777, true) == false) {
                Mage::log('Directory var/import doesn\'t exist and automatic creation failed.', null, 'process_attribute_cron.log');
            }
        }
        $filePath = $csvDirectory . DS . 'attribute.csv';
        $csvHeaders = array('sku', 'qty', 'plant_grade', 'manage_attribute');

        $csvFile = fopen($filePath, 'w');
        fputcsv($csvFile, $csvHeaders);

        foreach ($data as $item) {
            fputcsv($csvFile, $item);
        }

        fclose($csvFile);
    }

    /**
     * @param $string
     * @return mixed|string
     */
    private function formatMinHeight($string)
    {
        $string = str_replace('cm', '', $string);

        $pos = strpos($string, '-');
        if ($pos) {
            $string = substr($string, 0, $pos);
        }

        return $string;
    }

    /**
     * @param $string
     * @return mixed|string
     */
    private function formatMaxHeight($string)
    {
        $string = str_replace('cm', '', $string);

        $pos = strpos($string, '-');
        if ($pos) {
            $string = substr($string, $pos + 1, strlen($string) - 1);
        }


        return $string;
    }

    private function formatWeight($string)
    {
        $string = substr($string, 0, -1);

        return (is_numeric($string)) ? $string : '';
    }

    private function formatPlantForm($data, $plantFormData)
    {
        foreach ($plantFormData as $plantData)
        {
            if ($plantData[0] == $data['F']) {
                return $plantData[1];
            }
        }

        return $data['F'];
    }

    private function formatMsrp($data, $pricePointData)
    {
        $value = $data['L'] * 2.4;
        $priceArray = [];
        foreach ($pricePointData as $priceData) {
            $priceArray[] = $priceData[1];
        }

        return $this->getClosest($value, $priceArray);
    }

    private function formatPrice($data, $pricePointData)
    {
        $value = $data['L'] * 2;
        $priceArray = [];
        foreach ($pricePointData as $priceData) {
            $priceArray[] = $priceData[2];
        }

        return $this->getClosest($value, $priceArray);
    }

    private function getClosest($search, $arr) {
        $closest = null;
        foreach ($arr as $item) {
            if ($closest === null || abs($search - $closest) > abs($item - $search)) {
                $closest = $item;
            }
        }
        return $closest;
    }

    private function fillDescription(&$outputItem, $data, $descriptionData)
    {
        $outputItem['description'] = '';
        $outputItem['plant_position'] = '';
        $outputItem['plant_soil'] = '';
        $outputItem['plant_rate_of_growth'] = '';
        $outputItem['plant_hardiness'] = '';
        $outputItem['plant_garden_care'] = '';
        $outputItem['plant_pruning'] = '';
        $outputItem['plant_other'] = '';
        $outputItem['plant_season'] = '';
        $outputItem['plant_colour'] = '';
        $outputItem['plant_style'] = '';
        $outputItem['plant_aspect'] = '';

        foreach ($descriptionData as $descData)
        {
            if ($descData[0] == $data['C']) {
                $outputItem['description'] = $descData[1];
                $outputItem['plant_position'] = $descData[2];
                $outputItem['plant_soil'] = $descData[3];
                $outputItem['plant_rate_of_growth'] = $descData[4];
                $outputItem['plant_hardiness'] = $descData[5];
                $outputItem['plant_garden_care'] = $descData[6];
                $outputItem['plant_pruning'] = $descData[7];
                $outputItem['plant_other'] = $descData[8];
                $outputItem['plant_season'] = $descData[9];
                $outputItem['plant_colour'] = $descData[10];
                $outputItem['plant_style'] = $descData[11];
                $outputItem['plant_aspect'] = $descData[12];

                break;
            }
        }
    }

    protected function buildEbaySpecTable($outputItem)
    {
        $string = "<h2 style='clear:both'>Plant Specification</h2>";
        $string .= "<table id='specs'>";
        if (strlen($outputItem['latin_name']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Botanical Name</td>";
            $string .= "<td class='data'>" . $outputItem['latin_name'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['common_name']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Common Name</td>";
            $string .= "<td class='data'>" . $outputItem['common_name'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['pot_size']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Pot Size</td>";
            $string .= "<td class='data'>" . $outputItem['pot_size'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_size']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Height</td>";
            $string .= "<td class='data'>" . $outputItem['plant_size'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_form']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Form</td>";
            $string .= "<td class='data'>" . $outputItem['plant_form'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_secondary_size']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Head Size</td>";
            $string .= "<td class='data'>" . $outputItem['plant_secondary_size'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_trunk_size']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Trunk Size</td>";
            $string .= "<td class='data'>" . $outputItem['plant_trunk_size'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_trunk_width']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Other</td>";
            $string .= "<td class='data'>" . $outputItem['plant_trunk_width'] . "</td>";
            $string .= "</tr>";
        }
        $string .= "</table>";

        return $string;
    }

    protected function buildEbayCareTable($outputItem)
    {
        $string = "<h2 style='clear:both'>Care Advice</h2>";
        $string .= "<table id='specs'>";
        if (strlen($outputItem['plant_position']) > 0) {
            $string .= "<tr>";
            $string .= "<td width='119'>Position</td>";
            $string .= "<td width='323' class='data'>" . $outputItem['plant_position'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_aspect']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Aspect</td>";
            $string .= "<td class='data'>" . $outputItem['plant_aspect'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_soil']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Soil</td>";
            $string .= "<td class='data'>" . $outputItem['plant_soil'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_rate_of_growth']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Rate of growth</td>";
            $string .= "<td class='data'>" . $outputItem['plant_rate_of_growth'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_hardiness']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Hardiness</td>";
            $string .= "<td class='data'>" . $outputItem['plant_hardiness'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_garden_care']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Garden care</td>";
            $string .= "<td class='data'>" . $outputItem['plant_garden_care'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_pruning']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Pruning</td>";
            $string .= "<td class='data'>" . $outputItem['plant_pruning'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_other']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Other</td>";
            $string .= "<td class='data'>" . $outputItem['plant_other'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_season']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Season</td>";
            $string .= "<td class='data'>" . $outputItem['plant_season'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_colour']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Colour</td>";
            $string .= "<td class='data'>" . $outputItem['plant_colour'] . "</td>";
            $string .= "</tr>";
        }
        if (strlen($outputItem['plant_style']) > 0) {
            $string .= "<tr>";
            $string .= "<td>Style</td>";
            $string .= "<td class='data'>" . $outputItem['plant_style'] . "</td>";
            $string .= "</tr>";
        }
        $string .= "</table>";

        return $string;
    }
}
