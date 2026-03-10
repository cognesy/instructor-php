<?php

use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;

it('applies cached response format when request response format is empty', function () {
    $cachedContext = new CachedInferenceContext(
        responseFormat: ResponseFormat::jsonSchema(['type' => 'object']),
    );

    $request = new InferenceRequest(cachedContext: $cachedContext);
    $applied = $request->withCacheApplied();

    expect($applied->responseFormat()->toArray())->toBe($cachedContext->responseFormat()->toArray());
});

it('keeps request response format when it is not empty', function () {
    $cachedContext = new CachedInferenceContext(responseFormat: ResponseFormat::jsonObject());

    $request = new InferenceRequest(
        responseFormat: ResponseFormat::jsonSchema(['type' => 'object']),
        cachedContext: $cachedContext,
    );
    $applied = $request->withCacheApplied();

    expect($applied->responseFormat()->toArray())->toBe($request->responseFormat()->toArray());
});
