<?php

declare(strict_types=1);

final class TellHarnessExample
{
    public static function project(): string
    {
        $directory = sys_get_temp_dir().'/instructor-tell-harness-'.bin2hex(random_bytes(6));
        mkdir($directory, 0755, true);

        return $directory;
    }

    public static function remove(string $directory): void
    {
        if (! str_starts_with($directory, sys_get_temp_dir().'/instructor-tell-harness-')) {
            throw new RuntimeException("Refusing to remove unexpected Tell harness directory: {$directory}");
        }

        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            match ($item->isDir()) {
                true => rmdir($item->getPathname()),
                false => unlink($item->getPathname()),
            };
        }
        rmdir($directory);
    }
}
