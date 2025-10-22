<?php declare(strict_types=1);

if (PHP_VERSION_ID < 80300 && !class_exists('Override')) {
    #[\Attribute(\Attribute::TARGET_METHOD)]
    class Override {}
}
