<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

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

it('failed attempt without response preserves durable usage metadata', function () {
    $state = new \Cognesy\Polyglot\Inference\Streaming\InferenceStreamState();
    $state->applyDelta(new \Cognesy\Polyglot\Inference\Data\PartialInferenceDelta(
        contentDelta: 'Hel',
        toolName: 'search',
        toolArgs: '{"q":"hel',
        usage: new Usage(inputTokens: 1, outputTokens: 1),
    ));
    $state->applyDelta(new \Cognesy\Polyglot\Inference\Data\PartialInferenceDelta(
        contentDelta: 'lo',
        toolArgs: 'lo"}',
        usage: new Usage(inputTokens: 1, outputTokens: 1),
    ));
    $response = $state->finalResponse();

    $failed = InferenceAttempt::fromFailedResponse(
        response: null,
        usage: $state->usage(),
        errors: ['boom'],
    );
    $rehydratedFailed = InferenceAttempt::fromArray($failed->toArray());

    expect($rehydratedFailed->response())->toBeNull()
        ->and($rehydratedFailed->usage()->input())->toBe(2)
        ->and($rehydratedFailed->usage()->output())->toBe(2)
        ->and($rehydratedFailed->errors())->toBe(['boom']);

    $completed = InferenceAttempt::fromResponse($response);
    $rehydratedCompleted = InferenceAttempt::fromArray($completed->toArray());
    expect($rehydratedCompleted->response())->not->toBeNull()
        ->and($rehydratedCompleted->response()?->content())->toBe('Hello')
        ->and($rehydratedCompleted->response()?->toolCalls()->count())->toBe(1)
        ->and($rehydratedCompleted->response()?->toolCalls()->first()?->value('q'))->toBe('hello')
        ->and($rehydratedCompleted->usage()->input())->toBe(2)
        ->and($rehydratedCompleted->usage()->output())->toBe(2);
});
