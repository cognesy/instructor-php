<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;
use Cognesy\Http\Exceptions\TimeoutException;

it('dispatches completion events once for streamed responses', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceDelta(contentDelta: 'Hello', finishReason: 'stop'),
        ]],
    );

    $request = (new InferenceRequestBuilder())
        ->withMessages(\Cognesy\Messages\Messages::fromString('Hi'))
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

it('dispatches completion events when a streamed response is fully consumed without calling response()', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceDelta(contentDelta: 'Hello', finishReason: 'stop'),
        ]],
    );

    $request = (new InferenceRequestBuilder())
        ->withMessages(\Cognesy\Messages\Messages::fromString('Hi'))
        ->withStreaming(true)
        ->create();
    $execution = InferenceExecution::fromRequest($request);
    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    foreach ($pending->stream()->deltas() as $delta) {
        expect($delta->contentDelta)->toBe('Hello');
    }

    $attemptSucceeded = array_filter($captured, fn(object $event): bool => $event instanceof InferenceAttemptSucceeded);
    $usageReported = array_filter($captured, fn(object $event): bool => $event instanceof InferenceUsageReported);
    $completed = array_filter($captured, fn(object $event): bool => $event instanceof InferenceCompleted);

    expect(count($attemptSucceeded))->toBe(1);
    expect(count($usageReported))->toBe(1);
    expect(count($completed))->toBe(1);
});

it('does not duplicate completion events when response() is called after full stream consumption', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceDelta(contentDelta: 'Hello', finishReason: 'stop'),
        ]],
    );

    $request = (new InferenceRequestBuilder())
        ->withMessages(\Cognesy\Messages\Messages::fromString('Hi'))
        ->withStreaming(true)
        ->create();
    $execution = InferenceExecution::fromRequest($request);
    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    foreach ($pending->stream()->deltas() as $_) {
    }

    $response = $pending->response();

    $attemptSucceeded = array_filter($captured, fn(object $event): bool => $event instanceof InferenceAttemptSucceeded);
    $usageReported = array_filter($captured, fn(object $event): bool => $event instanceof InferenceUsageReported);
    $completed = array_filter($captured, fn(object $event): bool => $event instanceof InferenceCompleted);

    expect($response->content())->toBe('Hello');
    expect(count($attemptSucceeded))->toBe(1);
    expect(count($usageReported))->toBe(1);
    expect(count($completed))->toBe(1);
});

it('does not duplicate completion events when response() is called after partial stream consumption', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceDelta(contentDelta: 'Hello '),
            new PartialInferenceDelta(contentDelta: 'world', finishReason: 'stop'),
        ]],
    );

    $request = (new InferenceRequestBuilder())
        ->withMessages(\Cognesy\Messages\Messages::fromString('Hi'))
        ->withStreaming(true)
        ->create();
    $execution = InferenceExecution::fromRequest($request);
    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    foreach ($pending->stream()->deltas() as $index => $_) {
        if ($index === 0) {
            break;
        }
    }

    $response = $pending->response();

    $attemptSucceeded = array_filter($captured, fn(object $event): bool => $event instanceof InferenceAttemptSucceeded);
    $usageReported = array_filter($captured, fn(object $event): bool => $event instanceof InferenceUsageReported);
    $completed = array_filter($captured, fn(object $event): bool => $event instanceof InferenceCompleted);

    expect($response->content())->toBe('Hello world');
    expect(count($attemptSucceeded))->toBe(1);
    expect(count($usageReported))->toBe(1);
    expect(count($completed))->toBe(1);
});

it('dispatches failure completion events when a streamed response throws without calling response()', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $driver = new FakeInferenceDriver(
        onStream: function (): iterable {
            yield new PartialInferenceDelta(contentDelta: 'part-1');
            yield new PartialInferenceDelta(contentDelta: 'part-2');
            throw new TimeoutException('stream lost');
        },
    );

    $request = (new InferenceRequestBuilder())
        ->withMessages(\Cognesy\Messages\Messages::fromString('Hi'))
        ->withStreaming(true)
        ->create();
    $execution = InferenceExecution::fromRequest($request);
    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    try {
        foreach ($pending->stream()->deltas() as $_) {
        }
    } catch (TimeoutException) {
    }

    $completed = array_filter($captured, fn(object $event): bool => $event instanceof InferenceCompleted);

    expect(count($completed))->toBe(1);
});

it('does not duplicate failure completion events when response() drains a partially consumed stream', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $driver = new FakeInferenceDriver(
        onStream: function (): iterable {
            yield new PartialInferenceDelta(contentDelta: 'part-1');
            yield new PartialInferenceDelta(contentDelta: 'part-2');
            throw new TimeoutException('stream lost');
        },
    );

    $request = (new InferenceRequestBuilder())
        ->withMessages(\Cognesy\Messages\Messages::fromString('Hi'))
        ->withStreaming(true)
        ->create();
    $execution = InferenceExecution::fromRequest($request);
    $pending = new PendingInference(
        execution: $execution,
        driver: $driver,
        eventDispatcher: $events,
    );

    foreach ($pending->stream()->deltas() as $index => $_) {
        if ($index === 0) {
            break;
        }
    }

    try {
        $pending->response();
        throw new RuntimeException('Expected response() to rethrow stream failure');
    } catch (TimeoutException) {
    }

    $completed = array_filter($captured, fn(object $event): bool => $event instanceof InferenceCompleted);

    expect(count($completed))->toBe(1);
});
