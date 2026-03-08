<?php

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiUsageFormat;

it('Gemini native: parses final response content and tool calls deterministically', function () {
    $adapter = new GeminiResponseAdapter(new GeminiUsageFormat());

    $data = [
        'candidates' => [[
            'finishReason' => 'stop',
            'content' => [
                'parts' => [
                    ['text' => 'Hello'],
                    ['functionCall' => ['name' => 'search', 'args' => ['q' => 'Hello']]],
                ],
            ],
        ]],
        'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 2, 'totalTokenCount' => 3]
    ];
    $httpResp = MockHttpResponseFactory::json($data);

    $res = $adapter->fromResponse($httpResp);
    expect($res->content())->toContain('Hello');
    expect($res->hasToolCalls())->toBeTrue();
    $tool = $res->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});

it('Gemini native: keeps content empty for tool-only responses', function () {
    $adapter = new GeminiResponseAdapter(new GeminiUsageFormat());

    $data = [
        'candidates' => [[
            'finishReason' => 'STOP',
            'content' => [
                'parts' => [
                    ['functionCall' => ['name' => 'search', 'args' => ['q' => 'Hello']]],
                ],
            ],
        ]],
        'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1]
    ];
    $httpResp = MockHttpResponseFactory::json($data);

    $res = $adapter->fromResponse($httpResp);
    expect($res->content())->toBe('');
    expect($res->hasToolCalls())->toBeTrue();
});

it('Gemini native: parses streaming partial with text and tool args', function () {
    $adapter = new GeminiResponseAdapter(new GeminiUsageFormat());
    $event = json_encode([
        'candidates' => [[
            'content' => [
                'parts' => [
                    ['text' => 'Hel'],
                ],
            ],
        ]],
    ]);

    $delta1 = iterator_to_array($adapter->fromStreamDeltas([$event]))[0] ?? null;
    expect($delta1)->not->toBeNull();
    expect($delta1->contentDelta)->toBe('Hel');

    $event2 = json_encode([
        'candidates' => [[
            'content' => [
                'parts' => [
                    ['functionCall' => ['name' => 'search', 'args' => ['q' => 'Hello']]],
                ],
            ],
        ]],
    ]);
    $delta2 = iterator_to_array($adapter->fromStreamDeltas([$event2]))[0] ?? null;
    expect($delta2)->not->toBeNull();
    expect($delta2->contentDelta)->toBe('');
    expect($delta2->toolId)->toBe('part:0');
    expect($delta2->toolName)->toBe('search');
    expect($delta2->toolArgs)->toContain('Hello');
});

it('Gemini native: uses extracted per-part tool id even for single tool delta in chunk', function () {
    $adapter = new GeminiResponseAdapter(new GeminiUsageFormat());
    $event = json_encode([
        'candidates' => [[
            'id' => 'cand_1',
            'content' => [
                'parts' => [
                    ['functionCall' => ['name' => 'search', 'args' => ['q' => 'Hello']]],
                ],
            ],
        ]],
    ]);

    $delta = iterator_to_array($adapter->fromStreamDeltas([$event]))[0] ?? null;
    expect($delta)->not->toBeNull();
    expect($delta->toolId)->toBe('cand_1:part:0');
    expect($delta->toolName)->toBe('search');
    expect($delta->toolArgs)->toContain('Hello');
});

it('Gemini native: sets usageIsCumulative=true for streaming responses with usage data', function () {
    $adapter = new GeminiResponseAdapter(new GeminiUsageFormat());

    // Test streaming response with usage data
    $eventWithUsage = json_encode([
        'candidates' => [[
            'content' => ['parts' => [['text' => 'Hello']]],
            'finishReason' => ''
        ]],
        'usageMetadata' => ['promptTokenCount' => 120, 'candidatesTokenCount' => 3]
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
    expect($usage->inputTokens)->toBe(120);
    expect($usage->outputTokens)->toBe(3);
});
