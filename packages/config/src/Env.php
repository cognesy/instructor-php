<?php declare(strict_types=1);

namespace Cognesy\Config;

use Dotenv\Dotenv;

/**
 * Class responsible for managing environment variables.
 */
final class Env
{
    /** @var array<int, string> */
    private static array $paths = ['.'];
    /** @var array<int, string> */
    private static array $names = ['.env'];
    private static ?Dotenv $dotenv = null;

    /**
     * Sets the paths and names for the class.
     *
     * @param string|array<int, string> $paths An array or a single string of paths to set.
     * @param string|array<int, string>|null $names An array or a single string of names to set (optional).
     * @return void
     */
    public static function set(string|array $paths, string|array|null $names = null) : void {
        $normalizedPaths = self::normalizeInput($paths);
        if ($normalizedPaths !== []) {
            self::$paths = $normalizedPaths;
        }

        $normalizedNames = match (true) {
            $names === null => null,
            default => self::normalizeInput($names),
        };
        if ($normalizedNames !== null && $normalizedNames !== []) {
            self::$names = $normalizedNames;
        }

        self::$dotenv = null;
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
        if (!is_string($key) || $key === '') {
            return $default;
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (self::$dotenv === null) {
            self::load();
        }

        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
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
        if ([] === self::$paths || [] === self::$names) {
            return;
        }

        $resolvedPaths = BasePath::resolveExisting(...self::$paths);
        if ($resolvedPaths === []) {
            return;
        }

        self::$dotenv = Dotenv::createImmutable($resolvedPaths, self::$names);
        self::$dotenv->safeLoad();
    }

    /**
     * @param string|array<int, string> $values
     * @return array<int, string>
     */
    private static function normalizeInput(string|array $values): array {
        $list = is_string($values) ? [$values] : $values;
        $normalized = [];
        foreach ($list as $value) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $normalized[] = $trimmed;
        }

        return $normalized;
    }
}
