<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('OpenAI golden: tools + JSON mode + streaming assembly', function () {
    $mock = new MockHttpDriver();

    // Streaming SSE with content deltas and tool call args split
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withStream(true)
        ->withJsonSubset([
            'model' => 'gpt-4o-mini',
            'tools' => [[ 'type' => 'function', 'function' => [ 'name' => 'search' ]]],
            'response_format' => ['type' => 'json_object'],
            'stream' => true,
        ])
        ->replySSEFromJson([
            ['choices' => [['delta' => ['content' => 'Hel']]]],
            ['choices' => [['delta' => ['content' => 'lo']]]],
            ['choices' => [[ 'delta' => [ 'tool_calls' => [[ 'id' => 'call_1', 'function' => [ 'name' => 'search', 'arguments' => '{"q":"Hello"}' ] ]] ] ]]],
        ], addDone: true);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $tools = [[
        'type' => 'function',
        'function' => [
            'name' => 'search',
            'parameters' => [
                'type' => 'object',
                'properties' => ['q' => ['type' => 'string']],
                'required' => ['q']
            ],
        ]
    ]];

    $stream = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::fromConfig(\Cognesy\Polyglot\Tests\Support\TestConfig::llm('openai'), httpClient: $http))
        ->withModel('gpt-4o-mini')
        ->withTools(\Cognesy\Polyglot\Inference\Data\ToolDefinitions::fromArray($tools))
        ->withToolChoice(\Cognesy\Polyglot\Inference\Data\ToolChoice::auto())
        ->withResponseFormat(\Cognesy\Polyglot\Inference\Data\ResponseFormat::jsonObject())
        ->withMessages(\Cognesy\Messages\Messages::fromArray([
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => 'Search hello']
        ]))
        ->withStreaming(true)
        ->stream();

    // Drain stream
    iterator_to_array($stream->deltas());
    $final = $stream->final();

    expect($final)->not->toBeNull();
    expect(str_starts_with($final->content(), 'Hello'))->toBeTrue();
    expect($final->hasToolCalls())->toBeTrue();
    $tool = $final->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});
