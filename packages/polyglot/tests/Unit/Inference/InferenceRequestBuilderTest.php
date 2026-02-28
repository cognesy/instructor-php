<?php

use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

it('builds request with messages, model, options, streaming, max tokens and mode', function () {
    $b = new InferenceRequestBuilder();
    $req = $b
        ->withMessages('Hello')
        ->withModel('gpt-4o-mini')
        ->withOptions(['temperature' => 0.1])
        ->withStreaming(true)
        ->withMaxTokens(50)
        ->withOutputMode(OutputMode::Json)
        ->create();

    expect($req->messages()[0]['content'])->toBe('Hello');
    expect($req->model())->toBe('gpt-4o-mini');
    expect($req->options()['temperature'] ?? null)->toBe(0.1);
    expect($req->options()['max_tokens'] ?? null)->toBe(50);
    expect($req->isStreamed())->toBeTrue();
    expect($req->outputMode())->toBe(OutputMode::Json);
});

it('applies cached context when set', function () {
    $b = new InferenceRequestBuilder();
    $req = $b
        ->withCachedContext(messages: [['role' => 'system', 'content' => 'You are helpful']])
        ->withMessages('Hi')
        ->create()
        ->withCacheApplied();

    // Cached context prepends system message
    expect($req->messages()[0]['role'])->toBe('system');
});

it('with() does not reset model when model param is null', function () {
    $b = new InferenceRequestBuilder();
    $req = $b
        ->withModel('gpt-4o')
        ->with(messages: 'Hello') // model not specified, should preserve previous
        ->create();

    expect($req->model())->toBe('gpt-4o');
});

it('with() does not reset tools when tools param is null', function () {
    $b = new InferenceRequestBuilder();
    $tools = [['type' => 'function', 'function' => ['name' => 'test', 'parameters' => []]]];
    $req = $b
        ->withTools($tools)
        ->with(messages: 'Hello') // tools not specified, should preserve previous
        ->create();

    expect($req->tools())->toBe($tools);
});

it('with() does not reset options when options param is null', function () {
    $b = new InferenceRequestBuilder();
    $req = $b
        ->withOptions(['temperature' => 0.5])
        ->with(messages: 'Hello') // options not specified, should preserve previous
        ->create();

    expect($req->options()['temperature'])->toBe(0.5);
});

it('with() does not reset outputMode when mode param is null', function () {
    $b = new InferenceRequestBuilder();
    $req = $b
        ->withOutputMode(OutputMode::Json)
        ->with(messages: 'Hello') // mode not specified, should preserve previous
        ->create();

    expect($req->outputMode())->toBe(OutputMode::Json);
});

it('with() allows overriding previously set values', function () {
    $b = new InferenceRequestBuilder();
    $req = $b
        ->withModel('gpt-4o')
        ->withOptions(['temperature' => 0.5])
        ->with(
            messages: 'Hello',
            model: 'claude-3-opus',
            options: ['temperature' => 0.9],
        )
        ->create();

    expect($req->model())->toBe('claude-3-opus');
    expect($req->options()['temperature'])->toBe(0.9);
});

it('with() accepts empty messages array as explicit update', function () {
    $b = new InferenceRequestBuilder();
    $req = $b
        ->withMessages('Hello')
        ->with(messages: [])
        ->create();

    expect($req->messages())->toBe([]);
});

it('with() accepts empty responseFormat array as explicit update', function () {
    $b = new InferenceRequestBuilder();
    $req = $b
        ->withResponseFormat(['type' => 'json_object'])
        ->with(responseFormat: [])
        ->create();

    expect($req->responseFormat()->isEmpty())->toBeTrue();
});
