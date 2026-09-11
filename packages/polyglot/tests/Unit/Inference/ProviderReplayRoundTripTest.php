<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesUsageFormat;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

function replayResponse(array $data): HttpResponse
{
    return HttpResponse::sync(200, [], json_encode($data, JSON_THROW_ON_ERROR));
}

/** @param list<mixed> $parts */
function providerReplayEnvelope(string $owner, array $parts): ReplayEnvelope
{
    return ReplayEnvelope::fromParts($owner, $parts)
        ?? throw new LogicException('Replay test fixture must contain provider data.');
}

it('round trips Anthropic signed and redacted thinking in exact block order', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());
    $response = $adapter->fromResponse(replayResponse([
        'id' => 'msg_1',
        'model' => 'claude-test',
        'stop_reason' => 'tool_use',
        'content' => [
            ['type' => 'thinking', 'thinking' => 'Need weather.', 'signature' => 'sig-thinking'],
            ['type' => 'text', 'text' => 'Checking.'],
            ['type' => 'redacted_thinking', 'data' => 'opaque-redacted'],
            ['type' => 'tool_use', 'id' => 'call_1', 'name' => 'weather', 'input' => ['city' => 'Warsaw']],
        ],
    ]));

    $native = (new AnthropicMessageFormat())->map(Messages::fromMessages([$response->message()]));

    expect($response->message()->parts()->toArray())->toBe([
        ['type' => 'reasoning', 'text' => 'Need weather.'],
        ['type' => 'text', 'text' => 'Checking.'],
        ['type' => 'reasoning'],
        ['type' => 'tool_call', 'tool_call' => [
            'id' => 'call_1',
            'name' => 'weather',
            'arguments' => ['city' => 'Warsaw'],
            'raw_arguments' => '{"city":"Warsaw"}',
        ]],
    ])->and($native[0]['content'])->toBe([
        ['type' => 'thinking', 'thinking' => 'Need weather.', 'signature' => 'sig-thinking'],
        ['type' => 'text', 'text' => 'Checking.'],
        ['type' => 'redacted_thinking', 'data' => 'opaque-redacted'],
        ['type' => 'tool_use', 'id' => 'call_1', 'name' => 'weather', 'input' => ['city' => 'Warsaw']],
    ]);
});

it('assembles Anthropic signature deltas into replayable stream history', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());
    $state = new InferenceStreamState();
    $events = [
        ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'thinking', 'thinking' => '']],
        ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'thinking_delta', 'thinking_delta' => 'Think.']],
        ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'signature_delta', 'signature' => 'sig-']],
        ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'signature_delta', 'signature' => 'tail']],
        ['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'text', 'text' => '']],
        ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'text_delta', 'text' => 'Done.']],
    ];
    foreach ($adapter->fromStreamDeltas(array_map(
        static fn(array $event): string => json_encode($event, JSON_THROW_ON_ERROR),
        $events,
    )) as $delta) {
        $state->applyDelta($delta);
    }

    $native = (new AnthropicMessageFormat())->map(Messages::fromMessages([$state->finalResponse()->message()]));
    expect($native[0]['content'])->toBe([
        ['type' => 'thinking', 'thinking' => 'Think.', 'signature' => 'sig-tail'],
        ['type' => 'text', 'text' => 'Done.'],
    ]);
});

it('round trips Gemini thought signatures on reasoning text and tool-call parts', function () {
    $adapter = new GeminiResponseAdapter(new GeminiUsageFormat());
    $response = $adapter->fromResponse(replayResponse([
        'candidates' => [[
            'finishReason' => 'STOP',
            'content' => ['parts' => [
                ['text' => 'Plan.', 'thought' => true, 'thoughtSignature' => 'sig-reasoning'],
                ['text' => 'Calling.'],
                ['functionCall' => ['id' => 'call_1', 'name' => 'lookup', 'args' => ['q' => 'x']], 'thoughtSignature' => 'sig-tool'],
            ]],
        ]],
    ]));

    $native = (new GeminiMessageFormat())->map(Messages::fromMessages([$response->message()]));
    expect($native[0]['parts'])->toBe([
        ['text' => 'Plan.', 'thought' => true, 'thoughtSignature' => 'sig-reasoning'],
        ['text' => 'Calling.'],
        ['functionCall' => ['name' => 'lookup', 'args' => ['q' => 'x']], 'thoughtSignature' => 'sig-tool'],
    ]);
});

it('aligns Gemini streamed thought signatures across semantic block transitions', function () {
    $adapter = new GeminiResponseAdapter(new GeminiUsageFormat());
    $state = new InferenceStreamState();
    $events = [
        ['candidates' => [['id' => 'candidate-1', 'content' => ['parts' => [
            ['text' => 'Plan ', 'thought' => true, 'thoughtSignature' => 'sig-reasoning'],
        ]]]]],
        ['candidates' => [['id' => 'candidate-1', 'content' => ['parts' => [
            ['text' => 'carefully.', 'thought' => true],
        ]]]]],
        ['candidates' => [['id' => 'candidate-1', 'content' => ['parts' => [
            ['text' => 'Answer.', 'thoughtSignature' => 'sig-text'],
        ]]]]],
    ];
    foreach ($adapter->fromStreamDeltas(array_map(
        static fn(array $event): string => json_encode($event, JSON_THROW_ON_ERROR),
        $events,
    )) as $delta) {
        $state->applyDelta($delta);
    }

    $native = (new GeminiMessageFormat())->map(Messages::fromMessages([$state->finalResponse()->message()]));
    expect($native[0]['parts'])->toBe([
        ['text' => 'Plan carefully.', 'thought' => true, 'thoughtSignature' => 'sig-reasoning'],
        ['text' => 'Answer.', 'thoughtSignature' => 'sig-text'],
    ]);
});

