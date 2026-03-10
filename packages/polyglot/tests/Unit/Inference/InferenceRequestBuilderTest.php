<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;

it('builds request with messages, model, options, streaming, max tokens and response format', function () {
    $b = new InferenceRequestBuilder;
    $req = $b
        ->withMessages(Messages::fromString('Hello'))
        ->withModel('gpt-4o-mini')
        ->withOptions(['temperature' => 0.1])
        ->withStreaming(true)
        ->withMaxTokens(50)
        ->withResponseFormat(ResponseFormat::jsonObject())
        ->create();

    expect($req->messages()->toArray()[0]['content'])->toBe('Hello');
    expect($req->model())->toBe('gpt-4o-mini');
    expect($req->options()['temperature'] ?? null)->toBe(0.1);
    expect($req->options()['max_tokens'] ?? null)->toBe(50);
    expect($req->isStreamed())->toBeTrue();
    expect($req->responseFormat()->toArray())->toBe(['type' => 'json_object']);
});

it('applies cached context when set', function () {
    $b = new InferenceRequestBuilder;
    $req = $b
        ->withCachedContext(messages: Messages::fromArray([['role' => 'system', 'content' => 'You are helpful']]))
        ->withMessages(Messages::fromString('Hi'))
        ->create()
        ->withCacheApplied();

    // Cached context prepends system message
    expect($req->messages()->toArray()[0]['role'])->toBe('system');
});

it('with() does not reset model when model param is null', function () {
    $b = new InferenceRequestBuilder;
    $req = $b
        ->withModel('gpt-4o')
        ->with(messages: Messages::fromString('Hello')) // model not specified, should preserve previous
        ->create();

    expect($req->model())->toBe('gpt-4o');
});

it('with() does not reset tools when tools param is null', function () {
    $b = new InferenceRequestBuilder;
    $tools = [['type' => 'function', 'function' => ['name' => 'test', 'parameters' => []]]];
    $req = $b
        ->withTools(ToolDefinitions::fromArray($tools))
        ->with(messages: Messages::fromString('Hello')) // tools not specified, should preserve previous
        ->create();

    expect($req->tools()->toArray())->toBe($tools);
});

it('with() does not reset options when options param is null', function () {
    $b = new InferenceRequestBuilder;
    $req = $b
        ->withOptions(['temperature' => 0.5])
        ->with(messages: Messages::fromString('Hello')) // options not specified, should preserve previous
        ->create();

    expect($req->options()['temperature'])->toBe(0.5);
});

it('with() does not reset response format when responseFormat param is null', function () {
    $b = new InferenceRequestBuilder;
    $req = $b
        ->withResponseFormat(ResponseFormat::jsonObject())
        ->with(messages: Messages::fromString('Hello')) // mode not specified, should preserve previous
        ->create();

    expect($req->responseFormat()->toArray())->toBe(['type' => 'json_object']);
});

it('with() allows overriding previously set values', function () {
    $b = new InferenceRequestBuilder;
    $req = $b
        ->withModel('gpt-4o')
        ->withOptions(['temperature' => 0.5])
        ->with(
            messages: Messages::fromString('Hello'),
            model: 'claude-3-opus',
            options: ['temperature' => 0.9],
        )
        ->create();

    expect($req->model())->toBe('claude-3-opus');
    expect($req->options()['temperature'])->toBe(0.9);
});

it('with() accepts empty messages as explicit update', function () {
    $b = new InferenceRequestBuilder;
    $req = $b
        ->withMessages(Messages::fromString('Hello'))
        ->with(messages: Messages::empty())
        ->create();

    expect($req->messages()->isEmpty())->toBeTrue();
});

it('with() accepts empty response format as explicit update', function () {
    $b = new InferenceRequestBuilder;
    $req = $b
        ->withResponseFormat(ResponseFormat::jsonObject())
        ->with(responseFormat: ResponseFormat::empty())
        ->create();

    expect($req->responseFormat()->isEmpty())->toBeTrue();
});

it('accepts typed tool definitions and tool choice', function () {
    $tools = ToolDefinitions::fromArray([[
        'type' => 'function',
        'function' => [
            'name' => 'weather',
            'parameters' => ['type' => 'object'],
        ],
    ]]);

    $req = (new InferenceRequestBuilder)
        ->withTools($tools)
        ->withToolChoice(ToolChoice::auto())
        ->create();

    expect($req->tools())->toBe($tools)
        ->and($req->toolChoice())->toEqual(ToolChoice::auto());
});

it('accepts typed response format objects via builder methods', function () {
    $format = ResponseFormat::jsonSchema(
        schema: ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']]],
        name: 'answer_schema',
        strict: true,
    );

    $req = (new InferenceRequestBuilder)
        ->withResponseFormat($format)
        ->create();

    expect($req->responseFormat())->toBe($format);
});

it('accepts typed messages and response format in cached context', function () {
    $cachedMessages = Messages::fromArray([['role' => 'system', 'content' => 'You are helpful']]);
    $cachedFormat = ResponseFormat::jsonObject();

    $req = (new InferenceRequestBuilder)
        ->withCachedContext(
            messages: $cachedMessages,
            responseFormat: $cachedFormat,
        )
        ->create();

    expect($req->cachedContext())->not()->toBeNull()
        ->and($req->cachedContext()?->messages())->toEqual($cachedMessages)
        ->and($req->cachedContext()?->responseFormat())->toBe($cachedFormat);
});
