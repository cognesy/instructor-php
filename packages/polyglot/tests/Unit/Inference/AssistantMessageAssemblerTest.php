<?php declare(strict_types=1);

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Enums\ContentType;
use Cognesy\Messages\ToolCall;
use Cognesy\Polyglot\Inference\Assembly\AssistantMessageAssembler;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunk;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;

it('assembles reasoning, text, and interleaved tool blocks in first-seen order', function () {
    $assembler = new AssistantMessageAssembler();
    $chunks = [
        AssistantMessageChunk::blockStart(0, ContentType::Reasoning),
        AssistantMessageChunk::reasoningDelta(0, 'check '),
        AssistantMessageChunk::reasoningDelta(0, 'facts'),
        AssistantMessageChunk::textDelta(1, 'Answer'),
        AssistantMessageChunk::toolCallDelta(3, 'call-2', 'calculate', '{"n":'),
        AssistantMessageChunk::toolCallDelta(2, 'call-1', 'search', '{ "q" : '),
        AssistantMessageChunk::toolCallDelta(3, 'call-2', '', '2}'),
        AssistantMessageChunk::toolCallDelta(2, 'call-1', '', '"Warsaw" }'),
    ];
    foreach ($chunks as $chunk) {
        $assembler->apply($chunk);
    }

    expect($assembler->message()->parts()->toArray())->toBe([
        ['type' => 'reasoning', 'text' => 'check facts'],
        ['type' => 'text', 'text' => 'Answer'],
        [
            'type' => 'tool_call',
            'tool_call' => [
                'id' => 'call-2',
                'name' => 'calculate',
                'arguments' => ['n' => 2],
                'raw_arguments' => '{"n":2}',
            ],
        ],
        [
            'type' => 'tool_call',
            'tool_call' => [
                'id' => 'call-1',
                'name' => 'search',
                'arguments' => ['q' => 'Warsaw'],
                'raw_arguments' => '{ "q" : "Warsaw" }',
            ],
        ],
    ]);
});

it('uses the first completed block and ignores later deltas or duplicate closes', function () {
    $assembler = new AssistantMessageAssembler();
    $assembler->apply(AssistantMessageChunk::textDelta(0, 'streamed'));
    $assembler->apply(AssistantMessageChunk::blockEnd(0, ContentPart::text('authoritative')));
    $assembler->apply(AssistantMessageChunk::textDelta(0, ' straggler'));
    $assembler->apply(AssistantMessageChunk::blockEnd(0, ContentPart::reasoning('duplicate')));

    expect($assembler->message()->parts()->toArray())->toBe([
        ['type' => 'text', 'text' => 'authoritative'],
    ]);
});

it('prunes truly empty blocks and preserves raw tool argument spelling', function () {
    $assembler = new AssistantMessageAssembler();
    $assembler->apply(AssistantMessageChunk::blockEnd(0, ContentPart::text('')));
    $assembler->apply(AssistantMessageChunk::blockEnd(1, ContentPart::reasoning('')));
    $assembler->apply(AssistantMessageChunk::blockEnd(2, ContentPart::toolCall(ToolCall::fromArray([
        'id' => 'call-1',
        'name' => 'lookup',
        'arguments' => '{ "id" : 7 }',
    ]))));

    expect($assembler->message()->parts()->toArray())->toBe([
        [
            'type' => 'tool_call',
            'tool_call' => [
                'id' => 'call-1',
                'name' => 'lookup',
                'arguments' => ['id' => 7],
                'raw_arguments' => '{ "id" : 7 }',
            ],
        ],
    ]);
});

