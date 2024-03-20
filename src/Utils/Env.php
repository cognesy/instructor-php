<?php
namespace Cognesy\Instructor\Utils;

use Dotenv\Dotenv;

class Env
{
    static private array $paths = [__DIR__.'/../..'];
    static private array $names = ['.env'];
    static private Dotenv $dotenv;

    public static function set(string|array $paths, string|array $names = '') : void {
        if (is_string($paths)) {
            $paths = [$paths];
        }
        if (is_string($names)) {
            $names = [$names];
        }
        if (!empty($paths)) {
            self::$paths = $paths;
        }
        if (!empty($names)) {
            self::$names = $names;
        }
        self::load();
    }

    public static function get(mixed $key, mixed $default = null) : mixed
    {
        if (!isset(self::$dotenv)) {
            self::load();
        }
        return $_ENV[$key] ?? $default;
    }

    public static function load() : void {
        if (!isset(self::$paths) && !isset(self::$names)) {
            return;
        }
        self::$dotenv = Dotenv::createImmutable(self::$paths, self::$names);
        self::$dotenv->load();
    }
}
