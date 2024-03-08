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
            $settings = PluginService::getPluginSettings();
            $image = null;

            $file_info['filename'] = wp_unique_filename($file_info['dirname'], $file_info['filename'] . '.webp');
            $webp_file_path = $file_info['dirname'] . '/' . $file_info['filename'];

            list($originalWidth, $originalHeight) = getimagesize($file['file']);
            $maxSize = $settings['scale-original-to'];
            $resizeNeeded = $originalWidth > $maxSize || $originalHeight > $maxSize;

            if ($resizeNeeded) {
                $ratio = $originalWidth / $originalHeight;
                if ($ratio > 1) {
                    $newWidth = $maxSize;
                    $newHeight = $maxSize / $ratio;
                } else {
                    $newHeight = $maxSize;
                    $newWidth = $maxSize * $ratio;
                }
            } else {
                $newWidth = $originalWidth;
                $newHeight = $originalHeight;
            }

            $quality = 100;

            switch (strtolower($file_info['extension'])) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($file['file']);
                    $quality = $settings['types']['jpg']['compression'];
                    break;
                case 'png':
                    $image = imagecreatefrompng($file['file']);
                    break;
                case 'webp':
                    $image = imagecreatefromwebp($file['file']);
                    break;
            }

            if (!$image) {
                throw new Exception('Failed to create image resource');
            }

            imagepalettetotruecolor($image);

            if (in_array(strtolower($file_info['extension']), ['png', 'webp'])) {
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }

            if ($resizeNeeded) {
                $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
                imagedestroy($image);
                $image = $resizedImage;
            }

            ob_start();
                imagewebp($image, null, $quality);
            $webpData = ob_get_clean();

            imagedestroy($image);

            if (
                $settings['keep-smaller'] !== 'on'
                || filesize($file['file']) > strlen($webpData)
            ) {
                file_put_contents($webp_file_path, $webpData);

                unlink($file['file']);

                $file['file'] = $webp_file_path;
                $file['type'] = 'image/webp';
            }
        } catch (Exception $e) {
            error_log('LEXO WebP Converter: Failed to convert image to WebP format - ' . $e->getMessage());
        }

        return $file;
    }
}
