<?php declare(strict_types=1);

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Messages\Message;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiReplay;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesReplay;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesUsageFormat;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

function expectEquivalentAssistantMessages(Message $stream, Message $sync, string $replayOwner): void
{
    expect($stream->parts()->toArray())->toBe($sync->parts()->toArray());
    expect(ReplayEnvelope::fromMessage($stream, $replayOwner)?->parts())
        ->toBe(ReplayEnvelope::fromMessage($sync, $replayOwner)?->parts());
}

it('produces the same ordered assistant message from equivalent Anthropic sync and stream payloads', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());
    $sync = $adapter->fromResponse(MockHttpResponseFactory::json([
        'stop_reason' => 'tool_use',
        'content' => [
            ['type' => 'thinking', 'thinking' => 'Need data'],
            ['type' => 'text', 'text' => 'I will check.'],
            ['type' => 'tool_use', 'id' => 'call-1', 'name' => 'search', 'input' => ['q' => 'Warsaw']],
            ['type' => 'text', 'text' => 'Then calculate.'],
            ['type' => 'tool_use', 'id' => 'call-2', 'name' => 'calculate', 'input' => ['n' => 2]],
        ],
        'usage' => ['input_tokens' => 4, 'output_tokens' => 8],
    ]));

    $events = [
        ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'thinking', 'thinking' => '']],
        ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'thinking_delta', 'thinking_delta' => 'Need data']],
        ['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'text', 'text' => '']],
        ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'text_delta', 'text' => 'I will check.']],
        ['type' => 'content_block_start', 'index' => 2, 'content_block' => ['type' => 'tool_use', 'id' => 'call-1', 'name' => 'search']],
        ['type' => 'content_block_delta', 'index' => 2, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"q":"War']],
        ['type' => 'content_block_delta', 'index' => 2, 'delta' => ['type' => 'input_json_delta', 'partial_json' => 'saw"}']],
        ['type' => 'content_block_start', 'index' => 3, 'content_block' => ['type' => 'text', 'text' => '']],
        ['type' => 'content_block_delta', 'index' => 3, 'delta' => ['type' => 'text_delta', 'text' => 'Then calculate.']],
        ['type' => 'content_block_start', 'index' => 4, 'content_block' => ['type' => 'tool_use', 'id' => 'call-2', 'name' => 'calculate']],
        ['type' => 'content_block_delta', 'index' => 4, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"n":2}']],
        ['type' => 'message_delta', 'delta' => ['stop_reason' => 'tool_use']],
    ];

    $state = new InferenceStreamState();
    foreach ($adapter->fromStreamDeltas(array_map(json_encode(...), $events)) as $delta) {
        $state->applyDelta($delta);
    }

    expectEquivalentAssistantMessages(
        $state->finalResponse()->message(),
        $sync?->message(),
        'anthropic',
    );
});

it('produces the same ordered assistant message from equivalent Gemini sync and stream payloads', function () {
    $adapter = new GeminiResponseAdapter(new GeminiUsageFormat());
    $sync = $adapter->fromResponse(MockHttpResponseFactory::json([
        'candidates' => [[
            'id' => 'candidate-1',
            'finishReason' => 'STOP',
            'content' => ['parts' => [
                ['text' => 'Need data.', 'thought' => true, 'thoughtSignature' => 'sig-reasoning'],
                ['text' => 'I will check.', 'thoughtSignature' => 'sig-text'],
                ['functionCall' => [
                    'id' => 'call-1',
                    'name' => 'search',
                    'args' => ['q' => 'Warsaw'],
                ], 'thoughtSignature' => 'sig-tool'],
            ]],
        ]],
        'usageMetadata' => ['promptTokenCount' => 4, 'candidatesTokenCount' => 8],
    ]));

    $events = [
        ['candidates' => [[
            'id' => 'candidate-1',
            'content' => ['parts' => [[
                'text' => 'Need data.',
                'thought' => true,
                'thoughtSignature' => 'sig-reasoning',
            ]]],
        ]]],
        ['candidates' => [[
            'id' => 'candidate-1',
            'content' => ['parts' => [[
                'text' => 'I will check.',
                'thoughtSignature' => 'sig-text',
            ]]],
        ]]],
        ['candidates' => [[
            'id' => 'candidate-1',
            'content' => ['parts' => [[
                'functionCall' => [
                    'id' => 'call-1',
                    'name' => 'search',
                    'args' => ['q' => 'Warsaw'],
                ],
                'thoughtSignature' => 'sig-tool',
            ]]],
        ]]],
        ['candidates' => [[
            'id' => 'candidate-1',
            'finishReason' => 'STOP',
        ]]],
    ];

    $state = new InferenceStreamState();
    foreach ($adapter->fromStreamDeltas(array_map(json_encode(...), $events)) as $delta) {
        $state->applyDelta($delta);
    }

    expectEquivalentAssistantMessages(
        $state->finalResponse()->message(),
        $sync?->message(),
        GeminiReplay::OWNER,
    );
});

