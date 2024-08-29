<?php
namespace Cognesy\Instructor\Utils;

class Settings
{
    static private array $settings = [];

    public static function get(string $key, mixed $default = null) : mixed {
        if (empty(self::$settings)) {
            $path = $_ENV['INSTRUCTOR_CONFIG_PATH'] ?? '/../../config/instructor.php';
            self::$settings = require __DIR__ . $path;
        }
        return dot(self::$settings)->get($key, $default);
    }
}
