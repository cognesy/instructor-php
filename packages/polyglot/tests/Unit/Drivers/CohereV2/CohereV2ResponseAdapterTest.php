<?php

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2ResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2UsageFormat;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

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
    expect($res->message()->content()->toString())->toBe('');
    expect($res->message()->hasToolCalls())->toBeTrue();
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

    $delta = iterator_to_array($adapter->fromStreamDeltas([$event]))[0] ?? null;
    expect($delta)->not->toBeNull();
    $toolChunk = $delta->messageChunks->all()[0];
    expect($delta->messageChunks->textDelta())->toBe('');
    expect($toolChunk->toolCallArguments)->toContain('Hello');
});

it('Cohere V2: keeps sequential no-id tool calls at local index zero distinct', function () {
    $adapter = new CohereV2ResponseAdapter(new CohereV2UsageFormat());
    $events = [
        json_encode([
            'delta' => [
                'message' => [
                    'tool_calls' => [[
                        'function' => [
                            'name' => 'search',
                            'arguments' => '{"q":"alpha"}',
                        ],
                    ]],
                ],
            ],
        ]),
        json_encode([
            'delta' => [
                'message' => [
                    'tool_calls' => [[
                        'function' => [
                            'name' => 'search',
                            'arguments' => '{"q":"beta"}',
                        ],
                    ]],
                ],
            ],
        ]),
    ];

    $state = new InferenceStreamState();
    foreach ($adapter->fromStreamDeltas($events) as $delta) {
        $state->applyDelta($delta);
    }

    $tools = $state->finalResponse()->message()->toolCalls()->all();

    expect($tools)->toHaveCount(2);
    expect($tools[0]->value('q'))->toBe('alpha');
    expect($tools[1]->value('q'))->toBe('beta');
});

it('Cohere V2: extracts tool fields from a single-object tool_calls delta', function () {
    $adapter = new \Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2ResponseAdapter(
        new \Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2UsageFormat(),
    );

    $event = json_encode([
        'delta' => [
            'message' => [
                'tool_calls' => [
                    'id' => 'call_9',
                    'function' => ['name' => 'search', 'arguments' => '{"q":"x"}'],
                ],
            ],
        ],
    ]);

    $deltas = iterator_to_array($adapter->fromStreamDeltas([$event]));
    $toolChunk = $deltas[0]->messageChunks->all()[0];

    expect($deltas)->toHaveCount(1);
    expect($toolChunk->toolCallName)->toBe('search');
    expect($toolChunk->toolCallArguments)->toBe('{"q":"x"}');
    expect((string) $toolChunk->toolCallId)->not->toBe('');
});
