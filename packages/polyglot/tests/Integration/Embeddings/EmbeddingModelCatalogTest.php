<?php

declare(strict_types=1);

use Cognesy\Config\Config;
use Cognesy\Polyglot\Embeddings\Models\EmbeddingModel;
use Cognesy\Polyglot\Embeddings\Models\ModelCatalog;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    $this->embeddingModelRoot = sys_get_temp_dir().'/embedding-models-'.bin2hex(random_bytes(8));
    mkdir($this->embeddingModelRoot, 0700, true);
});

afterEach(function (): void {
    (new Filesystem)->remove($this->embeddingModelRoot);
    Config::flushSourceCache();
});

function writeEmbeddingModelRecord(
    string $root,
    string $driver,
    string $model,
    array $facts = [],
): string {
    $path = $root.'/'.ModelCatalog::relativePath($driver, $model);
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    file_put_contents($path, Yaml::dump([
        'schemaVersion' => 1,
        'version' => 'fixture-v1',
        'profile' => ['driver' => $driver, 'model' => $model, ...$facts],
    ], 8, 2));

    return $path;
}

it('loads the bundled OpenAI embedding model facts', function (): void {
    $model = ModelCatalog::discover()->find('openai', 'text-embedding-3-small');

    expect($model->maxInputs)->toBe(2048)
        ->and($model->maxInputTokens)->toBe(8192)
        ->and($model->maxRequestTokens)->toBe(300000)
        ->and($model->defaultDimensions)->toBe(1536)
        ->and($model->pricing?->inputPerMToken)->toBe(0.02)
        ->and($model->catalogVersion)->toBe('2026-09-18');
});

it('returns a typed unknown model for a missing exact route', function (): void {
    $model = ModelCatalog::fromPaths($this->embeddingModelRoot)->find('custom', 'new');

    expect($model->driver)->toBe('custom')
        ->and($model->model)->toBe('new')
        ->and($model->maxInputs)->toBeNull()
        ->and($model->pricing)->toBeNull();
});

it('loads only the selected record and caches it', function (): void {
    file_put_contents($this->embeddingModelRoot.'/unrelated.yaml', 'invalid: [');
    $selected = writeEmbeddingModelRecord($this->embeddingModelRoot, 'custom', 'selected', [
        'maxInputs' => 12,
    ]);
    $catalog = ModelCatalog::fromPaths($this->embeddingModelRoot);
    $model = $catalog->find('custom', 'selected');
    unlink($selected);
    Config::flushSourceCache();

    expect($catalog->find('custom', 'selected'))->toBe($model)
        ->and($model->maxInputs)->toBe(12);
});

it('uses first-root whole-record precedence', function (): void {
    $project = $this->embeddingModelRoot.'/project';
    $package = $this->embeddingModelRoot.'/package';
    writeEmbeddingModelRecord($package, 'custom', 'same', [
        'maxInputs' => 100,
        'defaultDimensions' => 128,
    ]);
    writeEmbeddingModelRecord($project, 'custom', 'same', ['maxInputs' => 10]);

    $model = ModelCatalog::fromPaths($project, $package)->find('custom', 'same');

    expect($model->maxInputs)->toBe(10)
        ->and($model->defaultDimensions)->toBeNull();
});

it('rejects malformed selected records without falling through', function (): void {
    $project = $this->embeddingModelRoot.'/project';
    $package = $this->embeddingModelRoot.'/package';
    writeEmbeddingModelRecord($package, 'custom', 'same', ['maxInputs' => 100]);
    writeEmbeddingModelRecord($project, 'custom', 'same', ['maxInputs' => '10']);

    expect(fn () => ModelCatalog::fromPaths($project, $package)->find('custom', 'same'))
        ->toThrow(InvalidArgumentException::class, 'maxInputs');
});

it('rejects invalid model facts', function (array $facts): void {
    expect(fn () => EmbeddingModel::fromArray([
        'driver' => 'custom',
        'model' => 'test',
        ...$facts,
    ]))->toThrow(InvalidArgumentException::class);
})->with([
    'unknown field' => [['contextWindow' => 10]],
    'zero limit' => [['maxInputs' => 0]],
    'boolean limit' => [['maxRequestTokens' => true]],
    'empty pricing' => [['pricing' => []]],
    'numeric string pricing' => [['pricing' => ['inputPerMToken' => '0.02']]],
    'negative pricing' => [['pricing' => ['inputPerMToken' => -0.02]]],
    'infinite pricing' => [['pricing' => ['inputPerMToken' => INF]]],
]);

it('rejects identity mismatches and unsupported schemas', function (): void {
    $path = writeEmbeddingModelRecord($this->embeddingModelRoot, 'custom', 'first');
    rename($path, $this->embeddingModelRoot.'/custom/second.yaml');

    expect(fn () => ModelCatalog::fromPaths($this->embeddingModelRoot)->find('custom', 'second'))
        ->toThrow(InvalidArgumentException::class, 'identity');

    file_put_contents(
        $this->embeddingModelRoot.'/custom/second.yaml',
        "schemaVersion: 2\nversion: fixture-v1\nprofile: {}\n",
    );
    Config::flushSourceCache();

    expect(fn () => ModelCatalog::fromPaths($this->embeddingModelRoot)->find('custom', 'second'))
        ->toThrow(InvalidArgumentException::class, 'schemaVersion');
});
