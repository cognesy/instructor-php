---
title: Codex Bridge
description: 'Use the Codex bridge for OpenAI Codex with sandbox controls, auto-approval modes, image input, and thread-based session management.'
---

## Overview

The Codex bridge wraps OpenAI's `codex` CLI, providing access to Codex's code-generation capabilities through Agent-Ctrl's unified API. Codex is particularly well-suited when you need fine-grained sandbox controls over filesystem and network access, image-based prompts, and automatic approval workflows.

The bridge is implemented by `CodexBridge` and configured through `CodexBridgeBuilder`. Access the builder through the `AgentCtrl` facade:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

// Dedicated factory method
$builder = AgentCtrl::codex();

// Or via the generic factory
$builder = AgentCtrl::make(AgentType::Codex);
```

## Basic Usage

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::codex()
    ->execute('Summarize the test suite in this repository.');

echo $response->text();
```

With model and sandbox configuration:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;

$response = AgentCtrl::codex()
    ->withModel('o4-mini')
    ->withSandbox(SandboxMode::WorkspaceWrite)
    ->execute('Write tests for the UserService class.');

echo $response->text();
```

## Sandbox Modes

Codex provides three sandbox modes that control what filesystem and network access the agent has during execution. These are managed through the `SandboxMode` enum:

```php
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;
```

| Mode | Filesystem | Network | CLI Value | Use Case |
|------|-----------|---------|-----------|----------|
| `SandboxMode::ReadOnly` | Read-only | No | `read-only` | Safe analysis, code review, reading files without modification |
| `SandboxMode::WorkspaceWrite` | Write access to workspace | No | `workspace-write` | Code generation, refactoring, test writing |
| `SandboxMode::DangerFullAccess` | Full access | Yes | `danger-full-access` | Tasks requiring network access or system-wide file operations |

```php
// Read-only: safe for analysis tasks
$response = AgentCtrl::codex()
    ->withSandbox(SandboxMode::ReadOnly)
    ->execute('Analyze the code structure and identify issues.');

// Workspace write: for code modifications
$response = AgentCtrl::codex()
    ->withSandbox(SandboxMode::WorkspaceWrite)
    ->execute('Refactor the database layer to use the repository pattern.');

// Full access: for tasks needing network or system access
$response = AgentCtrl::codex()
    ->withSandbox(SandboxMode::DangerFullAccess)
    ->execute('Install a dependency and update the code to use it.');
```

### Disabling the Sandbox

The `disableSandbox()` method is a shorthand for `withSandbox(SandboxMode::DangerFullAccess)`:

```php
$response = AgentCtrl::codex()
    ->disableSandbox()
    ->execute('Run the full test suite and report results.');
```

## Approval Modes

Codex supports two approval configuration methods that control how the agent handles permission requests.

### Full Auto Mode

`fullAuto()` enables automatic approval with workspace-write sandbox access. This is the default configuration (`true`), making it suitable for headless execution:

```php
$response = AgentCtrl::codex()
    ->fullAuto()
    ->execute('Implement the feature described in SPEC.md.');
```

When full-auto is enabled, the agent automatically approves tool executions that would normally require user confirmation, and on-failure actions are also auto-approved.

Disable it when you want more conservative behavior:

```php
$response = AgentCtrl::codex()
    ->fullAuto(false)
    ->execute('Analyze the codebase structure.');
```

### Dangerous Bypass

`dangerouslyBypass()` skips all approval prompts and all sandbox restrictions. This is the most permissive mode and should be used only when you fully trust the agent and the execution environment:

```php
$response = AgentCtrl::codex()
    ->dangerouslyBypass()
    ->execute('Deploy the application to staging.');
```

> **Warning:** This mode disables all safety guardrails. The agent can execute arbitrary commands, modify any file, and access the network without restriction.

## Git Repository Check

By default, Codex requires the working directory to be inside a Git repository. Use `skipGitRepoCheck()` to bypass this requirement when working with non-Git directories:

```php
$response = AgentCtrl::codex()
    ->skipGitRepoCheck()
    ->inDirectory('/tmp/workspace')
    ->execute('Create a new project skeleton.');
```

## Image Input

Codex supports image attachments, allowing the agent to analyze visual content alongside text prompts. Use `withImages()` to attach one or more image files:

```php
$response = AgentCtrl::codex()
    ->withImages(['/tmp/mockup.png'])
    ->execute('Implement the UI component shown in the mockup.');
```

Multiple images can be attached:

```php
$response = AgentCtrl::codex()
    ->withImages([
        '/tmp/current-ui.png',
        '/tmp/target-design.png',
    ])
    ->execute('Compare the current UI with the target design and list the differences.');
