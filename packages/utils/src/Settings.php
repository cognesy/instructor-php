<?php
namespace Cognesy\Utils;

use Exception;

class Settings
{
    /**
     * @var string The path to the configuration files.
     */
    static private ?string $path = null;

    /**
     * @var string The default path to the configuration files.
     */
    static private string $defaultPath = 'config/';
//    static private string $defaultPath = __DIR__ . '/../config/';

    /**
     * @var array The loaded settings.
     */
    static private array $settings = [];

//    /**
//     * @var string The base directory for resolving relative paths.
//     */
//    static private string $baseDir = __DIR__;

    // STATIC ////////////////////////////////////////////////////////////////////

    /**
     * Sets the path to the configuration files and clears the loaded settings.
     *
     * @param string $path The new path to the configuration files.
     */
    public static function setPath(string $path) : void {
        self::$path = self::resolvePath($path);
        self::$settings = [];
    }

    /**
     * Gets the current path to the configuration files.
     *
     * @return string The current path to the configuration files.
     */
    public static function getPath(): string {
        return self::$path ?? self::resolvePath($_ENV['INSTRUCTOR_CONFIG_PATH'] ?? self::$defaultPath);
    }

    /**
     * Gets the default path to the configuration files.
     * @return string
     * @throws Exception
     */
    public static function getDefaultPath(): string {
        return BasePath::get(self::$defaultPath);
    }

    /**
     * Gets a setting value by group and key.
     *
     * @param string $group The settings group.
     * @param string $key The settings key.
     * @param mixed $default The default value if the key is not found.
     * @return mixed The setting value.
     * @throws Exception If the group is not provided or the key is not found and no default value is provided.
     */
    public static function get(string $group, string $key, mixed $default = null) : mixed {
        if (empty($group)) {
            throw new Exception("Settings group not provided");
        }

        if (!self::isGroupLoaded($group)) {
            self::$settings[$group] = dot(self::loadGroup($group));
        }

        if ($default === null && !self::has($group, $key)) {
            throw new Exception("Settings key not found: $key in group: $group and no default value provided");
        }
        return self::$settings[$group]->get($key, $default);
    }

    /**
     * Checks if a setting exists by group and key.
     *
     * @param string $group The settings group.
     * @param string $key The settings key.
     * @return bool True if the setting exists, false otherwise.
     * @throws Exception If the group is not provided.
     */
    public static function has(string $group, string $key) : bool {
        if (empty($group)) {
            throw new Exception("Settings group not provided");
        }

        if (!self::isGroupLoaded($group)) {
            self::$settings[$group] = dot(self::loadGroup($group));
        }

        return self::$settings[$group]->has($key);
    }

    /**
     * Sets a setting value by group and key.
     *
     * @param string $group The settings group.
     * @param string $key The settings key.
     * @param mixed $value The value to set.
     */
    public static function set(string $group, string $key, mixed $value) : void {
        if (!self::isGroupLoaded($group)) {
            self::$settings[$group] = dot(self::loadGroup($group));
        }

        self::$settings[$group] = self::$settings[$group]->set($key, $value);
    }

    /**
     * Unsets a settings group.
     *
     * @param string $group The settings group.
     */
    public static function unset(string $group) : void {
        if (self::isGroupLoaded($group)) {
            self::$settings[$group] = [];
        }
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    /**
     * Checks if a settings group is loaded.
     *
     * @param string $group The settings group.
     * @return bool True if the group is loaded, false otherwise.
     */
    private static function isGroupLoaded(string $group) : bool {
        return isset(self::$settings[$group]) && (self::$settings[$group] !== null);
    }

    /**
     * Loads a settings group from a file.
     *
     * @param string $group The settings group.
     * @return array The loaded settings group.
     * @throws Exception If the settings file is not found.
     */
    private static function loadGroup(string $group) : array {
        $rootPath = self::getPath();

        // Ensure the rootPath ends with a directory separator
        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $path = $rootPath . $group . '.php';

        if (!file_exists($path)) {
            throw new Exception("Settings file not found: $path");
        }

        return require $path;
    }

    /**
     * Resolves a given path to an absolute path.
     *
     * @param string $path The path to resolve.
     * @return string The resolved absolute path.
     */
    private static function resolvePath(string $path): string {
        $path = self::isAbsolutePath($path) ? $path : BasePath::get($path);
        // if path does not end with DIRECTORY_SEPARATOR, add it
        return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
//    private static function resolvePath(string $path) : string {
//        if (self::isAbsolutePath($path)) {
//            return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
//        }
//
//        // Resolve relative paths based on the base directory
//        $resolvedPath = realpath(self::$baseDir . DIRECTORY_SEPARATOR . $path);
//        if ($resolvedPath !== false) {
//            return rtrim($resolvedPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
//        }
//
//        // If realpath fails (path doesn't exist), return the concatenated path
//        return rtrim(self::$baseDir . DIRECTORY_SEPARATOR . $path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
//    }

    /**
     * Checks if a given path is absolute.
     *
     * @param string $path The path to check.
     * @return bool True if the path is absolute, false otherwise.
     */
    private static function isAbsolutePath(string $path): bool {
        return strpos($path, '/') === 0 || preg_match('/^[a-zA-Z]:\\\\/', $path) === 1;
    }
}
