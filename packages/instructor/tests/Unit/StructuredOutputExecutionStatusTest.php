<?php declare(strict_types=1);

use Cognesy\Instructor\Collections\StructuredOutputAttemptList;
use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Polyglot\Inference\Data\InferenceExecution as PgExec;
use Cognesy\Polyglot\Inference\Data\InferenceRequest as PgReq;
use Cognesy\Polyglot\Inference\Data\InferenceResponse as PgResp;
use Cognesy\Polyglot\Inference\Data\Usage as PgUsage;

it('reports success for latest finalized attempt (instructor)', function () {
    $pg = PgExec::fromRequest(new PgReq());
    $pg = $pg->withNewResponse(new PgResp(usage: new PgUsage(inputTokens: 1, outputTokens: 1)));
    $attempt = new StructuredOutputAttempt(inferenceExecution: $pg, isFinalized: true);

    $exec = new StructuredOutputExecution(
        request: new \Cognesy\Instructor\Data\StructuredOutputRequest(messages: '', requestedSchema: []),
        attempts: StructuredOutputAttemptList::of($attempt),
        currentAttempt: $attempt,
        isFinalized: true,
    );

    expect($exec->isSuccessful())->toBeTrue()
        ->and($exec->isFinalFailed())->toBeFalse()
        ->and($exec->errors())->toBe([])
        ->and($exec->currentErrors())->toBe([]);
});

it('reports failure for latest finalized attempt (instructor)', function () {
    $pg = PgExec::fromRequest(new PgReq());
    $pg = $pg->withFailedResponse(new PgResp(), null, 'err');
    $attempt = new StructuredOutputAttempt(inferenceExecution: $pg, isFinalized: true, errors: ['err']);

    $exec = new StructuredOutputExecution(
        request: new \Cognesy\Instructor\Data\StructuredOutputRequest(messages: '', requestedSchema: []),
        attempts: StructuredOutputAttemptList::of($attempt),
        currentAttempt: $attempt,
        isFinalized: true,
    );

    expect($exec->isSuccessful())->toBeFalse()
        ->and($exec->isFinalFailed())->toBeTrue()
        ->and($exec->errors())->not()->toBe([]);
});

it('aggregates current errors and exposes currentErrors() (instructor)', function () {
    // in‑flight attempt with errors
    $pg = new PgExec(new PgReq());
    $attempt = new StructuredOutputAttempt(inferenceExecution: $pg, isFinalized: false, errors: ['e1']);
    $exec = new StructuredOutputExecution(
        request: new \Cognesy\Instructor\Data\StructuredOutputRequest(messages: '', requestedSchema: []),
        currentAttempt: $attempt,
        isFinalized: false
    );

    expect($exec->currentErrors())->toBe(['e1'])
        ->and($exec->errors())->toBe(['e1'])
        ->and($exec->isSuccessful())->toBeFalse()
        ->and($exec->isFinalFailed())->toBeFalse();
});
