<?php declare(strict_types=1);

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Builder\ClaudeCodeBridgeBuilder;
use Cognesy\AgentCtrl\Builder\CodexBridgeBuilder;
use Cognesy\AgentCtrl\Builder\OpenCodeBridgeBuilder;
use Cognesy\AgentCtrl\Enum\AgentType;

it('maps agent types to expected builders via make()', function () {
    expect(AgentCtrl::make(AgentType::ClaudeCode))->toBeInstanceOf(ClaudeCodeBridgeBuilder::class)
        ->and(AgentCtrl::make(AgentType::Codex))->toBeInstanceOf(CodexBridgeBuilder::class)
        ->and(AgentCtrl::make(AgentType::OpenCode))->toBeInstanceOf(OpenCodeBridgeBuilder::class);
});

it('provides explicit builder shortcuts', function () {
    expect(AgentCtrl::claudeCode())->toBeInstanceOf(ClaudeCodeBridgeBuilder::class)
        ->and(AgentCtrl::codex())->toBeInstanceOf(CodexBridgeBuilder::class)
        ->and(AgentCtrl::openCode())->toBeInstanceOf(OpenCodeBridgeBuilder::class);
});

it('invokes all streaming callbacks through claudeCode builder', function () {
    withTemporaryAgentBinary('claude', <<<'SH'
#!/bin/sh
printf '%s\n' '{"type":"assistant","session_id":"s1","message":{"role":"assistant","content":[{"type":"text","text":"hello"},{"type":"tool_use","id":"t1","name":"read_file","input":{"path":"README.md"}}]}}'
printf '%s\n' '{"type":"error","error":"warn"}'
exit 0
SH, function () {
        $textEvents = 0;
        $toolEvents = 0;
        $errorEvents = 0;
        $completeEvents = 0;

        AgentCtrl::claudeCode()
            ->onText(function () use (&$textEvents): void { $textEvents++; })
            ->onToolUse(function () use (&$toolEvents): void { $toolEvents++; })
            ->onError(function () use (&$errorEvents): void { $errorEvents++; })
            ->onComplete(function () use (&$completeEvents): void { $completeEvents++; })
            ->executeStreaming('ignored prompt');

        expect($textEvents)->toBeGreaterThan(0)
            ->and($toolEvents)->toBeGreaterThan(0)
            ->and($errorEvents)->toBeGreaterThan(0)
            ->and($completeEvents)->toBe(1);
    });
});

it('invokes all streaming callbacks through codex builder', function () {
    withTemporaryAgentBinary('codex', <<<'SH'
#!/bin/sh
printf '%s\n' '{"type":"thread.started","thread_id":"thread_stream"}'
printf '%s\n' '{"type":"error","message":"warn","code":"WARN"}'
printf '%s\n' '{"type":"item.completed","item":{"id":"msg_1","type":"agent_message","status":"completed","text":"hello"}}'
printf '%s\n' '{"type":"item.completed","item":{"id":"cmd_1","type":"command_execution","status":"completed","command":"echo hi","output":"hi","exit_code":0}}'
exit 0
SH, function () {
        $textEvents = 0;
        $toolEvents = 0;
        $errorEvents = 0;
        $completeEvents = 0;

        AgentCtrl::codex()
            ->onText(function () use (&$textEvents): void { $textEvents++; })
            ->onToolUse(function () use (&$toolEvents): void { $toolEvents++; })
            ->onError(function () use (&$errorEvents): void { $errorEvents++; })
            ->onComplete(function () use (&$completeEvents): void { $completeEvents++; })
            ->executeStreaming('ignored prompt');

        expect($textEvents)->toBeGreaterThan(0)
            ->and($toolEvents)->toBeGreaterThan(0)
            ->and($errorEvents)->toBeGreaterThan(0)
            ->and($completeEvents)->toBe(1);
    });
});

it('invokes all streaming callbacks through opencode builder', function () {
    withTemporaryAgentBinary('opencode', <<<'SH'
#!/bin/sh
printf '%s\n' '{"type":"step_start","timestamp":1,"sessionID":"sess_1","part":{"messageID":"m1","id":"p1","snapshot":"snap"}}'
printf '%s\n' '{"type":"text","timestamp":2,"sessionID":"sess_1","part":{"messageID":"m1","id":"p2","text":"hello","time":{"start":2,"end":3}}}'
printf '%s\n' '{"type":"error","timestamp":3,"sessionID":"sess_1","part":{"error":{"message":"warn","code":"WARN"}}}'
printf '%s\n' '{"type":"tool_use","timestamp":4,"sessionID":"sess_1","part":{"messageID":"m1","id":"p3","callID":"c1","tool":"bash","state":{"status":"completed","input":{"command":"pwd"},"output":{"cwd":"/tmp"},"time":{"start":4,"end":5}}}}'
printf '%s\n' '{"type":"step_finish","timestamp":5,"sessionID":"sess_1","part":{"messageID":"m1","id":"p4","reason":"stop","snapshot":"snap","tokens":{"input":1,"output":1,"reasoning":0,"cache":{"read":0,"write":0}}}}'
exit 0
SH, function () {
        $textEvents = 0;
        $toolEvents = 0;
        $errorEvents = 0;
        $completeEvents = 0;

        AgentCtrl::openCode()
            ->onText(function () use (&$textEvents): void { $textEvents++; })
            ->onToolUse(function () use (&$toolEvents): void { $toolEvents++; })
            ->onError(function () use (&$errorEvents): void { $errorEvents++; })
            ->onComplete(function () use (&$completeEvents): void { $completeEvents++; })
            ->executeStreaming('ignored prompt');

        expect($textEvents)->toBeGreaterThan(0)
            ->and($toolEvents)->toBeGreaterThan(0)
            ->and($errorEvents)->toBeGreaterThan(0)
            ->and($completeEvents)->toBe(1);
    });
});

/**
 * @param callable(): void $run
 */
function withTemporaryAgentBinary(string $binaryName, string $script, callable $run): void
{
    $binDir = sys_get_temp_dir() . '/agent-ctrl-facade-bin-' . uniqid('', true);
    mkdir($binDir);
    $scriptPath = $binDir . '/' . $binaryName;
    file_put_contents($scriptPath, $script);
    chmod($scriptPath, 0755);

    $previousPath = getenv('PATH');
    $previousStdbuf = getenv('COGNESY_STDBUF');
    putenv('PATH=' . $binDir . ':' . ($previousPath === false ? '' : $previousPath));
    putenv('COGNESY_STDBUF=0');

    try {
        $run();
    } finally {
        restoreEnvVar('PATH', $previousPath);
        restoreEnvVar('COGNESY_STDBUF', $previousStdbuf);
        @unlink($scriptPath);
        @rmdir($binDir);
    }
}

function restoreEnvVar(string $key, string|false $value): void
{
    if ($value === false) {
        putenv($key);
        return;
    }

    putenv($key . '=' . $value);
}
