<?php

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;

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
    expect($res->content())->toBe('Hello');
    expect(trim($res->reasoningContent()))->toBe('Reasoning...');
    expect($res->hasToolCalls())->toBeTrue();
    $tool = $res->toolCalls()->first();
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
    expect($res->content())->toBe('');
    expect($res->hasToolCalls())->toBeTrue();
});

it('Anthropic: parses streaming text and tool args deltas', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());

    $eventText = json_encode(['delta' => ['text' => 'Hel']]);
    $delta1 = iterator_to_array($adapter->fromStreamDeltas([$eventText]))[0] ?? null;
    expect($delta1)->not->toBeNull();
    expect($delta1->contentDelta)->toBe('Hel');

    $eventTool = json_encode([
        'content_block' => ['id' => 'c1', 'name' => 'search'],
        'delta' => ['partial_json' => json_encode(['q' => 'Hello'])],
    ]);
    $delta2 = iterator_to_array($adapter->fromStreamDeltas([$eventTool]))[0] ?? null;
    expect($delta2)->not->toBeNull();
    expect($delta2->contentDelta)->toBe('');
    expect($delta2->toolName)->toBe('search');
    expect($delta2->toolArgs)->toContain('Hello');
});

it('Anthropic: sets usageIsCumulative=true for streaming responses with usage data', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());

    // Test streaming response with usage data
    $eventWithUsage = json_encode([
        'delta' => ['text' => 'Hello'],
        'usage' => ['input_tokens' => 100, 'output_tokens' => 2]
    ]);

    $delta = iterator_to_array($adapter->fromStreamDeltas([$eventWithUsage]))[0] ?? null;
    expect($delta)->not->toBeNull();
    expect($delta->contentDelta)->toBe('Hello');

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

    expect($deltas)->toHaveCount(4);
    expect($deltas[2]->toolId)->toBe('tool_2');
    expect($deltas[2]->toolName)->toBe('');
    expect($deltas[2]->toolArgs)->toContain('Ber');
    expect($deltas[3]->toolId)->toBe('tool_1');
    expect($deltas[3]->toolName)->toBe('');
    expect($deltas[3]->toolArgs)->toContain('Par');
});
