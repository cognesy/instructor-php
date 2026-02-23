<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\Bridge\CodexBridge;
use Cognesy\AgentCtrl\Dto\CallbackStreamHandler;
use Cognesy\AgentCtrl\Dto\StreamError;
use Cognesy\AgentCtrl\Dto\ToolCall;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\ValueObject\AgentSessionId;
use Cognesy\AgentCtrl\ValueObject\AgentToolCallId;
use Cognesy\Utils\Json\JsonParsingException;

it('handles codex streaming output and normalizes ids', function () {
    $binDir = sys_get_temp_dir() . '/agent-ctrl-codex-bin-' . uniqid('', true);
    mkdir($binDir);
    $scriptPath = $binDir . '/codex';
    file_put_contents($scriptPath, <<<'SH'
#!/bin/sh
printf '%s\n' '{"type":"thread.started","thread_id":"thread_stream"}'
printf '%s\n' '{"type":"error","message":"Transient provider warning","code":"WARN_TRANSIENT"}'
printf '%s\n' '{"type":"item.completed","item":{"id":"msg_1","type":"agent_message","status":"completed","text":"Hello from codex"}}'
printf '%s\n' '{"type":"item.completed","item":{"id":"cmd_1","type":"command_execution","status":"completed","command":"echo hi","output":"hi","exit_code":0}}'
printf '%s\n' '{"type":"item.completed","item":{"id":"tool_1","type":"mcp_tool_call","status":"completed","server":"srv","tool":"lookup","arguments":{"q":"x"},"result":{"value":1}}}'
printf '%s\n' '{"type":"item.completed","item":{"id":"file_1","type":"file_change","status":"completed","path":"src/main.rs","action":"modify","diff":"@@ -1 +1 @@\n-foo\n+bar"}}'
printf '%s\n' '{"type":"item.completed","item":{"id":"web_1","type":"web_search","status":"completed","query":"rust async patterns","results":[{"url":"https://example.com","title":"Rust Async"}]}}'
printf '%s\n' '{"type":"item.completed","item":{"id":"plan_1","type":"plan_update","status":"completed","plan":"1. Review\n2. Fix"}}'
printf '%s\n' '{"type":"item.completed","item":{"id":"reason_1","type":"reasoning","status":"completed","text":"Analyzing failure."}}'
printf '%s\n' '{"type":"item.completed","item":{"id":"unknown_1","type":"mystery_type","status":"completed","payload":"raw"}}'
printf '%s\n' '{"type":"turn.completed","usage":{"input_tokens":9,"cached_input_tokens":3,"output_tokens":2}}'
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
        $bridge = new CodexBridge();
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
        restoreEnv('PATH', $previousPath);
        restoreEnv('COGNESY_STDBUF', $previousStdbuf);
        @unlink($scriptPath);
        @rmdir($binDir);
    }

    expect($response->agentType)->toBe(AgentType::Codex)
        ->and($response->text())->toBe('Hello from codex')
        ->and($response->sessionId())->toBeInstanceOf(AgentSessionId::class)
        ->and((string) ($response->sessionId() ?? ''))->toBe('thread_stream')
        ->and($response->usage()?->input)->toBe(9)
        ->and($response->usage()?->output)->toBe(2)
        ->and($response->usage()?->cacheRead)->toBe(3)
        ->and($response->toolCalls)->toHaveCount(7)
        ->and($response->toolCalls[0]->tool)->toBe('bash')
        ->and($response->toolCalls[0]->callId())->toBeInstanceOf(AgentToolCallId::class)
        ->and((string) ($response->toolCalls[0]->callId() ?? ''))->toBe('cmd_1')
        ->and($response->toolCalls[1]->tool)->toBe('lookup')
        ->and($response->toolCalls[1]->callId())->toBeInstanceOf(AgentToolCallId::class)
        ->and((string) ($response->toolCalls[1]->callId() ?? ''))->toBe('tool_1')
        ->and($response->toolCalls[2]->tool)->toBe('file_change')
        ->and($response->toolCalls[3]->tool)->toBe('web_search')
        ->and($response->toolCalls[4]->tool)->toBe('plan_update')
        ->and($response->toolCalls[5]->tool)->toBe('reasoning')
        ->and($response->toolCalls[6]->tool)->toBe('mystery_type')
        ->and($streamText)->toBe('Hello from codex')
        ->and($streamTools)->toHaveCount(7)
        ->and($streamErrors)->toHaveCount(1)
        ->and($streamErrors[0]->message)->toBe('Transient provider warning')
        ->and($streamErrors[0]->code)->toBe('WARN_TRANSIENT');
});

it('throws on malformed codex streaming json line', function () {
    $binDir = sys_get_temp_dir() . '/agent-ctrl-codex-bin-' . uniqid('', true);
    mkdir($binDir);
    $scriptPath = $binDir . '/codex';
    file_put_contents($scriptPath, <<<'SH'
#!/bin/sh
printf '%s\n' '{"type":"thread.started","thread_id":"thread_stream"}'
printf '%s\n' 'not-json'
exit 0
SH);
    chmod($scriptPath, 0755);

    $previousPath = getenv('PATH');
    $previousStdbuf = getenv('COGNESY_STDBUF');
    putenv('PATH=' . $binDir . ':' . ($previousPath === false ? '' : $previousPath));
    putenv('COGNESY_STDBUF=0');

    try {
        $bridge = new CodexBridge();
        expect(fn() => $bridge->executeStreaming('ignored prompt', new CallbackStreamHandler()))
            ->toThrow(JsonParsingException::class);
    } finally {
        restoreEnv('PATH', $previousPath);
        restoreEnv('COGNESY_STDBUF', $previousStdbuf);
        @unlink($scriptPath);
        @rmdir($binDir);
    }
});

it('can disable fail-fast for malformed codex streaming json line', function () {
    $binDir = sys_get_temp_dir() . '/agent-ctrl-codex-bin-' . uniqid('', true);
    mkdir($binDir);
    $scriptPath = $binDir . '/codex';
    file_put_contents($scriptPath, <<<'SH'
#!/bin/sh
printf '%s\n' '{"type":"thread.started","thread_id":"thread_stream"}'
printf '%s\n' 'not-json'
printf '%s\n' '{"type":"item.completed","item":{"id":"msg_1","type":"agent_message","status":"completed","text":"Hello from codex"}}'
exit 0
SH);
    chmod($scriptPath, 0755);

    $previousPath = getenv('PATH');
    $previousStdbuf = getenv('COGNESY_STDBUF');
    putenv('PATH=' . $binDir . ':' . ($previousPath === false ? '' : $previousPath));
    putenv('COGNESY_STDBUF=0');

    try {
        $bridge = new CodexBridge(failFast: false);
        $response = $bridge->executeStreaming('ignored prompt', new CallbackStreamHandler());
    } finally {
        restoreEnv('PATH', $previousPath);
        restoreEnv('COGNESY_STDBUF', $previousStdbuf);
        @unlink($scriptPath);
        @rmdir($binDir);
    }

    expect($response->text())->toBe('Hello from codex')
        ->and($response->parseFailures())->toBe(1)
        ->and($response->parseFailureSamples())->toHaveCount(1);
});

function restoreEnv(string $key, string|false $value): void
{
    if ($value === false) {
        putenv($key);
        return;
    }

    putenv($key . '=' . $value);
}
