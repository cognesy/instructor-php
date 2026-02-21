<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequestId;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

it('accepts retry and cache policies via constructor named args', function () {
    $retryPolicy = new InferenceRetryPolicy(maxAttempts: 3);
    $request = new InferenceRequest(
        messages: 'Hello',
        retryPolicy: $retryPolicy,
        responseCachePolicy: ResponseCachePolicy::None,
    );

    expect($request->retryPolicy())->toBe($retryPolicy);
    expect($request->responseCachePolicy())->toBe(ResponseCachePolicy::None);
});

it('uses default retry and cache policies when constructor args are omitted', function () {
    $request = new InferenceRequest(messages: 'Hello');

    expect($request->retryPolicy())->toBeNull();
    expect($request->responseCachePolicy())->toBe(ResponseCachePolicy::Memory);
});

it('uses typed request id', function () {
    $request = new InferenceRequest(messages: 'Hello');

    expect($request->id())->toBeInstanceOf(InferenceRequestId::class);
});
