<?php declare(strict_types=1);

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Polyglot\Inference\Drivers\Deepseek\DeepseekResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;

it('Deepseek: marks streamed usage as cumulative', function () {
    $adapter = new DeepseekResponseAdapter(new OpenAIUsageFormat());

    $event = json_encode([
        'choices' => [[
            'delta' => ['content' => 'Par'],
            'finish_reason' => null,
        ]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 2],
    ]);

    $deltas = iterator_to_array($adapter->fromStreamDeltas([$event]));

    expect($deltas)->toHaveCount(1);
    expect($deltas[0]->usageIsCumulative)->toBeTrue();
});

it('Deepseek: reads all reasoning keys from stream deltas, including thinking', function () {
    $adapter = new DeepseekResponseAdapter(new OpenAIUsageFormat());

    $events = array_map(
        static fn(array $delta): string => json_encode([
            'choices' => [['delta' => $delta, 'finish_reason' => null]],
        ]),
        [
            ['reasoning_content' => 'via-reasoning-content'],
            ['reasoning' => 'via-reasoning'],
            ['thinking' => 'via-thinking'],
            ['analysis' => 'via-analysis'],
        ],
    );

    $deltas = iterator_to_array($adapter->fromStreamDeltas($events));

    expect(array_map(static fn($d) => $d->reasoningContentDelta, $deltas))->toBe([
        'via-reasoning-content',
        'via-reasoning',
        'via-thinking',
        'via-analysis',
    ]);
});

it('Deepseek: reads thinking key from non-stream response message', function () {
    $adapter = new DeepseekResponseAdapter(new OpenAIUsageFormat());
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
    expect($res?->content())->toBe('Paris');
    expect($res?->reasoningContent())->toBe('Reasoning path.');
});

it('Deepseek: carries streamed tool call deltas alongside reasoning', function () {
    $adapter = new DeepseekResponseAdapter(new OpenAIUsageFormat());

    $event = json_encode([
        'choices' => [[
            'delta' => [
                'reasoning_content' => 'step-1',
                'tool_calls' => [[
                    'id' => 'call_1',
                    'index' => 0,
                    'function' => ['name' => 'search', 'arguments' => '{"q":"Pa'],
                ]],
            ],
            'finish_reason' => null,
        ]],
    ]);

    $deltas = iterator_to_array($adapter->fromStreamDeltas([$event]));

    expect($deltas)->toHaveCount(1);
    expect($deltas[0]->toolId)->toBe('call_1');
    expect($deltas[0]->toolName)->toBe('search');
    expect($deltas[0]->toolArgs)->toBe('{"q":"Pa');
    expect($deltas[0]->reasoningContentDelta)->toBe('step-1');
});
