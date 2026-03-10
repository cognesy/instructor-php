<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\AgentEndEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\AgentStartEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\ErrorEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\MessageEndEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\MessageStartEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\MessageUpdateEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\SessionEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\ToolExecutionEndEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\ToolExecutionStartEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\TurnEndEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\TurnStartEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\UnknownEvent;

it('dispatches session event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'session',
        'version' => 3,
        'id' => 'abc-123',
        'timestamp' => '2026-01-01T00:00:00Z',
        'cwd' => '/home/user',
    ]);

    expect($event)->toBeInstanceOf(SessionEvent::class)
        ->and($event->type())->toBe('session')
        ->and($event->version)->toBe(3)
        ->and($event->cwd)->toBe('/home/user')
        ->and((string) $event->sessionId())->toBe('abc-123');
});

it('dispatches agent_start event', function () {
    $event = StreamEvent::fromArray(['type' => 'agent_start']);

    expect($event)->toBeInstanceOf(AgentStartEvent::class)
        ->and($event->type())->toBe('agent_start');
});

it('dispatches agent_end event with messages', function () {
    $event = StreamEvent::fromArray([
        'type' => 'agent_end',
        'messages' => [
            ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'hello']]],
            ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'world']], 'usage' => ['input' => 5, 'output' => 3, 'cost' => ['total' => 0.01]]],
        ],
    ]);

    expect($event)->toBeInstanceOf(AgentEndEvent::class)
        ->and($event->assistantText())->toBe('world')
        ->and($event->usage())->not->toBeNull()
        ->and($event->usage()['input'])->toBe(5)
        ->and($event->cost())->toBe(0.01);
});

it('dispatches turn_start event', function () {
    $event = StreamEvent::fromArray(['type' => 'turn_start']);
    expect($event)->toBeInstanceOf(TurnStartEvent::class);
});

it('dispatches turn_end event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'turn_end',
        'message' => ['role' => 'assistant'],
        'toolResults' => [['id' => 'tr_1']],
    ]);

    expect($event)->toBeInstanceOf(TurnEndEvent::class)
        ->and($event->message)->toBe(['role' => 'assistant'])
        ->and($event->toolResults)->toHaveCount(1);
});

it('dispatches message_start event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'message_start',
        'message' => ['role' => 'assistant', 'content' => []],
    ]);

    expect($event)->toBeInstanceOf(MessageStartEvent::class)
        ->and($event->role)->toBe('assistant')
        ->and($event->isAssistant())->toBeTrue()
        ->and($event->isUser())->toBeFalse();
});

it('dispatches message_update text_delta event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'message_update',
        'assistantMessageEvent' => [
            'type' => 'text_delta',
            'contentIndex' => 0,
            'delta' => 'Hello',
        ],
        'message' => ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Hello']]],
    ]);

    expect($event)->toBeInstanceOf(MessageUpdateEvent::class)
        ->and($event->isTextDelta())->toBeTrue()
        ->and($event->isTextStart())->toBeFalse()
        ->and($event->isTextEnd())->toBeFalse()
        ->and($event->textDelta())->toBe('Hello')
        ->and($event->contentIndex)->toBe(0);
});

it('dispatches message_update text_end event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'message_update',
        'assistantMessageEvent' => [
            'type' => 'text_end',
            'content' => 'Full text',
        ],
        'message' => [],
    ]);

    expect($event)->toBeInstanceOf(MessageUpdateEvent::class)
        ->and($event->isTextEnd())->toBeTrue()
        ->and($event->content)->toBe('Full text')
        ->and($event->textDelta())->toBeNull();
});

it('dispatches message_end event with usage', function () {
    $event = StreamEvent::fromArray([
        'type' => 'message_end',
        'message' => [
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => 'Done']],
            'usage' => ['input' => 10, 'output' => 5],
        ],
    ]);

    expect($event)->toBeInstanceOf(MessageEndEvent::class)
        ->and($event->isAssistant())->toBeTrue()
        ->and($event->text())->toBe('Done')
        ->and($event->usage())->not->toBeNull()
        ->and($event->usage()['input'])->toBe(10);
});

it('dispatches tool_execution_start event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'tool_execution_start',
        'toolCallId' => 'call_1',
        'toolName' => 'bash',
        'args' => ['command' => 'ls -la'],
    ]);

    expect($event)->toBeInstanceOf(ToolExecutionStartEvent::class)
        ->and($event->toolCallId)->toBe('call_1')
        ->and($event->toolName)->toBe('bash')
        ->and($event->args)->toBe(['command' => 'ls -la']);
});

it('dispatches tool_execution_end event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'tool_execution_end',
        'toolCallId' => 'call_1',
        'toolName' => 'bash',
        'result' => 'file1.txt',
        'isError' => false,
    ]);

    expect($event)->toBeInstanceOf(ToolExecutionEndEvent::class)
        ->and($event->toolCallId)->toBe('call_1')
        ->and($event->toolName)->toBe('bash')
        ->and($event->result)->toBe('file1.txt')
        ->and($event->isError)->toBeFalse()
        ->and($event->resultAsString())->toBe('file1.txt');
});

it('dispatches tool_execution_end event with error', function () {
    $event = StreamEvent::fromArray([
        'type' => 'tool_execution_end',
        'toolCallId' => 'call_2',
        'toolName' => 'write',
        'result' => 'Permission denied',
        'isError' => true,
    ]);

    expect($event)->toBeInstanceOf(ToolExecutionEndEvent::class)
        ->and($event->isError)->toBeTrue();
});

it('dispatches tool_execution_end with array result', function () {
    $event = StreamEvent::fromArray([
        'type' => 'tool_execution_end',
        'toolCallId' => 'call_3',
        'toolName' => 'read',
        'result' => ['content' => 'data', 'lines' => 10],
        'isError' => false,
    ]);

    expect($event)->toBeInstanceOf(ToolExecutionEndEvent::class)
        ->and($event->result)->toBe(['content' => 'data', 'lines' => 10])
        ->and($event->resultAsString())->toBe('{"content":"data","lines":10}');
});

it('dispatches error event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'error',
        'message' => 'Something went wrong',
        'code' => 'RATE_LIMIT',
    ]);

    expect($event)->toBeInstanceOf(ErrorEvent::class)
        ->and($event->message)->toBe('Something went wrong')
        ->and($event->code)->toBe('RATE_LIMIT');
});

it('dispatches unknown event for unrecognized types', function () {
    $event = StreamEvent::fromArray([
        'type' => 'auto_compaction_start',
        'reason' => 'threshold',
    ]);

    expect($event)->toBeInstanceOf(UnknownEvent::class)
        ->and($event->type())->toBe('auto_compaction_start')
        ->and($event->rawData)->toHaveKey('reason');
});
