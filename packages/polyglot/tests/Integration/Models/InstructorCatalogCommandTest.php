<?php

declare(strict_types=1);

use Cognesy\Config\Config;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Symfony\Component\Process\Process;

it('rejects malformed source facts without replacing valid output', function (array $facts) {
    $directory = catalogCommandFixture();
    try {
        expect(runCatalogCommand($directory, 'build')->isSuccessful())->toBeTrue();
        $before = catalogCommandArtifact($directory);
        $source = catalogCommandSource($directory);
        $source['models'][0] = [...$source['models'][0], ...$facts];
        writeCatalogCommandSource($directory, $source);

        $build = runCatalogCommand($directory, 'build');
        $validation = runCatalogCommand($directory, 'validate');

        expect($build->isSuccessful())->toBeFalse()
            ->and($build->getErrorOutput())->not->toBe('')
            ->and($validation->isSuccessful())->toBeFalse()
            ->and($validation->getErrorOutput())->not->toBe('')
            ->and(catalogCommandArtifact($directory))->toBe($before);
    } finally {
        deleteCatalogCommandFixture($directory);
    }
})->with([
    'invalid status type' => [['status' => 42]],
    'misspelled capability' => [['capabilities' => ['jsonShema' => 'unsupported']]],
    'boolean capability' => [['capabilities' => ['tools' => false]]],
    'unknown section' => [['capabilites' => []]],
    'invalid reasoning field' => [['capabilities' => ['reasoning' => ['effort' => 'high']]]],
]);

it('builds deterministic readable records and removes obsolete output', function () {
    $directory = catalogCommandFixture();
    try {
        expect(runCatalogCommand($directory, 'build')->isSuccessful())->toBeTrue();
        $record = $directory . '/target/test/mapped.yaml';
        $firstBuild = catalogCommandArtifact($directory);

        expect(fileperms($record) & 0777)->toBe(0644)
            ->and(runCatalogCommand($directory, 'build')->isSuccessful())->toBeTrue()
            ->and(catalogCommandArtifact($directory))->toBe($firstBuild)
            ->and(runCatalogCommand($directory, 'check')->isSuccessful())->toBeTrue()
            ->and(runCatalogCommand($directory, 'validate')->isSuccessful())->toBeTrue();

        writeCatalogCommandSource($directory, ['version' => 'v1', 'models' => []]);

        expect(runCatalogCommand($directory, 'build')->isSuccessful())->toBeTrue()
            ->and(file_exists($record))->toBeFalse()
            ->and(runCatalogCommand($directory, 'check')->isSuccessful())->toBeTrue()
            ->and(runCatalogCommand($directory, 'validate')->isSuccessful())->toBeTrue();
    } finally {
        deleteCatalogCommandFixture($directory);
    }
});

it('detects stale generated records until they are rebuilt', function () {
    $directory = catalogCommandFixture();
    try {
        expect(runCatalogCommand($directory, 'build')->isSuccessful())->toBeTrue();
        $source = catalogCommandSource($directory);
        $source['version'] = 'v2';
        $source['models'][0]['limits']['contextWindow'] = 200;
        writeCatalogCommandSource($directory, $source);

        expect(runCatalogCommand($directory, 'check')->isSuccessful())->toBeFalse()
            ->and(runCatalogCommand($directory, 'build')->isSuccessful())->toBeTrue()
            ->and(runCatalogCommand($directory, 'check')->isSuccessful())->toBeTrue()
            ->and(catalogCommandArtifact($directory)['version'])->toBe('v2');
    } finally {
        deleteCatalogCommandFixture($directory);
    }
});

it('rejects removed catalog commands without compatibility behavior', function (string $command) {
    $directory = catalogCommandFixture();
    try {
        $process = runCatalogCommand($directory, $command);

        expect($process->getExitCode())->toBe(2)
            ->and($process->getErrorOutput())->toContain("Unknown command '{$command}'")
            ->and($process->getErrorOutput())->toContain('Use build, check, or validate');
    } finally {
        deleteCatalogCommandFixture($directory);
    }
})->with(['list', 'refresh', 'apply']);

function catalogCommandFixture(): string
{
    $directory = sys_get_temp_dir() . '/instructor-catalog-' . bin2hex(random_bytes(6));
    mkdir($directory, 0777, true);
    writeCatalogCommandSource($directory, [
        'version' => 'v1',
        'models' => [[
            'driver' => 'test',
            'model' => 'mapped',
            'status' => 'supported',
            'limits' => ['contextWindow' => 100, 'maxOutput' => 10],
            'modalities' => ['inputText' => 'supported', 'outputText' => 'supported'],
            'capabilities' => ['tools' => 'supported'],
            'source' => 'fixture',
        ]],
    ]);
    file_put_contents($directory . '/overrides.json', json_encode([
        'version' => 'v1',
        'models' => [],
    ], JSON_THROW_ON_ERROR));

    return $directory;
}

function runCatalogCommand(string $directory, string $command): Process
{
    $script = dirname(__DIR__, 3) . '/bin/instructor-catalog';
    $process = new Process([
        PHP_BINARY,
        $script,
        $command,
        "--source={$directory}/source.json",
        "--overrides={$directory}/overrides.json",
        "--target={$directory}/target",
    ]);
    $process->run();

    return $process;
}

/** @return array<string, mixed> */
function catalogCommandSource(string $directory): array
{
    return json_decode(
        (string) file_get_contents($directory . '/source.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

/** @param array<string, mixed> $source */
function writeCatalogCommandSource(string $directory, array $source): void
{
    file_put_contents($directory . '/source.json', json_encode($source, JSON_THROW_ON_ERROR));
}

/** @return array<string, mixed> */
function catalogCommandArtifact(string $directory): array
{
    Config::flushSourceCache();

    return ModelCatalog::fromPaths($directory . '/target')->toArray();
}

function deleteCatalogCommandFixture(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());

            continue;
        }
        unlink($item->getPathname());
    }
    rmdir($directory);
}
