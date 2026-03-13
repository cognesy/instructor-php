<?php declare(strict_types=1);

namespace Cognesy\Config\Tests\Integration;

use Cognesy\Config\BasePath;
use InvalidArgumentException;

it('resolveExisting returns existing candidate paths and ignores missing ones', function () {
    $projectRoot = makeTempProjectRoot();
    $existingA = $projectRoot . '/config';
    $existingB = $projectRoot . '/packages/polyglot/resources/config';
    mkdir($existingA, 0777, true);
    mkdir($existingB, 0777, true);

    BasePath::set($projectRoot);
    try {
        $resolved = BasePath::resolveExisting(
            'config',
            'packages/missing/resources/config',
            'packages/polyglot/resources/config',
        );

        expect($resolved)->toBe([$existingA, $existingB]);
        expect(BasePath::all(
            'config',
            'packages/missing/resources/config',
            'packages/polyglot/resources/config',
        ))->toBe([$existingA, $existingB]);
    } finally {
        BasePath::set(getcwd() ?: $projectRoot);
    }
});

it('resolves first existing candidate path', function () {
    $projectRoot = makeTempProjectRoot();
    $existing = $projectRoot . '/packages/polyglot/resources/config';
    mkdir($existing, 0777, true);

    BasePath::set($projectRoot);
    try {
        $resolved = BasePath::resolveFirst(
            'packages/missing/resources/config',
            'packages/polyglot/resources/config',
        );

        expect($resolved)->toBe($existing);
    } finally {
        BasePath::set(getcwd() ?: $projectRoot);
    }
});

it('throws when resolveFirst cannot resolve any existing candidate path', function () {
    $projectRoot = makeTempProjectRoot();
    BasePath::set($projectRoot);

    try {
        expect(fn () => BasePath::resolveFirst(
            'packages/missing-a/resources/config',
            'packages/missing-b/resources/config',
        ))->toThrow(InvalidArgumentException::class, 'Unable to resolve any existing path from provided candidates');
    } finally {
        BasePath::set(getcwd() ?: $projectRoot);
    }
});

function makeTempProjectRoot(): string {
    $dir = sys_get_temp_dir() . '/instructor-config-basepath-test-' . bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/composer.json', "{}\n");
    register_shutdown_function(static function () use ($dir): void {
        deleteDirRecursiveForBasePath($dir);
    });

    return $dir;
}

function deleteDirRecursiveForBasePath(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            deleteDirRecursiveForBasePath($path);
            continue;
        }

        unlink($path);
    }

    rmdir($dir);
}
