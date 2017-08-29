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

    protected $headerCsv = [
        'sku',
        'common_name',
        'latin_name',
        'pot_size',
        'plant_size',
        'plant_min_height',
        'plant_max_height',
        'plant_form',
        'plant_secondary_size',
        'plant_trunk_size',
        'plant_trunk_width',
        'pot_information',
        'name',
        'cost',
        'msrp',
        'price',
        'tax_class_id',
        'weight',
        'description',
        'plant_position',
        'plant_soil',
        'plant_rate_of_growth',
        'plant_hardiness',
        'Plant_garden_care',
        'plant_pruning',
        'plant_other',
        'plant_season',
        'plant_colour',
        'plant_style',
        'plant_aspect',
        'image_label',
        'small_image_label',
        'thumbnail_label',
        '_media_label',
        'ebay_title',
        'ebay_spec_table',
        'ebay_care_table'
    ];

    protected $msrpMultiplier;

    protected $priceMultiplier;

    public function __construct()
    {
        $this->msrpMultiplier = Mage::getStoreConfig('csv_settings/attributes/msrp_multiplier');
        if (empty($this->msrpMultiplier)) {
            $this->msrpMultiplier = 2.4;
        }
        $this->priceMultiplier = Mage::getStoreConfig('csv_settings/attributes/price_multiplier');
        if (empty($this->priceMultiplier)) {
            $this->priceMultiplier = 2;
        }
    }

    protected function getSource()
    {
        $default = 'http://agrumi.co.uk/inventory/WWW_INV.ASC';
        $configValue = Mage::getStoreConfig('csv_settings/external_files/attributes_file');

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
            $outputItem['plant_min_height'] = is_numeric($this->formatMinHeight($data['E'])) ?
                $this->formatMinHeight($data['E']) : '';
            $outputItem['plant_max_height'] = is_numeric($this->formatMaxHeight($data['E'])) ?
                $this->formatMaxHeight($data['E']) : '';

            $outputItem['plant_form'] = $this->formatPlantForm($data, $plantFormData);

            $outputItem['plant_secondary_size'] = $data['G'];
            $outputItem['plant_trunk_size'] = $data['H'];
            $outputItem['plant_trunk_width'] = $data['I'];
            $outputItem['pot_information'] = $data['J'];

            $outputItem['name'] = $this->formatName($outputItem);

            $outputItem['cost'] = $data['L'];

            $outputItem['msrp'] = $this->formatMsrp($data, $pricePointsData);

            $outputItem['price'] = $this->formatPrice($data, $pricePointsData);

            $outputItem['tax_class_id'] = ($data['O'] == 'T1') ? 2 : 6;
            $outputItem['weight'] = $this->formatWeight($data['D']);

            $this->fillDescription($outputItem, $data, $descriptionsData);

            $outputItem['image_label'] = $outputItem['name'];
            $outputItem['small_image_label'] = $outputItem['name'];
            $outputItem['thumbnail_label'] = $outputItem['name'];
            $outputItem['_media_label'] = $outputItem['name'];

            $outputItem['ebay_title'] = $this->formatEbayTitle($outputItem);
            $outputItem['ebay_spec_table'] = $this->buildEbaySpecTable($outputItem);
            $outputItem['ebay_care_table'] = $this->buildEbayCareTable($outputItem);

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

        $csvFile = fopen($filePath, 'w');
        fputcsv($csvFile, $this->headerCsv);

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

    private function formatName($outputItem)
    {
        $string = '';
        if (!empty($outputItem['latin_name'])) {
            $string .= $outputItem['latin_name'] . ' / ';
        }
        if (!empty($outputItem['common_name'])) {
            $string .= $outputItem['common_name'];
        }

        if (!empty($outputItem['plant_form'])) {
            $string .= ' ' . $outputItem['plant_form'];
        }

        if (!empty($outputItem['pot_size'])) {
            $string .= ' : ' . $outputItem['pot_size'] . ' : ';
        }
        if (!empty($outputItem['plant_size'])) {
            $string .= $outputItem['plant_size'] . ' High (exc pot)';
        }

        return $string;
    }

    private function formatMsrp($data, $pricePointData)
    {
        $value = $data['L'] * $this->msrpMultiplier;
        $priceArray = [];
        foreach ($pricePointData as $priceData) {
            $priceArray[] = $priceData[2];
        }

        return $this->getClosest($value, $priceArray);
    }

    private function formatPrice($data, $pricePointData)
    {
        $value = $data['L'] * $this->priceMultiplier;
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

    private function formatEbayTitle($outputItem)
    {
        if (strlen($outputItem['name']) <= 80) {
            return $outputItem['name'];
        }

        $string = $outputItem['latin_name'] . ' / ' . $outputItem['common_name'] . ' ' . $outputItem['plant_form'] . ' ' .
            $outputItem['pot_size'] . ' ' . $outputItem['plant_size'];
        if (strlen($string) <= 80) {
            return $string;
        }

        $string = $outputItem['latin_name'] . ' ' . $outputItem['plant_form'] . ' ' . $outputItem['pot_size'] . ' ' . $outputItem['plant_size'];
        return $string;
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
