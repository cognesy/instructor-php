<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

it('returns content for OpenAI chat completions (non-streaming)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withJsonSubset([
            'model' => 'gpt-4o-mini',
        ])
        ->replyJson([
            'id' => 'cmpl_test',
            'choices' => [
                ['message' => ['content' => 'Hi there!'], 'finish_reason' => 'stop']
            ],
            'usage' => [
                'prompt_tokens' => 3,
                'completion_tokens' => 2,
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $content = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::fromConfig(\Cognesy\Polyglot\Tests\Support\TestConfig::llm('openai'), httpClient: $http))
        ->withModel('gpt-4o-mini')
        ->withMessages(\Cognesy\Messages\Messages::fromString('Hello'))
        ->get();

    expect($content)->toBe('Hi there!');
});

it('supports runtime-style create with explicit request', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withJsonSubset([
            'model' => 'gpt-4o-mini',
        ])
        ->replyJson([
            'id' => 'cmpl_test',
            'choices' => [
                ['message' => ['content' => 'Hi from request!'], 'finish_reason' => 'stop']
            ],
            'usage' => [
                'prompt_tokens' => 3,
                'completion_tokens' => 3,
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $content = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::fromConfig(\Cognesy\Polyglot\Tests\Support\TestConfig::llm('openai'), httpClient: $http))
        ->create(new InferenceRequest(
            messages: Messages::fromString('Hello'),
            model: 'gpt-4o-mini',
        ))
        ->get();

    expect($content)->toBe('Hi from request!');
});

it('supports facade runtime extraction and runtime static factories', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withJsonSubset(['model' => 'gpt-4o-mini'])
        ->times(1)
        ->replyJson([
            'id' => 'cmpl_test',
            'choices' => [
                ['message' => ['content' => 'Hi from runtime!'], 'finish_reason' => 'stop']
            ],
            'usage' => [
                'prompt_tokens' => 3,
                'completion_tokens' => 3,
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $request = new InferenceRequest(
        messages: Messages::fromString('Hello'),
        model: 'gpt-4o-mini',
    );

    $runtime = \Cognesy\Polyglot\Inference\InferenceRuntime::fromConfig(
        \Cognesy\Polyglot\Tests\Support\TestConfig::llm('openai'),
        httpClient: $http,
    );
    $fromFacadeRuntime = Inference::fromRuntime($runtime)
        ->create($request)
        ->get();

    expect($fromFacadeRuntime)->toBe('Hi from runtime!');

    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withJsonSubset(['model' => 'gpt-4o-mini'])
        ->times(1)
        ->replyJson([
            'id' => 'cmpl_test',
            'choices' => [
                ['message' => ['content' => 'Hi from static runtime!'], 'finish_reason' => 'stop']
            ],
            'usage' => [
                'prompt_tokens' => 3,
                'completion_tokens' => 3,
            ],
        ]);

    $fromStaticRuntime = InferenceRuntime::fromConfig(
        config: \Cognesy\Polyglot\Tests\Support\TestConfig::llm('openai'),
        httpClient: $http,
    )->create($request)->get();

    expect($fromStaticRuntime)->toBe('Hi from static runtime!');
});
