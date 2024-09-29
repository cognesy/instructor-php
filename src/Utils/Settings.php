<?php
namespace Cognesy\Instructor\Utils;

use Exception;

class Settings
{
    static private string $path = '/../../config/';
    static private array $settings = [];

    // STATIC ////////////////////////////////////////////////////////////////////

    public static function setPath(string $path) : void {
        self::$path = $path;
        self::$settings = [];
    }

    public static function get(string $group, string $key, mixed $default = null) : mixed {
        if (empty($group)) {
            throw new Exception("Settings group not provided");
        }

        if (!self::isGroupLoaded($group)) {
            self::$settings[$group] = dot(self::loadGroup($group));
        }

        return self::$settings[$group]->get($key, $default);
    }

    public static function has(string $group, string $key) : bool {
        if (empty($group)) {
            throw new Exception("Settings group not provided");
        }

        if (!self::isGroupLoaded($group)) {
            self::$settings[$group] = dot(self::loadGroup($group));
        }

        return self::$settings[$group]->has($key);
    }

    public static function set(string $group, string $key, mixed $value) : void {
        if (!self::isGroupLoaded($group)) {
            self::$settings[$group] = dot(self::loadGroup($group));
        }

        self::$settings[$group] = self::$settings[$group]->set($key, $value);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    private static function isGroupLoaded(string $group) : bool {
        return isset(self::$settings[$group]) && (self::$settings[$group] !== null);
    }

    private static function loadGroup(string $group) : array {
        $rootPath = $_ENV['INSTRUCTOR_CONFIG_PATH'] ?? self::$path;
        $path = $rootPath . $group . '.php';
        if (!file_exists(__DIR__ . $path)) {
            throw new Exception("Settings file not found: $path");
        }
        return require __DIR__ . $path;
    }
}
