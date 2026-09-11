<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

function catalogCommandFixture(): string
{
    $directory = sys_get_temp_dir().'/instructor-catalog-'.bin2hex(random_bytes(6));
    mkdir($directory.'/staging', 0777, true);
    file_put_contents($directory.'/source.json', json_encode([
        'version' => 'v1',
        'models' => [
            [
                'driver' => 'test',
                'model' => 'mapped',
                'status' => 'supported',
                'limits' => ['contextWindow' => 100, 'maxOutput' => 10],
                'modalities' => ['inputText' => 'supported', 'outputText' => 'supported'],
                'capabilities' => ['tools' => 'supported'],
                'source' => 'fixture',
            ],
            [
                'driver' => 'test',
                'model' => 'local',
                'status' => 'supported',
                'limits' => ['contextWindow' => 50],
                'source' => 'fixture',
            ],
        ],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($directory.'/overrides.json', json_encode([
        'version' => 'old-overrides',
        'models' => [],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($directory.'/mappings.json', json_encode([
        'schemaVersion' => 1,
        'snapshot' => ['url' => $directory.'/snapshot.json'],
        'offerings' => [
            [
                'driver' => 'test',
                'model' => 'mapped',
                'upstream' => ['provider' => 'upstream', 'model' => 'mapped-v1'],
                'imports' => [
                    'limits.contextWindow' => 'limit.context',
                    'limits.maxOutput' => 'limit.output',
                    'modalities.inputText' => 'modalities.input:text',
                    'modalities.inputImage' => 'modalities.input:image',
                    'modalities.inputAudio' => 'modalities.input:audio',
                    'modalities.inputFile' => 'modalities.input:file,pdf',
                    'modalities.outputText' => 'modalities.output:text',
                    'capabilities.tools' => 'tool_call',
                    'capabilities.reasoning' => 'reasoning_options',
                ],
            ],
            [
                'driver' => 'test',
                'model' => 'local',
                'upstream' => null,
                'imports' => [],
            ],
        ],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($directory.'/snapshot.json', json_encode([
        'upstream' => [
            'models' => [
                'mapped-v1' => [
                    'limit' => ['context' => 200, 'output' => 20],
                    'modalities' => ['input' => ['text', 'image', 'pdf'], 'output' => ['text']],
                    'tool_call' => false,
                    'reasoning_options' => [
                        ['type' => 'toggle'],
                        ['type' => 'effort', 'values' => ['low', 'high']],
                        ['type' => 'budget_tokens', 'min' => 16, 'max' => 32],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    return $directory;
}

function runCatalogCommand(string $directory, string $command, string ...$arguments): Process
{
    $script = dirname(__DIR__, 3).'/bin/instructor-catalog';
    $process = new Process([
        PHP_BINARY,
        $script,
        $command,
        "--source={$directory}/source.json",
        "--overrides={$directory}/overrides.json",
        "--target={$directory}/target.json",
        "--mappings={$directory}/mappings.json",
        "--staging={$directory}/staging",
        "--provenance={$directory}/provenance.json",
        ...$arguments,
    ]);
    $process->run();

    return $process;
}

function deleteCatalogCommandFixture(string $directory): void
{
    if (! is_dir($directory)) {
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

it('stages, reviews, and atomically applies only exact mapped offering fields', function () {
    $directory = catalogCommandFixture();
    try {
        $build = runCatalogCommand($directory, 'build');
        expect($build->isSuccessful())->toBeTrue();
        $firstBuild = file_get_contents($directory.'/target.json');
        expect(runCatalogCommand($directory, 'build')->isSuccessful())->toBeTrue()
            ->and(file_get_contents($directory.'/target.json'))->toBe($firstBuild);

        $refresh = runCatalogCommand($directory, 'refresh', '--revision=v2');
        $sourceBeforeApply = json_decode((string) file_get_contents($directory.'/source.json'), true, 512, JSON_THROW_ON_ERROR);
        $staged = json_decode((string) file_get_contents($directory.'/staging/models-source.json'), true, 512, JSON_THROW_ON_ERROR);
        $sourceByModel = array_column($sourceBeforeApply['models'], null, 'model');
        $stagedByModel = array_column($staged['models'], null, 'model');
        expect($refresh->isSuccessful())->toBeTrue()
            ->and($sourceBeforeApply['version'])->toBe('v1')
            ->and($staged['version'])->toBe('v2')
            ->and($stagedByModel['mapped']['limits'])->toBe(['contextWindow' => 200, 'maxOutput' => 20])
            ->and($stagedByModel['mapped']['modalities']['inputFile'])->toBe('supported')
            ->and($stagedByModel['mapped']['capabilities']['tools'])->toBe('unsupported')
            ->and($stagedByModel['mapped']['capabilities']['reasoning'])->toBe([
                'selections' => ['disabled', 'enabled', 'effort', 'budget'],
                'efforts' => [
                    ['requested' => 'low', 'provider' => 'low'],
                    ['requested' => 'high', 'provider' => 'high'],
                ],
                'budget' => ['min' => 16, 'max' => 32],
            ])
            ->and($stagedByModel['local'])->toBe($sourceByModel['local']);

        $apply = runCatalogCommand($directory, 'apply');
        $applied = json_decode((string) file_get_contents($directory.'/source.json'), true, 512, JSON_THROW_ON_ERROR);
        $compiled = json_decode((string) file_get_contents($directory.'/target.json'), true, 512, JSON_THROW_ON_ERROR);
        expect($apply->isSuccessful())->toBeTrue()
            ->and($applied)->toBe($staged)
            ->and($compiled['version'])->toBe('v2')
            ->and(runCatalogCommand($directory, 'check')->isSuccessful())->toBeTrue()
            ->and(runCatalogCommand($directory, 'validate')->isSuccessful())->toBeTrue();

        $listed = runCatalogCommand($directory, 'list', '--json');
        $list = json_decode($listed->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        expect($list['models'][0]['upstream'])->toBe('')
            ->and($list['models'][1]['upstream'])->toBe('upstream/mapped-v1')
            ->and($list['models'][1]['refreshRevision'])->toBe('v2');
    } finally {
        deleteCatalogCommandFixture($directory);
    }
});

it('rejects staged edits outside each offering mapping', function () {
    $directory = catalogCommandFixture();
    try {
        expect(runCatalogCommand($directory, 'refresh', '--revision=v2')->isSuccessful())->toBeTrue();
        $stagePath = $directory.'/staging/models-source.json';
        $provenancePath = $directory.'/staging/provenance.json';
        $staged = json_decode((string) file_get_contents($stagePath), true, 512, JSON_THROW_ON_ERROR);
        $staged['models'][0]['status'] = 'unknown';
        file_put_contents($stagePath, json_encode($staged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $provenance = json_decode((string) file_get_contents($provenancePath), true, 512, JSON_THROW_ON_ERROR);
        $provenance['stagedSourceSha256'] = hash_file('sha256', $stagePath);
        file_put_contents($provenancePath, json_encode($provenance, JSON_THROW_ON_ERROR));

        $apply = runCatalogCommand($directory, 'apply');
        expect($apply->isSuccessful())->toBeFalse()
            ->and($apply->getErrorOutput())->toContain('changed unowned fields');
    } finally {
        deleteCatalogCommandFixture($directory);
    }
});

it('rejects malformed and incomplete exact mappings during offline validation', function () {
    $directory = catalogCommandFixture();
    try {
        expect(runCatalogCommand($directory, 'build')->isSuccessful())->toBeTrue();
        $mappings = json_decode((string) file_get_contents($directory.'/mappings.json'), true, 512, JSON_THROW_ON_ERROR);
        array_pop($mappings['offerings']);
        $mappings['offerings'][0]['imports']['capabilities.jsonSchema'] = 'structured_output';
        file_put_contents($directory.'/mappings.json', json_encode($mappings, JSON_THROW_ON_ERROR));

        $validate = runCatalogCommand($directory, 'validate');
        expect($validate->isSuccessful())->toBeFalse()
            ->and($validate->getErrorOutput())->toContain('Unowned catalog import');
    } finally {
        deleteCatalogCommandFixture($directory);
    }
});
