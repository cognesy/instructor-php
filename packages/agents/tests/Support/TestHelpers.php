<?php declare(strict_types=1);

namespace Tests\Addons\Support;

class TestHelpers
{
    public static function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }
}
