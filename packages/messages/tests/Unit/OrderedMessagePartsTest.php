<?php

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Message;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Messages\ToolResult;

it('keeps one authoritative ordered sequence for an assistant turn', function () {
    $rawArguments = "{\n  \"city\": \"Warsaw\", \"unit\": \"c\"\n}";
    $toolCall = ToolCall::fromArray([
        'id' => 'call_weather',
        'name' => 'weather',
        'arguments' => $rawArguments,
    ]);

    $message = new Message(
        role: 'assistant',
        parts: new ContentParts(
            ContentPart::reasoning('Need current conditions.'),
            ContentPart::text('I will check.'),
            ContentPart::toolCall($toolCall),
            ContentPart::text('One moment.'),
        ),
    );

    expect($message->parts()->map(fn(ContentPart $part) => $part->type()))
        ->toBe(['reasoning', 'text', 'tool_call', 'text'])
        ->and($message->content()->toArray())->toBe([
            ['type' => 'text', 'text' => 'I will check.'],
            ['type' => 'text', 'text' => 'One moment.'],
        ])
        ->and($message->reasoningContent())->toBe('Need current conditions.')
        ->and($message->toolCalls()->first()?->argsAsJson())->toBe($rawArguments);
});

it('round trips canonical parts and raw tool arguments without reordering', function () {
    $rawArguments = '{ "b": 2, "a": 1 }';
    $message = new Message(
        role: 'assistant',
        parts: new ContentParts(
            ContentPart::reasoning('First'),
            ContentPart::toolCall(ToolCall::fromArray([
                'id' => 'call_1',
                'name' => 'ordered',
                'arguments' => $rawArguments,
            ])),
            ContentPart::text('After'),
        ),
    );

    $hydrated = Message::fromArray($message->toArray());

    expect($hydrated->parts()->toArray())->toBe($message->parts()->toArray())
        ->and($hydrated->toolCalls()->first()?->argsAsJson())->toBe($rawArguments)
        ->and($hydrated->content()->toString())->toBe('After')
        ->and($hydrated->reasoningContent())->toBe('First');
});

it('rejects flattened tool fields instead of guessing block order', function (array $message) {
    expect(fn() => Message::fromArray($message))
        ->toThrow(InvalidArgumentException::class, 'encode tool blocks in parts');
})->with([
    'tool calls' => [[
        'role' => 'assistant',
        'content' => '',
        'tool_calls' => [],
    ]],
    'tool result' => [[
        'role' => 'tool',
        'content' => '{"ok":true}',
        'tool_result' => ['call_id' => 'call_1'],
    ]],
    'metadata tool fields' => [[
        'role' => 'assistant',
        'content' => '',
        '_metadata' => ['tool_calls' => []],
    ]],
]);

it('serializes parts as the only semantic message payload', function () {
    $message = new Message(
        role: 'assistant',
        parts: new ContentParts(
            ContentPart::text('Before'),
            ContentPart::toolCall(new ToolCall('lookup', ['id' => 42], 'call_1')),
        ),
    );

    expect($message->toArray())
        ->toHaveKeys(['id', 'createdAt', 'role', 'parts'])
        ->not->toHaveKeys(['content', 'tool_calls', 'tool_result'])
        ->and($message->parts()->map(fn(ContentPart $part) => $part->type()))
        ->toBe(['text', 'tool_call']);
});

it('replaces tool-call projections by rewriting canonical parts', function () {
    $original = new Message(
        role: 'assistant',
        parts: new ContentParts(
            ContentPart::reasoning('why'),
            ContentPart::text('before'),
            ContentPart::toolCall(new ToolCall('old', [], 'call_old')),
            ContentPart::text('after'),
        ),
    );

    $updated = $original->withToolCalls(
        new ToolCalls(new ToolCall('new', ['x' => 1], 'call_new')),
    );

    expect($updated->parts()->map(fn(ContentPart $part) => $part->type()))
        ->toBe(['reasoning', 'text', 'tool_call', 'text'])
        ->and($updated->toolCalls()->first()?->name())->toBe('new')
        ->and($updated->reasoningContent())->toBe('why')
        ->and($updated->content()->toString())->toBe("before\nafter")
        ->and($original->toolCalls()->first()?->name())->toBe('old');
});

it('merges complete ordered turns and still refuses tool results', function () {
    $first = new Message(
        role: 'assistant',
        parts: new ContentParts(
            ContentPart::text('a'),
            ContentPart::toolCall(new ToolCall('first', [], 'call_1')),
        ),
    );
    $second = new Message(
        role: 'assistant',
        parts: new ContentParts(
            ContentPart::reasoning('b'),
            ContentPart::text('c'),
        ),
    );

    $merged = $first->withMergedFrom($second);

    expect($merged->parts()->map(fn(ContentPart $part) => $part->type()))
        ->toBe(['text', 'tool_call', 'reasoning', 'text']);

    $withResult = (new Message('tool', 'result'))->withToolResult(
        ToolResult::success('result', 'call_1'),
    );
    expect(fn() => $first->withMergedFrom($withResult))
        ->toThrow(InvalidArgumentException::class, 'tool result');
});
