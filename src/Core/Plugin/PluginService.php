<?php

namespace LEXO\WebPC\Core\Plugin;

use LEXO\WebPC\Core\Abstracts\Singleton;
use LEXO\WebPC\Core\Traits\Helpers;
use LEXO\WebPC\Core\Loader\Loader;
use LEXO\WebPC\Core\Updater\PluginUpdater;

use const LEXO\WebPC\{
    ASSETS,
    PLUGIN_NAME,
    PLUGIN_SLUG,
    VERSION,
    MIN_PHP_VERSION,
    MIN_WP_VERSION,
    DOMAIN,
    BASENAME,
    CACHE_KEY,
    UPDATE_PATH,
    FIELD_NAME,
    ORIGINAL_NAME_ADDITION
};

class PluginService extends Singleton
{
    use Helpers;

    private static string $namespace    = 'custom-plugin-namespace';
    protected static $instance          = null;

    private const CHECK_UPDATE          = 'check-update-' . PLUGIN_SLUG;
    private const MANAGE_PLUGIN_CAP     = 'administrator';
    private const MANAGE_DASH_WIDGET    = 'edit_posts';
    private const SETTINGS_PARENT_SLUG  = 'options-general.php';
    private const SETTINGS_PAGE_SLUG    = 'settings-' . PLUGIN_SLUG;
    private bool $can_manage_plugin     = false;
    public const INIT_SCALE_SIZE        = 1920;

    private $settingsPage;

    private static $negative_disable_period_entered = false;

    public function setNamespace(string $namespace)
    {
        self::$namespace = $namespace;
    }

    public function registerNamespace()
    {
        $config = require_once trailingslashit(ASSETS) . 'config/config.php';

        $loader = Loader::getInstance();

        $loader->registerNamespace(self::$namespace, $config);

        if (is_user_logged_in()) {
            $this->can_manage_plugin = current_user_can(self::getManagePluginCap());
        }

        $this->settingsPage = new SettingsPage();

        add_action('admin_post_' . self::CHECK_UPDATE, [$this, 'checkForUpdateManually']);
        add_action('wp_dashboard_setup', [$this, 'addDashboardWidget']);
        add_action('admin_post_toggle_webp_converter', [$this, 'handleToggleWebPConverter']);
    }

    public function handleDeleteAttachment($post_id)
    {
        $file_path = get_attached_file($post_id);

        if (file_exists($file_path)) {
            if (pathinfo($file_path, PATHINFO_EXTENSION) === 'webp') {
                $base_path = pathinfo($file_path, PATHINFO_DIRNAME);
                $file_name_without_extension = pathinfo($file_path, PATHINFO_FILENAME);

                foreach (self::allowedImageTypes() as $ext) {
                    $alternative_file_path = $base_path . DIRECTORY_SEPARATOR . $file_name_without_extension .  ORIGINAL_NAME_ADDITION . '.' . $ext;

                    if (file_exists($alternative_file_path)) {
                        unlink($alternative_file_path);
                    }
                }
            }
        }
    }

    public function hanldleSaveSettings()
    {
        add_action('admin_post_save_' . FIELD_NAME, [$this, 'saveSettings']);
    }

    public function checkForGD()
    {
        if (!extension_loaded('gd') || !function_exists('gd_info')) {
            $this->notices->add(
                $this->notice->message(
                    __('The GD PHP extension is required for the LEXO WebP Converter plugin to work.', 'webpc')
                )
                ->type('error')
            );
            return;
        }
    }

    public function compareWithLargeImageSize()
    {
        $large_width = (int) get_option('large_size_w');
        $scaling_value = (int) self::getPluginSettings()['scale-original-to'];

        if ($large_width > $scaling_value) {
            $this->notices->add(
                $this->notice->message(
                    sprintf(
                        __('The value of the <a href="%s">"large" image size width</a> (<b>%d</b>) is larger than the <a href="%s">value used for scaling</a> (<b>%d</b>) in %s plugin. This could lead to potential issues.', 'webpc'),
                        admin_url('options-media.php'),
                        $large_width,
                        self::getOptionsLink(),
                        $scaling_value,
                        PLUGIN_NAME
                    )
                )
                ->dismissible(false)
                ->type('error')
            );
            return;
        }
    }

