<?php

namespace LEXO\WebPC\Core\Plugin;

use Exception;

class Converter
{
    public function run($file)
    {
        $file_info = pathinfo($file['file']);

        if (!in_array(strtolower($file_info['extension']), PluginService::allowedImageTypes())) {
            return $file;
        }

        try {
            $image = null;

            $file_info['filename'] = wp_unique_filename($file_info['dirname'], $file_info['filename'] . '.webp');

            $webp_file_path = $file_info['dirname'] . '/' . $file_info['filename'];

            $quality = 100;

            switch (strtolower($file_info['extension'])) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($file['file']);
                    imagepalettetotruecolor($image);
                    $quality = PluginService::getPluginSettings()['types']['jpg']['compression'];

                    break;
                case 'png':
                    $image = imagecreatefrompng($file['file']);

                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);

                    break;
            }

            if (!$image) {
                throw new Exception('Failed to create image resource');
            }

            imagewebp($image, $webp_file_path, $quality);

            imagedestroy($image);

            unlink($file['file']);

            $file['file'] = $webp_file_path;
            $file['type'] = 'image/webp';
        } catch (Exception $e) {
            error_log('WebP Converter: Failed to convert image to WebP format - ' . $e->getMessage());
        }

        return $file;
    }
}
