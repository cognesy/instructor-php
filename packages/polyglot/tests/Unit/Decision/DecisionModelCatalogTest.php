<?php

declare(strict_types=1);

use Cognesy\Config\Config;
use Cognesy\Polyglot\Decision\Data\DecisionPricing;
use Cognesy\Polyglot\Decision\Data\DecisionUsage;
use Cognesy\Polyglot\Decision\Models\DecisionModel;
use Cognesy\Polyglot\Decision\Models\DecisionPrimitiveSupport;
use Cognesy\Polyglot\Decision\Models\ModelCatalog;
use Cognesy\Polyglot\Decision\Pricing\FlatRateCostCalculator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    $this->decisionModelRoot = sys_get_temp_dir().'/decision-models-'.bin2hex(random_bytes(8));
    mkdir($this->decisionModelRoot, 0700, true);
});

afterEach(function (): void {
    (new Filesystem)->remove($this->decisionModelRoot);
    Config::flushSourceCache();
});

function writeDecisionModelRecord(
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

it('loads pinned and alias Jev facts independently', function (): void {
    $catalog = ModelCatalog::discover();
    $pinned = $catalog->find('typesafe', 'jev-1.13.0');
    $alias = $catalog->find('typesafe', 'jev-latest');

    expect($pinned->maxRequestTokens)->toBe(64000)
        ->and($pinned->maxStateAndQuestionTokens)->toBe(32000)
        ->and($pinned->pricing?->inputPerMToken)->toBe(0.042)
        ->and($pinned->pricing?->outputPerMToken)->toBe(0.0)
        ->and($pinned->capabilities->choice)->toBe(DecisionPrimitiveSupport::Native)
        ->and($pinned->capabilities->noul)->toBe(DecisionPrimitiveSupport::Native)
        ->and($pinned->capabilities->score)->toBe(DecisionPrimitiveSupport::Native)
        ->and($alias->model)->toBe('jev-latest')
        ->and($alias)->not->toBe($pinned);
});

it('loads Jeff primitive facts under the returned model identity', function (): void {
    $model = ModelCatalog::discover()->find('jeff', 'gliformer-large-v1');

    expect($model->model)->toBe('gliformer-large-v1')
        ->and($model->capabilities->choice)->toBe(DecisionPrimitiveSupport::Native)
        ->and($model->capabilities->noul)->toBe(DecisionPrimitiveSupport::Native)
        ->and($model->capabilities->score)->toBe(DecisionPrimitiveSupport::Native);
});

it('loads classifier.dev projected primitive facts', function (): void {
    $model = ModelCatalog::discover()->find('classifier-dev', 'fast');

    expect($model->model)->toBe('fast')
        ->and($model->capabilities->choice)->toBe(DecisionPrimitiveSupport::Native)
        ->and($model->capabilities->noul)->toBe(DecisionPrimitiveSupport::Projected)
        ->and($model->capabilities->score)->toBe(DecisionPrimitiveSupport::Projected);
});

it('loads every Laya route with native primitive facts and explicit context limits', function (): void {
    $catalog = ModelCatalog::discover();

    foreach (['laya' => 512, 'laya-multilingual' => 1024, 'laya-typed-decisions' => 1024] as $route => $limit) {
        $model = $catalog->find('laya', $route);
        expect($model->maxStateAndQuestionTokens)->toBe($limit)
            ->and($model->capabilities->choice)->toBe(DecisionPrimitiveSupport::Native)
            ->and($model->capabilities->noul)->toBe(DecisionPrimitiveSupport::Native)
            ->and($model->capabilities->score)->toBe(DecisionPrimitiveSupport::Native);
    }
});

it('returns typed unknown facts for uncatalogued decision models', function (): void {
    $model = ModelCatalog::fromPaths($this->decisionModelRoot)->find('typesafe', 'jev-next');

    expect($model->driver)->toBe('typesafe')
        ->and($model->model)->toBe('jev-next')
        ->and($model->maxRequestTokens)->toBeNull()
        ->and($model->capabilities->choice)->toBe(DecisionPrimitiveSupport::Unknown)
        ->and($model->capabilities->toArray())->toBe([])
        ->and($model->pricing)->toBeNull();
});

it('round-trips native projected unsupported and unknown primitive capabilities', function (): void {
    $model = DecisionModel::fromArray([
        'driver' => 'classifier-dev',
        'model' => 'fast',
        'capabilities' => [
            'choice' => 'native',
            'noul' => 'projected',
            'score' => 'unsupported',
        ],
    ]);

    expect($model->capabilities->choice)->toBe(DecisionPrimitiveSupport::Native)
        ->and($model->capabilities->noul)->toBe(DecisionPrimitiveSupport::Projected)
        ->and($model->capabilities->score)->toBe(DecisionPrimitiveSupport::Unsupported)
        ->and(DecisionModel::fromArray($model->toArray())->toArray())->toEqual($model->toArray());
});

it('loads only the exact selected record and uses whole-record precedence', function (): void {
    $project = $this->decisionModelRoot.'/project';
    $package = $this->decisionModelRoot.'/package';
    file_put_contents($this->decisionModelRoot.'/unrelated.yaml', 'invalid: [');
    writeDecisionModelRecord($package, 'typesafe', 'same', [
        'maxRequestTokens' => 64000,
        'maxStateAndQuestionTokens' => 32000,
    ]);
    writeDecisionModelRecord($project, 'typesafe', 'same', ['maxRequestTokens' => 12000]);

    $model = ModelCatalog::fromPaths($project, $package)->find('typesafe', 'same');

    expect($model->maxRequestTokens)->toBe(12000)
        ->and($model->maxStateAndQuestionTokens)->toBeNull();
});

it('rejects malformed selected records and model facts', function (array $facts): void {
    expect(fn () => DecisionModel::fromArray([
        'driver' => 'typesafe',
        'model' => 'jev-test',
        ...$facts,
    ]))->toThrow(InvalidArgumentException::class);
})->with([
    'unknown field' => [['contextWindow' => 64000]],
    'zero limit' => [['maxRequestTokens' => 0]],
    'string limit' => [['maxStateAndQuestionTokens' => '32000']],
    'empty pricing' => [['pricing' => []]],
    'partial pricing' => [['pricing' => ['inputPerMToken' => 0.042]]],
    'negative pricing' => [['pricing' => ['inputPerMToken' => -1, 'outputPerMToken' => 0]]],
    'infinite pricing' => [['pricing' => ['inputPerMToken' => INF, 'outputPerMToken' => 0]]],
    'capabilities list' => [['capabilities' => ['native']]],
    'unknown capability' => [['capabilities' => ['choice' => 'maybe']]],
]);

it('estimates cost only when required usage is known', function (): void {
    $calculator = new FlatRateCostCalculator;
    $jev = new DecisionPricing(inputPerMToken: 0.042, outputPerMToken: 0);
    $paidOutput = new DecisionPricing(inputPerMToken: 0, outputPerMToken: 2);
    $free = new DecisionPricing(inputPerMToken: 0, outputPerMToken: 0);

    $cost = $calculator->calculate(new DecisionUsage(1_000_000, null), $jev);

    expect($cost?->total)->toBe(0.042)
        ->and($cost?->breakdown)->toBe(['input' => 0.042, 'output' => 0.0])
        ->and($calculator->calculate(new DecisionUsage(null, 100), $jev))->toBeNull()
        ->and($calculator->calculate(new DecisionUsage(null, null), $paidOutput))->toBeNull()
        ->and($calculator->calculate(new DecisionUsage(null, null), $free)?->total)->toBe(0.0);
});

it('rejects record identity mismatch and unsupported schema', function (): void {
    $path = writeDecisionModelRecord($this->decisionModelRoot, 'typesafe', 'first');
    rename($path, $this->decisionModelRoot.'/typesafe/second.yaml');

    expect(fn () => ModelCatalog::fromPaths($this->decisionModelRoot)->find('typesafe', 'second'))
        ->toThrow(InvalidArgumentException::class, 'identity');

    file_put_contents(
        $this->decisionModelRoot.'/typesafe/second.yaml',
        "schemaVersion: 2\nversion: fixture-v1\nprofile: {}\n",
    );
    Config::flushSourceCache();

    expect(fn () => ModelCatalog::fromPaths($this->decisionModelRoot)->find('typesafe', 'second'))
        ->toThrow(InvalidArgumentException::class, 'schemaVersion');
});