    public function saveSettings()
    {
        if (!current_user_can(self::getManagePluginCap())) {
            wp_die(__('This user doesn\'t have permission to run this plugin.', 'webpc'));
        }

        check_admin_referer(FIELD_NAME);

        $settings = self::getPluginSettings();

        foreach (self::allowedImageTypes() as $ait) {
            if (isset($_POST[$ait]) && !empty($_POST[$ait])) {
                $settings['types'][$ait]['compression'] = sanitize_text_field($_POST[$ait]);
            }
        }

        foreach (
            [
                'keep-smaller',
                'scale-original-to',
                'preserve-original'
            ] as $field_name
        ) {
            $settings[$field_name] = isset($_POST[$field_name])
                ? sanitize_text_field($_POST[$field_name])
                : '';
        }

        update_option(FIELD_NAME, $settings);

        set_transient(
            DOMAIN . '_update_success_notice',
            sprintf(
                __('The settings for the %s have been successfully saved.', 'webpc'),
                PLUGIN_NAME
            ),
            HOUR_IN_SECONDS
        );

        wp_safe_redirect(self::getOptionsLink());

        exit;
    }

    public static function getManagePluginCap()
    {
        $capability = self::MANAGE_PLUGIN_CAP;

        $capability = apply_filters(self::$namespace . '/options-page/capability', $capability);

        return $capability;
    }

    public static function getManageDashWidgetCap()
    {
        $capability = self::MANAGE_DASH_WIDGET;

        $filtered_capability = apply_filters(self::$namespace . '/dashboard-widget/capability', $capability);

        return $filtered_capability;
    }

    public static function getDisableMsgDateFormat(): string
    {
        $date_format = 'd.m.Y H:i:s';
        return apply_filters(self::$namespace . '/dashboard-widget/date-format', $date_format);
    }

    public static function getTemporaryDisablePeriod(): int
    {
        $period = 60; // minutes

        $filtered_period = apply_filters(self::$namespace . '/temporary-disable-period', $period);

        if ($filtered_period < 0) {
            self::$negative_disable_period_entered = true;
            return 3600; // Default to 1 hour in seconds
        }

        return $filtered_period * 60; // Convert minutes to seconds
    }

    public function addNegativePeriodNotice()
    {
        if (!self::$negative_disable_period_entered) {
            return false;
        }

        if (!current_user_can(self::getManageDashWidgetCap())) {
            return false;
        }

        if (self::isTemporarilyDisabled()) {
            return false;
        }

        $message = sprintf(__('The temporary disable period for %s plugin cannot be negative. The default value of 1 hour will be used.', 'webpc'), PLUGIN_NAME);

        $this->notices->add(
            $this->notice->message($message)
            ->dismissible(false)
            ->type('warning')
        );
    }

    public static function getSettingsPageParentSlug()
    {
        $slug = self::SETTINGS_PARENT_SLUG;

        $slug = apply_filters(self::$namespace . '/options-page/parent-slug', $slug);

        return $slug;
    }

    public function addAdminLocalizedScripts()
    {
        $vars = [
            'plugin_name'       => PLUGIN_NAME,
            'plugin_slug'       => PLUGIN_SLUG,
            'plugin_version'    => VERSION,
            'min_php_version'   => MIN_PHP_VERSION,
            'min_wp_version'    => MIN_WP_VERSION,
            'text_domain'       => DOMAIN
        ];

        $vars = apply_filters(self::$namespace . '/admin_localized_script', $vars);

        wp_localize_script(trailingslashit(self::$namespace) . 'admin-' . DOMAIN . '.js', DOMAIN . 'AdminLocalized', $vars);
    }

    public function addSettingsLink()
    {
        add_filter(
            'plugin_action_links_' . BASENAME,
            [$this, 'setSettingsLink']
        );
    }

