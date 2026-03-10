---
title: Claude Code Bridge
description: 'Use the Claude Code bridge for general coding workflows with system prompts, turn limits, permission controls, verbose streaming, and multi-directory access.'
---

## Overview

The Claude Code bridge wraps Anthropic's `claude` CLI, providing access to Claude's code-generation and reasoning capabilities through Agent-Ctrl's unified API. Claude Code is a strong default choice for general coding workflows, tool-heavy tasks, and scenarios where you want fine-grained control over the agent's system prompt and permission behavior.

The bridge is implemented by `ClaudeCodeBridge` and configured through `ClaudeCodeBridgeBuilder`. Access the builder through the `AgentCtrl` facade:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

// Dedicated factory method
$builder = AgentCtrl::claudeCode();

// Or via the generic factory
$builder = AgentCtrl::make(AgentType::ClaudeCode);
```

## Basic Usage

The simplest Claude Code interaction requires just a prompt:

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::claudeCode()
    ->execute('Review this package and summarize the design.');

echo $response->text();
```

With model selection:

```php
$response = AgentCtrl::claudeCode()
    ->withModel('claude-sonnet-4-5')
    ->execute('Explain the architecture of this project.');

echo $response->text();
```

## System Prompts

Claude Code supports two complementary approaches to system prompt configuration, giving you precise control over the agent's behavior.

### Replacing the System Prompt

Use `withSystemPrompt()` to completely replace the default system prompt with your own. The agent will follow only your instructions, without the built-in Claude Code behavior:

```php
$response = AgentCtrl::claudeCode()
    ->withSystemPrompt('You are a security auditor. Focus exclusively on identifying vulnerabilities, injection risks, and authentication weaknesses.')
    ->execute('Audit the authentication module.');
```

### Appending to the System Prompt

Use `appendSystemPrompt()` to add instructions on top of the default system prompt. This preserves Claude Code's built-in capabilities (file reading, code editing, command execution) while layering in your project-specific context:

```php
$response = AgentCtrl::claudeCode()
    ->appendSystemPrompt('This project uses Laravel conventions. Follow PSR-12 coding standards. Always add type declarations to method signatures.')
    ->execute('Refactor the UserService class.');
```

### Combining Both Methods

You can use both methods together. `withSystemPrompt()` sets the base prompt and `appendSystemPrompt()` adds to it:

```php
$response = AgentCtrl::claudeCode()
    ->withSystemPrompt('You are a code reviewer specializing in PHP.')
    ->appendSystemPrompt('Pay special attention to error handling, edge cases, and performance implications.')
    ->execute('Review the PaymentGateway class.');
```

## Permission Modes

When running Claude Code headlessly (as Agent-Ctrl does), you need to configure how the agent handles tool permission requests. The `PermissionMode` enum provides four levels of autonomy:

```php
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;
```

| Mode | CLI Flag | Behavior |
|------|----------|----------|
| `DefaultMode` | `default` | Standard interactive permission prompts. **Not suitable for headless execution** -- prompts cannot be answered. |
| `Plan` | `plan` | The agent can plan and reason but will prompt before executing any tool. Useful for review workflows where you want to inspect the plan before execution. |
| `AcceptEdits` | `acceptEdits` | Auto-approve file editing tools (create, write, edit) but prompt for other actions like shell commands. A middle ground between safety and automation. |
| `BypassPermissions` | `bypassPermissions` | Auto-approve all tool uses without prompting. **This is the default** for Agent-Ctrl because headless execution cannot respond to permission prompts. |

```php
$response = AgentCtrl::claudeCode()
    ->withPermissionMode(PermissionMode::AcceptEdits)
    ->execute('Write unit tests for the PaymentService.');
```

The default is `BypassPermissions` because Agent-Ctrl runs the CLI in a non-interactive, headless mode. If you use `DefaultMode` or `Plan` without an interactive terminal, the agent will hang waiting for permission responses that never come, eventually timing out.

## Turn Limits

Each "turn" represents one cycle where the agent reads context, reasons, and takes an action (such as reading a file, editing code, or running a command). Limiting turns helps control execution time, cost, and scope:

```php
$response = AgentCtrl::claudeCode()
    ->withMaxTurns(5)
    ->execute('Make a small improvement to the README.');
```

### Guidelines for Turn Limits

| Task Complexity | Suggested Turns |
|----------------|----------------|
| Simple question or summary | 3-5 |
| Single-file edit | 5-10 |
| Multi-file refactoring | 15-30 |
| Complex feature implementation | 30-50 |

