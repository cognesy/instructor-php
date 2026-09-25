<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsResponseReceived;
use Cognesy\Polyglot\Embeddings\Models\ModelCatalog;

it('keeps ordinary embedding execution independent of model knowledge', function (): void {
    $runtime = new EmbeddingsRuntime(
        driver: new EmbeddingProfileTestDriver,
        events: new EventDispatcher,
        driverName: 'openai',
        defaultModel: 'text-embedding-3-small',
    );

    $request = $runtime->create(new EmbeddingsRequest(input: 'hello'))->request();

    expect($request->model())->toBe('text-embedding-3-small')
        ->and($request->modelProfile())->toBeNull();
});

it('attaches facts for the effective embedding route and keeps them transient', function (): void {
    $runtime = new EmbeddingsRuntime(
        driver: new EmbeddingProfileTestDriver,
        events: new EventDispatcher,
        models: ModelCatalog::discover(),
        driverName: 'openai',
        defaultModel: 'text-embedding-3-small',
    );

    $known = $runtime->create(new EmbeddingsRequest(input: 'known'))->request();
    $unknown = $runtime->create(new EmbeddingsRequest(
        input: 'unknown',
        model: 'private-embedding-model',
    ))->request();

    expect($known->modelProfile()?->model)->toBe('text-embedding-3-small')
        ->and($known->modelProfile()?->maxInputs)->toBe(2048)
        ->and($known->toArray())->not->toHaveKeys(['modelProfile', 'model_profile'])
        ->and($unknown->modelProfile()?->model)->toBe('private-embedding-model')
        ->and($unknown->modelProfile()?->maxInputs)->toBeNull();
});

it('rejects a known embedding input-count violation before driver execution', function (): void {
    $driver = new EmbeddingProfileTestDriver;
    $runtime = new EmbeddingsRuntime(
        driver: $driver,
        events: new EventDispatcher,
        models: ModelCatalog::discover(),
        driverName: 'openai',
        defaultModel: 'text-embedding-3-small',
    );

    expect(fn () => $runtime->create(new EmbeddingsRequest(
        input: array_fill(0, 2049, 'input'),
    )))->toThrow(InvalidArgumentException::class, 'model limit of 2048')
        ->and($driver->calls)->toBe(0);
});

it('projects only embedding model identity and catalog revision into lifecycle metadata', function (): void {
    $events = new EventDispatcher;
    $captured = null;
    $events->addListener(
        EmbeddingsResponseReceived::class,
        static function (EmbeddingsResponseReceived $event) use (&$captured): void {
            $captured = $event;
        },
    );
    $runtime = new EmbeddingsRuntime(
        driver: new EmbeddingProfileTestDriver,
        events: $events,
        models: ModelCatalog::discover(),
        driverName: 'openai',
        defaultModel: 'text-embedding-3-small',
    );

    $runtime->create(new EmbeddingsRequest(input: 'hello'))->get();

    expect($captured)->toBeInstanceOf(EmbeddingsResponseReceived::class)
        ->and($captured->data)->toMatchArray([
            'model' => 'text-embedding-3-small',
            'modelKey' => 'openai/text-embedding-3-small',
            'modelCatalogVersion' => '2026-09-18',
        ])
        ->and($captured->data)->not->toHaveKeys(['modelProfile', 'pricing']);
});

final class EmbeddingProfileTestDriver implements CanHandleVectorization
{
    public int $calls = 0;

    public function handle(EmbeddingsRequest $request): EmbeddingsResponse
    {
        $this->calls++;

        return new EmbeddingsResponse;
    }
}
