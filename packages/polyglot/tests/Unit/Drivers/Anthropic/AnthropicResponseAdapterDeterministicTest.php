<?php

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

it('Anthropic: parses content, reasoning, and tool calls deterministically', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());

    $data = [
        'stop_reason' => 'end_turn',
        'content' => [
            ['type' => 'text', 'text' => 'Hello'],
            ['type' => 'thinking', 'thinking' => 'Reasoning...'],
            ['type' => 'tool_use', 'id' => 'c1', 'name' => 'search', 'input' => ['q' => 'Hello']],
        ],
        'usage' => ['input_tokens' => 1, 'output_tokens' => 2],
    ];

    $res = $adapter->fromResponse(MockHttpResponseFactory::json($data));
    expect($res->message()->content()->toString())->toBe('Hello');
    expect(trim($res->message()->reasoningContent()))->toBe('Reasoning...');
    expect($res->message()->hasToolCalls())->toBeTrue();
    $tool = $res->message()->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});

it('Anthropic: keeps content empty for tool-only responses', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());

    $data = [
        'stop_reason' => 'tool_use',
        'content' => [
            ['type' => 'tool_use', 'id' => 'c1', 'name' => 'search', 'input' => ['q' => 'Hello']],
        ],
        'usage' => ['input_tokens' => 1, 'output_tokens' => 2],
    ];

    $res = $adapter->fromResponse(MockHttpResponseFactory::json($data));
    expect($res->message()->content()->toString())->toBe('');
    expect($res->message()->hasToolCalls())->toBeTrue();
});

it('Anthropic: parses streaming text and tool args deltas', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());

    $eventText = json_encode([
        'type' => 'content_block_delta',
        'index' => 0,
        'delta' => ['type' => 'text_delta', 'text' => 'Hel'],
    ]);
    $delta1 = iterator_to_array($adapter->fromStreamDeltas([$eventText]))[0] ?? null;
    expect($delta1)->not->toBeNull();
    expect($delta1->messageChunks->textDelta())->toBe('Hel');

    $toolEvents = [
        json_encode([
            'type' => 'content_block_start',
            'index' => 1,
            'content_block' => ['type' => 'tool_use', 'id' => 'c1', 'name' => 'search', 'input' => []],
        ]),
        json_encode([
            'type' => 'content_block_delta',
            'index' => 1,
            'delta' => ['type' => 'input_json_delta', 'partial_json' => json_encode(['q' => 'Hello'])],
        ]),
    ];
    $state = new InferenceStreamState();
    foreach ($adapter->fromStreamDeltas($toolEvents) as $delta) {
        $state->applyDelta($delta);
    }
    $tool = $state->finalResponse()->message()->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->rawArguments())->toContain('Hello');
});

it('Anthropic: sets usageIsCumulative=true for streaming responses with usage data', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());

    // Test streaming response with usage data
    $eventWithUsage = json_encode([
        'type' => 'content_block_delta',
        'index' => 0,
        'delta' => ['type' => 'text_delta', 'text' => 'Hello'],
        'usage' => ['input_tokens' => 100, 'output_tokens' => 2]
    ]);

    $delta = iterator_to_array($adapter->fromStreamDeltas([$eventWithUsage]))[0] ?? null;
    expect($delta)->not->toBeNull();
    expect($delta->messageChunks->textDelta())->toBe('Hello');

    // CRITICAL: Verify that usageIsCumulative is set to true
    // This prevents exponential token growth during accumulation
    expect($delta->usageIsCumulative)->toBeTrue();

    // Verify usage values are parsed correctly
    $usage = $delta->usage;
    expect($usage)->not->toBeNull();
    expect($usage->inputTokens)->toBe(100);
    expect($usage->outputTokens)->toBe(2);
});

it('Anthropic: propagates tool id by content block index for delta events', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());

    $events = [
        json_encode([
            'type' => 'content_block_start',
            'index' => 0,
            'content_block' => ['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'search'],
        ]),
        json_encode([
            'type' => 'content_block_start',
            'index' => 1,
            'content_block' => ['type' => 'tool_use', 'id' => 'tool_2', 'name' => 'search'],
        ]),
        json_encode([
            'type' => 'content_block_delta',
            'index' => 1,
            'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"q":"Ber'],
        ]),
        json_encode([
            'type' => 'content_block_delta',
            'index' => 0,
            'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"q":"Par'],
        ]),
    ];

    $deltas = array_values(iterator_to_array($adapter->fromStreamDeltas($events)));
    $secondToolArgs = $deltas[2]->messageChunks->all()[0];
    $firstToolArgs = $deltas[3]->messageChunks->all()[0];

    expect($deltas)->toHaveCount(4);
    expect($secondToolArgs->toolCallId)->toBe('tool_2');
    expect($secondToolArgs->toolCallName)->toBe('');
    expect($secondToolArgs->toolCallArguments)->toContain('Ber');
    expect($firstToolArgs->toolCallId)->toBe('tool_1');
    expect($firstToolArgs->toolCallName)->toBe('');
    expect($firstToolArgs->toolCallArguments)->toContain('Par');
});
