<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\StructuredOutputRecoveryLimitReached;
use Cognesy\Instructor\Exceptions\StructuredOutputRecoveryException;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Tests\Addons\Support\FakeInferenceRequestDriver;

// Minimal DTOs
class RetryFailSyncUser { public int $age; }
class RetryFailStreamUser { public int $age; }

it('emits retry events and throws after max retries (sync)', function () {
    // Two failing responses (no JSON)
    $driver = new FakeInferenceRequestDriver(
        responses: [
            new InferenceResponse(content: 'not json 1'),
            new InferenceResponse(content: 'not json 2'),
        ],
    );

    $events = new EventDispatcher();
    $attempts = 0;
    $limitReached = 0;
    $events->addListener(NewValidationRecoveryAttempt::class, function () use (&$attempts) { $attempts++; });
    $events->addListener(StructuredOutputRecoveryLimitReached::class, function () use (&$limitReached) { $limitReached++; });

    $so = (new StructuredOutput())
        ->withEventHandler($events)
        ->withDriver($driver)
        ->withMessages('sync failure')
        ->withResponseClass(RetryFailSyncUser::class)
        ->withOutputMode(OutputMode::Json)
        ->withMaxRetries(1);

    expect(function () use ($so) { $so->getObject(); })
        ->toThrow(StructuredOutputRecoveryException::class);

    // One retry attempt emitted, limit reached once
    expect($attempts)->toBe(1);
    expect($limitReached)->toBe(1);
});

it('emits retry events and throws after max retries (streaming)', function () {
    // Two failing streaming attempts: no deltas in both batches
    $batch1 = [];
    $batch2 = [];
    $driver = new FakeInferenceRequestDriver(
        responses: [],
        streamBatches: [ $batch1, $batch2 ],
    );

    $events = new EventDispatcher();
    $attempts = 0;
    $limitReached = 0;
    $events->addListener(NewValidationRecoveryAttempt::class, function () use (&$attempts) { $attempts++; });
    $events->addListener(StructuredOutputRecoveryLimitReached::class, function () use (&$limitReached) { $limitReached++; });

    $so = (new StructuredOutput())
        ->withEventHandler($events)
        ->withDriver($driver)
        ->withMessages('stream failure')
        ->withResponseClass(RetryFailStreamUser::class)
        ->withOutputMode(OutputMode::Json)
        ->withMaxRetries(1)
        ->withStreaming();

    expect(function () use ($so) { $so->getObject(); })
        ->toThrow(StructuredOutputRecoveryException::class);

    // One retry attempt emitted, limit reached once
    expect($attempts)->toBe(1);
    expect($limitReached)->toBe(1);
});

