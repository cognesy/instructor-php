<?php

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2ResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2UsageFormat;

it('Cohere V2: keeps content empty for tool-only responses', function () {
    $adapter = new CohereV2ResponseAdapter(new CohereV2UsageFormat());
    $response = MockHttpResponseFactory::json([
        'message' => [
            'tool_calls' => [[
                'id' => 'call_1',
                'function' => [
                    'name' => 'search',
                    'arguments' => '{"q":"Hello"}',
                ],
            ]],
        ],
        'finish_reason' => 'tool_calls',
        'usage' => ['billed_units' => ['input_tokens' => 1, 'output_tokens' => 1]],
    ]);

    $res = $adapter->fromResponse($response);
    expect($res->content())->toBe('');
    expect($res->hasToolCalls())->toBeTrue();
});

it('Cohere V2: does not map tool deltas into contentDelta', function () {
    $adapter = new CohereV2ResponseAdapter(new CohereV2UsageFormat());
    $event = json_encode([
        'delta' => [
            'message' => [
                'tool_calls' => [
                    'function' => [
                        'id' => 'call_1',
                        'name' => 'search',
                        'arguments' => '{"q":"Hello"}',
                    ],
                ],
            ],
        ],
    ]);

    $partial = $adapter->fromStreamResponse($event);
    expect($partial)->not->toBeNull();
    expect($partial->contentDelta)->toBe('');
    expect($partial->toolArgs)->toContain('Hello');
});
