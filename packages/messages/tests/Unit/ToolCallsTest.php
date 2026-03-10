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

it('provides a consistent empty collection', function () {
    $toolCalls = ToolCalls::empty();

    expect($toolCalls->isEmpty())->toBeTrue()
        ->and($toolCalls->count())->toBe(0)
        ->and($toolCalls->first())->toBeNull()
        ->and($toolCalls->last())->toBeNull()
        ->and($toolCalls->toArray())->toBe([]);
});