it('round trips complete OpenAI Responses reasoning identity and encrypted state', function () {
    $adapter = new OpenResponsesResponseAdapter(new OpenResponsesUsageFormat());
    $reasoning = [
        'type' => 'reasoning',
        'id' => 'rs_1',
        'status' => 'completed',
        'phase' => 'analysis',
        'content' => [['type' => 'reasoning_text', 'text' => 'Private.']],
        'summary' => [['type' => 'summary_text', 'text' => 'Summary.']],
        'encrypted_content' => 'encrypted-state',
    ];
    $response = $adapter->fromResponse(replayResponse([
        'id' => 'resp_1',
        'model' => 'gpt-test',
        'status' => 'completed',
        'output' => [
            $reasoning,
            ['type' => 'message', 'role' => 'assistant', 'content' => [
                ['type' => 'output_text', 'text' => 'Answer.'],
            ]],
        ],
    ]));

    $native = (new OpenResponsesMessageFormat())->map(Messages::fromMessages([$response->message()]));
    expect($native)->toBe([
        $reasoning,
        ['type' => 'message', 'role' => 'assistant', 'content' => [
            ['type' => 'output_text', 'text' => 'Answer.'],
        ]],
    ]);
});

it('replays OpenAI Responses reasoning items assembled from semantic stream events', function () {
    $adapter = new OpenResponsesResponseAdapter(new OpenResponsesUsageFormat());
    $state = new InferenceStreamState();
    $reasoning = [
        'type' => 'reasoning',
        'id' => 'rs_stream',
        'status' => 'completed',
        'content' => [['type' => 'reasoning_text', 'text' => 'Private.']],
        'summary' => [],
        'encrypted_content' => 'encrypted-stream-state',
    ];
    $events = [
        ['type' => 'response.output_item.added', 'item' => [
            'type' => 'reasoning',
            'id' => 'rs_stream',
            'status' => 'in_progress',
            'encrypted_content' => 'encrypted-stream-state',
        ]],
        ['type' => 'response.reasoning_text.delta', 'item_id' => 'rs_stream', 'delta' => 'Private.'],
        ['type' => 'response.output_item.done', 'item' => $reasoning],
        ['type' => 'response.output_item.added', 'item' => [
            'type' => 'message',
            'id' => 'msg_stream',
            'role' => 'assistant',
            'content' => [],
        ]],
        ['type' => 'response.output_text.delta', 'item_id' => 'msg_stream', 'content_index' => 0, 'delta' => 'Answer.'],
        ['type' => 'response.completed', 'response' => [
            'id' => 'resp_stream',
            'model' => 'gpt-test',
            'status' => 'completed',
        ]],
    ];
    foreach ($adapter->fromStreamDeltas(array_map(
        static fn(array $event): string => json_encode($event, JSON_THROW_ON_ERROR),
        $events,
    )) as $delta) {
        $state->applyDelta($delta);
    }

    $native = (new OpenResponsesMessageFormat())->map(Messages::fromMessages([$state->finalResponse()->message()]));
    expect($native)->toBe([
        $reasoning,
        ['type' => 'message', 'role' => 'assistant', 'content' => [
            ['type' => 'output_text', 'text' => 'Answer.'],
        ]],
    ]);
});

it('degrades foreign stale and misaligned replay without leaking private fields', function () {
    $format = new OpenResponsesMessageFormat();
    $parts = new ContentParts(
        ContentPart::reasoning('Edited.'),
        ContentPart::text('Answer.'),
    );
    $stale = new Message(
        role: 'assistant',
        parts: $parts,
        metadata: [ReplayEnvelope::MESSAGE_METADATA_KEY => providerReplayEnvelope('openresponses', [[
                'item' => [
                    'type' => 'reasoning',
                    'id' => 'rs_1',
                    'content' => [['type' => 'reasoning_text', 'text' => 'Original.']],
                ],
            ], null])->toArray()],
    );
    $foreign = $stale->withMetadata(ReplayEnvelope::MESSAGE_METADATA_KEY, providerReplayEnvelope(
        'anthropic',
        [['kind' => 'thinking', 'signature' => 'secret'], null],
    )->toArray());
    $misaligned = $stale->withMetadata(ReplayEnvelope::MESSAGE_METADATA_KEY, providerReplayEnvelope(
        'openresponses',
        [['item' => ['type' => 'reasoning', 'id' => 'rs_1']]],
    )->toArray());
    $invalidVersion = $stale->withMetadata(ReplayEnvelope::MESSAGE_METADATA_KEY, [
        'version' => 999,
        'owner' => 'openresponses',
        'parts' => [null, null],
    ]);

    $expected = [[
        'type' => 'message',
        'role' => 'assistant',
        'content' => [['type' => 'output_text', 'text' => 'Answer.']],
    ]];
    expect($format->map(Messages::fromMessages([$stale])))->toBe($expected)
        ->and($format->map(Messages::fromMessages([$foreign])))->toBe($expected)
        ->and($format->map(Messages::fromMessages([$misaligned])))->toBe($expected)
        ->and($format->map(Messages::fromMessages([$invalidVersion])))->toBe($expected);
});
