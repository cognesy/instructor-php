<?php declare(strict_types=1);

use Cognesy\Utils\Files;

function removePathRecursivelyForCopyDirectoryTest(string $path): void
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
        if ($item->isLink() || $item->isFile()) {
            @unlink($pathname);
            continue;
        }

        if ($item->isDir()) {
            @rmdir($pathname);
        }
    }

    @rmdir($path);
}

it('rejects destination nested inside source before recursive copy starts', function () {
    $workspace = sys_get_temp_dir() . '/utils_files_copy_inside_source_' . uniqid('', true);
    $source = $workspace . '/source';
    $destination = $source . '/sub';

    mkdir($source, 0777, true);
    file_put_contents($source . '/keep.txt', 'keep');

    try {
        expect(fn() => Files::copyDirectory($source, $destination))
            ->toThrow(RuntimeException::class, 'Cannot copy directory into itself or one of its descendants');

        expect(is_dir($destination))->toBeFalse();
        expect(file_exists($source . '/keep.txt'))->toBeTrue();
    } finally {
        removePathRecursivelyForCopyDirectoryTest($workspace);
    }
});
