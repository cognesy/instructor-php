<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('Anthropic golden: streaming text + tool_use aggregation', function () {
    $mock = new MockHttpDriver();

    $mock->on()
        ->post('https://api.anthropic.com/v1/messages')
        ->withStream(true)
        ->replySSEFromJson([
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Hel']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'lo']],
            [
                'type' => 'content_block_start',
                'index' => 1,
                'content_block' => ['type' => 'tool_use', 'id' => 'tb1', 'name' => 'get_weather', 'input' => []],
            ],
            [
                'type' => 'content_block_delta',
                'index' => 1,
                'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"city":"Paris"}'],
            ],
            'event: message_stop',
        ], addDone: false);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $tools = [[
        'type' => 'function',
        'function' => [
            'name' => 'get_weather',
            'parameters' => [
                'type' => 'object',
                'properties' => ['city' => ['type' => 'string']],
                'required' => ['city']
            ],
        ]
    ]];

    $stream = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::fromConfig(\Cognesy\Polyglot\Tests\Support\TestConfig::llm('anthropic'), httpClient: $http))
        ->withModel('claude-3-haiku-20240307')
        ->withTools(\Cognesy\Polyglot\Inference\Data\ToolDefinitions::fromArray($tools))
        ->withToolChoice(\Cognesy\Polyglot\Inference\Data\ToolChoice::auto())
        ->withMessages(\Cognesy\Messages\Messages::fromArray([
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => 'Weather in Paris']
        ]))
        ->withStreaming(true)
        ->stream();

    iterator_to_array($stream->deltas());
    $final = $stream->final();

    expect($final)->not->toBeNull();
    expect(str_starts_with($final->message()->content()->toString(), 'Hello'))->toBeTrue();
    expect($final->message()->hasToolCalls())->toBeTrue();
    $tool = $final->message()->toolCalls()->first();
    expect($tool->name())->toBe('get_weather');
    expect($tool->value('city'))->toBe('Paris');
});
