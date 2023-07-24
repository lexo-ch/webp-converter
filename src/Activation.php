<?php

namespace LEXO\WebPC;

use LEXO\WebPC\Core\Plugin\PluginService;

use const LEXO\WebPC\{
    FIELD_NAME
};

class Activation
{
    public function run()
    {
        if (false === get_option(FIELD_NAME)) {
            add_option(FIELD_NAME, PluginService::getInitSettings());
        }
    }
}
