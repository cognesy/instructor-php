<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\ErrorEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\InitEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\MessageEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\ResultEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\ToolResultEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\ToolUseEvent;
use Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent\UnknownEvent;

it('dispatches init event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'init',
        'session_id' => 'sess-abc-123',
        'model' => 'gemini-2.5-pro',
        'timestamp' => '2025-01-01T00:00:00Z',
    ]);

    expect($event)->toBeInstanceOf(InitEvent::class)
        ->and($event->type())->toBe('init')
        ->and($event->sessionId())->not->toBeNull()
        ->and($event->sessionId()->toString())->toBe('sess-abc-123')
        ->and($event->model)->toBe('gemini-2.5-pro')
        ->and($event->timestamp)->toBe('2025-01-01T00:00:00Z');
});

it('dispatches assistant message delta event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'message',
        'role' => 'assistant',
        'content' => 'Hello ',
        'delta' => true,
        'timestamp' => '2025-01-01T00:00:01Z',
    ]);

    expect($event)->toBeInstanceOf(MessageEvent::class)
        ->and($event->type())->toBe('message')
        ->and($event->isAssistant())->toBeTrue()
        ->and($event->isUser())->toBeFalse()
        ->and($event->isDelta())->toBeTrue()
        ->and($event->textDelta())->toBe('Hello ')
        ->and($event->content)->toBe('Hello ');
});

it('dispatches user message event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'message',
        'role' => 'user',
        'content' => 'What is 2+2?',
        'delta' => false,
        'timestamp' => '2025-01-01T00:00:00Z',
    ]);

    expect($event)->toBeInstanceOf(MessageEvent::class)
        ->and($event->isUser())->toBeTrue()
        ->and($event->isAssistant())->toBeFalse()
        ->and($event->isDelta())->toBeFalse()
        ->and($event->textDelta())->toBeNull();
});

it('dispatches tool_use event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'tool_use',
        'tool_name' => 'read_file',
        'tool_id' => 'call_abc123',
        'parameters' => ['path' => 'composer.json'],
        'timestamp' => '2025-01-01T00:00:02Z',
    ]);

    expect($event)->toBeInstanceOf(ToolUseEvent::class)
        ->and($event->type())->toBe('tool_use')
        ->and($event->toolName)->toBe('read_file')
        ->and($event->toolId)->toBe('call_abc123')
        ->and($event->parameters)->toBe(['path' => 'composer.json']);
});

it('dispatches tool_result success event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'tool_result',
        'tool_id' => 'call_abc123',
        'status' => 'success',
        'output' => '{"name": "my-project"}',
        'timestamp' => '2025-01-01T00:00:03Z',
    ]);

    expect($event)->toBeInstanceOf(ToolResultEvent::class)
        ->and($event->type())->toBe('tool_result')
        ->and($event->toolId)->toBe('call_abc123')
        ->and($event->isSuccess())->toBeTrue()
        ->and($event->isError())->toBeFalse()
        ->and($event->resultAsString())->toBe('{"name": "my-project"}');
});

it('dispatches tool_result error event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'tool_result',
        'tool_id' => 'call_def456',
        'status' => 'error',
        'error' => ['type' => 'not_found', 'message' => 'File not found'],
        'timestamp' => '2025-01-01T00:00:03Z',
    ]);

    expect($event)->toBeInstanceOf(ToolResultEvent::class)
        ->and($event->isError())->toBeTrue()
        ->and($event->isSuccess())->toBeFalse()
        ->and($event->resultAsString())->toBe('File not found');
});

it('dispatches error event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'error',
        'severity' => 'error',
        'message' => 'Rate limit exceeded',
        'timestamp' => '2025-01-01T00:00:04Z',
    ]);

    expect($event)->toBeInstanceOf(ErrorEvent::class)
        ->and($event->type())->toBe('error')
        ->and($event->severity)->toBe('error')
        ->and($event->isError())->toBeTrue()
        ->and($event->isWarning())->toBeFalse()
        ->and($event->message)->toBe('Rate limit exceeded');
});

it('dispatches warning error event', function () {
    $event = StreamEvent::fromArray([
        'type' => 'error',
        'severity' => 'warning',
        'message' => 'Tool deprecated',
        'timestamp' => '2025-01-01T00:00:04Z',
    ]);

    expect($event)->toBeInstanceOf(ErrorEvent::class)
        ->and($event->isWarning())->toBeTrue()
        ->and($event->isError())->toBeFalse();
});

it('dispatches result event with stats', function () {
    $event = StreamEvent::fromArray([
        'type' => 'result',
        'status' => 'success',
        'stats' => [
            'total_tokens' => 150,
            'input_tokens' => 50,
            'output_tokens' => 100,
            'cached' => 10,
            'duration_ms' => 2500,
            'tool_calls' => 3,
        ],
        'timestamp' => '2025-01-01T00:00:05Z',
    ]);

    expect($event)->toBeInstanceOf(ResultEvent::class)
        ->and($event->type())->toBe('result')
        ->and($event->isSuccess())->toBeTrue()
        ->and($event->isError())->toBeFalse()
        ->and($event->inputTokens())->toBe(50)
        ->and($event->outputTokens())->toBe(100)
        ->and($event->cachedTokens())->toBe(10)
        ->and($event->totalTokens())->toBe(150)
        ->and($event->durationMs())->toBe(2500)
        ->and($event->toolCallCount())->toBe(3);
});

it('dispatches result event with error status', function () {
    $event = StreamEvent::fromArray([
        'type' => 'result',
        'status' => 'error',
        'error' => ['type' => 'auth', 'message' => 'Invalid API key'],
        'stats' => [],
        'timestamp' => '2025-01-01T00:00:05Z',
    ]);

    expect($event)->toBeInstanceOf(ResultEvent::class)
        ->and($event->isError())->toBeTrue()
        ->and($event->error)->toBe(['type' => 'auth', 'message' => 'Invalid API key']);
});

it('dispatches unknown event for unrecognized types', function () {
    $event = StreamEvent::fromArray([
        'type' => 'custom_event',
        'data' => 'something',
    ]);

    expect($event)->toBeInstanceOf(UnknownEvent::class)
        ->and($event->type())->toBe('custom_event')
        ->and($event->rawType)->toBe('custom_event');
});

it('dispatches unknown event for missing type', function () {
    $event = StreamEvent::fromArray(['data' => 'test']);

    expect($event)->toBeInstanceOf(UnknownEvent::class)
        ->and($event->type())->toBe('unknown');
});
