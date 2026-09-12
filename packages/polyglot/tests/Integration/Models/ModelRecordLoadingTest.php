<?php declare(strict_types=1);

use Cognesy\Config\Config;
use Cognesy\Config\BasePath;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Models\ModelKey;
use Cognesy\Polyglot\Inference\Models\ModelRecordDirectory;
use Cognesy\Polyglot\Inference\Models\SupportStatus;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    $this->recordRoot = sys_get_temp_dir() . '/instructor-records-' . bin2hex(random_bytes(8));
    mkdir($this->recordRoot . '/custom', 0700, true);
});

afterEach(function () {
    (new Filesystem)->remove($this->recordRoot);
    Config::flushSourceCache();
});

function writeFoundationRecord(string $root, string $driver, string $model, array $facts = []): string {
    $path = $root . '/' . ModelRecordDirectory::relativePath(new ModelKey($driver, $model));
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    file_put_contents($path, Yaml::dump([
        'schemaVersion' => 1,
        'version' => 'fixture-v1',
        'profile' => ['driver' => $driver, 'model' => $model, ...$facts],
    ], 12, 2));

    return $path;
}

it('loads only the selected record and reuses it without filesystem access', function () {
    file_put_contents($this->recordRoot . '/custom/unrelated.yaml', 'invalid: [');
    $selected = writeFoundationRecord($this->recordRoot, 'custom', 'selected');
    $catalog = ModelCatalog::fromPaths($this->recordRoot);
    $profile = $catalog->find('custom', 'selected');
    unlink($selected);
    Config::flushSourceCache();

    foreach (range(1, 1000) as $iteration) {
        expect($catalog->find('custom', 'selected'))->toBe($profile);
    }
    expect(fn () => $catalog->count())->toThrow(\Symfony\Component\Yaml\Exception\ParseException::class);
});

it('caches missing records without hiding newly configured scopes', function () {
    $catalog = ModelCatalog::fromPaths($this->recordRoot);
    $missing = $catalog->find('custom', 'new');
    writeFoundationRecord($this->recordRoot, 'custom', 'new', ['status' => 'supported']);

    expect($catalog->find('custom', 'new'))->toBe($missing)
        ->and($missing->status)->toBe(SupportStatus::Unknown)
        ->and(ModelCatalog::fromPaths($this->recordRoot)->find('custom', 'new')->status)
        ->toBe(SupportStatus::Supported);
});

it('uses first-root whole-record precedence and isolates catalog scopes', function () {
    $base = $this->recordRoot . '/base';
    $project = $this->recordRoot . '/project';
    writeFoundationRecord($base, 'custom', 'same', ['limits' => ['maxOutput' => 10]]);
    writeFoundationRecord($project, 'custom', 'same', ['limits' => ['contextWindow' => 20]]);
    $catalog = ModelCatalog::fromPaths($project, $base);
    $profile = $catalog->find('custom', 'same');

    expect($profile->limits->contextWindow)->toBe(20)
        ->and($profile->limits->maxOutput)->toBeNull()
        ->and(ModelCatalog::fromPaths($base)->find('custom', 'same')->limits->maxOutput)->toBe(10)
        ->and($catalog)->toHaveCount(1);
});

it('does not load an overridden malformed packaged record', function () {
    $base = $this->recordRoot . '/base';
    $project = $this->recordRoot . '/project';
    $path = writeFoundationRecord($base, 'custom', 'same');
    file_put_contents($path, 'invalid: [');
    writeFoundationRecord($project, 'custom', 'same', ['status' => 'supported']);

    $catalog = ModelCatalog::fromPaths($base)->overlay(ModelCatalog::fromPaths($project));
    expect($catalog->find('custom', 'same')->status)->toBe(SupportStatus::Supported)
        ->and($catalog)->toHaveCount(1);
});

it('does not fall back from malformed selected records', function () {
    $base = $this->recordRoot . '/base';
    $project = $this->recordRoot . '/project';
    writeFoundationRecord($base, 'custom', 'same');
    writeFoundationRecord($project, 'custom', 'same', ['status' => false]);

    expect(fn () => ModelCatalog::fromPaths($project, $base)->find('custom', 'same'))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps exact identities including slashes and literal environment placeholders', function () {
    writeFoundationRecord($this->recordRoot, 'custom', 'vendor/model', ['source' => '${UNRESOLVED_MODEL_SOURCE}']);
    $catalog = ModelCatalog::fromPaths($this->recordRoot);

    expect($catalog->find('custom', 'vendor/model')->source)->toBe('${UNRESOLVED_MODEL_SOURCE}')
        ->and($catalog->find('custom', 'vendor-model')->status)->toBe(SupportStatus::Unknown)
        ->and($catalog->toArray()['models'][0]['model'])->toBe('vendor/model');
});

