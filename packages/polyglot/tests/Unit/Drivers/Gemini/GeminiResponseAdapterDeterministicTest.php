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
    expect($res->message()->content()->toString())->toContain('Hello');
    expect($res->message()->hasToolCalls())->toBeTrue();
    $tool = $res->message()->toolCalls()->first();
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
    expect($res->message()->content()->toString())->toBe('');
    expect($res->message()->hasToolCalls())->toBeTrue();
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
    expect($delta1->messageChunks->textDelta())->toBe('Hel');

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
    $toolChunk = $delta2->messageChunks->all()[0];
    expect($delta2->messageChunks->textDelta())->toBe('');
    expect($toolChunk->toolCallId)->toBe('candidate:0:part:0');
    expect($toolChunk->toolCallName)->toBe('search');
    expect($toolChunk->toolCallArguments)->toContain('Hello');
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
    $toolChunk = $delta->messageChunks->all()[0];
    expect((string) $toolChunk->toolCallId)->toBe('cand_1:part:0');
    expect($toolChunk->toolCallName)->toBe('search');
    expect($toolChunk->toolCallArguments)->toContain('Hello');
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
    expect($delta->messageChunks->textDelta())->toBe('Hello');

    // CRITICAL: Verify that usageIsCumulative is set to true
    // This prevents exponential token growth during accumulation
    expect($delta->usageIsCumulative)->toBeTrue();

    // Verify usage values are parsed correctly
    $usage = $delta->usage;
    expect($usage)->not->toBeNull();
    expect($usage->inputTokens)->toBe(120);
    expect($usage->outputTokens)->toBe(3);
});