    public static function getOptionsLink()
    {
        $path = self::getSettingsPageParentSlug();

        if (strpos($path, '.php') === false) {
            $path = 'admin.php';
        }

        return esc_url(
            add_query_arg(
                'page',
                self::SETTINGS_PAGE_SLUG,
                admin_url($path)
            )
        );
    }

    public function setSettingsLink($links)
    {
        $url = self::getOptionsLink();

        $settings_link = "<a href='$url'>" . __('Settings', 'webpc') . '</a>';

        array_push(
            $links,
            $settings_link
        );

        return $links;
    }

    public function updater()
    {
        return (new PluginUpdater())
            ->setBasename(BASENAME)
            ->setSlug(PLUGIN_SLUG)
            ->setVersion(VERSION)
            ->setRemotePath(UPDATE_PATH)
            ->setCacheKey(CACHE_KEY)
            ->setCacheExpiration(HOUR_IN_SECONDS)
            ->setCache(true);
    }

    public function checkForUpdateManually()
    {
        if (!wp_verify_nonce($_REQUEST['nonce'], self::CHECK_UPDATE)) {
            wp_die(__('Security check failed.', 'webpc'));
        }

        $plugin_settings = PluginService::getInstance();

        if (!$plugin_settings->updater()->hasNewUpdate()) {
            set_transient(
                DOMAIN . '_no_updates_notice',
                sprintf(
                    __('Plugin %s is up to date.', 'webpc'),
                    PLUGIN_NAME
                ),
                HOUR_IN_SECONDS
            );

            wp_safe_redirect(self::getOptionsLink());
        } else {
            delete_transient(CACHE_KEY);
            wp_safe_redirect(admin_url('plugins.php'));
        }

        exit;
    }

    public static function nextAutoUpdateCheck()
    {
        $expiration_datetime = get_option('_transient_timeout_' . CACHE_KEY);

        if (!$expiration_datetime) {
            return false;
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $expiration_datetime);
        ;
    }

    public function noUpdatesNotice()
    {
        $message = get_transient(DOMAIN . '_no_updates_notice');
        delete_transient(DOMAIN . '_no_updates_notice');

        if (!$message) {
            return false;
        }

        $this->notices->add(
            $this->notice->message($message)->type('success')
        );
    }

    public function updateSuccessNotice()
    {
        $message = get_transient(DOMAIN . '_update_success_notice');
        delete_transient(DOMAIN . '_update_success_notice');

        if (!$message) {
            return false;
        }

        $this->notices->add(
            $this->notice->message($message)->type('success')
        );
    }

    public function addSettingsPage()
    {
        add_submenu_page(
            self::getSettingsPageParentSlug(),
            __('LEXO WebP Converter', 'webpc'),
            __('LEXO WebP Converter', 'webpc'),
            self::getManagePluginCap(),
            self::SETTINGS_PAGE_SLUG,
            [$this->settingsPage, 'getSettingsPageContent']
        );
    }

    public static function getInitSettings(): array
    {
        return [
            'types' => [
                'jpg' => [
                    'compression' => 85
                ]
            ],
            'keep-smaller' => 'on',
            'scale-original-to' => self::getInitialScaleSize(),
            'temporary_disable_timestamp' => 0
        ];
    }

    private static function mergeSettingsAndUpdateOption($currentSettings, $defaultSettings)
    {
        // Flag to track if there are any changes
        $isChanged = false;

        foreach ($defaultSettings as $key => $value) {
            if (!isset($currentSettings[$key])) {
                $currentSettings[$key] = $value;
                $isChanged = true; // Mark as changed
            } elseif (is_array($value)) {
                // Recursively merge sub-arrays and check for changes
                list($mergedSubArray, $subArrayChanged) = self::mergeSettingsAndUpdateOption($currentSettings[$key], $value);
                $currentSettings[$key] = $mergedSubArray;
                if ($subArrayChanged) {
                    $isChanged = true; // Propagate change flag
                }
            }
        }

        if ($isChanged) {
            // Update the WordPress option only if there are changes
            update_option(FIELD_NAME, $currentSettings);
        }

        // Return the merged settings and the change flag
        return array($currentSettings, $isChanged);
    }

