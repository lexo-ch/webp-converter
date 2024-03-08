<?php

namespace LEXO\WebPC\Core;

use LEXO\WebPC\Core\Abstracts\Singleton;
use LEXO\WebPC\Core\Plugin\PluginService;
use LEXO\WebPC\Core\Notices\Notices;
use LEXO\WebPC\Core\Plugin\Converter;

use const LEXO\WebPC\{
    DOMAIN,
    PATH,
    LOCALES
};

class Bootloader extends Singleton
{
    protected static $instance = null;

    public function run()
    {
        add_action('init', [$this, 'onInit'], 10);
        add_action(DOMAIN . '/localize/admin-webpc.js', [$this, 'onAdminWebpcJsLoad']);
        add_action('after_setup_theme', [$this, 'onAfterSetupTheme']);
        add_action('admin_menu', [$this, 'onAdminMenu'], 100);
        add_action('admin_init', [$this, 'onAdminInit'], 10);
        add_filter('wp_handle_upload', [$this, 'wpHandleUpload']);
    }

    public function onAdminInit()
    {
        $plugin_settings = PluginService::getInstance();
        $plugin_settings->updateMissingSettings();
        $plugin_settings->hanldleSaveSettings();
        $plugin_settings->checkForGD();
        $plugin_settings->compareWithLargeImageSize();
    }

    public function onInit()
    {
        do_action(DOMAIN . '/init');

        $plugin_settings = PluginService::getInstance();
        $plugin_settings->setNamespace(DOMAIN);
        $plugin_settings->registerNamespace();
        $plugin_settings->addSettingsLink();
        $plugin_settings->noUpdatesNotice();
        $plugin_settings->updateSuccessNotice();

        (new Notices())->run();
    }

    public function onAdminMenu()
    {
        PluginService::getInstance()->addSettingsPage();
    }

    public function onAdminWebpcJsLoad()
    {
        PluginService::getInstance()->addAdminLocalizedScripts();
    }

    public function onAfterSetupTheme()
    {
        $this->loadPluginTextdomain();
        PluginService::getInstance()->updater()->run();
    }

    public function wpHandleUpload($file)
    {
        return (new Converter())->run($file);
    }

    public function loadPluginTextdomain()
    {
        load_plugin_textdomain(DOMAIN, false, trailingslashit(trailingslashit(basename(PATH)) . LOCALES));
    }
}
