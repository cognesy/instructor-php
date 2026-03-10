<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsResponseReceived;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Tests\Support\FakeEmbeddingsDriver;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('allows fluent event registration on inference runtime', function () {
    $events = new EventDispatcher('test.inference.runtime.listeners');
    $completed = 0;
    $tapped = 0;

    $runtime = (new InferenceRuntime(
        driver: new FakeInferenceDriver(responses: [new InferenceResponse(content: 'hello')]),
        events: $events,
    ))
        ->onEvent(InferenceCompleted::class, static function () use (&$completed): void {
            $completed++;
        })
        ->wiretap(static function () use (&$tapped): void {
            $tapped++;
        });

    $runtime->create(new InferenceRequest(messages: Messages::fromString('hello')))->response();

    expect($completed)->toBe(1);
    expect($tapped)->toBeGreaterThan(0);
});

it('allows fluent event registration on embeddings runtime', function () {
    $events = new EventDispatcher('test.embeddings.runtime.listeners');
    $received = 0;
    $tapped = 0;

    $runtime = (new EmbeddingsRuntime(
        driver: new FakeEmbeddingsDriver([
            new EmbeddingsResponse(vectors: [new Vector([0.1, 0.2])]),
        ]),
        events: $events,
    ))
        ->onEvent(EmbeddingsResponseReceived::class, static function () use (&$received): void {
            $received++;
        })
        ->wiretap(static function () use (&$tapped): void {
            $tapped++;
        });

    $runtime->create(new EmbeddingsRequest(input: 'hello'))->get();

    expect($received)->toBe(1);
    expect($tapped)->toBeGreaterThan(0);
});
