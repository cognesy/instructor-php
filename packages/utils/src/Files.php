<?php declare(strict_types=1);

namespace Cognesy\Utils;

use DirectoryIterator;
use FilesystemIterator;
use InvalidArgumentException;
use Iterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Utility class for working with files and directories.
 */
class Files
{
    /**
     * Recursively remove a directory and all its contents.
     *
     * @param string $directory The path to the directory that should be removed.
     *
     * @return bool Returns true on success or false on failure.
     */
    public static function removeDirectory(string $directory): bool
    {
        // If the path is not a directory or doesn't exist, return false.
        if (!is_dir($directory)) {
            return false;
        }

        // Create a recursive directory iterator to skip `.` and `..`.
        $items = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);

        // Traverse directory contents in a bottom-up manner.
        $iterator = new RecursiveIteratorIterator($items, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isLink() || $fileInfo->isFile()) {
                // Remove file or symbolic link.
                unlink($fileInfo->getPathname());
            } elseif ($fileInfo->isDir()) {
                // Remove subdirectory.
                rmdir($fileInfo->getPathname());
            }
        }

        // Finally, remove the now-empty directory itself.
        return rmdir($directory);
    }

    /**
     * Recursively copy a directory from source to destination (including subdirectories).
     *
     * @param string $source      The path to the source directory.
     * @param string $destination The path to the destination directory.
     *
     * @throws InvalidArgumentException If the source directory does not exist.
     * @throws RuntimeException         If any directory creation or file copying fails.
     */
    public static function copyDirectory(string $source, string $destination): void
    {
        // Normalize paths to avoid trailing slashes.
        $source      = rtrim($source, DIRECTORY_SEPARATOR);
        $destination = rtrim($destination, DIRECTORY_SEPARATOR);

        // Ensure source directory exists.
        if (!is_dir($source)) {
            throw new InvalidArgumentException("Source directory does not exist: '{$source}'");
        }

        // Prevent recursively copying a directory into itself.
        if (realpath($source) === realpath($destination)) {
            throw new RuntimeException("Cannot copy '{$source}' into itself.");
        }

        // Create the destination directory if it doesn't exist.
        if (!file_exists($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
            throw new RuntimeException("Failed to create destination directory: '{$destination}'");
        }

        // Use DirectoryIterator for robust iteration of the source directory.
        $dirIterator = new DirectoryIterator($source);

        foreach ($dirIterator as $fileinfo) {
            if ($fileinfo->isDot()) {
                // Skip "." and ".." entries.
                continue;
            }

            $srcPath  = $fileinfo->getPathname();
            $destPath = $destination . DIRECTORY_SEPARATOR . $fileinfo->getBasename();

            // Recursively copy subdirectories; copy files directly.
            if ($fileinfo->isDir()) {
                self::copyDirectory($srcPath, $destPath);
            } else {
                if (!copy($srcPath, $destPath)) {
                    throw new RuntimeException("Failed to copy file '{$srcPath}' to '{$destPath}'");
                }
            }
        }
    }

    /**
     * Recursively rename file extensions within a given directory.
     *
     * @param string $directory Absolute path to the directory to process.
     * @param string $oldExt    Current extension (e.g. "md" or ".md").
     * @param string $newExt    Desired extension (e.g. "mdx" or ".mdx").
     *
     * @return void
     */
    public static function renameFileExtensions(string $directory, string $oldExt, string $newExt): void
    {
        // Sanitize extension inputs (remove any leading dot).
        $oldExt = ltrim($oldExt, '.');
        $newExt = ltrim($newExt, '.');

        // Ensure we have a valid directory to work with.
        if (!is_dir($directory)) {
            return;
        }

        // Create a RecursiveDirectoryIterator to traverse subdirectories/files.
        $items = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($items, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $fileInfo) {
            // Process only real files (skip directories, symlinks, etc.).
            if ($fileInfo->isFile()) {
                $currentExt = pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION);

                // If extension matches, rename the file with the new extension.
                if ($currentExt === $oldExt) {
                    $oldPath = $fileInfo->getPathname();
                    $filenameOnly = pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME);
                    $newPath = $fileInfo->getPath() . DIRECTORY_SEPARATOR . $filenameOnly . '.' . $newExt;

                    // Attempt to rename; you could also add try/catch around this if desired.
                    rename($oldPath, $newPath);
                }
            }
        }
    }

    /**
     * Copy a file from a source path to a target path, ensuring
     * the destination directory exists and throwing exceptions on errors.
     *
     * @param string $source      Absolute path to the source file.
     * @param string $destination Absolute path to the destination file.
     *
     * @throws InvalidArgumentException if the source is not a valid file.
     * @throws RuntimeException if the destination directory cannot be created,
     *                          or if copying the file fails for any reason.
     */
    public static function copyFile(string $source, string $destination): void
    {
        // Ensure the source is a valid, readable file
        if (!is_file($source) || !is_readable($source)) {
            throw new \InvalidArgumentException(sprintf('Source file "%s" is not readable or does not exist.', $source));
        }

        // Attempt to create the destination directory if it doesn't exist
        $destDir = dirname($destination);
        if (!is_dir($destDir) && !mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            throw new \RuntimeException(sprintf('Failed to create directory "%s".', $destDir));
        }

        // Perform the file copy and verify success
        if (!@copy($source, $destination)) {
            // Prepend the '@' to suppress PHP native warnings, but handle errors ourselves
            throw new \RuntimeException(sprintf('Failed to copy "%s" to "%s".', $source, $destination));
        }
    }

    /**
     * @param string $path
     * @return Iterator<SplFileInfo>
     */
    public static function directories(string $path): Iterator {
        if (!is_dir($path)) {
            throw new InvalidArgumentException("The provided path is not a directory: {$path}");
        }

        $iterator = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
        $recursiveIterator = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($recursiveIterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                yield $fileInfo;
            }
        }
    }

    /**
     * @param string $path
     * @return Iterator<SplFileInfo>
     */
    public static function files(string $path): Iterator {
        if (!is_dir($path)) {
            throw new InvalidArgumentException("The provided path is not a directory: {$path}");
        }

        $iterator = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
        $recursiveIterator = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($recursiveIterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                yield $fileInfo;
            }
        }
    }
}