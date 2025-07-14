<?php
namespace Cognesy\Config;

use Cognesy\Config\Exceptions\MissingSettingException;
use Cognesy\Config\Exceptions\NoSettingsFileException;
use Exception;
use InvalidArgumentException;
use RuntimeException;

class Settings
{
    /**
     * @var ?string The path to the configuration files.
     */
    static private ?string $path = null;

    /**
     * @var array<string> The default path to the configuration files.
     */
    static private array $defaultPaths = [
        'config/',
        'vendor/cognesy/instructor-php/config/',
    ];

    /**
     * @var array The loaded settings.
     */
    static private array $settings = [];

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
    public static function getPath(?string $group = null): string {
        return self::$path
            ?? self::getFirstValidPath(
                paths: $_ENV['INSTRUCTOR_CONFIG_PATHS']
                    ?? $_ENV['INSTRUCTOR_CONFIG_PATH']
                    ?? self::$defaultPaths,
                group: $group,
            );
    }

    /**
     * Gets the default path to the configuration files.
     * @return array<string> The default paths to the configuration files.
     * @throws Exception
     */
    public static function getDefaultPaths(): array {
        return self::$defaultPaths;
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
            throw new InvalidArgumentException("Settings group not provided");
        }

        if (!self::isGroupLoaded($group)) {
            self::$settings[$group] = dot(self::loadGroup($group));
        }

        if ($default === null && !self::has($group, $key)) {
            throw new MissingSettingException("Settings key not found: $key in group: $group and no default value provided");
        }
        return self::$settings[$group]->get($key, $default);
    }

    /**
     * Checks if a setting exists by group and key.
     * If key is not provided, it checks if the group exists.
     *
     * @param string $group The settings group.
     * @param ?string $key The settings key.
     * @return bool True if the setting exists, false otherwise.
     * @throws Exception If the group is not provided.
     */
    public static function has(string $group, ?string $key = null) : bool {
        if (empty($group)) {
            throw new RuntimeException("Settings group not provided");
        }

        if (empty($key)) {
            return self::hasGroup($group);
        }

        if (!self::isGroupLoaded($group)) {
            self::$settings[$group] = dot(self::loadGroup($group));
        }

        return self::$settings[$group]->has($key);
    }

    /**
     * Checks if a settings group exists.
     *
     * @param string $group The settings group.
     * @return bool True if the group exists, false otherwise.
     * @throws Exception If the group is not provided.
     */
    public static function hasGroup(string $group) : bool {
        if (empty($group)) {
            throw new RuntimeException("Settings group not provided");
        }

        if (!self::isGroupLoaded($group)) {
            self::$settings[$group] = dot(self::loadGroup($group));
        }

        return !empty(self::$settings[$group]);
    }

    /**
     * Gets a settings group.
     *
     * @param string $group The settings group.
     * @return mixed The settings group.
     * @throws Exception If the group is not provided or the settings file is not found.
     */
    public static function getGroup(string $group) : mixed {
        if (empty($group)) {
            throw new RuntimeException("Settings group not provided");
        }

        if (!self::isGroupLoaded($group)) {
            self::$settings[$group] = dot(self::loadGroup($group));
        }

        return self::$settings[$group];
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
        $rootPath = self::getPath($group);

        // Ensure the rootPath ends with a directory separator
        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $path = $rootPath . $group . '.php';

        if (!file_exists($path)) {
            throw new NoSettingsFileException("Settings file not found: $path");
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

    private static function getFirstValidPath(string|array $paths, ?string $group = null): string {
        $paths = is_array($paths) ? $paths : explode(',', $paths);
        if (empty($paths)) {
            throw new InvalidArgumentException("No settings paths provided");
        }

        foreach ($paths as $path) {
            $resolvedPath = self::resolvePath($path);
            if (is_dir($resolvedPath)) {
                // if $group is not provided, return the resolved path
                if (empty($group)) {
                    return $resolvedPath;
                }
                // check if group file exists
                $groupPath = $resolvedPath . $group . '.php';
                if (file_exists($groupPath)) {
                    return $resolvedPath;
                }
            }
        }

        throw new NoSettingsFileException("No valid settings path found in: " . implode(', ', $paths));
    }

    /**
     * Checks if a given path is absolute.
     *
     * @param string $path The path to check.
     * @return bool True if the path is absolute, false otherwise.
     */
    private static function isAbsolutePath(string $path): bool {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\\\\/', $path) === 1;
    }
}
