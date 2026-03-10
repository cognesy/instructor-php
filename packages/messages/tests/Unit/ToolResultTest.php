<?php

use Cognesy\Messages\ToolCallId;
use Cognesy\Messages\ToolResult;

it('constructs success and error tool results', function () {
    $success = ToolResult::success('done', callId: 'call_1', toolName: 'search');
    $error = ToolResult::error('failed', callId: 'call_2', toolName: 'lookup');

    expect($success->isError())->toBeFalse()
        ->and($success->content())->toBe('done')
        ->and($success->callIdString())->toBe('call_1')
        ->and($success->toolName())->toBe('search')
        ->and($error->isError())->toBeTrue()
        ->and($error->content())->toBe('failed')
        ->and($error->callIdString())->toBe('call_2')
        ->and($error->toolName())->toBe('lookup');
});

it('accepts ToolCallId objects in factory methods', function () {
    $id = new ToolCallId('call_1');
    $result = ToolResult::success('done', callId: $id, toolName: 'search');

    expect($result->callId())->toBeInstanceOf(ToolCallId::class)
        ->and($result->callIdString())->toBe('call_1');
});

it('hydrates tool result from canonical arrays', function () {
    $toolResult = ToolResult::fromArray([
        'content' => 'done',
        'call_id' => 'call_1',
        'tool_name' => 'search',
        'is_error' => true,
    ]);

    expect($toolResult->content())->toBe('done')
        ->and($toolResult->callIdString())->toBe('call_1')
        ->and($toolResult->toolName())->toBe('search')
        ->and($toolResult->isError())->toBeTrue();
});

it('round-trips canonical tool result arrays', function () {
    $data = [
        'content' => 'done',
        'call_id' => 'call_1',
        'tool_name' => 'search',
        'is_error' => false,
    ];

    expect(ToolResult::fromArray($data)->toArray())->toBe($data);
});

it('applies sensible defaults when optional fields are absent', function () {
    $toolResult = ToolResult::fromArray(['content' => 'done']);

    expect($toolResult->callId())->toBeNull()
        ->and($toolResult->toolName())->toBeNull()
        ->and($toolResult->isError())->toBeFalse();
});
