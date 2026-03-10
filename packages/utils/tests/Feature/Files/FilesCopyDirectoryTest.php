<?php declare(strict_types=1);

use Cognesy\Utils\Files;

function removePathRecursivelyForFilesCopyDirectoryTest(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    $items = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($items, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        $pathname = $item->getPathname();
        match (true) {
            $item->isLink(), $item->isFile() => @unlink($pathname),
            $item->isDir() => @rmdir($pathname),
            default => null,
        };
    }

    @rmdir($path);
}

it('copies a directory tree without following symlinked directories', function () {
    $workspace = sys_get_temp_dir() . '/utils_files_copy_symlink_dir_' . uniqid('', true);
    $source = $workspace . '/source';
    $destination = $workspace . '/destination';
    $outside = $workspace . '/outside';
    $link = $source . '/linked-dir';

    mkdir($source, 0777, true);
    mkdir($outside, 0777, true);
    file_put_contents($outside . '/keep.txt', 'keep');

    try {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('Symlink function is not available in this environment.');
        }

        if (@symlink($outside, $link) === false) {
            $this->markTestSkipped('Unable to create symlink in this environment.');
        }

        expect(fn() => Files::copyDirectory($source, $destination))
            ->toThrow(RuntimeException::class, "Source directory contains unsupported symlink: '{$link}'");

        expect(is_dir($destination))->toBeTrue();
        expect(file_exists($destination . '/linked-dir/keep.txt'))->toBeFalse();
        expect(is_link($destination . '/linked-dir'))->toBeFalse();
        expect(file_exists($outside . '/keep.txt'))->toBeTrue();
    } finally {
        removePathRecursivelyForFilesCopyDirectoryTest($workspace);
    }
});

it('copies a directory tree without following symlinked files', function () {
    $workspace = sys_get_temp_dir() . '/utils_files_copy_symlink_file_' . uniqid('', true);
    $source = $workspace . '/source';
    $destination = $workspace . '/destination';
    $outside = $workspace . '/outside.txt';
    $link = $source . '/linked-file.txt';

    mkdir($source, 0777, true);
    file_put_contents($outside, 'keep');

    try {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('Symlink function is not available in this environment.');
        }

        if (@symlink($outside, $link) === false) {
            $this->markTestSkipped('Unable to create symlink in this environment.');
        }

        expect(fn() => Files::copyDirectory($source, $destination))
            ->toThrow(RuntimeException::class, "Source directory contains unsupported symlink: '{$link}'");

        expect(is_dir($destination))->toBeTrue();
        expect(file_exists($destination . '/linked-file.txt'))->toBeFalse();
        expect(file_exists($outside))->toBeTrue();
    } finally {
        removePathRecursivelyForFilesCopyDirectoryTest($workspace);
    }
});
