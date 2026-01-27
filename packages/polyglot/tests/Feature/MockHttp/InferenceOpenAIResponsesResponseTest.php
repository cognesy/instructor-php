<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('returns content for OpenAI Responses API (non-streaming)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/responses')
        ->withJsonSubset([
            'model' => 'gpt-4o-mini',
        ])
        ->replyJson([
            'id' => 'resp_test',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Hi there!',
                        ],
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 3,
                'completion_tokens' => 2,
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $content = (new Inference())
        ->withHttpClient($http)
        ->using('openai-responses')
        ->withModel('gpt-4o-mini')
        ->withMessages('Hello')
        ->get();

    expect($content)->toBe('Hi there!');
});

it('extracts system messages to instructions field', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/responses')
        ->withJsonSubset([
            'instructions' => 'You are a helpful assistant.',
        ])
        ->replyJson([
            'id' => 'resp_test',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Hello!']],
                ],
            ],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $content = (new Inference())
        ->withHttpClient($http)
        ->using('openai-responses')
        ->withModel('gpt-4o-mini')
        ->withMessages([
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Hi'],
        ])
        ->get();

    expect($content)->toBe('Hello!');
});

it('uses max_output_tokens instead of max_tokens', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/responses')
        // Verify request contains max_output_tokens (not max_tokens) via body check
        ->body(function(string $body) {
            $decoded = json_decode($body, true);
            // Must have max_output_tokens
            expect(array_key_exists('max_output_tokens', $decoded))->toBeTrue();
            // Must NOT have max_tokens
            expect(array_key_exists('max_tokens', $decoded))->toBeFalse();
            return true;
        })
        ->replyJson([
            'id' => 'resp_test',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Response']],
                ],
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $content = (new Inference())
        ->withHttpClient($http)
        ->using('openai-responses')
        ->withModel('gpt-4o-mini')
        ->withMessages('Hello')
        ->get();

    expect($content)->toBe('Response');
});

it('maps completed status to stop finish reason', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/responses')
        ->replyJson([
            'id' => 'resp_test',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Done!']],
                ],
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $response = (new Inference())
        ->withHttpClient($http)
        ->using('openai-responses')
        ->withModel('gpt-4o-mini')
        ->withMessages('Hello')
        ->response();

    expect($response->finishReason()->value)->toBe('stop');
});

it('maps incomplete status to length finish reason and throws', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/responses')
        ->replyJson([
            'id' => 'resp_test',
            'status' => 'incomplete',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Partial...']],
                ],
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    // incomplete status → length finish reason → treated as failure → exception
    expect(fn() => (new Inference())
        ->withHttpClient($http)
        ->using('openai-responses')
        ->withModel('gpt-4o-mini')
        ->withMessages('Write a very long story')
        ->response()
    )->toThrow(\RuntimeException::class, 'Inference execution failed: length');
});

it('extracts usage information', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/responses')
        ->replyJson([
            'id' => 'resp_test',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Hello']],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $response = (new Inference())
        ->withHttpClient($http)
        ->using('openai-responses')
        ->withModel('gpt-4o-mini')
        ->withMessages('Hi')
        ->response();

    expect($response->usage()->inputTokens)->toBe(10);
    expect($response->usage()->outputTokens)->toBe(5);
});