```

Each path must point to an existing image file on the local filesystem.

## Additional Directories

Use `withAdditionalDirs()` to grant the agent write access to directories beyond the working directory:

```php
$response = AgentCtrl::codex()
    ->inDirectory('/projects/my-app')
    ->withAdditionalDirs(['/shared/assets', '/configs'])
    ->execute('Update the shared configuration files.');
```

## Streaming with Codex

Codex streams output as JSON Lines containing item events (started, completed), turn events, thread events, and error events. The bridge normalizes these into the standard callback API:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;

$response = AgentCtrl::codex()
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("\n> [{$tool}]\n"))
    ->onError(fn(string $message, ?string $code) => print("\nError [{$code}]: {$message}\n"))
    ->executeStreaming('Explain the test framework used in this project.');
```

### Tool Call Normalization

Codex produces several item types that are normalized into `ToolCall` objects:

| Codex Item Type | Normalized Tool Name | Input Structure |
|----------------|---------------------|----------------|
| `CommandExecution` | `'bash'` | `['command' => '...']` |
| `FileChange` | `'file_change'` | `['path' => '...', 'action' => '...']` |
| `McpToolCall` | Original tool name | Original arguments |
| `WebSearch` | `'web_search'` | `['query' => '...']` |
| `PlanUpdate` | `'plan_update'` | `[]` |
| `Reasoning` | `'reasoning'` | `[]` |
| `UnknownItem` | Original item type | `[]` |

The `isError` flag is set when the item has an error status (`error`, `failed`, `cancelled`) or when a `CommandExecution` has a non-zero exit code.

### Working with Tool Calls

```php
foreach ($response->toolCalls as $tc) {
    if ($tc->tool === 'bash') {
        echo "Command: {$tc->input['command']}\n";
        echo "Output: {$tc->output}\n";
    }

    if ($tc->tool === 'file_change') {
        echo "Changed: {$tc->input['path']} ({$tc->input['action']})\n";
    }

    if ($tc->tool === 'web_search') {
        echo "Searched: {$tc->input['query']}\n";
    }
}
```

## Session Management

Codex uses thread-based conversations. Agent-Ctrl normalizes the thread ID into an `AgentSessionId`:

```php
// First execution
$first = AgentCtrl::codex()->execute('Create a plan for the refactoring.');
$sessionId = $first->sessionId();

// Continue the most recent session
$next = AgentCtrl::codex()
    ->continueSession()
    ->execute('Proceed with step 1.');

// Resume a specific thread
if ($sessionId !== null) {
    $next = AgentCtrl::codex()
        ->resumeSession((string) $sessionId)
        ->execute('Continue from where we left off.');
}
```

## Data Availability

| Data Point | Available | Notes |
|------------|-----------|-------|
| Text output | Yes | Extracted from `AgentMessage` items |
| Tool calls | Yes | Normalized from all item types (see table above) |
| Session ID | Yes | Normalized from Codex thread ID |
| Token usage | Yes | Input tokens, output tokens, cached input tokens |
| Cost | No | Codex CLI does not expose cost data |
| Parse diagnostics | Yes | Malformed JSON line counts and samples |

### Token Usage

When Codex exposes usage statistics, they are converted into the unified `TokenUsage` DTO:

```php
$response = AgentCtrl::codex()->execute('Analyze the codebase.');

$usage = $response->usage();
if ($usage !== null) {
    echo "Input tokens: {$usage->input}\n";
    echo "Output tokens: {$usage->output}\n";
    echo "Cache read: " . ($usage->cacheRead ?? 'N/A') . "\n";
    echo "Total: {$usage->total()}\n";
}
```

## Complete Example

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;

$logger = new AgentCtrlConsoleLogger(
    showStreaming: true,
    showPipeline: true,
);

$response = AgentCtrl::codex()
    ->withModel('o4-mini')
    ->withSandbox(SandboxMode::WorkspaceWrite)
    ->fullAuto()
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->withAdditionalDirs(['/shared/test-fixtures'])
    ->withImages(['/tmp/design-spec.png'])
    ->wiretap($logger->wiretap())
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("\n> [{$tool}]\n"))
    ->executeStreaming('Implement the component shown in the design spec and write tests.');

if ($response->isSuccess()) {
    echo "\n\nTask completed successfully.\n";
    echo "Tools used: " . count($response->toolCalls) . "\n";

    // Summarize file changes
    $fileChanges = array_filter($response->toolCalls, fn($tc) => $tc->tool === 'file_change');
    echo "Files changed: " . count($fileChanges) . "\n";

    $usage = $response->usage();
    if ($usage !== null) {
        echo "Tokens: {$usage->total()}\n";
    }

    $sessionId = $response->sessionId();
    if ($sessionId !== null) {
        echo "Thread: {$sessionId}\n";
    }
} else {
    echo "\n\nTask failed with exit code: {$response->exitCode}\n";
}
```