    public function updateMissingSettings()
    {
        self::mergeSettingsAndUpdateOption(
            get_option(FIELD_NAME),
            self::getInitSettings()
        );
    }

    public static function getPluginSettings()
    {
        return wp_parse_args(get_option(FIELD_NAME, []), self::getInitSettings());
    }

    public static function getInitialScaleSize(): int
    {
        return max((int) get_option('large_size_w'), self::INIT_SCALE_SIZE);
    }

    public static function getMaxScaleSize()
    {
        return get_option('large_size_w') < 5000 ? 5000 : get_option('large_size_w');
    }

    public static function allowedImageTypes(): array
    {
        return [
            'jpeg',
            'jpg',
            'png',
            'webp'
        ];
    }

    public static function getSettingsPageFields(): array
    {
        $settings = self::getPluginSettings();

        if (!$settings) {
            return [];
        }

        $vars = [
            'jpg' => [
                'min' => 50,
                'max' => 100
            ],
            'scale-original-to' => [
                'min' => self::getInitialScaleSize(),
                'max' => self::getMaxScaleSize()
            ]
        ];

        return [
            'jpg' => [
                'type'          => 'number',
                'min'           => $vars['jpg']['min'],
                'max'           => $vars['jpg']['max'],
                'step'          => 1,
                'value'         => $settings['types']['jpg']['compression'],
                'label'         => __('Image compression in percentages.', 'webpc'),
                'description'   => sprintf(
                    __('Applies to WebP image created from uploaded JPG/JPEG image. Acceptable values range from %d to %d.', 'webpc'),
                    $vars['jpg']['min'],
                    $vars['jpg']['max']
                )
            ],
            'keep-smaller' => [
                'type'          => 'checkbox',
                'value'         => $settings['keep-smaller'] ?? '',
                'label'   => __('Keep smaller image (WebP or original, whatever is smaller)', 'webpc')
            ],
            'scale-original-to' => [
                'type'          => 'number',
                'min'           => $vars['scale-original-to']['min'],
                'max'           => $vars['scale-original-to']['max'],
                'step'          => 1,
                'value'         => $settings['scale-original-to'],
                'label'         => __('Scale down uploaded images.', 'webpc'),
                'description'   => sprintf(
                    __('This setting affects all uploaded images, no matter what type they are. If an image is wider or taller than the limit we set, it will automatically be scaled down to fit within this limit. The starting point for this limit is the larger of two values: <a href="%s">the width set for "large" image size</a>, or %d. You can choose any value within the range of %d (the larger of the "large" image size width or %d) to %d.', 'webpc'),
                    admin_url('options-media.php'),
                    self::INIT_SCALE_SIZE,
                    $vars['scale-original-to']['min'],
                    self::INIT_SCALE_SIZE,
                    $vars['scale-original-to']['max']
                )
            ],
            'preserve-original' => [
                'type'          => 'checkbox',
                'value'         => $settings['preserve-original'] ?? '',
                'label'   => __('Backup original image without scaling or compression', 'webpc'),
                'description' => __('Backups won\'t be created in cases when original image is WebP or option "Keep smaller image" is enabled and original image is smaller than converted WebP (and therefore we keep original instead of WebP).', 'webpc')
            ],
        ];
    }

    public static function getManualUpdateCheckLink(): string
    {
        return esc_url(
            add_query_arg(
                [
                    'action' => self::CHECK_UPDATE,
                    'nonce' => wp_create_nonce(self::CHECK_UPDATE)
                ],
                admin_url('admin-post.php')
            )
        );
    }

