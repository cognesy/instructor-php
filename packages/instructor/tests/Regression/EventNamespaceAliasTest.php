<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;

/**
 * F1 (research/v2-cleanup-plan/02 Phase F, ships in 2.5): event classes moved
 * to domain namespaces; old FQCNs remain as class_alias shims for >= one minor
 * release. This test locks the compatibility mechanism:
 *  - old FQCNs resolve and are the SAME class as the new ones
 *  - listeners registered under the OLD name receive events dispatched as the
 *    NEW class (dispatcher canonicalizes alias names)
 */

const EVENT_ALIAS_MAP = [
    // PartialsGenerator -> Streaming
    \Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived::class
        => \Cognesy\Instructor\Events\Streaming\ChunkReceived::class,
    \Cognesy\Instructor\Events\PartialsGenerator\PartialJsonReceived::class
        => \Cognesy\Instructor\Events\Streaming\PartialJsonReceived::class,
    \Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated::class
        => \Cognesy\Instructor\Events\Streaming\PartialResponseGenerated::class,
    \Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerationFailed::class
        => \Cognesy\Instructor\Events\Streaming\PartialResponseGenerationFailed::class,
    \Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseFinished::class
        => \Cognesy\Instructor\Events\Streaming\StreamedResponseFinished::class,
    \Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived::class
        => \Cognesy\Instructor\Events\Streaming\StreamedResponseReceived::class,
    \Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted::class
        => \Cognesy\Instructor\Events\Streaming\StreamedToolCallCompleted::class,
    \Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted::class
        => \Cognesy\Instructor\Events\Streaming\StreamedToolCallStarted::class,
    \Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated::class
        => \Cognesy\Instructor\Events\Streaming\StreamedToolCallUpdated::class,
    // Request -> Streaming / ResponseModel / Attempt
    \Cognesy\Instructor\Events\Request\SequenceUpdated::class
        => \Cognesy\Instructor\Events\Streaming\SequenceUpdated::class,
    \Cognesy\Instructor\Events\Request\ResponseModelRequested::class
        => \Cognesy\Instructor\Events\ResponseModel\ResponseModelRequested::class,
    \Cognesy\Instructor\Events\Request\ResponseModelBuilt::class
        => \Cognesy\Instructor\Events\ResponseModel\ResponseModelBuilt::class,
    \Cognesy\Instructor\Events\Request\ResponseModelBuildModeSelected::class
        => \Cognesy\Instructor\Events\ResponseModel\ResponseModelBuildModeSelected::class,
    \Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt::class
        => \Cognesy\Instructor\Events\Attempt\NewValidationRecoveryAttempt::class,
    \Cognesy\Instructor\Events\Request\StructuredOutputRecoveryLimitReached::class
        => \Cognesy\Instructor\Events\Attempt\StructuredOutputRecoveryLimitReached::class,
];

it('resolves every legacy event FQCN to the same class as its new name', function () {
    foreach (EVENT_ALIAS_MAP as $old => $new) {
        expect(class_exists($old))->toBeTrue("legacy FQCN gone: {$old}");
        expect((new ReflectionClass($old))->getName())->toBe($new);
    }
});

it('delivers new-namespace events to listeners registered under legacy FQCNs', function () {
    $events = new EventDispatcher();
    $received = [];
    $events->addListener(
        \Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived::class, // legacy name
        static function (object $event) use (&$received): void { $received[] = $event; },
    );

    $events->dispatch(new \Cognesy\Instructor\Events\Streaming\ChunkReceived(['contentLength' => 1]));

    expect($received)->toHaveCount(1);
    expect($received[0])->toBeInstanceOf(\Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived::class);
});

it('reports listener presence across alias boundaries (gating stays correct)', function () {
    $events = new EventDispatcher();
    $events->addListener(
        \Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated::class,
        static fn(object $e) => null,
    );

    expect($events->hasListenersFor(\Cognesy\Instructor\Events\Streaming\PartialResponseGenerated::class))->toBeTrue();
    expect($events->hasListenersFor(\Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated::class))->toBeTrue();
});
