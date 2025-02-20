<?php
namespace Cognesy\Utils;

use Dotenv\Dotenv;

/**
 * Class responsible for managing environment variables.
 */
class Env
{
    static private array $paths = [__DIR__.'/..'];
    static private array $names = ['.env'];
    static private Dotenv $dotenv;

    /**
     * Sets the paths and names for the class.
     *
     * @param string|array $paths An array or a single string of paths to set.
     * @param string|array $names An array or a single string of names to set (optional).
     * @return void
     */
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

    /**
     * Retrieves the value of an environment variable. First, attempts to get
     * the value from the system's environment variables. If not found,
     * checks the manually loaded environment variables.
     *
     * @param mixed $key The name of the environment variable.
     * @param mixed $default The default value to return if the environment variable is not found.
     * @return mixed The value of the environment variable or the default value.
     */
    public static function get(mixed $key, mixed $default = null) : mixed
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        if (!isset(self::$dotenv)) {
            self::load();
        }
        return $_ENV[$key] ?? $default;
    }

    /**
     * Loads environment variables from the specified paths and names.
     *
     * This method checks if both the paths and names arrays are empty.
     * If they are not, it initializes the Dotenv instance with the given paths
     * and names, and loads the environment variables safely.
     *
     * @return void
     */
    public static function load() : void {
        if ([] === self::$paths && [] === self::$names) {
            return;
        }
        self::$dotenv = Dotenv::createImmutable(self::$paths, self::$names);
        self::$dotenv->safeLoad();
    }
}
