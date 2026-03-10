---
title: Response Object
description: 'Read text output, session identifiers, token usage, cost, tool calls, and parse diagnostics from the unified AgentResponse returned by every execution.'
---

## Introduction

Every call to `execute()` or `executeStreaming()` returns an `AgentResponse` -- a readonly DTO that provides a normalized view of the agent's output regardless of which CLI tool produced it. The response contains the text content, process exit code, session identifier, token usage statistics, cost, tool call records, and parse diagnostics.

The `AgentResponse` class is defined at `Cognesy\AgentCtrl\Dto\AgentResponse`.

## Response Properties

The following public properties are available directly on the `AgentResponse` object:

### `agentType: AgentType`

The agent type that produced this response. This is one of `AgentType::ClaudeCode`, `AgentType::Codex`, or `AgentType::OpenCode`:

```php
echo "Produced by: {$response->agentType->value}"; // e.g., "claude-code"
```

### `text: string`

The agent's main text output. This is the concatenation of all text content blocks from the agent's response. For most use cases, you should access this through the `text()` method instead:

```php
echo $response->text;
// or
echo $response->text();
```

### `exitCode: int`

The process exit code. A value of `0` indicates success. Non-zero values indicate errors, timeouts, or other failures:

```php
if ($response->exitCode !== 0) {
    echo "Process failed with exit code: {$response->exitCode}";
}
```

Common exit codes:
- **0** -- Success
- **1** -- General error (invalid configuration, agent-side failure)
- **2** -- Invalid arguments passed to the CLI
- **124 / 137** -- Process killed due to timeout

### `usage: ?TokenUsage`

Token usage statistics, when available. This is `null` for agents that do not expose usage data (Claude Code does not; Codex and OpenCode do when the data is present in the CLI output):

```php
$usage = $response->usage;
if ($usage !== null) {
    echo "Input tokens: {$usage->input}\n";
    echo "Output tokens: {$usage->output}\n";
    echo "Total tokens: {$usage->total()}\n";
}
```

### `cost: ?float`

The estimated cost in USD, when available. Currently, only OpenCode exposes cost data:

```php
if ($response->cost !== null) {
    echo sprintf("Cost: $%.4f", $response->cost);
}
```

### `toolCalls: array`