it('produces the same ordered assistant message from equivalent OpenResponses sync and stream payloads', function () {
    $adapter = new OpenResponsesResponseAdapter(new OpenResponsesUsageFormat());
    $reasoning = [
        'type' => 'reasoning',
        'id' => 'reasoning-1',
        'status' => 'completed',
        'content' => [['type' => 'reasoning_text', 'text' => 'Need data.']],
        'summary' => [],
        'encrypted_content' => 'encrypted-reasoning',
    ];
    $sync = $adapter->fromResponse(MockHttpResponseFactory::json([
        'id' => 'response-1',
        'model' => 'gpt-test',
        'status' => 'completed',
        'output' => [
            $reasoning,
            ['type' => 'message', 'id' => 'message-1', 'role' => 'assistant', 'content' => [
                ['type' => 'output_text', 'text' => 'I will check.'],
            ]],
            ['type' => 'function_call', 'id' => 'tool-item-1', 'call_id' => 'call-1', 'name' => 'search', 'arguments' => '{"q":"Warsaw"}'],
        ],
        'usage' => ['input_tokens' => 4, 'output_tokens' => 8],
    ]));

    $events = [
        ['type' => 'response.output_item.added', 'item' => [
            'type' => 'reasoning',
            'id' => 'reasoning-1',
            'status' => 'in_progress',
            'encrypted_content' => 'encrypted-reasoning',
        ]],
        ['type' => 'response.reasoning_text.delta', 'item_id' => 'reasoning-1', 'delta' => 'Need data.'],
        ['type' => 'response.output_item.done', 'item' => $reasoning],
        ['type' => 'response.output_item.added', 'item' => [
            'type' => 'message',
            'id' => 'message-1',
            'role' => 'assistant',
            'content' => [],
        ]],
        ['type' => 'response.output_text.delta', 'item_id' => 'message-1', 'content_index' => 0, 'delta' => 'I will check.'],
        ['type' => 'response.output_item.added', 'item' => [
            'type' => 'function_call',
            'id' => 'tool-item-1',
            'call_id' => 'call-1',
            'name' => 'search',
        ]],
        ['type' => 'response.function_call_arguments.delta', 'item_id' => 'tool-item-1', 'delta' => '{"q":"War'],
        ['type' => 'response.function_call_arguments.delta', 'item_id' => 'tool-item-1', 'delta' => 'saw"}'],
        ['type' => 'response.function_call_arguments.done', 'item_id' => 'tool-item-1', 'arguments' => '{"q":"Warsaw"}'],
        ['type' => 'response.completed', 'response' => [
            'id' => 'response-1',
            'model' => 'gpt-test',
            'status' => 'completed',
            'usage' => ['input_tokens' => 4, 'output_tokens' => 8],
        ]],
    ];

    $state = new InferenceStreamState();
    foreach ($adapter->fromStreamDeltas(array_map(json_encode(...), $events)) as $delta) {
        $state->applyDelta($delta);
    }

    expectEquivalentAssistantMessages(
        $state->finalResponse()->message(),
        $sync?->message(),
        OpenResponsesReplay::OWNER,
    );
});
