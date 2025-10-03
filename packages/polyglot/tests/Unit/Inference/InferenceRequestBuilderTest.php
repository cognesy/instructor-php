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

