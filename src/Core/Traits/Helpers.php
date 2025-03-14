<?php

namespace LEXO\WebPC\Core\Traits;

trait Helpers
{
    public function __construct()
    {
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

    public static function convertSecondsToHoursAndMinutes(int $seconds): string
    {
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;

        if ($hours > 0) {
            return $remaining_minutes === 0 ? sprintf('%dh', $hours) : sprintf('%dh %dmin', $hours, $remaining_minutes);
        }

        return sprintf('%dmin', $remaining_minutes);
    }
}
