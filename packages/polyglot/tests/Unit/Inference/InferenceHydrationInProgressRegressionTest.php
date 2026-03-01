<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
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

it('round-trips in-progress attempt with accumulated partial state', function () {
    $p1 = (new PartialInferenceResponse(
        contentDelta: 'Hel',
        toolName: 'search',
        toolArgs: '{"q":"hel',
        usage: new Usage(inputTokens: 1, outputTokens: 1),
    ))->withAccumulatedContent(PartialInferenceResponse::empty());
    $accumulated = (new PartialInferenceResponse(
        contentDelta: 'lo',
        toolArgs: 'lo"}',
        usage: new Usage(inputTokens: 1, outputTokens: 1),
    ))->withAccumulatedContent($p1);

    $attempt = InferenceAttempt::started()->withNewPartialResponse($accumulated);
    $rehydrated = InferenceAttempt::fromArray($attempt->toArray());

    expect($rehydrated->partialResponse())->not->toBeNull()
        ->and($rehydrated->partialResponse()?->content())->toBe('Hello')
        ->and($rehydrated->partialResponse()?->toolCalls()->count())->toBe(1)
        ->and($rehydrated->partialResponse()?->toolCalls()->first()?->name())->toBe('search')
        ->and($rehydrated->partialResponse()?->toolCalls()->first()?->value('q'))->toBe('hello')
        ->and($rehydrated->usage()->input())->toBe(2)
        ->and($rehydrated->usage()->output())->toBe(2);

    $finalized = $rehydrated->withFinalizedPartialResponse();
    expect($finalized->response())->not->toBeNull()
        ->and($finalized->response()?->content())->toBe('Hello')
        ->and($finalized->response()?->toolCalls()->count())->toBe(1)
        ->and($finalized->response()?->toolCalls()->first()?->value('q'))->toBe('hello');
});
