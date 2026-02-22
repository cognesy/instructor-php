---
title: 'OpenAI Codex CLI - Streaming'
docname: 'codex_streaming'
id: '9b3e'
---
## Overview

This example demonstrates real-time streaming output from the Codex CLI.
Text and tool calls are displayed as they arrive, with `AgentCtrlConsoleLogger`
providing execution lifecycle visibility alongside the streaming output.

Key concepts:
- `executeStreaming()`: Execute with real-time output
- `onText()` / `onToolUse()`: Callbacks for streaming events
- `AgentCtrlConsoleLogger`: Shows execution lifecycle alongside streaming
- `inDirectory()`: Set working directory for sandbox access

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;

// Console logger for execution lifecycle visibility
$logger = new AgentCtrlConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showToolArgs: true,
);

$toolCalls = [];

echo "=== Agent Execution Log ===\n\n";

$response = AgentCtrl::codex()
    ->wiretap($logger->wiretap())
    ->withSandbox(SandboxMode::ReadOnly)
    ->inDirectory(getcwd())
    ->onText(function (string $text) {
        echo $text;
    })
    ->onToolUse(function (string $tool, array $input, ?string $output) use (&$toolCalls) {
        $target = $input['command'] ?? $input['pattern'] ?? '';
        if (strlen($target) > 40) {
            $target = '...' . substr($target, -37);
        }
        $toolCalls[] = $tool;
        echo "\n  >> [{$tool}] {$target}\n";
    })
    ->executeStreaming('List the files in the current directory and explain what you see.');

echo "\n=== Result ===\n";
echo "Tools used: " . implode(' > ', $toolCalls) . "\n";
echo "Total tool calls: " . count($toolCalls) . "\n";

if ($response->usage) {
    echo "Tokens: {$response->usage->input} in / {$response->usage->output} out\n";
}
if ($response->cost) {
    echo "Cost: $" . number_format($response->cost, 4) . "\n";
}

if (!$response->isSuccess()) {
    echo "Error: Command failed with exit code {$response->exitCode}\n";
    exit(1);
}
?>
```

## Expected Output

```
=== Agent Execution Log ===

14:32:15.123 [codex] [EXEC] Execution started [prompt=List the files in the current directory...]
14:32:15.234 [codex] [PROC] Process started [commands=8]
I'll list the files in the current directory.
14:32:16.234 [codex] [TOOL] bash {command=ls -la}

  >> [bash] ls -la

The directory contains several PHP project files including:
- composer.json for dependency management
- src/ directory with source code
14:32:17.890 [codex] [DONE] Execution completed [exit=0, tools=1, tokens=98]

=== Result ===
Tools used: bash
Total tool calls: 1
Tokens: 42 in / 56 out
```

## Key Points

- **Real-time output**: Text appears as the agent generates it
- **Tool visibility**: See each tool call with arguments as it executes
- **Console logger**: Execution lifecycle events interleaved with streaming output
- **Working directory**: Use `inDirectory()` to set the sandbox working directory
