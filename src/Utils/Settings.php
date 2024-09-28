<?php
namespace Cognesy\Instructor\Utils;

use Exception;

class Settings
{
    static private string $path = '/../../config/';
    static private array $settings = [];

    public static function setPath(string $path) : void {
        self::$path = $path;
    }

    public static function get(string $group, string $key, mixed $default = null) : mixed {
        if (empty($group)) {
            throw new Exception("Settings group not provided");
        }

        if (empty(self::$settings[$group])) {
            $rootPath = $_ENV['INSTRUCTOR_CONFIG_PATH'] ?? self::$path;
            $path = $rootPath . $group . '.php';
            if (!file_exists(__DIR__ . $path)) {
                throw new Exception("Settings file not found: $path");
            }

            self::$settings[$group] = require __DIR__ . $path;
        }

        return dot(self::$settings[$group])->get($key, $default);
    }

    public static function has(string $group, string $key) : bool {
        return dot(self::$settings[$group])->has($key);
    }

    public static function set(string $group, string $key, mixed $value) : void {
        dot(self::$settings[$group])->set($key, $value);
    }
}