it('retains empty replay-bearing blocks while pruning empty semantic and replay pairs together', function () {
    $assembler = new AssistantMessageAssembler();
    $assembler->apply(AssistantMessageChunk::blockEnd(0, ContentPart::reasoning('')));
    $assembler->apply(AssistantMessageChunk::blockEnd(1, ContentPart::text('')));
    $assembler->apply(AssistantMessageChunk::blockEnd(2, ContentPart::text('answer')));
    $assembler->applyReplay(ReplayEnvelope::fromParts(
        owner: 'test-adapter',
        parts: [
            ['signature' => 'signed-empty-reasoning'],
            null,
            ['item_id' => 'item-2'],
        ],
        response: ['id' => 'response-1'],
    ));

    $message = $assembler->message();
    expect($message->parts()->toArray())->toBe([
        ['type' => 'reasoning'],
        ['type' => 'text', 'text' => 'answer'],
    ])->and(ReplayEnvelope::fromMessage($message, 'test-adapter')?->parts())->toBe([
        ['signature' => 'signed-empty-reasoning'],
        ['item_id' => 'item-2'],
    ]);
});

it('drops a misaligned replay envelope without dropping semantic blocks', function () {
    $assembler = new AssistantMessageAssembler();
    $assembler->apply(AssistantMessageChunk::blockEnd(0, ContentPart::text('one')));
    $assembler->apply(AssistantMessageChunk::blockEnd(1, ContentPart::text('two')));
    $assembler->applyReplay(ReplayEnvelope::fromParts('test-adapter', [['only-one-entry']]));

    expect($assembler->message()->parts()->toArray())->toHaveCount(2)
        ->and($assembler->replay())->toBeNull();
});

it('assembles one stable snapshot per revision across every projection', function () {
    $assembler = new AssistantMessageAssembler();
    $assembler->apply(AssistantMessageChunk::textDelta(0, 'one'));
    $assembler->apply(AssistantMessageChunk::toolCallDelta(1, 'call-1', 'lookup', '{"id":1}'));
    $assembler->applyReplay(ReplayEnvelope::fromParts(
        owner: 'test-adapter',
        parts: [null, ['item_id' => 'tool-item-1']],
    ));

    $message = $assembler->message();
    $parts = $assembler->parts();
    $replay = $assembler->replay();
    $toolCalls = $assembler->toolCalls();

    expect($assembler->message())->toBe($message)
        ->and($assembler->parts())->toBe($parts)
        ->and($message->parts())->toBe($parts)
        ->and($assembler->replay())->toBe($replay)
        ->and($assembler->toolCalls())->toBe($toolCalls);

    $assembler->apply(AssistantMessageChunk::textDelta(0, ' two'));

    expect($assembler->message())->not->toBe($message)
        ->and($assembler->parts())->not->toBe($parts)
        ->and($assembler->message()->content()->toString())->toBe('one two')
        ->and($assembler->message())->toBe($assembler->message());
});

it('does not invalidate a snapshot for ignored mutations', function () {
    $assembler = new AssistantMessageAssembler();
    $part = ContentPart::text('authoritative');
    $assembler->apply(AssistantMessageChunk::blockEnd(0, $part));
    $message = $assembler->message();

    $assembler->apply(AssistantMessageChunk::blockStart(0, ContentType::Text));
    $assembler->apply(AssistantMessageChunk::textDelta(0, 'ignored'));
    $assembler->apply(AssistantMessageChunk::blockEnd(0, ContentPart::text('duplicate')));

    expect($assembler->message())->toBe($message);
});

it('projects the active streamed tool directly without parsing incomplete arguments', function () {
    $assembler = new AssistantMessageAssembler();
    $assembler->apply(AssistantMessageChunk::toolCallDelta(0, 'call-1', 'lookup', '{"query":'));

    $toolCall = $assembler->currentToolCall();

    expect($toolCall)->toBeInstanceOf(ToolCall::class)
        ->and($toolCall?->name())->toBe('lookup')
        ->and($toolCall?->arguments())->toBe([])
        ->and($toolCall?->rawArguments())->toBe('{"query":')
        ->and($assembler->currentToolCall())->toBe($toolCall);

    $assembler->apply(AssistantMessageChunk::toolCallDelta(0, arguments: '"Warsaw"}'));
    $updated = $assembler->currentToolCall();

    expect($updated)->not->toBe($toolCall)
        ->and($updated?->rawArguments())->toBe('{"query":"Warsaw"}')
        ->and($assembler->message()->toolCalls()->first()?->arguments())->toBe(['query' => 'Warsaw']);
});
