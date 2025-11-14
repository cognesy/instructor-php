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

    $p1 = $adapter->fromStreamResponse($event);
    expect($p1)->not->toBeNull();
    expect($p1->contentDelta)->toBe('Hel');

    $event2 = json_encode([
        'candidates' => [[
            'content' => [
                'parts' => [
                    ['functionCall' => ['name' => 'search', 'args' => ['q' => 'Hello']]],
                ],
            ],
        ]],
    ]);
    $p2 = $adapter->fromStreamResponse($event2);
    expect($p2)->not->toBeNull();
    expect($p2->toolName)->toBe('search');
    expect($p2->toolArgs)->toContain('Hello');
});
