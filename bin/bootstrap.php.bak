<?php
/**
 * Bootstrap file to initialize autoloading.
 */

/**
 * Dynamically locate the main Composer autoload file.
 *
 * @return string Path to vendor/autoload.php
 * @throws RuntimeException If autoload.php is not found.
 */
function findAutoload()
{
    $dir = __DIR__;
    $previousDir = '';

    while ($dir !== $previousDir) {
        $autoload = $dir . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            return $autoload;
        }
        $previousDir = $dir;
        $dir = dirname($dir);
    }

    throw new RuntimeException('Unable to locate vendor/autoload.php. Please ensure you have run "composer install".');
}

require findAutoload();
