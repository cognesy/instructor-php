<?php

use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCallId;

it('constructs tool call from typed fields', function () {
    $toolCall = new ToolCall(
        name: 'search',
        arguments: ['q' => 'hello'],
        id: new ToolCallId('call_1'),
    );

    expect($toolCall->id())->toBeInstanceOf(ToolCallId::class)
        ->and($toolCall->idString())->toBe('call_1')
        ->and($toolCall->name())->toBe('search')
        ->and($toolCall->arguments())->toBe(['q' => 'hello']);
});

it('hydrates tool call from canonical arrays with array or json arguments', function () {
    $fromArray = ToolCall::fromArray([
        'id' => 'call_1',
        'name' => 'search',
        'arguments' => ['q' => 'hello'],
    ]);

    $fromJson = ToolCall::fromArray([
        'id' => 'call_2',
        'name' => 'lookup',
        'arguments' => '{"id":42}',
    ]);

    expect($fromArray->arguments())->toBe(['q' => 'hello'])
        ->and($fromArray->idString())->toBe('call_1')
        ->and($fromJson->arguments())->toBe(['id' => 42]);
});

it('round-trips canonical tool call arrays', function () {
    $data = [
        'id' => 'call_1',
        'name' => 'search',
        'arguments' => ['q' => 'hello'],
    ];

    expect(ToolCall::fromArray($data)->toArray())->toBe($data);
});

it('renders arguments as json and string form', function () {
    $toolCall = new ToolCall(
        name: 'search',
        arguments: ['q' => 'hello', 'filters' => ['recent' => true]],
    );

    expect($toolCall->argumentsAsJson())->toBe('{"q":"hello","filters":{"recent":true}}')
        ->and($toolCall->toString())->toBe('search(q=hello, filters={"recent":true})');
});

it('returns updated copies from with mutators', function () {
    $toolCall = new ToolCall(name: 'search');
    $updated = $toolCall
        ->withId('call_1')
        ->withName('lookup')
        ->withArguments('{"id":42}');

    expect($updated->idString())->toBe('call_1')
        ->and($updated->name())->toBe('lookup')
        ->and($updated->arguments())->toBe(['id' => 42]);
});

it('handles null id gracefully', function () {
    $toolCall = new ToolCall(name: 'search');

    expect($toolCall->id())->toBeNull()
        ->and($toolCall->idString())->toBe('');
});
