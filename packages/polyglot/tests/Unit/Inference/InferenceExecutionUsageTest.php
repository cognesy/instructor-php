<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

it('computes usage from finalized attempts only (no double count)', function () {
    $exec = InferenceExecution::fromRequest(new InferenceRequest());

    // First finalized response with usage (2 in, 3 out)
    $exec = $exec->withNewResponse(new InferenceResponse(usage: new Usage(inputTokens: 2, outputTokens: 3)));

    // Second finalized response with usage (5 in, 7 out)
    $exec = $exec->withNewResponse(new InferenceResponse(usage: new Usage(inputTokens: 5, outputTokens: 7)));

    $usage = $exec->usage();

    // Expect sum of finalized attempts only: (2+3) + (5+7) = 17
    expect($usage->input())->toBe(2 + 5)
        ->and($usage->output())->toBe(3 + 7)
        ->and($usage->total())->toBe((2+3) + (5+7));
});

it('includes current attempt usage until finalized', function () {
    $exec = InferenceExecution::fromRequest(new InferenceRequest());

    // One finalized response
    $exec = $exec->withNewResponse(new InferenceResponse(usage: new Usage(inputTokens: 1, outputTokens: 1)));

    // Simulate streaming: add partial responses to current (not finalized) attempt
    $exec = $exec->withNewPartialResponse(new PartialInferenceResponse(usage: new Usage(inputTokens: 2, outputTokens: 3)));
    $exec = $exec->withNewPartialResponse(new PartialInferenceResponse(usage: new Usage(inputTokens: 1, outputTokens: 2)));

    $usageDuring = $exec->usage();
    // Expect finalized (2) + current partials ( (2+3)+(1+2) = 8 ) = 10 total
    expect($usageDuring->input())->toBe(1 + 2 + 1)
        ->and($usageDuring->output())->toBe(1 + 3 + 2)
        ->and($usageDuring->total())->toBe(2 + 8);
});
