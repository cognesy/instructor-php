<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

it('round-trips started attempt without synthetic response', function () {
    $attempt = InferenceAttempt::started();

    $rehydrated = InferenceAttempt::fromArray($attempt->toArray());

    expect($rehydrated->response())->toBeNull()
        ->and($rehydrated->isFinalized())->toBeFalse();
});

it('round-trips execution with null current attempt without synthesizing attempt', function () {
    $execution = new InferenceExecution(
        request: new InferenceRequest(),
        attempts: null,
        currentAttempt: null,
        isFinalized: false,
    );

    $rehydrated = InferenceExecution::fromArray($execution->toArray());

    expect($rehydrated->currentAttempt())->toBeNull()
        ->and($rehydrated->response())->toBeNull();
});

it('hydrates inference response safely when responseData key is missing', function () {
    $response = InferenceResponse::fromArray([
        'content' => 'ok',
        'finishReason' => 'stop',
    ]);

    expect($response->responseData()->statusCode())->toBe(0)
        ->and($response->responseData()->isStreamed())->toBeFalse();
});
