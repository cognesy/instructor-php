<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Bridge\ClaudeCodeBridge;
use Cognesy\AgentCtrl\Bridge\CodexBridge;
use Cognesy\AgentCtrl\Bridge\OpenCodeBridge;

it('provides actionable diagnostics when claude binary is missing', function () {
    $previousPath = getenv('PATH');
    putenv('PATH=/__agent_ctrl_missing__/bin');

    try {
        $bridge = new ClaudeCodeBridge();
        expect(fn() => $bridge->execute('hello'))
            ->toThrow(RuntimeException::class, 'Claude Code CLI executable `claude` was not found in PATH');
    } finally {
        restorePathEnv($previousPath);
    }
});

it('provides actionable diagnostics when codex binary is missing', function () {
    $previousPath = getenv('PATH');
    putenv('PATH=/__agent_ctrl_missing__/bin');

    try {
        $bridge = new CodexBridge();
        expect(fn() => $bridge->execute('hello'))
            ->toThrow(RuntimeException::class, 'Codex CLI executable `codex` was not found in PATH');
    } finally {
        restorePathEnv($previousPath);
    }
});

it('provides actionable diagnostics when opencode binary is missing', function () {
    $previousPath = getenv('PATH');
    putenv('PATH=/__agent_ctrl_missing__/bin');

    try {
        $bridge = new OpenCodeBridge();
        expect(fn() => $bridge->execute('hello'))
            ->toThrow(RuntimeException::class, 'OpenCode CLI executable `opencode` was not found in PATH');
    } finally {
        restorePathEnv($previousPath);
    }
});

function restorePathEnv(string|false $value): void
{
    if ($value === false) {
        putenv('PATH');
        return;
    }

    putenv('PATH=' . $value);
}
