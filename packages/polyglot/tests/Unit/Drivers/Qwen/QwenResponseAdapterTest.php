<?php declare(strict_types=1);

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\Qwen\QwenResponseAdapter;

it('Qwen: reads reasoning_content from non-stream response', function () {
    $adapter = new QwenResponseAdapter(new OpenAIUsageFormat());
    $response = MockHttpResponseFactory::json([
        'choices' => [[
            'message' => [
                'content' => 'Paris',
                'reasoning_content' => 'Thinking path.',
            ],
            'finish_reason' => 'stop',
        ]],
    ]);

    $res = $adapter->fromResponse($response);

    expect($res)->not->toBeNull();
    expect($res?->message()->content()->toString())->toBe('Paris');
    expect($res?->message()->reasoningContent())->toBe('Thinking path.');
});

it('Qwen: falls back to <think> tags when reasoning field is missing', function () {
    $adapter = new QwenResponseAdapter(new OpenAIUsageFormat());
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

it('Qwen: keeps tool id stable across streamed tool deltas by index', function () {
    $adapter = new QwenResponseAdapter(new OpenAIUsageFormat());

    $firstEvent = json_encode([
        'choices' => [[
            'delta' => [
                'reasoning_content' => 'step-1',
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
