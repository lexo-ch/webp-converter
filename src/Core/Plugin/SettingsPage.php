<?php

namespace LEXO\WebPC\Core\Plugin;

use const LEXO\WebPC\{
    FIELD_NAME,
};

class SettingsPage
{
    public function getSettingsPageContent()
    {
        \ob_start(); ?>
            <div id="webp-converter-settings" class="wrap">
                <h1><?php _e('WebP Converter', 'webpc'); ?></h1>

                <h2><?php _e('Compression quality settings', 'webpc'); ?></h2>

                <form method="post" action="admin-post.php">
                    <input type="hidden" name="action" value="<?php echo esc_attr('save_' . FIELD_NAME); ?>" />

                    <?php

                    wp_nonce_field(FIELD_NAME);

                    $settingsPageFields = PluginService::getSettingsPageFields(); ?>

                    <div id="webp-converter-setting-settings-wrapper">
                        <?php foreach ($settingsPageFields as $type => $options) { ?>
                            <div class="row">
                                <label>
                                    <?php switch ($options['type']) {
                                        case 'number':
                                            ?>
                                            <input
                                                required
                                                type="number"
                                                id="<?php echo esc_attr("type-{$type}"); ?>"
                                                name="<?php echo esc_attr($type); ?>"
                                                value="<?php echo esc_attr($options['value']); ?>"
                                                min="50"
                                                max="100"
                                                step="1"
                                            />
                                            <?php echo in_array($type, ['jpg']) ? '%' : '';
                                            break;

                                        case 'checkbox':
                                            ?>
                                            <input
                                                type="checkbox"
                                                id="<?php echo esc_attr("type-{$type}"); ?>"
                                                name="<?php echo esc_attr($type); ?>"
                                                <?php checked($options['value'], 'on'); ?>
                                            />
                                            <?php
                                            break;
                                    } ?>

                                    <?php echo $options['translation']; ?>
                                </label>
                            </div>
                        <?php } ?>
                    </div>

                    <div id="save-settings-holder">
                        <input
                            type="submit"
                            value="<?php esc_attr_e('Save settings', 'webpc'); ?>"
                            class="button-primary"
                            id="save-settings"
                        />
                    </div>

                    <hr>

                    <?php $nextAutoUpdateCheck = PluginService::nextAutoUpdateCheck();

                    if ($nextAutoUpdateCheck) { ?>
                        <div id="next-auto-update-check">
                            <?php echo sprintf(__('Next automatic update check at <b>%s</b>.', 'webpc'), $nextAutoUpdateCheck); ?>
                        </div>
                    <?php } ?>

                    <a
                        href="<?php echo PluginService::getManualUpdateCheckLink(); ?>"
                    >
                        <?php _e('Manually check for update', 'webpc'); ?>
                    </a>
                </form>
            </div>
        <?php echo \ob_get_clean();
    }
}
