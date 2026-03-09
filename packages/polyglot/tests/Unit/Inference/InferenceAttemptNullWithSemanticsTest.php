<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

it('keeps existing nullable fields when with() receives null', function () {
    $response = new InferenceResponse(content: 'ok', usage: new Usage(inputTokens: 2, outputTokens: 3));
    $attempt = new InferenceAttempt(
        response: $response,
        usage: new Usage(inputTokens: 5, outputTokens: 7),
        isFinalized: true,
        errors: ['boom'],
    );

    $updated = $attempt->with(
        response: null,
        usage: null,
        isFinalized: null,
        errors: null,
    );

    expect($updated->response())->toBe($response)
        ->and($updated->usage()->total())->toBe(12)
        ->and($updated->isFinalized())->toBeTrue()
        ->and($updated->errors())->toBe(['boom']);
});
