<?php

/**
 * Plugin Name:       LEXO WebP Converter
 * Plugin URI:        https://github.com/lexo-ch/webp-converter/
 * Description:       Automatically converts images to WebP format upon upload.
 * Version:           2.0.2
 * Requires at least: 6.4
 * Requires PHP:      7.4.1
 * Author:            LEXO GmbH
 * Author URI:        https://www.lexo.ch
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webpc
 * Domain Path:       /languages
 * Update URI:        webp-converter
 */

namespace LEXO\WebPC;

use Exception;
use LEXO\WebPC\Activation;
use LEXO\WebPC\Deactivation;
use LEXO\WebPC\Uninstalling;
use LEXO\WebPC\Core\Bootloader;

// Prevent direct access
!defined('WPINC')
    && die;

// Define Main plugin file
!defined('LEXO\WebPC\FILE')
    && define('LEXO\WebPC\FILE', __FILE__);

// Define plugin name
!defined('LEXO\WebPC\PLUGIN_NAME')
    && define('LEXO\WebPC\PLUGIN_NAME', get_file_data(FILE, [
        'Plugin Name' => 'Plugin Name'
    ])['Plugin Name']);

// Define plugin slug
!defined('LEXO\WebPC\PLUGIN_SLUG')
    && define('LEXO\WebPC\PLUGIN_SLUG', get_file_data(FILE, [
        'Update URI' => 'Update URI'
    ])['Update URI']);

// Define Basename
!defined('LEXO\WebPC\BASENAME')
    && define('LEXO\WebPC\BASENAME', plugin_basename(FILE));

// Define internal path
!defined('LEXO\WebPC\PATH')
    && define('LEXO\WebPC\PATH', plugin_dir_path(FILE));

// Define assets path
!defined('LEXO\WebPC\ASSETS')
    && define('LEXO\WebPC\ASSETS', trailingslashit(PATH) . 'assets');

// Define internal url
!defined('LEXO\WebPC\URL')
    && define('LEXO\WebPC\URL', plugin_dir_url(FILE));

// Define internal version
!defined('LEXO\WebPC\VERSION')
    && define('LEXO\WebPC\VERSION', get_file_data(FILE, [
        'Version' => 'Version'
    ])['Version']);

// Define min PHP version
!defined('LEXO\WebPC\MIN_PHP_VERSION')
    && define('LEXO\WebPC\MIN_PHP_VERSION', get_file_data(FILE, [
        'Requires PHP' => 'Requires PHP'
    ])['Requires PHP']);

// Define min WP version
!defined('LEXO\WebPC\MIN_WP_VERSION')
    && define('LEXO\WebPC\MIN_WP_VERSION', get_file_data(FILE, [
        'Requires at least' => 'Requires at least'
    ])['Requires at least']);

// Define Text domain
!defined('LEXO\WebPC\DOMAIN')
    && define('LEXO\WebPC\DOMAIN', get_file_data(FILE, [
        'Text Domain' => 'Text Domain'
    ])['Text Domain']);

// Define locales folder (with all translations)
!defined('LEXO\WebPC\LOCALES')
    && define('LEXO\WebPC\LOCALES', 'languages');

!defined('LEXO\WebPC\FIELD_NAME')
    && define('LEXO\WebPC\FIELD_NAME', 'webp_converter_setting');

!defined('LEXO\WebPC\CACHE_KEY')
    && define('LEXO\WebPC\CACHE_KEY', DOMAIN . '_cache_key_update');

!defined('LEXO\WebPC\UPDATE_PATH')
    && define('LEXO\WebPC\UPDATE_PATH', 'https://wprepo.lexo.ch/public/webp-converter/info.json');

!defined('LEXO\WebPC\ORIGINAL_NAME_ADDITION')
    && define('LEXO\WebPC\ORIGINAL_NAME_ADDITION', '---webpc-original---');

if (!file_exists($composer = PATH . '/vendor/autoload.php')) {
    wp_die('Error locating autoloader in LEXO WebP Converter.
        Please run a following command:<pre>composer install</pre>', 'webpc');
}

require $composer;

register_activation_hook(FILE, function () {
    (new Activation())->run();
});

register_deactivation_hook(FILE, function () {
    (new Deactivation())->run();
});

if (!function_exists('webpc_uninstall')) {
    function webpc_uninstall()
    {
        (new Uninstalling())->run();
    }
}
register_uninstall_hook(FILE, __NAMESPACE__ . '\webpc_uninstall');

try {
    Bootloader::getInstance()->run();
} catch (Exception $e) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');

    deactivate_plugins(FILE);

    wp_die($e->getMessage());
}