    public static function isTemporarilyDisabled(): bool
    {
        $settings = self::getPluginSettings();
        $timestamp = $settings['temporary_disable_timestamp'];

        $disable_period = self::getTemporaryDisablePeriod();
        $current_time = current_time('timestamp');

        if ($timestamp > 0 && ($timestamp + $disable_period) > $current_time) {
            return true;
        }

        // Reset and trigger temporary-disablement-has-ended action if time has expired
        if ($timestamp > 0 && ($timestamp + $disable_period) <= $current_time) {
            self::enablePlugin();
        }

        return false;
    }

    public static function getDisableMessage(): string
    {
        $settings = self::getPluginSettings();
        $timestamp = $settings['temporary_disable_timestamp'];
        $disable_period = self::getTemporaryDisablePeriod();
        $until = $timestamp + $disable_period;
        $date_format = self::getDisableMsgDateFormat();

        return sprintf(__('The %s plugin (<strong>image optimization</strong>) is temporarily disabled until <strong>%s</strong>', 'webpc'), PLUGIN_NAME, date($date_format, $until));
    }

    public static function enablePlugin()
    {
        $settings = self::getPluginSettings();
        $was_disabled = $settings['temporary_disable_timestamp'] > 0;
        $settings['temporary_disable_timestamp'] = 0;
        update_option(FIELD_NAME, $settings);

        if ($was_disabled) {
            do_action(DOMAIN . '/temporary-disablement-has-ended');
        }
    }

    public static function disablePlugin()
    {
        $settings = self::getPluginSettings();
        $settings['temporary_disable_timestamp'] = current_time('timestamp');
        update_option(FIELD_NAME, $settings);
        do_action(DOMAIN . '/plugin-temporarily-disabled');
    }

    public function addDashboardWidget()
    {
        if (!current_user_can(self::getManageDashWidgetCap())) {
            return;
        }

        wp_add_dashboard_widget(
            'webp_converter_dashboard_widget',
            sprintf(
                __('%s Control', 'webpc'),
                PLUGIN_NAME
            ),
            [$this, 'renderDashboardWidget']
        );
    }

    public function renderDashboardWidget()
    {
        $is_disabled = self::isTemporarilyDisabled();
        $disable_period_message = $this->infoDisableMessage(self::getTemporaryDisablePeriod()); ?>

        <div id="webp-converter-dashboard-widget">
            <form method="post" action="admin-post.php">
                <input type="hidden" name="action" value="toggle_webp_converter" />
                <?php wp_nonce_field('toggle_webp_converter'); ?>

                <?php if ($is_disabled) { ?>
                    <input type="submit" name="enable_plugin" value="<?php esc_attr_e('Enable Image Optimization', 'webpc'); ?>" class="button-primary" />
                    <p><?php echo self::getDisableMessage(); ?></p>
                <?php } else { ?>
                    <input type="submit" name="disable_plugin" value="<?php esc_attr_e('Disable Image Optimization', 'webpc'); ?>" class="button-primary" />
                    <p><?php echo $disable_period_message; ?></p>
                <?php } ?>
            </form>
        </div>
        <?php
    }

    public function addTemporaryDisableNotice()
    {
        if (!self::isTemporarilyDisabled()) {
            return false;
        }

        $message = self::getDisableMessage();

        $this->notices->add(
            $this->notice->message($message)
            ->dismissible(false)
            ->type('info')
        );
    }

    private function infoDisableMessage(int $seconds): string
    {
        $time_display = self::convertSecondsToHoursAndMinutes($seconds);

        return sprintf(__('If you disable image optimization, uploaded images will not be automatically converted and optimized for the web in WebP format for <strong>%s</strong>. The function will then be automatically reactivated. You can enable image optimization at any time before this period expires.', 'webpc'), $time_display);
    }

    public function handleToggleWebPConverter()
    {
        if (!current_user_can(self::getManageDashWidgetCap())) {
            wp_die(__('This user doesn\'t have permission to run this plugin.', 'webpc'));
        }

        check_admin_referer('toggle_webp_converter');

        if (isset($_POST['disable_plugin'])) {
            self::disablePlugin();
        } else {
            self::enablePlugin();
        }

        wp_safe_redirect(admin_url());
        exit;
    }
}
