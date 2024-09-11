<?php
namespace Cognesy\Instructor\Utils;

use Exception;

class Settings
{
    static private array $settings = [];

    public static function get(string $group, string $key, mixed $default = null) : mixed {
        if (empty($group)) {
            throw new Exception("Settings group not provided");
        }

        if (empty(self::$settings[$group])) {
            $rootPath = $_ENV['INSTRUCTOR_CONFIG_PATH'] ?? '/../../config/';
            $path = $rootPath . $group . '.php';
            if (!file_exists(__DIR__ . $path)) {
                throw new Exception("Settings file not found: $path");
            }

            self::$settings[$group] = require __DIR__ . $path;
        }

        return dot(self::$settings[$group])->get($key, $default);
    }
}
