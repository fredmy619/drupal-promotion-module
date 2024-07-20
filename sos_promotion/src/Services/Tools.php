<?php

namespace Drupal\sos_promotion\Services;
use Drupal\file\Entity\File;

class Tools {
    /**
     * File upload data convert to JSON data
     */
    public static function fileDataEncode($file_id, $type){
        // Initialize
        $csv_items = [];
        $data = [];

        if(!empty($file_id)){
            $file = \Drupal::entityTypeManager()->getStorage('file')->load($file_id[0]);

            if ($file) {
                // Perform operations on the uploaded file.
                $file_uri = $file->getFileUri();

                if (($handle = fopen($file_uri, "rb")) !== FALSE) {
                    while (!feof($handle)) {
                        $csv_items[] = fgetcsv($handle);
                    }
                    fclose($handle);
                }

                if($type === 'product_list') {
                    $data['data'] = [];
                    $column_headers = [
                        'product_id',
                        'product_name'
                    ];

                    foreach ($csv_items as $item) {
                        $temp_data = [];

                        // Skip the header from the CSV file
                        if (!is_numeric($item[0])) {
                            continue;
                        }

                        foreach($column_headers as $index => $value) {
                            $temp_data[$value] = htmlspecialchars($item[$index] ?: ''); // add sanitize
                        }

                        // Add to final data array
                        array_push($data['data'],$temp_data);
                    }

                }else{
                    foreach ($csv_items as $item) {
                        // Skip the header from the CSV file
                        if (!is_numeric($item[0])) {
                            continue;
                        }

                        $data['shipToId'][] = htmlspecialchars($item[0]); // add sanitize
                    }
                }

                return json_encode($data);
            }
        }

        return false;
    }

    /**
     * File upload returns file ID
     * Can be seen in "file_managed" and "sos_promotions" table.
     */
    public static function getFileId(array $data) {
        $file = \Drupal::entityTypeManager()->getStorage('file')->load($data[0]);

        if(!empty($file)){
            return $file->id();
        }
    }

    /**
     * Returns the file upload URL using ID
     *
     */
    public static function getFileUrl($file_id) {
        $file = File::load($file_id);

        // Check if the file exists.
        if ($file) {
            // Generate the file URL.
            $file_uri = $file->getFileUri();
            $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file_uri);

            return $file_url;
        }
        else {
            // Return NULL if no file was found.
            return NULL;
        }
    }

    /**
     * Element validation callback for the file upload field.
     */
    public static function fileValidate($file_ids) {
        foreach ($file_ids as $file_id) {
            $file = File::load($file_id);

            if ($file) {
                // Set the file status to permanent.
                $file->setPermanent();
                $file->save();

                // Add file usage to prevent removal.
                \Drupal::service('file.usage')->add($file, 'sos_promotion', 'node', $file_id);
            }
        }
    }
}