Without a turn limit, Claude Code continues working until it decides the task is complete or the timeout is reached. For predictable behavior, combine `withMaxTurns()` with `withTimeout()`.

## Additional Directories

By default, the agent operates within the working directory set by `inDirectory()`. Use `withAdditionalDirs()` to grant access to additional directories, such as shared libraries, configuration repositories, or reference codebases:

```php
$response = AgentCtrl::claudeCode()
    ->inDirectory('/projects/my-app')
    ->withAdditionalDirs(['/shared/libraries', '/configs/production'])
    ->execute('Update the app to use the latest shared authentication library.');
```

Each path in the array must be an absolute path to an existing directory.

## Verbose Mode

The `verbose()` method controls whether Claude Code emits detailed output. Verbose mode is enabled by default and is required for proper JSON stream parsing. In most cases, you should leave this at its default value:

```php
// Verbose is true by default -- you rarely need to change this
AgentCtrl::claudeCode()->verbose(true);
```

Disabling verbose mode may prevent Agent-Ctrl from correctly parsing the agent's output.

## Streaming with Claude Code

Claude Code streams output as JSON Lines containing message events, system events, error events, and result events. The bridge parses these in real time and delivers them through the standard streaming callbacks:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;

$response = AgentCtrl::claudeCode()
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("\n> [{$tool}]\n"))
    ->onError(fn(string $message, ?string $code) => print("\nError: {$message}\n"))
    ->executeStreaming('Explain the architecture of this project.');
```

### Event Normalization

During streaming, Claude Code emits several types of events that are normalized into the callback API:

- **Text content** from `MessageEvent` messages (type `text`) is delivered through `onText()` with the text string.
- **Tool use** from `MessageEvent` messages (type `tool_use`) is delivered through `onToolUse()` with the tool name, input parameters, and call ID.
- **Tool results** from `MessageEvent` messages (type `tool_result`) are delivered through `onToolUse()` with `tool` set to `'tool_result'`, the tool use ID in the input array, and the result content as output.
- **Error events** are delivered through `onError()` with the error message.

## Session Management

Claude Code session IDs are extracted from the `session_id` field in the stream JSON output. Use them to maintain conversational context across multiple executions:

```php
// First execution
$first = AgentCtrl::claudeCode()->execute('Create an implementation plan.');
$sessionId = $first->sessionId();

// Continue the most recent session (no ID needed)
$next = AgentCtrl::claudeCode()
    ->continueSession()
    ->execute('Begin implementing the plan.');

// Or resume a specific session by ID
if ($sessionId !== null) {
    $next = AgentCtrl::claudeCode()
        ->resumeSession((string) $sessionId)
        ->execute('Now implement the first item in the plan.');
}
```

## Data Availability

Not all data points are available from every agent. Claude Code's current JSON output format has the following coverage:

| Data Point | Available | Notes |
|------------|-----------|-------|
| Text output | Yes | Concatenated from all text content blocks |
| Tool calls | Yes | With call IDs, inputs, and results |
| Session ID | Yes | Extracted from `session_id` field in stream |
| Token usage | No | Claude Code CLI does not expose token counts |
| Cost | No | Claude Code CLI does not expose cost data |
| Parse diagnostics | Yes | Malformed JSON line counts and samples |

If you need token usage and cost tracking, consider using OpenCode with an Anthropic model, which provides both.

## Complete Example

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;

$logger = new AgentCtrlConsoleLogger(showPipeline: true);

$response = AgentCtrl::claudeCode()
    ->withModel('claude-sonnet-4-5')
    ->withSystemPrompt('You are a careful code reviewer.')
    ->appendSystemPrompt('Focus on error handling and edge cases.')
    ->withPermissionMode(PermissionMode::BypassPermissions)
    ->withMaxTurns(15)
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->withAdditionalDirs(['/shared/utils'])
    ->wiretap($logger->wiretap())
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("\n> [{$tool}]\n"))
    ->executeStreaming('Review the PaymentService for error handling issues.');

if ($response->isSuccess()) {
    echo "\n\nReview completed successfully.";
    echo "\nTools used: " . count($response->toolCalls);

    $sessionId = $response->sessionId();
    if ($sessionId !== null) {
        echo "\nSession: {$sessionId}";
    }
} else {
    echo "\n\nReview failed with exit code: {$response->exitCode}";
}
```
