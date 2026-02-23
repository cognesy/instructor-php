<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\Bridge\ClaudeCodeBridge;
use Cognesy\AgentCtrl\Dto\CallbackStreamHandler;
use Cognesy\AgentCtrl\Dto\StreamError;
use Cognesy\AgentCtrl\Dto\ToolCall;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\ValueObject\AgentSessionId;
use Cognesy\AgentCtrl\ValueObject\AgentToolCallId;
use Cognesy\Utils\Json\JsonParsingException;

it('handles claude streaming output including tool results', function () {
    $binDir = sys_get_temp_dir() . '/agent-ctrl-claude-bin-' . uniqid('', true);
    mkdir($binDir);
    $scriptPath = $binDir . '/claude';
    file_put_contents($scriptPath, <<<'SH'
#!/bin/sh
printf '%s\n' '{"type":"assistant","session_id":"claude_session_1","message":{"role":"assistant","content":[{"type":"text","text":"Hello from claude"},{"type":"tool_use","id":"tool_1","name":"read_file","input":{"path":"README.md"}},{"type":"tool_result","tool_use_id":"tool_1","content":"file content","is_error":false}]}}'
printf '%s\n' '{"type":"error","error":"Rate limit warning"}'
exit 0
SH);
    chmod($scriptPath, 0755);

    $previousPath = getenv('PATH');
    $previousStdbuf = getenv('COGNESY_STDBUF');
    putenv('PATH=' . $binDir . ':' . ($previousPath === false ? '' : $previousPath));
    putenv('COGNESY_STDBUF=0');

    $streamText = '';
    $streamTools = [];
    $streamErrors = [];

    try {
        $bridge = new ClaudeCodeBridge();
        $handler = new CallbackStreamHandler(
            onText: function (string $text) use (&$streamText): void {
                $streamText .= $text;
            },
            onToolUse: function (ToolCall $toolCall) use (&$streamTools): void {
                $streamTools[] = $toolCall;
            },
            onError: function (StreamError $error) use (&$streamErrors): void {
                $streamErrors[] = $error;
            },
        );

        $response = $bridge->executeStreaming('ignored prompt', $handler);
    } finally {
        restoreClaudeEnv('PATH', $previousPath);
        restoreClaudeEnv('COGNESY_STDBUF', $previousStdbuf);
        @unlink($scriptPath);
        @rmdir($binDir);
    }

    expect($response->agentType)->toBe(AgentType::ClaudeCode)
        ->and($response->text())->toBe('Hello from claude')
        ->and($response->sessionId())->toBeInstanceOf(AgentSessionId::class)
        ->and((string) ($response->sessionId() ?? ''))->toBe('claude_session_1')
        ->and($response->toolCalls)->toHaveCount(2)
        ->and($response->toolCalls[0]->tool)->toBe('read_file')
        ->and($response->toolCalls[0]->callId())->toBeInstanceOf(AgentToolCallId::class)
        ->and((string) ($response->toolCalls[0]->callId() ?? ''))->toBe('tool_1')
        ->and($response->toolCalls[1]->tool)->toBe('tool_result')
        ->and($response->toolCalls[1]->output)->toBe('file content')
        ->and($response->toolCalls[1]->isError)->toBeFalse()
        ->and((string) ($response->toolCalls[1]->callId() ?? ''))->toBe('tool_1')
        ->and($streamText)->toBe('Hello from claude')
        ->and($streamTools)->toHaveCount(2)
        ->and($streamErrors)->toHaveCount(1)
        ->and($streamErrors[0]->message)->toBe('Rate limit warning');
});

it('throws on malformed claude streaming json line', function () {
    $binDir = sys_get_temp_dir() . '/agent-ctrl-claude-bin-' . uniqid('', true);
    mkdir($binDir);
    $scriptPath = $binDir . '/claude';
    file_put_contents($scriptPath, <<<'SH'
#!/bin/sh
printf '%s\n' 'not-json'
exit 0
SH);
    chmod($scriptPath, 0755);

    $previousPath = getenv('PATH');
    $previousStdbuf = getenv('COGNESY_STDBUF');
    putenv('PATH=' . $binDir . ':' . ($previousPath === false ? '' : $previousPath));
    putenv('COGNESY_STDBUF=0');

    try {
        $bridge = new ClaudeCodeBridge();
        expect(fn() => $bridge->executeStreaming('ignored prompt', new CallbackStreamHandler()))
            ->toThrow(JsonParsingException::class);
    } finally {
        restoreClaudeEnv('PATH', $previousPath);
        restoreClaudeEnv('COGNESY_STDBUF', $previousStdbuf);
        @unlink($scriptPath);
        @rmdir($binDir);
    }
});

function restoreClaudeEnv(string $key, string|false $value): void
{
    if ($value === false) {
        putenv($key);
        return;
    }

    putenv($key . '=' . $value);
}
