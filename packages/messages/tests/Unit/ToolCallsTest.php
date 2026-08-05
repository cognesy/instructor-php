<?php

use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCallId;
use Cognesy\Messages\ToolCalls;

it('constructs tool calls from typed objects', function () {
    $toolCalls = new ToolCalls(
        new ToolCall('search', ['q' => 'hello'], new ToolCallId('call_1')),
        new ToolCall('lookup', ['id' => 42], new ToolCallId('call_2')),
    );

    expect($toolCalls->count())->toBe(2)
        ->and($toolCalls->isEmpty())->toBeFalse()
        ->and($toolCalls->first()?->name())->toBe('search')
        ->and($toolCalls->last()?->name())->toBe('lookup');
});

it('hydrates tool calls from canonical arrays', function () {
    $toolCalls = ToolCalls::fromArray([
        ['id' => 'call_1', 'name' => 'search', 'arguments' => ['q' => 'hello']],
        ['id' => 'call_2', 'name' => 'lookup', 'arguments' => ['id' => 42]],
    ]);

    expect($toolCalls->count())->toBe(2)
        ->and($toolCalls->all()[0]->arguments())->toBe(['q' => 'hello']);
});

it('maps and filters tool calls', function () {
    $toolCalls = ToolCalls::fromArray([
        ['id' => 'call_1', 'name' => 'search', 'arguments' => ['q' => 'hello']],
        ['id' => 'call_2', 'name' => 'lookup', 'arguments' => ['id' => 42]],
    ]);

    expect($toolCalls->map(fn (ToolCall $toolCall) => $toolCall->name()))->toBe(['search', 'lookup'])
        ->and($toolCalls->filter(fn (ToolCall $toolCall) => $toolCall->name() === 'lookup')->count())->toBe(1);
});

it('round-trips canonical arrays and renders string output', function () {
    $data = [
        ['id' => 'call_1', 'name' => 'search', 'arguments' => ['q' => 'hello']],
        ['id' => 'call_2', 'name' => 'lookup', 'arguments' => ['id' => 42]],
    ];

    $toolCalls = ToolCalls::fromArray($data);

    expect($toolCalls->toArray())->toBe($data)
        ->and($toolCalls->toString())->toBe('search(q=hello) | lookup(id=42)');
});

it('throws on a list of bare JSON strings with no name', function () {
    expect(fn () => ToolCalls::fromArray(['{"a":1}']))
        ->toThrow(InvalidArgumentException::class, "Tool call at index 0 is a JSON string with no name; use ['toolName' => \$jsonArgs] for the map form, or a full array form ['name' => ..., 'arguments' => ...]");
});

it('hydrates a single tool call from the map form', function () {
    $toolCalls = ToolCalls::fromArray(['myTool' => '{"a":1}']);

    expect($toolCalls->count())->toBe(1)
        ->and($toolCalls->first()?->name())->toBe('myTool')
        ->and($toolCalls->first()?->arguments())->toBe(['a' => 1]);
});

it('hydrates a list of canonical arrays', function () {
    $toolCalls = ToolCalls::fromArray([
        ['id' => '1', 'name' => 'f', 'arguments' => []],
    ]);

    expect($toolCalls->count())->toBe(1)
        ->and($toolCalls->first()?->name())->toBe('f')
        ->and($toolCalls->first()?->arguments())->toBe([]);
});

it('hydrates a mixed map of string and array tool calls', function () {
    $toolCalls = ToolCalls::fromArray([
        'myTool' => '{"a":1}',
        'other' => ['id' => '1', 'name' => 'other', 'arguments' => ['b' => 2]],
    ]);

    expect($toolCalls->count())->toBe(2)
        ->and($toolCalls->all()[0]->name())->toBe('myTool')
        ->and($toolCalls->all()[0]->arguments())->toBe(['a' => 1])
        ->and($toolCalls->all()[1]->name())->toBe('other')
        ->and($toolCalls->all()[1]->arguments())->toBe(['b' => 2]);
});

it('provides a consistent empty collection', function () {
    $toolCalls = ToolCalls::empty();

    expect($toolCalls->isEmpty())->toBeTrue()
        ->and($toolCalls->count())->toBe(0)
        ->and($toolCalls->first())->toBeNull()
        ->and($toolCalls->last())->toBeNull()
        ->and($toolCalls->toArray())->toBe([]);
});
