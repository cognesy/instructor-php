<?php declare(strict_types=1);

use Cognesy\Instructor\Collections\StructuredOutputAttemptList;
use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Data\InferenceResponse as PgResp;
use Cognesy\Polyglot\Inference\Data\Usage as PgUsage;

it('reports success for latest finalized attempt (instructor)', function () {
    $attempt = new StructuredOutputAttempt(
        inferenceResponse: new PgResp(usage: new PgUsage(inputTokens: 1, outputTokens: 1)),
        isFinalized: true,
    );

    $exec = new StructuredOutputExecution(
        request: new \Cognesy\Instructor\Data\StructuredOutputRequest(messages: '', requestedSchema: []),
        attemptHistory: StructuredOutputAttemptList::of($attempt),
        status: ExecutionStatus::Succeeded,
    );

    expect($exec->isSuccessful())->toBeTrue()
        ->and($exec->isFinalFailed())->toBeFalse()
        ->and($exec->errors())->toBe([])
        ->and($exec->currentErrors())->toBe([])
        ->and($exec->activeAttempt())->toBeNull()
        ->and($exec->lastFinalizedAttempt())->toBe($attempt);
});

it('reports failure for latest finalized attempt (instructor)', function () {
    $attempt = new StructuredOutputAttempt(
        inferenceResponse: new PgResp(finishReason: 'error'),
        isFinalized: true,
        errors: ['err'],
    );

    $exec = new StructuredOutputExecution(
        request: new \Cognesy\Instructor\Data\StructuredOutputRequest(messages: '', requestedSchema: []),
        attemptHistory: StructuredOutputAttemptList::of($attempt),
        status: ExecutionStatus::Failed,
    );

    expect($exec->isSuccessful())->toBeFalse()
        ->and($exec->isFinalFailed())->toBeTrue()
        ->and($exec->errors())->not()->toBe([])
        ->and($exec->activeAttempt())->toBeNull()
        ->and($exec->lastFinalizedAttempt())->toBe($attempt);
});

it('aggregates current errors and exposes currentErrors() (instructor)', function () {
    $attempt = new StructuredOutputAttempt(isFinalized: false, errors: ['e1']);
    $exec = new StructuredOutputExecution(
        request: new \Cognesy\Instructor\Data\StructuredOutputRequest(messages: '', requestedSchema: []),
        activeAttempt: $attempt,
        status: ExecutionStatus::Running,
    );

    expect($exec->currentErrors())->toBe(['e1'])
        ->and($exec->errors())->toBe(['e1'])
        ->and($exec->isSuccessful())->toBeFalse()
        ->and($exec->isFinalFailed())->toBeFalse();
});
