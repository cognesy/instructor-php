<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiRequestAdapter;

it('maps InferenceRequest to Gemini HttpRequest correctly (non-stream)', function () {
    $config = new LLMConfig(
        apiUrl: 'https://generativelanguage.googleapis.com/v1beta',
        apiKey: 'KEY',
        endpoint: '/models/{model}:generateContent',
        model: 'gemini-1.5-flash',
        driver: 'gemini'
    );
    $adapter = new GeminiRequestAdapter(
        $config,
        new GeminiBodyFormat($config, new GeminiMessageFormat())
    );

    $req = new InferenceRequest(messages: Messages::fromString('Hello'), options: ['stream' => false]);
    $http = $adapter->toHttpRequest($req);

    expect($http->method())->toBe('POST');
    expect($http->url())->toContain('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent');
    expect($http->headers())->toHaveKey('x-goog-api-key');
    expect($http->headers()['x-goog-api-key'])->toBe('KEY');
    expect($http->isStreamed())->toBeFalse();
});

it('maps InferenceRequest to Gemini HttpRequest correctly (stream)', function () {
    $config = new LLMConfig(
        apiUrl: 'https://generativelanguage.googleapis.com/v1beta',
        apiKey: 'KEY',
        endpoint: '/models/{model}:generateContent',
        model: 'gemini-1.5-flash',
        driver: 'gemini'
    );
    $adapter = new GeminiRequestAdapter(
        $config,
        new GeminiBodyFormat($config, new GeminiMessageFormat())
    );

    $req = new InferenceRequest(messages: Messages::fromString('Hello'), options: ['stream' => true]);
    $http = $adapter->toHttpRequest($req);

    expect($http->url())->toContain(':streamGenerateContent');
    expect($http->url())->toContain('alt=sse');
    expect($http->isStreamed())->toBeTrue();
});

it('uses configured endpoint template for non-stream requests', function () {
    $config = new LLMConfig(
        apiUrl: 'https://proxy.example.com',
        apiKey: 'KEY',
        endpoint: '/google/{model}:generateContent',
        model: 'gemini-1.5-flash',
        driver: 'gemini'
    );
    $adapter = new GeminiRequestAdapter(
        $config,
        new GeminiBodyFormat($config, new GeminiMessageFormat())
    );

    $req = new InferenceRequest(messages: Messages::fromString('Hello'), options: ['stream' => false]);
    $http = $adapter->toHttpRequest($req);

    expect($http->url())->toBe('https://proxy.example.com/google/gemini-1.5-flash:generateContent');
});

it('derives stream endpoint from configured Gemini endpoint template', function () {
    $config = new LLMConfig(
        apiUrl: 'https://proxy.example.com',
        apiKey: 'KEY',
        endpoint: '/google/{model}:generateContent',
        model: 'gemini-1.5-flash',
        driver: 'gemini'
    );
    $adapter = new GeminiRequestAdapter(
        $config,
        new GeminiBodyFormat($config, new GeminiMessageFormat())
    );

    $req = new InferenceRequest(messages: Messages::fromString('Hello'), options: ['stream' => true]);
    $http = $adapter->toHttpRequest($req);

    expect($http->url())->toBe('https://proxy.example.com/google/gemini-1.5-flash:streamGenerateContent?alt=sse');
});
