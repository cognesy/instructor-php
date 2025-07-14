<?php declare(strict_types=1);

namespace Cognesy\Config;

use Adbar\Dot;
use Cognesy\Config\Exceptions\MissingSettingException;
use Cognesy\Config\Exceptions\NoSettingsFileException;

/**
 * Lightweight, read-only config loader.
 *
 * Search order (first match wins):
 *   1. Path set via Settings::setPath('/dir')         ← highest priority
 *   2. INSTRUCTOR_CONFIG_PATHS or INSTRUCTOR_CONFIG_PATH (comma-separated)
 *   3. Internal defaults listed in DEFAULT_PATHS
 *
 * Usage:
 *   Settings::setPath(__DIR__.'/config');       // optional override
 *   $dsn = Settings::get('database', 'dsn');    // dot-notation supported
 *
 * Implementation notes:
 *   • All data is cached per-group in static $cache (Dot objects).
 *   • Settings::flush() clears both cache _and_ custom override.
 *   • locate() walks the final path list once per group; O(N) worst-case.
 *   • Throws:
 *       – NoSettingsFileException   when group file missing
 *       – MissingSettingException   when key absent and no default given
 */
final class Settings
{
    private const DEFAULT_PATHS = [
        'config/',
        'vendor/cognesy/instructor-php/config/',
        'vendor/cognesy/instructor-struct/config/',
        'vendor/cognesy/instructor-polyglot/config/',
        'vendor/cognesy/instructor-config/config/',
    ];

    private static array $customPaths = [];
    private static array $cache = [];   // group => Dot

    /** Highest-priority override */
    public static function setPath(string $dir): void {
        self::$customPaths = [self::dir($dir)];
        self::$cache = [];
    }

    /** Drop all cached groups (tests, hot-reload) */
    public static function flush(): void {
        self::$cache = [];
        self::$customPaths = [];
    }

    public static function has(string $group, ?string $key = null): bool {
        if ($key === null) {
            return self::locate($group) !== null;
        }

        $dot = self::load($group, false);
        return $dot?->has($key) ?? false;
    }

    public static function get(string $group, string $key, mixed $default = null): mixed {
        $dot = self::load($group);
        if (!$dot->has($key)) {
            if (func_num_args() === 3) {
                return $default;
            }
            throw new MissingSettingException("Key '$key' missing in '$group'");
        }
        return $dot->get($key);
    }

    // ----------------------------------------------------------------

    private static function load(string $group, bool $throw = true): ?Dot {
        if (isset(self::$cache[$group])) {
            return self::$cache[$group];
        }
        $file = self::locate($group);
        if ($file === null) {
            if ($throw) {
                throw new NoSettingsFileException("Config '$group' not found in any search path");
            }
            return null;
        }
        /** @var array $data */
        $data = require $file;
        return self::$cache[$group] = new Dot($data);
    }

    /** Return full path to file or null */
    private static function locate(string $group): ?string {
        foreach (self::paths() as $dir) {
            $file = $dir . $group . '.php';
            if (is_file($file)) {
                return $file;
            }
        }
        return null;
    }

    /** Final search path list, in order */
    private static function paths(): array {
        if (self::$customPaths) {
            return self::$customPaths;
        }
        $env = getenv('INSTRUCTOR_CONFIG_PATHS')
            ?: ($_ENV['INSTRUCTOR_CONFIG_PATHS'] ?? '')
            ?: getenv('INSTRUCTOR_CONFIG_PATH')
            ?: ($_ENV['INSTRUCTOR_CONFIG_PATH'] ?? '');
        $envPaths = array_filter(array_map(fn($p) => self::dir($p), explode(',', $env)));
        return array_unique([...$envPaths, ...array_map(fn($p) => self::dir($p), self::DEFAULT_PATHS)]);
    }

    /** Normalise dir string, always with trailing slash */
    private static function dir(string $dir): string {
        $dir = rtrim($dir);
        if ($dir === '') {
            return '';
        }
        // relative → project base
        if (!str_starts_with($dir, '/') && !preg_match('/^[A-Z]:\\\\/i', $dir)) {
            $dir = BasePath::get($dir);
        }
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
