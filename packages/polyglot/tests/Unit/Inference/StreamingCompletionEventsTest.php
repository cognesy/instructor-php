<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('dispatches completion events once for streamed responses', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceResponse(contentDelta: 'Hello', finishReason: 'stop'),
        ]],
    );

    $request = (new InferenceRequestBuilder())
        ->withMessages('Hi')
        ->withStreaming(true)
        ->create();
    $execution = InferenceExecution::fromRequest($request);
    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    $pending->response();

    $attemptSucceeded = array_filter($captured, fn(object $event): bool => $event instanceof InferenceAttemptSucceeded);
    $usageReported = array_filter($captured, fn(object $event): bool => $event instanceof InferenceUsageReported);
    $completed = array_filter($captured, fn(object $event): bool => $event instanceof InferenceCompleted);

    expect(count($attemptSucceeded))->toBe(1);
    expect(count($usageReported))->toBe(1);
    expect(count($completed))->toBe(1);
});
