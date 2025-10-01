<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\Usage;

it('reports success for latest finalized attempt', function () {
    $exec = InferenceExecution::fromRequest(new InferenceRequest());
    $exec = $exec->withNewResponse(new InferenceResponse(usage: new Usage(inputTokens: 1, outputTokens: 1)));

    expect($exec->isSuccessful())->toBeTrue()
        ->and($exec->isFailedFinal())->toBeFalse()
        ->and($exec->hasErrors())->toBeFalse()
        ->and($exec->errors())->toBe([]);
});

it('reports failure for latest finalized attempt', function () {
    $exec = InferenceExecution::fromRequest(new InferenceRequest());
    $exec = $exec->withFailedResponse(
        response: new InferenceResponse(usage: new Usage()),
        partialResponses: null,
        errors: 'boom'
    );

    expect($exec->isSuccessful())->toBeFalse()
        ->and($exec->isFailedFinal())->toBeTrue()
        ->and($exec->hasErrors())->toBeTrue()
        ->and($exec->errors())->not()->toBe([]);
});

it('aggregates current errors and exposes currentErrors()', function () {
    // Create an in-flight attempt with errors (not finalized)
    $attempt = new InferenceAttempt(
        response: null,
        partialResponses: null,
        isFinalized: false,
        errors: ['e1']
    );
    $exec = new InferenceExecution(
        request: new InferenceRequest(),
        attempts: null,
        currentAttempt: $attempt,
        isFinalized: false,
    );

    expect($exec->currentErrors())->toBe(['e1'])
        ->and($exec->errors())->toBe(['e1'])
        ->and($exec->hasErrors())->toBeTrue()
        ->and($exec->isSuccessful())->toBeFalse()
        ->and($exec->isFailedFinal())->toBeFalse();
});
