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

            $quality = 100;

            $is_image_corrected = false;

            switch (strtolower($file_info['extension'])) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($file['file']);
                    $quality = $settings['types']['jpg']['compression'];
                    $corrected_image = self::correctImageOrientation($image, $file['file']);
                    $is_image_corrected = $corrected_image['rotated'];
                    $image = $corrected_image['image'];
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

            if ($is_image_corrected) {
                list($originalHeight, $originalWidth) = getimagesize($file['file']);
            } else {
                list($originalWidth, $originalHeight) = getimagesize($file['file']);
            }

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

            $newWidth = ceil($newWidth);
            $newHeight = ceil($newHeight);

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

    private static function correctImageOrientation($image, $filePath)
    {
        $rotated = false;

        if (!function_exists('exif_read_data')) {
            return [
                'image' => $image,
                'rotated' => $rotated
            ];
        }

        $exif = exif_read_data($filePath);

        if ($exif && isset($exif['Orientation'])) {
            $orientation = $exif['Orientation'];

            switch ($orientation) {
                case 3:
                    $image = imagerotate($image, 180, 0);
                    break;
                case 6:
                    $image = imagerotate($image, -90, 0); // Rotate clockwise
                    $rotated = true;
                    break;
                case 8:
                    $image = imagerotate($image, 90, 0); // Rotate counter-clockwise
                    $rotated = true;
                    break;
            }
        }

        return [
            'image' => $image,
            'rotated' => $rotated
        ];
    }
}
