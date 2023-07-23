<?php

namespace LEXO\WebPC;

use const LEXO\WebPC\{
    CACHE_KEY
};

class Deactivation
{
    public static function run()
    {
        delete_transient(CACHE_KEY);
    }
}
