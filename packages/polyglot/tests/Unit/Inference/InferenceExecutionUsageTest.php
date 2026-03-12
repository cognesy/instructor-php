<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceExecutionId;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;

it('computes usage from finalized attempts only (no double count)', function () {
    $exec = InferenceExecution::fromRequest(new InferenceRequest());

    // First finalized response with usage (2 in, 3 out)
    $exec = $exec->withSuccessfulAttempt(new InferenceResponse(usage: new InferenceUsage(inputTokens: 2, outputTokens: 3)));

    // Second finalized response with usage (5 in, 7 out)
    $exec = $exec->withSuccessfulAttempt(new InferenceResponse(usage: new InferenceUsage(inputTokens: 5, outputTokens: 7)));

    $usage = $exec->usage();

    // Expect sum of finalized attempts only: (2+3) + (5+7) = 17
    expect($usage->input())->toBe(2 + 5)
        ->and($usage->output())->toBe(3 + 7)
        ->and($usage->total())->toBe((2+3) + (5+7));
});

it('includes current attempt usage until finalized', function () {
    $exec = InferenceExecution::fromRequest(new InferenceRequest());

    // One finalized response
    $exec = $exec->withSuccessfulAttempt(new InferenceResponse(usage: new InferenceUsage(inputTokens: 1, outputTokens: 1)));

    $currentAttempt = new \Cognesy\Polyglot\Inference\Data\InferenceAttempt(
        response: null,
        usage: new InferenceUsage(inputTokens: 3, outputTokens: 5),
        isFinalized: false,
        errors: [],
    );
    $exec = new InferenceExecution(
        request: new InferenceRequest(),
        attempts: $exec->attempts(),
        currentAttempt: $currentAttempt,
        isFinalized: false,
        id: $exec->id,
        createdAt: $exec->createdAt,
        updatedAt: $exec->updatedAt,
    );

    $usageDuring = $exec->usage();
    expect($usageDuring->input())->toBe(1 + 3)
        ->and($usageDuring->output())->toBe(1 + 5)
        ->and($usageDuring->total())->toBe(10);
});

it('uses typed execution id and serializes it to string', function () {
    $exec = InferenceExecution::fromRequest(new InferenceRequest());
    $array = $exec->toArray();

    expect($exec->id)->toBeInstanceOf(InferenceExecutionId::class)
        ->and($array['id'])->toBeString()
        ->and($array['id'])->toBe($exec->id->toString());
});
