<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('handles tool call during streaming for Gemini', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post(null)
        ->urlStartsWith('https://generativelanguage.googleapis.com/v1beta')
        ->withStream(true)
        ->replySSEFromJson([
            // Some text chunk first
            ['candidates' => [[ 'content' => ['parts' => [['text' => 'Thinking...']]], 'finishReason' => '' ]]],
            // Tool call chunk (single consolidated args JSON to produce correct final)
            ['candidates' => [[
                'content' => ['parts' => [[ 'functionCall' => ['name' => 'search', 'args' => ['q' => 'Hello']] ]]],
                'finishReason' => 'STOP',
            ]]],
        ], addDone: true);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $final = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::fromConfig(\Cognesy\Polyglot\Tests\Support\TestConfig::llm('gemini'), httpClient: $http))
        ->withModel('gemini-1.5-flash')
        ->withMessages(\Cognesy\Messages\Messages::fromString('Search'))
        ->withStreaming(true)
        ->stream()
        ->final();

    expect($final)->not->toBeNull();
    expect($final->hasToolCalls())->toBeTrue();
    $tool = $final->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});

it('handles parallel tool calls during streaming for Gemini', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post(null)
        ->urlStartsWith('https://generativelanguage.googleapis.com/v1beta')
        ->withStream(true)
        ->replySSEFromJson([
            ['candidates' => [[
                'id' => 'cand_1',
                'content' => ['parts' => [
                    ['functionCall' => ['name' => 'search', 'args' => ['q' => 'Hello']]],
                    ['functionCall' => ['name' => 'calculate', 'args' => ['n' => 42]]],
                ]],
                'finishReason' => 'STOP',
            ]]],
        ], addDone: true);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $final = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::fromConfig(\Cognesy\Polyglot\Tests\Support\TestConfig::llm('gemini'), httpClient: $http))
        ->withModel('gemini-1.5-flash')
        ->withMessages(\Cognesy\Messages\Messages::fromString('Search'))
        ->withStreaming(true)
        ->stream()
        ->final();

    expect($final)->not->toBeNull();
    expect($final->hasToolCalls())->toBeTrue();
    expect($final->toolCalls()->count())->toBe(2);

    $tools = $final->toolCalls()->all();
    expect($tools[0]->name())->toBe('search');
    expect($tools[0]->value('q'))->toBe('Hello');
    expect($tools[1]->name())->toBe('calculate');
    expect($tools[1]->value('n'))->toBe(42);
});
