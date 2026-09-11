<?php declare(strict_types=1);

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Polyglot\Inference\Drivers\Glm\GlmResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;

it('GLM: reads thinking from non-stream response', function () {
    $adapter = new GlmResponseAdapter(new OpenAIUsageFormat());
    $response = MockHttpResponseFactory::json([
        'choices' => [[
            'message' => [
                'content' => 'Paris',
                'thinking' => 'Reasoning path.',
            ],
            'finish_reason' => 'stop',
        ]],
    ]);

    $res = $adapter->fromResponse($response);

    expect($res)->not->toBeNull();
    expect($res?->message()->content()->toString())->toBe('Paris');
    expect($res?->message()->reasoningContent())->toBe('Reasoning path.');
});

it('GLM: falls back to <think> tags when reasoning field is missing', function () {
    $adapter = new GlmResponseAdapter(new OpenAIUsageFormat());
    $response = MockHttpResponseFactory::json([
        'choices' => [[
            'message' => [
                'content' => '<think>Reasoning block</think>Final answer',
            ],
            'finish_reason' => 'stop',
        ]],
    ]);

    $res = $adapter->fromResponse($response);

    expect($res)->not->toBeNull();
    expect($res?->message()->content()->toString())->toBe('Final answer');
    expect($res?->message()->reasoningContent())->toBe('Reasoning block');
});

it('GLM: keeps tool id stable across streamed tool deltas by index', function () {
    $adapter = new GlmResponseAdapter(new OpenAIUsageFormat());

    $firstEvent = json_encode([
        'choices' => [[
            'delta' => [
                'thinking' => 'step-1',
                'tool_calls' => [[
                    'id' => 'call_1',
                    'index' => 0,
                    'function' => [
                        'name' => 'search',
                        'arguments' => '{"q":"Pa',
                    ],
                ]],
            ],
            'finish_reason' => null,
        ]],
    ]);
    $secondEvent = json_encode([
        'choices' => [[
            'delta' => [
                'tool_calls' => [[
                    'index' => 0,
                    'function' => [
                        'arguments' => 'ris"}',
                    ],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]],
    ]);

    $deltas = iterator_to_array($adapter->fromStreamDeltas([$firstEvent, $secondEvent]));
    $firstToolChunk = $deltas[0]->messageChunks->all()[1];
    $secondToolChunk = $deltas[1]->messageChunks->all()[0];

    expect($deltas)->toHaveCount(2);
    expect($firstToolChunk->toolCallId)->toBe('call_1');
    expect($firstToolChunk->toolCallName)->toBe('search');
    expect($firstToolChunk->toolCallArguments)->toContain('{"q":"Pa');
    expect($deltas[0]->messageChunks->reasoningDelta())->toBe('step-1');
    expect($secondToolChunk->toolCallId)->toBe('call_1');
    expect($secondToolChunk->toolCallArguments)->toContain('ris"}');
});
