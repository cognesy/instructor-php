<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\Bridge\OpenCodeBridge;
use Cognesy\AgentCtrl\Dto\CallbackStreamHandler;
use Cognesy\AgentCtrl\Dto\StreamError;
use Cognesy\AgentCtrl\Dto\ToolCall;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\ValueObject\AgentSessionId;
use Cognesy\AgentCtrl\ValueObject\AgentToolCallId;
use Cognesy\Utils\Json\JsonParsingException;

it('handles opencode streaming output and normalizes ids', function () {
    $binDir = sys_get_temp_dir() . '/agent-ctrl-opencode-bin-' . uniqid('', true);
    mkdir($binDir);
    $scriptPath = $binDir . '/opencode';
    file_put_contents($scriptPath, <<<'SH'
#!/bin/sh
printf '%s\n' '{"type":"step_start","timestamp":1,"sessionID":"sess_stream","part":{"messageID":"msg_1","id":"part_1","snapshot":"snap"}}'
printf '%s\n' '{"type":"text","timestamp":2,"sessionID":"sess_stream","part":{"messageID":"msg_1","id":"part_2","text":"Hello OpenCode","time":{"start":2,"end":3}}}'
printf '%s\n' '{"type":"error","timestamp":2,"sessionID":"sess_stream","part":{"error":{"message":"Temporary upstream issue","code":"UPSTREAM_TEMP"}}}'
printf '%s\n' '{"type":"tool_use","timestamp":3,"sessionID":"sess_stream","part":{"messageID":"msg_1","id":"part_3","callID":"call_1","tool":"bash","state":{"status":"completed","input":{"command":"pwd"},"output":{"cwd":"/tmp"},"time":{"start":3,"end":4}}}}'
printf '%s\n' '{"type":"step_finish","timestamp":4,"sessionID":"sess_stream","part":{"messageID":"msg_1","id":"part_4","reason":"stop","snapshot":"snap","cost":0.42,"tokens":{"input":11,"output":5,"reasoning":1,"cache":{"read":2,"write":1}}}}'
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
        $bridge = new OpenCodeBridge();
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
        restoreOpenCodeEnv('PATH', $previousPath);
        restoreOpenCodeEnv('COGNESY_STDBUF', $previousStdbuf);
        @unlink($scriptPath);
        @rmdir($binDir);
    }

    expect($response->agentType)->toBe(AgentType::OpenCode)
        ->and($response->text())->toBe('Hello OpenCode')
        ->and($response->sessionId())->toBeInstanceOf(AgentSessionId::class)
        ->and((string) ($response->sessionId() ?? ''))->toBe('sess_stream')
        ->and($response->usage()?->input)->toBe(11)
        ->and($response->usage()?->output)->toBe(5)
        ->and($response->usage()?->reasoning)->toBe(1)
        ->and($response->usage()?->cacheRead)->toBe(2)
        ->and($response->usage()?->cacheWrite)->toBe(1)
        ->and($response->cost())->toBe(0.42)
        ->and($response->toolCalls)->toHaveCount(1)
        ->and($response->toolCalls[0]->tool)->toBe('bash')
        ->and($response->toolCalls[0]->callId())->toBeInstanceOf(AgentToolCallId::class)
        ->and((string) ($response->toolCalls[0]->callId() ?? ''))->toBe('call_1')
        ->and($response->toolCalls[0]->output)->toBe('{"cwd":"\\/tmp"}')
        ->and($streamText)->toBe('Hello OpenCode')
        ->and($streamTools)->toHaveCount(1)
        ->and($streamErrors)->toHaveCount(1)
        ->and($streamErrors[0]->message)->toBe('Temporary upstream issue')
        ->and($streamErrors[0]->code)->toBe('UPSTREAM_TEMP');
});

it('throws on malformed opencode streaming json line', function () {
    $binDir = sys_get_temp_dir() . '/agent-ctrl-opencode-bin-' . uniqid('', true);
    mkdir($binDir);
    $scriptPath = $binDir . '/opencode';
    file_put_contents($scriptPath, <<<'SH'
#!/bin/sh
printf '%s\n' '{"type":"step_start","timestamp":1,"sessionID":"sess_stream","part":{"messageID":"msg_1","id":"part_1","snapshot":"snap"}}'
printf '%s\n' 'not-json'
exit 0
SH);
    chmod($scriptPath, 0755);

    $previousPath = getenv('PATH');
    $previousStdbuf = getenv('COGNESY_STDBUF');
    putenv('PATH=' . $binDir . ':' . ($previousPath === false ? '' : $previousPath));
    putenv('COGNESY_STDBUF=0');

    try {
        $bridge = new OpenCodeBridge();
        expect(fn() => $bridge->executeStreaming('ignored prompt', new CallbackStreamHandler()))
            ->toThrow(JsonParsingException::class);
    } finally {
        restoreOpenCodeEnv('PATH', $previousPath);
        restoreOpenCodeEnv('COGNESY_STDBUF', $previousStdbuf);
        @unlink($scriptPath);
        @rmdir($binDir);
    }
});

it('can disable fail-fast for malformed opencode streaming json line', function () {
    $binDir = sys_get_temp_dir() . '/agent-ctrl-opencode-bin-' . uniqid('', true);
    mkdir($binDir);
    $scriptPath = $binDir . '/opencode';
    file_put_contents($scriptPath, <<<'SH'
#!/bin/sh
printf '%s\n' '{"type":"step_start","timestamp":1,"sessionID":"sess_stream","part":{"messageID":"msg_1","id":"part_1","snapshot":"snap"}}'
printf '%s\n' 'not-json'
printf '%s\n' '{"type":"text","timestamp":2,"sessionID":"sess_stream","part":{"messageID":"msg_1","id":"part_2","text":"Hello OpenCode","time":{"start":2,"end":3}}}'
exit 0
SH);
    chmod($scriptPath, 0755);

    $previousPath = getenv('PATH');
    $previousStdbuf = getenv('COGNESY_STDBUF');
    putenv('PATH=' . $binDir . ':' . ($previousPath === false ? '' : $previousPath));
    putenv('COGNESY_STDBUF=0');

    try {
        $bridge = new OpenCodeBridge(failFast: false);
        $response = $bridge->executeStreaming('ignored prompt', new CallbackStreamHandler());
    } finally {
        restoreOpenCodeEnv('PATH', $previousPath);
        restoreOpenCodeEnv('COGNESY_STDBUF', $previousStdbuf);
        @unlink($scriptPath);
        @rmdir($binDir);
    }

    expect($response->text())->toBe('Hello OpenCode')
        ->and($response->parseFailures())->toBe(1)
        ->and($response->parseFailureSamples())->toHaveCount(1);
});

function restoreOpenCodeEnv(string $key, string|false $value): void
{
    if ($value === false) {
        putenv($key);
        return;
    }

    putenv($key . '=' . $value);
}
