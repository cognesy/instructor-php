<?php

use Cognesy\Utils\Files;

function removePathRecursively(string $path): void
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
        /** @var \SplFileInfo $item */
        if ($item->isLink() || $item->isFile()) {
            @unlink($item->getPathname());
            continue;
        }
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        }
    }

    @rmdir($path);
}

it('removes symlink root without touching target contents', function () {
    $workspace = sys_get_temp_dir() . '/utils_files_remove_symlink_root_' . uniqid('', true);
    $target = $workspace . '/target';
    $link = $workspace . '/link';

    mkdir($target, 0777, true);
    file_put_contents($target . '/keep.txt', 'keep');

    try {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('Symlink function is not available in this environment.');
        }

        if (@symlink($target, $link) === false) {
            $this->markTestSkipped('Unable to create symlink in this environment.');
        }

        expect(file_exists($target . '/keep.txt'))->toBeTrue();

        $removed = Files::removeDirectory($link);

        expect($removed)->toBeTrue();
        expect(is_link($link))->toBeFalse();
        expect(file_exists($target . '/keep.txt'))->toBeTrue();
    } finally {
        removePathRecursively($workspace);
    }
});

it('does not follow nested symlinks while removing a directory tree', function () {
    $workspace = sys_get_temp_dir() . '/utils_files_remove_nested_symlink_' . uniqid('', true);
    $root = $workspace . '/root';
    $nested = $root . '/nested';
    $outside = $workspace . '/outside';
    $externalLink = $nested . '/external-link';

    mkdir($nested, 0777, true);
    mkdir($outside, 0777, true);
    file_put_contents($outside . '/keep.txt', 'keep');

    try {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('Symlink function is not available in this environment.');
        }

        if (@symlink($outside, $externalLink) === false) {
            $this->markTestSkipped('Unable to create symlink in this environment.');
        }

        expect(file_exists($outside . '/keep.txt'))->toBeTrue();

        $removed = Files::removeDirectory($root);

        expect($removed)->toBeTrue();
        expect(is_dir($root))->toBeFalse();
        expect(file_exists($outside . '/keep.txt'))->toBeTrue();
    } finally {
        removePathRecursively($workspace);
    }
});
