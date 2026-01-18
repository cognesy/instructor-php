<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\CachedContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

it('returns false when request and cached context have no messages', function () {
    $request = new InferenceRequest();

    expect($request->hasMessages())->toBeFalse();
});

it('returns true when request has messages', function () {
    $request = new InferenceRequest(messages: Messages::fromString('Hello'));

    expect($request->hasMessages())->toBeTrue();
});

it('returns true when cached context has messages', function () {
    $cachedContext = new CachedContext(messages: [['role' => 'system', 'content' => 'Cached']]);
    $request = new InferenceRequest(messages: null, cachedContext: $cachedContext);

    expect($request->hasMessages())->toBeTrue();
});
