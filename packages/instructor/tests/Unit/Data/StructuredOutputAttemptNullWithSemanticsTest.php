<?php declare(strict_types=1);

use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

it('keeps existing fields when with() receives null', function () {
    $response = new InferenceResponse(content: '{"ok":true}');
    $attempt = new StructuredOutputAttempt(
        inferenceResponse: $response,
        isFinalized: true,
        errors: ['boom'],
        output: ['ok' => true],
    );

    $updated = $attempt->with(
        inferenceResponse: null,
        usage: null,
        isFinalized: null,
        errors: null,
        output: null,
    );

    expect($updated->inferenceResponse())->toBe($response)
        ->and($updated->isFinalized())->toBeTrue()
        ->and($updated->errors())->toBe(['boom'])
        ->and($updated->output())->toBe(['ok' => true]);
});
