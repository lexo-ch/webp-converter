<?php

namespace LEXO\WebPC\Core\Traits;

use LEXO\WebPC\Core\Notices\Notice;
use LEXO\WebPC\Core\Notices\Notices;

trait Helpers
{
    public $notice;
    public $notices;

    public function __construct()
    {
        $this->notice = new Notice();
        $this->notices = new Notices();
    }

    public static function getClassName($classname)
    {
        if ($name = strrpos($classname, '\\')) {
            return substr($classname, $name + 1);
        };

        return $name;
    }

    public static function setStatus404()
    {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }

    public static function printr(mixed $data): string
    {
        return "<pre>" . \print_r($data, true) . "</pre>";
    }
}