An array of `ToolCall` objects representing every tool invocation made during the execution. See the [Tool Calls](#tool-calls) section below for details:

```php
echo "Tool calls made: " . count($response->toolCalls);
```

### `rawResponse: mixed`

The original bridge-specific response object (`ClaudeResponse`, `CodexResponse`, or `OpenCodeResponse`). This provides access to agent-specific data that is not part of the normalized response:

```php
// Access the raw Codex response for agent-specific data
$codexResponse = $response->rawResponse;
```

### `parseFailures: int`

The number of malformed JSON lines that were skipped during response parsing:

```php
if ($response->parseFailures > 0) {
    echo "Warning: {$response->parseFailures} JSON parse failures";
}
```

## Response Methods

### `isSuccess(): bool`

Returns `true` if the exit code is `0`. This is the recommended way to check whether the execution succeeded:

```php
$response = AgentCtrl::codex()->execute('Create a short summary.');

if ($response->isSuccess()) {
    echo $response->text();
} else {
    echo "Failed with exit code: {$response->exitCode}";
    echo "Partial output: " . $response->text();
}
```

A completed execution with a non-zero exit code does **not** throw an exception. Always check `isSuccess()` before treating the text output as authoritative.

### `text(): string`

Returns the agent's text output. Equivalent to accessing the `text` property directly:

```php
echo $response->text();
```

### `sessionId(): ?AgentSessionId`

Returns the session identifier as an `AgentSessionId` value object, or `null` if no session ID was available. The value object implements `__toString()` for easy serialization:

```php
$sessionId = $response->sessionId();

if ($sessionId !== null) {
    // Store for later resumption
    $cache->set('last_session', (string) $sessionId);
}
```

### `usage(): ?TokenUsage`

Returns the token usage statistics, or `null` if the agent does not expose this data:

```php
$usage = $response->usage();
if ($usage !== null) {
    echo "Tokens used: {$usage->total()}";
}
```

### `cost(): ?float`

Returns the estimated cost in USD, or `null` if the agent does not expose cost data:

```php
$cost = $response->cost();
if ($cost !== null) {
    echo sprintf("This execution cost $%.4f", $cost);
}
```

### `parseFailures(): int`

Returns the number of malformed JSON lines encountered during parsing:

```php
echo "Parse failures: {$response->parseFailures()}";
```

### `parseFailureSamples(): array`

Returns a list of sample malformed payload strings (truncated to 200 characters each) for debugging purposes:

```php
if ($response->parseFailures() > 0) {
    echo "Skipped {$response->parseFailures()} malformed JSON lines:\n";
    foreach ($response->parseFailureSamples() as $sample) {
        echo "  - {$sample}\n";
    }
}
```

## Tool Calls

The `toolCalls` array contains `ToolCall` objects -- a normalized representation of every tool invocation across all agent types. Each `ToolCall` has the following structure:

### ToolCall Properties

| Property | Type | Description |
|----------|------|-------------|
| `tool` | `string` | The tool name (e.g., `'bash'`, `'file_change'`, `'web_search'`, `'tool_result'`) |
| `input` | `array` | The tool's input parameters as an associative array |
| `output` | `?string` | The tool's output, or `null` if not yet completed |
| `isError` | `bool` | Whether the tool call resulted in an error |

### ToolCall Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `callId()` | `?AgentToolCallId` | The unique identifier for this tool call, or `null` |
| `isCompleted()` | `bool` | Whether the tool has produced output (`output !== null`) |

### Tool Name Normalization

Agent-Ctrl normalizes tool names across all agents so you can handle them consistently:

**Claude Code** tool calls preserve their original tool names and include separate `tool_result` entries for tool outputs:
- Tool invocations: original tool name (e.g., `'Read'`, `'Edit'`, `'Bash'`)
- Tool results: `'tool_result'` with `['tool_use_id' => '...']` in the input

**Codex** items are normalized as follows:
- `CommandExecution` becomes `tool: 'bash'`, `input: ['command' => '...']`
- `FileChange` becomes `tool: 'file_change'`, `input: ['path' => '...', 'action' => '...']`
- `McpToolCall` becomes `tool: <original tool name>`, `input: <arguments>`
- `WebSearch` becomes `tool: 'web_search'`, `input: ['query' => '...']`
- `PlanUpdate` becomes `tool: 'plan_update'`
- `Reasoning` becomes `tool: 'reasoning'`

**OpenCode** tool calls preserve their original tool names from the `ToolUseEvent`.

### Working with Tool Calls

```php
$response = AgentCtrl::claudeCode()->execute('Refactor the UserService.');

foreach ($response->toolCalls as $toolCall) {
    echo "Tool: {$toolCall->tool}\n";
    echo "Input: " . json_encode($toolCall->input) . "\n";

    if ($toolCall->isCompleted()) {
        echo "Output: " . substr($toolCall->output, 0, 200) . "\n";
    }

    if ($toolCall->isError) {
        echo "  [ERROR]\n";
    }

    $callId = $toolCall->callId();
    if ($callId !== null) {
        echo "Call ID: {$callId}\n";
    }

    echo "---\n";
}
```

## Token Usage

The `TokenUsage` DTO provides detailed token statistics when the agent's CLI exposes this data. It is defined at `Cognesy\AgentCtrl\Dto\TokenUsage`.

### TokenUsage Properties

| Property | Type | Description |
|----------|------|-------------|
| `input` | `int` | Number of input tokens consumed |
| `output` | `int` | Number of output tokens produced |
| `cacheRead` | `?int` | Tokens read from cache (when available) |
| `cacheWrite` | `?int` | Tokens written to cache (when available) |
| `reasoning` | `?int` | Tokens used for reasoning (when available) |

### TokenUsage Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `total()` | `int` | Sum of `input` and `output` tokens |

### Usage Data Availability by Agent

| Agent | Token Usage | Cost |
|-------|------------|------|
| Claude Code | No | No |
| Codex | Yes (input, output, cacheRead) | No |
| OpenCode | Yes (input, output, cacheRead, cacheWrite, reasoning) | Yes |

```php
$response = AgentCtrl::openCode()
    ->withModel('anthropic/claude-sonnet-4-5')
    ->execute('Summarize this project.');

$usage = $response->usage();
if ($usage !== null) {
    echo "Input tokens: {$usage->input}\n";
    echo "Output tokens: {$usage->output}\n";
    echo "Total tokens: {$usage->total()}\n";

    if ($usage->cacheRead !== null) {
        echo "Cache read: {$usage->cacheRead}\n";
    }
    if ($usage->cacheWrite !== null) {
        echo "Cache write: {$usage->cacheWrite}\n";
    }
    if ($usage->reasoning !== null) {
        echo "Reasoning tokens: {$usage->reasoning}\n";
    }
}

$cost = $response->cost();
if ($cost !== null) {
    echo sprintf("Cost: $%.4f\n", $cost);
}
```

## Complete Example

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::codex()
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->execute('Review the authentication module and list any issues.');

// Check success
if (!$response->isSuccess()) {
    echo "Execution failed (exit code {$response->exitCode})\n";
    exit(1);
}

// Display text output
echo $response->text() . "\n";

// Display session info
$sessionId = $response->sessionId();
if ($sessionId !== null) {
    echo "Session: {$sessionId}\n";
}

// Display usage stats
$usage = $response->usage();
if ($usage !== null) {
    echo "Tokens: {$usage->total()} (in: {$usage->input}, out: {$usage->output})\n";
}

// Display cost
if ($response->cost() !== null) {
    echo sprintf("Cost: $%.4f\n", $response->cost());
}

// Summarize tool activity
echo "Tool calls: " . count($response->toolCalls) . "\n";
$toolSummary = [];
foreach ($response->toolCalls as $tc) {
    $toolSummary[$tc->tool] = ($toolSummary[$tc->tool] ?? 0) + 1;
}
foreach ($toolSummary as $tool => $count) {
    echo "  {$tool}: {$count}x\n";
}

// Check parse health
if ($response->parseFailures() > 0) {
    echo "Warning: {$response->parseFailures()} parse failures\n";
}
```
