<?php

use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

it('serializes inference request to a JSON-encodable array', function () {
    $request = (new InferenceRequestBuilder())
        ->withMessages([['role' => 'user', 'content' => 'Hi']])
        ->withResponseFormat([
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'schema',
                'schema' => ['type' => 'object'],
                'strict' => true,
            ],
        ])
        ->create();

    $data = $request->toArray();
    $json = json_encode($data, JSON_THROW_ON_ERROR);

    expect($json)->toBeString();
    expect($data['messages'])->toBeArray();
    expect($data['response_format'])->toBeArray();
});

it('round-trips inference request via toArray/fromArray', function () {
    $request = (new InferenceRequestBuilder())
        ->withMessages([['role' => 'user', 'content' => 'Hi']])
        ->withResponseFormat([
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'schema',
                'schema' => ['type' => 'object'],
                'strict' => true,
            ],
        ])
        ->create();

    $data = $request->toArray();
    $copy = InferenceRequest::fromArray($data);

    expect($copy->messages())->toBe($request->messages());
    expect($copy->responseFormat()->toArray())->toBe($request->responseFormat()->toArray());
});

it('preserves retry policy and cached context across serialization round-trip', function () {
    $request = (new InferenceRequestBuilder())
        ->withMessages([['role' => 'user', 'content' => 'Hi']])
        ->withRetryPolicy(new InferenceRetryPolicy(
            maxAttempts: 7,
            baseDelayMs: 100,
            maxDelayMs: 1200,
            jitter: 'equal',
            retryOnStatus: [408, 429],
            retryOnExceptions: [\RuntimeException::class],
            lengthRecovery: 'continue',
            lengthMaxAttempts: 3,
            lengthContinuePrompt: 'Continue now.',
            maxTokensIncrement: 256,
        ))
        ->withCachedContext(
            messages: [['role' => 'assistant', 'content' => 'Cached reply']],
            tools: [['name' => 'tool1']],
            toolChoice: 'auto',
            responseFormat: ['type' => 'json_object'],
        )
        ->create();

    $copy = InferenceRequest::fromArray($request->toArray());

    expect($copy->retryPolicy())->not()->toBeNull()
        ->and($copy->retryPolicy()?->maxAttempts)->toBe(7)
        ->and($copy->retryPolicy()?->lengthRecovery)->toBe('continue')
        ->and($copy->cachedContext())->not()->toBeNull()
        ->and($copy->cachedContext()?->tools())->toBe([['name' => 'tool1']])
        ->and($copy->cachedContext()?->toolChoice())->toBe('auto')
        ->and($copy->cachedContext()?->responseFormat()->toArray())->toBe(['type' => 'json_object'])
        ->and($copy->cachedContext()?->messages()->toArray()[0]['role'] ?? null)->toBe('assistant')
        ->and($copy->cachedContext()?->messages()->toArray()[0]['content'] ?? null)->toBe('Cached reply');
});
