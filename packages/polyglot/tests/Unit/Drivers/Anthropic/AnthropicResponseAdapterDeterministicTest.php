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

it('Anthropic: parses streaming text and tool args deltas', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());

    $eventText = json_encode(['delta' => ['text' => 'Hel']]);
    $p1 = $adapter->fromStreamResponse($eventText);
    expect($p1)->not->toBeNull();
    expect($p1->contentDelta)->toBe('Hel');

    $eventTool = json_encode([
        'content_block' => ['id' => 'c1', 'name' => 'search'],
        'delta' => ['partial_json' => json_encode(['q' => 'Hello'])],
    ]);
    $p2 = $adapter->fromStreamResponse($eventTool);
    expect($p2)->not->toBeNull();
    expect($p2->toolName)->toBe('search');
    expect($p2->toolArgs)->toContain('Hello');
});

it('Anthropic: sets usageIsCumulative=true for streaming responses with usage data', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());

    // Test streaming response with usage data
    $eventWithUsage = json_encode([
        'delta' => ['text' => 'Hello'],
        'usage' => ['input_tokens' => 100, 'output_tokens' => 2]
    ]);

    $partial = $adapter->fromStreamResponse($eventWithUsage);
    expect($partial)->not->toBeNull();
    expect($partial->contentDelta)->toBe('Hello');

    // CRITICAL: Verify that usageIsCumulative is set to true
    // This prevents exponential token growth during accumulation
    expect($partial->isUsageCumulative())->toBeTrue();

    // Verify usage values are parsed correctly
    $usage = $partial->usage();
    expect($usage)->not->toBeNull();
    expect($usage->inputTokens)->toBe(100);
    expect($usage->outputTokens)->toBe(2);
});
