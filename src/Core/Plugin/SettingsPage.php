<?php

namespace LEXO\WebPC\Core\Plugin;

use const LEXO\WebPC\{
    FIELD_NAME,
    CACHE_KEY
};

class SettingsPage
{
    public function getSettingsPageContent()
    {
        \ob_start(); ?>
            <div id="webp-converter-settings" class="wrap">
                <h1><?php _e('WebP Converter', 'webpc'); ?></h1>

                <h2><?php _e('Compression settings', 'webpc'); ?></h2>

                <form method="post" action="admin-post.php">
                    <input type="hidden" name="action" value="<?php echo 'save_' . FIELD_NAME; ?>" />

                    <?php

                    wp_nonce_field(FIELD_NAME);

                    $settingsPageFields = PluginService::getSettingsPageFields();

                    foreach ($settingsPageFields as $type => $options) { ?>
                        <label>
                            <?php echo $options['translation']; ?>:
                            <input
                                required
                                type="number"
                                id="<?php echo "type-{$type}"; ?>"
                                name="<?php echo $type; ?>"
                                value="<?php echo $options['compression']; ?>"
                                min="50"
                                max="100"
                                step="1"
                            />%
                        </label>
                    <?php } ?>

                    <div id="save-settings-holder">
                        <input
                            type="submit"
                            value="<?php _e('Save settings', 'webpc'); ?>"
                            class="button-primary"
                            id="save-settings"
                        />
                    </div>

                    <hr>

                    <?php $nextAutoUpdateCheck = PluginService::nextAutoUpdateCheck();

                    if ($nextAutoUpdateCheck) { ?>
                        <div id="next-tauto-update-check">
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
