<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Models\ModelProfile;

it('rejects malformed facts instead of silently converting them to unknown', function (array $facts) {
    expect(fn () => ModelProfile::fromArray([
        'driver' => 'custom', 'model' => 'test', ...$facts,
    ]))->toThrow(InvalidArgumentException::class);
})->with([
    'numeric status' => [['status' => 42]],
    'boolean status' => [['status' => true]],
    'null status' => [['status' => null]],
    'misspelled section' => [['capabilites' => []]],
    'null section' => [['capabilities' => null]],
    'list section' => [['modalities' => ['text']]],
    'misspelled limit' => [['limits' => ['contextLenght' => 42]]],
    'boolean limit' => [['limits' => ['contextWindow' => true]]],
    'negative limit' => [['limits' => ['maxOutput' => -1]]],
    'unknown modality' => [['modalities' => ['inputVideo' => 'supported']]],
    'removed generic file modality' => [['modalities' => ['inputFile' => 'supported']]],
    'boolean capability' => [['capabilities' => ['tools' => false]]],
    'null capability' => [['capabilities' => ['tools' => null]]],
    'misspelled capability' => [['capabilities' => ['jsonShema' => 'unsupported']]],
    'unknown reasoning key' => [['capabilities' => ['reasoning' => ['effort' => []]]]],
    'null reasoning' => [['capabilities' => ['reasoning' => null]]],
    'null visibility' => [['capabilities' => ['reasoning' => ['contentVisible' => null]]]],
    'mapped selections' => [['capabilities' => ['reasoning' => ['selections' => ['kind' => 'effort']]]]],
    'mapped efforts' => [['capabilities' => ['reasoning' => ['efforts' => ['low' => []]]]]],
    'misspelled budget' => [['capabilities' => ['reasoning' => ['budget' => ['min' => 1, 'maximum' => 20]]]]],
    'misspelled mapping' => [['capabilities' => ['reasoning' => ['efforts' => [
        ['requested' => 'low', 'provider' => 'low', 'qualty' => 'exact'],
    ]]]]],
    'null mapping quality' => [['capabilities' => ['reasoning' => ['efforts' => [
        ['requested' => 'low', 'provider' => 'low', 'quality' => null],
    ]]]]],
    'duplicate mapping' => [['capabilities' => ['reasoning' => ['efforts' => [
        ['requested' => 'low', 'provider' => 'low'],
        ['requested' => 'low', 'provider' => 'medium'],
    ]]]]],
]);

it('rejects unsupported catalog schemas and unknown envelope fields', function (array $fields) {
    expect(fn () => ModelCatalog::fromArray(['version' => 'test', 'models' => [], ...$fields]))
        ->toThrow(InvalidArgumentException::class);
})->with([
    [['schemaVersion' => 2]],
    [['schemaVersion' => '1']],
    [['schemaVersion' => null]],
    [['modelz' => []]],
]);

it('retains valid unknown facts and explicitly nullable numeric limits', function () {
    $profile = ModelProfile::fromArray([
        'driver' => 'custom', 'model' => 'test',
        'limits' => ['contextWindow' => null],
        'capabilities' => ['tools' => 'unknown'],
    ]);

    expect($profile->toArray())->toBe(['driver' => 'custom', 'model' => 'test']);
});

it('does not invent a common revision for independent records with different revisions', function () {
    $catalog = new ModelCatalog([
        ModelProfile::fromArray(['driver' => 'custom', 'model' => 'first'], 'first-revision'),
        ModelProfile::fromArray(['driver' => 'custom', 'model' => 'second'], 'second-revision'),
    ]);

    expect($catalog->toArray()['version'])->toBe('')
        ->and($catalog->find('custom', 'first')->catalogVersion)->toBe('first-revision')
        ->and($catalog->find('custom', 'second')->catalogVersion)->toBe('second-revision');
});
