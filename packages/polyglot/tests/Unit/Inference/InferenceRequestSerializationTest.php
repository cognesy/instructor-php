<?php

use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
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
