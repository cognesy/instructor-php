<?php

namespace Cognesy\Utils;

use Composer\Autoload\ClassLoader;
use ReflectionClass;
use Throwable;

/**
 * BasePath class provides a method to determine the base path of the application
 */
class BasePath
{
    /**
     * Stores the base path once determined
     *
     * @var string|null
     */
    private static ?string $basePath = null;

    /**
     * Get the base path of the application
     *
     * @param string $path Optional path to append to the base path
     * @return string The absolute path to the application root
     * @throws \Exception When unable to determine the base path
     */
    public static function get(string $path = ''): string
    {
        // if provided path has a leading slash, return it as is
        if (self::startsWithSlash($path)) {
            return $path;
        }

        // Return the cached path if it exists
        if (self::$basePath !== null) {
            return self::makePath($path);
        }

        // Try each method in sequence to determine the base path
        self::$basePath = self::getBasePathFromEnv()
            ?? self::getBasePathFromComposer()
            ?? self::getBasePathFromReflection();

        // If we still don't have a base path, throw an exception
        if (self::$basePath === null) {
            throw new \Exception('Unable to determine application base path');
        }

        return self::makePath($path);
    }

    /**
     * Manually set the application base path
     *
     * @param string $path The absolute path to set as the base path
     * @return void
     */
    public static function set(string $path): void
    {
        self::$basePath = rtrim($path, '/\\');
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    /**
     * Create a full path by appending the given path to the base path
     *
     * @param string|null $path The path to append
     * @return string The full path
     */
    private static function makePath(string $path = null): string
    {
        return $path
            ? self::$basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\')
            : self::$basePath;
    }

    /**
     * Try to get the base path from the APP_BASE_PATH environment variable
     *
     * @return string|null The base path, or null if not found
     */
    private static function getBasePathFromEnv(): ?string
    {
        return isset($_ENV['APP_BASE_PATH']) ? rtrim($_ENV['APP_BASE_PATH'], '/\\') : null;
    }

    /**
     * Try to get the base path using Composer's ClassLoader
     *
     * @return string|null The base path, or null if not found
     */
    private static function getBasePathFromComposer(): ?string
    {
        try {
            if (class_exists(ClassLoader::class)) {
                $reflection = new ReflectionClass(ClassLoader::class);
                $vendorDir = dirname($reflection->getFileName(), 2);
                return dirname($vendorDir);
            }
        } catch (Throwable $e) {
            // Silently fail and try the next method
        }

        return null;
    }

    /**
     * Try to get the base path by walking up directories from current class file
     * until composer.json is found (Symfony-like approach)
     *
     * @return string|null The base path, or null if not found
     */
    private static function getBasePathFromReflection(): ?string
    {
        try {
            $reflection = new ReflectionClass(self::class);
            $dir = dirname($reflection->getFileName());

            // Walk up directories until we find composer.json
            while (!file_exists($dir . '/composer.json')) {
                // If we've reached the filesystem root, abort
                if ($dir === dirname($dir)) {
                    return null;
                }
                $dir = dirname($dir);
            }

            return $dir;
        } catch (Throwable $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Check if the given path starts with a slash
     *
     * @param string $path The path to check
     * @return bool True if the path starts with a slash, false otherwise
     */
    private static function startsWithSlash(string $path) : bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\');
    }
}