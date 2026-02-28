<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Unit;

use Cognesy\Sandbox\Utils\Workdir;

function removeTree(string $path): void
{
    if ($path === '' || !file_exists($path)) {
        return;
    }
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        removeTree($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
}

describe('Workdir', function () {
    it('does not follow symlinked directories outside workdir during remove', function () {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('Symlink support is unavailable.');
        }

        $base = sys_get_temp_dir() . '/sandbox-workdir-test-' . bin2hex(random_bytes(6));
        $workdir = $base . '/workdir';
        $outside = $base . '/outside';
        @mkdir($workdir, 0o700, true);
        @mkdir($outside, 0o700, true);

        $outsideFile = $outside . '/keep.txt';
        file_put_contents($outsideFile, 'keep');
        symlink($outside, $workdir . '/outside-link');

        Workdir::remove($workdir);

        expect(file_exists($outsideFile))->toBeTrue();

        removeTree($base);
    });

    it('removes nested directories and files inside workdir', function () {
        $base = sys_get_temp_dir() . '/sandbox-workdir-test-' . bin2hex(random_bytes(6));
        $workdir = $base . '/workdir';
        @mkdir($workdir . '/a/b', 0o700, true);
        file_put_contents($workdir . '/a/b/file.txt', 'data');
        file_put_contents($workdir . '/root.txt', 'data');

        Workdir::remove($workdir);

        expect(file_exists($workdir))->toBeFalse();

        removeTree($base);
    });
});