it('rejects records whose content does not match the exact requested identity', function () {
    $path = writeFoundationRecord($this->recordRoot, 'custom', 'first');
    rename($path, $this->recordRoot . '/custom/second.yaml');

    expect(fn () => ModelCatalog::fromPaths($this->recordRoot)->find('custom', 'second'))
        ->toThrow(InvalidArgumentException::class, 'identity');
});

it('keeps record lookup inside configured roots', function () {
    writeFoundationRecord($this->recordRoot, '..', '../outside');
    $catalog = ModelCatalog::fromPaths($this->recordRoot);

    expect($catalog->find('..', '../outside')->key->model)->toBe('../outside');
});

it('rejects unsupported schemas before hydration', function () {
    file_put_contents($this->recordRoot . '/custom/selected.yaml', "schemaVersion: 2\n");
    expect(fn () => ModelCatalog::fromPaths($this->recordRoot)->find('custom', 'selected'))
        ->toThrow(InvalidArgumentException::class, 'schemaVersion');
});

it('rejects invalid file boundaries without falling back to packaged facts', function () {
    $base = $this->recordRoot . '/base';
    $project = $this->recordRoot . '/project';
    writeFoundationRecord($base, 'custom', 'same');
    mkdir($project . '/custom/same.yaml', 0700, true);

    expect(fn () => ModelCatalog::fromPaths($project, $base)->find('custom', 'same'))
        ->toThrow(RuntimeException::class, 'readable file');
});

it('rejects symlinks escaping the configured record root', function () {
    $outside = writeFoundationRecord($this->recordRoot . '/outside', 'custom', 'same');
    symlink($outside, $this->recordRoot . '/custom/same.yaml');

    expect(ModelCatalog::fromPaths($this->recordRoot)->find('custom', 'same')->key->model)->toBe('same');

    mkdir($this->recordRoot . '/project/custom', 0700, true);
    symlink($outside, $this->recordRoot . '/project/custom/same.yaml');
    expect(fn () => ModelCatalog::fromPaths($this->recordRoot . '/project')->find('custom', 'same'))
        ->toThrow(InvalidArgumentException::class, 'escapes base path');
});

it('reuses exact records across repeated inference creation and preserves model overrides', function () {
    $selected = writeFoundationRecord($this->recordRoot, 'custom', 'selected');
    file_put_contents($this->recordRoot . '/custom/default.yaml', 'invalid: [');
    $catalog = ModelCatalog::fromPaths($this->recordRoot);
    $seen = [];
    $driver = Mockery::mock(CanProcessInferenceRequest::class);
    $driver->shouldReceive('makeResponseFor')->times(1000)->andReturnUsing(
        function (InferenceRequest $request) use (&$seen): InferenceResponse {
            $seen[spl_object_id($request->modelProfile())] = $request->model();
            return InferenceResponse::empty();
        },
    );
    $events = new EventDispatcher;

    foreach (range(1, 1000) as $iteration) {
        $runtime = new InferenceRuntime($driver, $events, $catalog, 'custom', 'default');
        $runtime->create(new InferenceRequest(model: 'selected'))->response();
        if ($iteration === 1) {
            unlink($selected);
            Config::flushSourceCache();
        }
    }

    expect(array_values($seen))->toBe(['selected']);
});

it('does not discover model records for repeated ordinary inference', function () {
    $consumerRoot = $this->recordRoot . '/consumer';
    $modelRoot = $consumerRoot . '/config/llm/models/custom';
    mkdir($modelRoot, 0700, true);
    file_put_contents($modelRoot . '/selected.yaml', 'invalid: [');
    BasePath::set($consumerRoot);

    $requests = 0;
    $driver = Mockery::mock(CanProcessInferenceRequest::class);
    $driver->shouldReceive('makeResponseFor')->times(1000)->andReturnUsing(
        function (InferenceRequest $request) use (&$requests): InferenceResponse {
            expect($request->model())->toBe('selected')
                ->and($request->modelProfile())->toBeNull();
            $requests++;

            return InferenceResponse::empty();
        },
    );
    $events = new EventDispatcher;

    try {
        foreach (range(1, 1000) as $iteration) {
            (new InferenceRuntime($driver, $events, driverName: 'custom', defaultModel: 'selected'))
                ->create(new InferenceRequest)
                ->response();
        }
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }

    expect($requests)->toBe(1000);
});
