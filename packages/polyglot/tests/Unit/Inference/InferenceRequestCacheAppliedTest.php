<?php

use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

it('applies cached response format when request response format is empty', function () {
    $cachedContext = new CachedInferenceContext(responseFormat: [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'schema',
            'schema' => ['type' => 'object'],
            'strict' => true,
        ],
    ]);

    $request = new InferenceRequest(cachedContext: $cachedContext);
    $applied = $request->withCacheApplied();

    expect($applied->responseFormat()->toArray())->toBe($cachedContext->responseFormat()->toArray());
});

it('keeps request response format when it is not empty', function () {
    $cachedContext = new CachedInferenceContext(responseFormat: [
        'type' => 'json_object',
    ]);

    $request = new InferenceRequest(
        responseFormat: ['type' => 'json_schema', 'schema' => ['type' => 'object']],
        cachedContext: $cachedContext,
    );
    $applied = $request->withCacheApplied();

    expect($applied->responseFormat()->toArray())->toBe($request->responseFormat()->toArray());
});
