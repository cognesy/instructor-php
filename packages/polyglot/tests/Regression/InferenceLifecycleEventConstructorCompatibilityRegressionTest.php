<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;

it('preserves legacy array construction for typed lifecycle events', function () {
    $events = [
        new InferenceStarted(['executionId' => 'legacy-started', 'custom' => 'kept']),
        new InferenceAttemptSucceeded(['executionId' => 'legacy-succeeded', 'custom' => 'kept']),
        new InferenceAttemptFailed(['executionId' => 'legacy-failed', 'custom' => 'kept']),
        new InferenceUsageReported(['executionId' => 'legacy-usage', 'custom' => 'kept']),
        new InferenceCompleted(['executionId' => 'legacy-completed', 'custom' => 'kept']),
    ];

    foreach ($events as $event) {
        expect($event->data)->toMatchArray(['custom' => 'kept']);
    }
});

it('builds the same payload through legacy constructors and typed factories', function () {
    $usage = new InferenceUsage(inputTokens: 10, outputTokens: 4);
    $cases = [
        [
            InferenceStarted::fromLifecycle('exec-1', 'req-1', false, 'model-1', 2, ['custom' => 'value']),
            new InferenceStarted([
                'executionId' => 'exec-1',
                'requestId' => 'req-1',
                'isStreamed' => false,
                'model' => 'model-1',
                'messageCount' => 2,
                'custom' => 'value',
            ]),
        ],
        [
            InferenceAttemptSucceeded::fromLifecycle('exec-1', 'attempt-1', 1, 'stop', 12.5, $usage, ['custom' => 'value']),
            new InferenceAttemptSucceeded([
                'executionId' => 'exec-1',
                'attemptId' => 'attempt-1',
                'attemptNumber' => 1,
                'finishReason' => 'stop',
                'durationMs' => 12.5,
                ...$usage->toTokenCounts(),
                'custom' => 'value',
            ]),
        ],
        [
            InferenceAttemptFailed::fromLifecycle('exec-1', 'attempt-1', 1, 'failed', RuntimeException::class, 500, false, 8.5, ['custom' => 'value']),
            new InferenceAttemptFailed([
                'executionId' => 'exec-1',
                'attemptId' => 'attempt-1',
                'attemptNumber' => 1,
                'errorMessage' => 'failed',
                'errorType' => RuntimeException::class,
                'httpStatusCode' => 500,
                'willRetry' => false,
                'durationMs' => 8.5,
                'custom' => 'value',
            ]),
        ],
        [
            InferenceUsageReported::fromLifecycle('exec-1', 'model-1', true, $usage, ['custom' => 'value']),
            new InferenceUsageReported([
                'executionId' => 'exec-1',
                'model' => 'model-1',
                'isFinal' => true,
                ...$usage->toTokenCounts(),
                'custom' => 'value',
            ]),
        ],
        [
            InferenceCompleted::fromLifecycle('exec-1', true, 'stop', 23.0, 1, $usage, ['custom' => 'value']),
            new InferenceCompleted([
                'executionId' => 'exec-1',
                'isSuccess' => true,
                'finishReason' => 'stop',
                'durationMs' => 23.0,
                'attemptCount' => 1,
                ...$usage->toTokenCounts(),
                'custom' => 'value',
            ]),
        ],
    ];

    foreach ($cases as [$typed, $legacy]) {
        expect($typed->data)->toEqual($legacy->data);
    }
});
