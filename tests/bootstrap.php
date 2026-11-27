<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (!function_exists('__')) {
    function __(string $message, ?string $domain = null): string
    {
        return $message;
    }
}
