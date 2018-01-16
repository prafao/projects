<?php

/**
 * Class Shaun_Csv_Model_Images
 */
class Shaun_Csv_Model_Images
{
    /**
     * Process cron
     */
    public function processImages()
    {
        $imageArray = $this->getData();

        $outputArray = $this->formatData($imageArray);

        $this->writeCsv($outputArray);
    }

    /**
     * Get list of images from media/import directory
     *
     * @return array
     */
    private function getData()
    {
        $imageDirectory = Mage::getBaseDir('media') . DS . 'import';
        $handle = opendir($imageDirectory);
        $imageArray = array();

        while($file = readdir($handle)) {
            if($file !== '.' && $file !== '..'){
                $imageArray[] = $file;
            }
        }

        return $imageArray;
    }

    /**
     * Format image data array
     *
     * @param $imageArray
     * @return array
     */
    private function formatData($imageArray)
    {
        $outputArray = array();

        foreach ($imageArray as $image) {
            $pos = strpos($image, '-');
            if (!$pos) {
                $pos = strpos($image, '.');
                $imageNumber = false;
            } else {
                $rest = substr($image, $pos + 1, strlen($image));
                $imageNumber = substr($rest, 0, strpos($rest, '-'));
                if (!$imageNumber) {
                    $imageNumber = substr($rest, 0, strpos($rest, '.'));
                }
            }

            $sku = substr($image, 0, $pos);
            if (!array_key_exists($sku, $outputArray)) {
                $outputArray[$sku] = array();
            }

            if (is_numeric($imageNumber)) {
                if (!array_key_exists('other', $outputArray[$sku])) {
                    $outputArray[$sku]['other'] = array();
                }
                $outputArray[$sku]['other'][] = ['sort' => $imageNumber, 'image' => $image];
            } else {
                $outputArray[$sku]['main'] = $image;
            }
        }

        // sort 'other' images subarray by 'sort' value
        foreach ($outputArray as $sku => $product) {
            if (!array_key_exists('other', $outputArray[$sku])) {
                continue;
            }
            usort($outputArray[$sku]['other'], function ($a, $b) { return strnatcmp($a['sort'], $b['sort']); });
        }

        return $outputArray;
    }

    /**
     * Write images data to csv
     *
     * @param $data
     */
    private function writeCsv($data)
    {
        $csvDirectory = Mage::getBaseDir('var') . DS . 'import';
        if(is_dir($csvDirectory) == false) {
            $file = new Varien_Io_File();
            if ($file->mkdir($csvDirectory, 0777, true) == false) {
                Mage::log('Directory var/import doesn\'t exist and automatic creation failed.', null, 'process_images_cron.log');
            }
        }
        $filePath = $csvDirectory . DS . 'images.csv';
        $csvHeaders = array('sku', 'thumbnail', 'small_image', 'image', 'media_gallery');

        $csvFile = fopen($filePath, 'w');
        fputcsv($csvFile, $csvHeaders);

        foreach ($data as $sku => $product) {
            $mediaGallery = '';
            if (array_key_exists('other', $product) && !empty($product['other'])) {
                foreach ($product['other'] as $otherImage) {
                    $mediaGallery .= $otherImage['image'] . ';';
                }
            }
            $mediaGallery = trim($mediaGallery, ';');

            $csvLine = array(
                $sku,
                $product['main'],
                $product['main'],
                $product['main'],
                $mediaGallery
            );
            fputcsv($csvFile, $csvLine);
        }

        fclose($csvFile);
    }
}
