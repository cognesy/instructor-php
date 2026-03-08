<?php declare(strict_types=1);

use Cognesy\Instructor\Collections\StructuredOutputAttemptList;
use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Data\InferenceResponse as PgInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage as PgUsage;

it('accumulates usage for synchronous finalized attempts only', function () {
    $attempt = new StructuredOutputAttempt(
        inferenceResponse: new PgInferenceResponse(usage: new PgUsage(inputTokens: 2, outputTokens: 3)),
        isFinalized: true,
    );

    $exec = new StructuredOutputExecution(
        request: new \Cognesy\Instructor\Data\StructuredOutputRequest(messages: '', requestedSchema: []),
        attemptHistory: StructuredOutputAttemptList::of($attempt),
        status: ExecutionStatus::Succeeded,
    );

    $usage = $exec->usage();
    expect($usage->input())->toBe(2)
        ->and($usage->output())->toBe(3)
        ->and($usage->total())->toBe(5);
});

it('accumulates usage for finalized attempts plus current in-flight usage until finalized', function () {
    $attempt1 = new StructuredOutputAttempt(
        inferenceResponse: new PgInferenceResponse(usage: new PgUsage(inputTokens: 1, outputTokens: 1)),
        isFinalized: true,
    );

    $attemptCurrent = new StructuredOutputAttempt(
        usage: new PgUsage(inputTokens: 3, outputTokens: 5),
        isFinalized: false,
    );

    $exec = new StructuredOutputExecution(
        request: new \Cognesy\Instructor\Data\StructuredOutputRequest(messages: '', requestedSchema: []),
        attemptHistory: StructuredOutputAttemptList::of($attempt1),
        activeAttempt: $attemptCurrent,
        status: ExecutionStatus::Running,
    );

    $usage = $exec->usage();
    expect($usage->input())->toBe(4)
        ->and($usage->output())->toBe(6)
        ->and($usage->total())->toBe(10);
});

it('counts usage from failed attempts recorded via withFailedAttempt', function () {
    $exec = (new StructuredOutputExecution(
        request: new \Cognesy\Instructor\Data\StructuredOutputRequest(messages: '', requestedSchema: []),
    ))->withFailedAttempt(
        inferenceResponse: new PgInferenceResponse(
            content: 'bad json',
            finishReason: 'error',
            usage: new PgUsage(inputTokens: 4, outputTokens: 6),
        ),
        errors: ['Validation failed'],
    );

    expect($exec->attemptCount())->toBe(1);
    expect($exec->attempts()->last()?->isFinalized())->toBeTrue();

    $usage = $exec->usage();
    expect($usage->input())->toBe(4)
        ->and($usage->output())->toBe(6)
        ->and($usage->total())->toBe(10);
});
