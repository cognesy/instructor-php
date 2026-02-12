---
title: 'Claude Code CLI - Agentic Search'
docname: 'claude_code_search'
id: 'e4a3'
---
## Overview

This example demonstrates the agentic capabilities of Claude Code CLI by having
it search through the codebase to find and explain validation examples. The
`AgentCtrlConsoleLogger` provides full visibility into tool calls, streaming,
and the agent's decision-making process.

Key concepts:
- `AgentCtrl::claudeCode()`: Factory for Claude Code agent builder
- `withMaxTurns()`: Allow multiple turns for exploration
- `onText()` / `onToolUse()`: Real-time streaming callbacks
- `AgentCtrlConsoleLogger`: Shows execution lifecycle alongside streaming output

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;

// Console logger for execution lifecycle visibility
$logger = new AgentCtrlConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showToolArgs: true,   // Show tool input args
    showSandbox: true,    // Show sandbox setup
);

$toolCalls = [];

echo "=== Agent Execution Log ===\n\n";

$response = AgentCtrl::claudeCode()
    ->wiretap($logger->wiretap())
    ->withMaxTurns(10)
    ->onText(function (string $text) {
        echo $text;
    })
    ->onToolUse(function (string $tool, array $input, ?string $output) use (&$toolCalls) {
        $target = $input['pattern'] ?? $input['file_path'] ?? $input['command'] ?? '';
        if (strlen($target) > 50) {
            $target = '...' . substr($target, -47);
        }
        $toolCalls[] = $tool;
        echo "\n  >> [{$tool}] {$target}\n";
    })
    ->executeStreaming(<<<'PROMPT'
Complete this task in steps:
1. Use Glob or find command to locate PHP files with "validation" in the filename under ./examples
2. Read the contents of relevant PHP file
3. Analyze the code and provide a concise explanation (under 200 words) covering:
   - What validation is being performed
   - What validation constraints/attributes are used
   - How validation is triggered
   - What happens when validation fails

Provide your final explanation as a clear, structured response.
PROMPT);

echo "\n=== Result ===\n";
echo "Tools used: " . implode(' > ', $toolCalls) . "\n";
echo "Total tool calls: " . count($toolCalls) . "\n";
echo "Exit code: {$response->exitCode}\n";

if ($response->usage) {
    echo "Tokens: {$response->usage->input} in / {$response->usage->output} out\n";
}
if ($response->cost) {
    echo "Cost: $" . number_format($response->cost, 4) . "\n";
}

if (!$response->isSuccess()) {
    echo "Error: Agent search failed with exit code {$response->exitCode}\n";
    exit(1);
}
?>
```

## Expected Output

```
=== Agent Execution Log ===

14:32:15.123 [claude-code] [EXEC] Execution started [prompt=Complete this task in steps:...]
14:32:15.234 [claude-code] [SBOX] Policy configured [driver=host, timeout=120s, network=on]
14:32:15.235 [claude-code] [SBOX] Ready [driver=host, setup=1ms]
14:32:15.236 [claude-code] [PROC] Process started [commands=12]
I'll search for PHP files with "validation" in the filename under ./examples.
14:32:16.456 [claude-code] [TOOL] Glob {pattern=./examples/**/*validation*.php}

  >> [Glob] ./examples/**/*validation*.php

Found a validation example. Let me read it.
14:32:17.234 [claude-code] [TOOL] Read {file_path=./examples/A01_Basics/Validation/run.php}

  >> [Read] ./examples/A01_Basics/Validation/run.php

The validation example demonstrates...
14:32:19.890 [claude-code] [DONE] Execution completed [exit=0, tools=2]

=== Result ===
Tools used: Glob > Read
Total tool calls: 2
Exit code: 0
```

## Key Points

- **Agentic search**: Agent autonomously explores files and synthesizes answers
- **Full visibility**: Console logger shows every tool call alongside streaming output
- **Sandbox awareness**: Enable `showSandbox` to see sandbox initialization details
- **Multi-turn**: `withMaxTurns(10)` allows the agent to explore iteratively
