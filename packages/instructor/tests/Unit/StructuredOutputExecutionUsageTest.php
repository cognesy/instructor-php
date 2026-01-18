<?php declare(strict_types=1);

use Cognesy\Instructor\Collections\StructuredOutputAttemptList;
use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Polyglot\Inference\Data\InferenceExecution as PgInferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest as PgInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse as PgInferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse as PgPartial;
use Cognesy\Polyglot\Inference\Data\Usage as PgUsage;

it('accumulates usage for synchronous finalized attempts only', function () {
    // Build a finalized Polyglot execution with usage 2 in, 3 out
    $pg = PgInferenceExecution::fromRequest(new PgInferenceRequest());
    $pg = $pg->withSuccessfulAttempt(new PgInferenceResponse(usage: new PgUsage(inputTokens: 2, outputTokens: 3)));

    $attempt = new StructuredOutputAttempt(
        inferenceExecution: $pg,
        isFinalized: true,
    );

    $exec = new StructuredOutputExecution(
        request: new \Cognesy\Instructor\Data\StructuredOutputRequest(messages: '', requestedSchema: []),
        attempts: StructuredOutputAttemptList::of($attempt),
        currentAttempt: $attempt,
        isFinalized: true,
    );

    $usage = $exec->usage();
    expect($usage->input())->toBe(2)
        ->and($usage->output())->toBe(3)
        ->and($usage->total())->toBe(5);
});

it('accumulates usage for finalized attempts plus current partials until finalized', function () {
    // Finalized previous attempt: 1 in, 1 out
    $pg1 = PgInferenceExecution::fromRequest(new PgInferenceRequest());
    $pg1 = $pg1->withSuccessfulAttempt(new PgInferenceResponse(usage: new PgUsage(inputTokens: 1, outputTokens: 1)));
    $attempt1 = new StructuredOutputAttempt(
        inferenceExecution: $pg1,
        isFinalized: true,
    );

    // Current in-progress attempt with partials: (2,3) and (1,2)
    $pg2 = PgInferenceExecution::fromRequest(new PgInferenceRequest());
    $pg2 = $pg2->withNewPartialResponse(new PgPartial(usage: new PgUsage(inputTokens: 2, outputTokens: 3)));
    $pg2 = $pg2->withNewPartialResponse(new PgPartial(usage: new PgUsage(inputTokens: 1, outputTokens: 2)));
    $attemptCurrent = new StructuredOutputAttempt(
        inferenceExecution: $pg2,
        isFinalized: false,
    );

    $exec = new StructuredOutputExecution(
        request: new \Cognesy\Instructor\Data\StructuredOutputRequest(messages: '', requestedSchema: []),
        attempts: StructuredOutputAttemptList::of($attempt1, $attemptCurrent),
        currentAttempt: $attemptCurrent,
        isFinalized: false,
    );

    $usage = $exec->usage();
    // Expect finalized (2 total) + current partials (8 total) = 10 total; input 4, output 6
    expect($usage->input())->toBe(1 + 2 + 1)
        ->and($usage->output())->toBe(1 + 3 + 2)
        ->and($usage->total())->toBe(10);
});
