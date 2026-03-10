---
title: OpenCode Bridge
description: 'Use the OpenCode bridge for flexible model selection with provider-prefixed IDs, named agents, file attachments, session sharing, and cost tracking.'
---

## Overview

The OpenCode bridge wraps the `opencode` CLI, providing access to a multi-provider code agent through Agent-Ctrl's unified API. OpenCode is the most flexible bridge in terms of model selection -- it supports provider-prefixed model IDs that let you use models from Anthropic, OpenAI, Google, and other providers through a single CLI tool. It is also the only bridge that currently exposes both token usage and cost data.

The bridge is implemented by `OpenCodeBridge` and configured through `OpenCodeBridgeBuilder`. Access the builder through the `AgentCtrl` facade:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

// Dedicated factory method
$builder = AgentCtrl::openCode();

// Or via the generic factory
$builder = AgentCtrl::make(AgentType::OpenCode);
```

## Basic Usage

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::openCode()
    ->execute('Explain the architecture of this project in short paragraphs.');

echo $response->text();
```

With model selection:

```php
$response = AgentCtrl::openCode()
    ->withModel('anthropic/claude-sonnet-4-5')
    ->execute('Review the test suite.');

echo $response->text();
```

## Model Selection

OpenCode uses provider-prefixed model IDs, giving you access to models from multiple providers through a single CLI. The format is `provider/model-name`:

```php
// Anthropic models
AgentCtrl::openCode()->withModel('anthropic/claude-sonnet-4-5');
AgentCtrl::openCode()->withModel('anthropic/claude-opus-4');

// OpenAI models
AgentCtrl::openCode()->withModel('openai/gpt-4o');
AgentCtrl::openCode()->withModel('openai/o4-mini');

// Google models
AgentCtrl::openCode()->withModel('google/gemini-2.5-pro');
```

The exact set of available providers and models depends on your OpenCode installation and configuration. If no model is specified, OpenCode uses its configured default. Check OpenCode's documentation for the full list of supported providers and models.

## Named Agents

OpenCode supports named agents -- preconfigured agent profiles that define specific behaviors, tools, and system prompts. Use `withAgent()` to select one:

```php
$response = AgentCtrl::openCode()
    ->withAgent('coder')
    ->execute('Refactor the authentication module.');
```

```php
$response = AgentCtrl::openCode()
    ->withAgent('task')
    ->execute('Create a detailed implementation plan.');
```

The available agent names depend on your OpenCode configuration. Common agents include `coder` (for code-focused tasks) and `task` (for planning and general tasks).

## File Attachments

Use `withFiles()` to attach specific files to the prompt. The agent will have direct access to these files as context, without needing to discover and read them:

```php
$response = AgentCtrl::openCode()
    ->withFiles([
        '/projects/my-app/src/Services/PaymentService.php',
        '/projects/my-app/src/Models/Payment.php',
    ])
    ->execute('Refactor the PaymentService to handle partial refunds.');
```

Unlike `inDirectory()` which sets the working directory, `withFiles()` explicitly includes specific files in the prompt context. This is useful when you want the agent to focus on particular files rather than browsing the project directory.

## Session Title

Use `withTitle()` to set a descriptive title for the session. The title appears in OpenCode's session listing and makes it easier to identify sessions later:

```php
$response = AgentCtrl::openCode()
    ->withTitle('Payment module refactoring')
    ->execute('Plan the payment module refactoring.');
```

Titles are especially useful when you manage multiple sessions and need to identify them by purpose rather than by opaque session IDs.

## Session Sharing

Use `shareSession()` to mark the session for sharing after completion. Shared sessions can be accessed by other users or tools, enabling collaborative workflows where multiple team members can review or continue an agent's work:

```php
$response = AgentCtrl::openCode()
    ->shareSession()
    ->withTitle('Architecture review for team')
    ->execute('Create a comprehensive architecture review.');

// The session ID can be shared with others
$sessionId = $response->sessionId();
if ($sessionId !== null) {
    echo "Share this session ID with your team: {$sessionId}\n";
}
```

## Streaming with OpenCode

OpenCode streams output as JSON Lines containing text events, tool use events, step events, and error events. The bridge normalizes these into the standard callback API:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;

$response = AgentCtrl::openCode()
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("\n> [{$tool}]\n"))
    ->onError(fn(string $message, ?string $code) => print("\nError [{$code}]: {$message}\n"))
    ->executeStreaming('Analyze the error handling in this codebase.');
```

### Event Normalization

During streaming, OpenCode emits several event types that are normalized:

- **`TextEvent`** -- Text content is delivered through `onText()` with the text string.
- **`ToolUseEvent`** -- Tool invocations are delivered through `onToolUse()` with the tool name, input parameters, optional output, and call ID. The `isError` flag is set based on whether the tool completed successfully.
- **`ErrorEvent`** -- Error events are delivered through `onError()` with the message, optional code, and raw data.
- **`StepStartEvent` / `StepFinishEvent`** -- Step lifecycle events are processed internally and available through the `wiretap()` event system but not directly exposed through user callbacks.

## Session Management

OpenCode maintains its own session system with session IDs. Agent-Ctrl extracts and normalizes these into `AgentSessionId` value objects. Internally, OpenCode uses `OpenCodeSessionId` which is mapped to the unified `AgentSessionId`:

```php
// First execution
$first = AgentCtrl::openCode()->execute('Create an implementation plan.');
$sessionId = $first->sessionId();

// Continue the most recent session (no ID needed)
$next = AgentCtrl::openCode()
    ->continueSession()
    ->execute('Begin implementing the plan.');

// Resume a specific session by ID
if ($sessionId !== null) {
    $next = AgentCtrl::openCode()
        ->resumeSession((string) $sessionId)
        ->execute('Continue with the next step.');
}
```

## Usage and Cost Data

OpenCode provides the most comprehensive usage and cost reporting of the three bridges. Both token usage and cost data are available after execution.

### Token Usage

OpenCode's `TokenUsage` includes all five token categories -- input, output, cache read, cache write, and reasoning:

```php
$response = AgentCtrl::openCode()
    ->withModel('anthropic/claude-sonnet-4-5')
    ->execute('Analyze the project dependencies.');

$usage = $response->usage();
if ($usage !== null) {
    echo "Input tokens:    {$usage->input}\n";
    echo "Output tokens:   {$usage->output}\n";
    echo "Total tokens:    {$usage->total()}\n";

    if ($usage->cacheRead !== null) {
        echo "Cache read:      {$usage->cacheRead}\n";
    }
    if ($usage->cacheWrite !== null) {
        echo "Cache write:     {$usage->cacheWrite}\n";
    }
    if ($usage->reasoning !== null) {
        echo "Reasoning:       {$usage->reasoning}\n";
    }
}
```

### Cost Tracking

OpenCode is the only supported agent that exposes cost data. The cost is returned in USD:

```php
$cost = $response->cost();
if ($cost !== null) {
    echo sprintf("This execution cost $%.4f\n", $cost);
}
```

This makes OpenCode a good choice when you need to track and report on the cost of agent executions, build usage dashboards, or enforce cost budgets.

## Data Availability

| Data Point | Available | Notes |
|------------|-----------|-------|
| Text output | Yes | Extracted from `TextEvent` stream events |
| Tool calls | Yes | Normalized from `ToolUseEvent` with call IDs and completion status |
| Session ID | Yes | Extracted from OpenCode session data |
| Token usage | Yes | Input, output, cache read, cache write, reasoning tokens |
| Cost | Yes | Cost in USD |
| Parse diagnostics | Yes | Malformed JSON line counts and samples |

## Complete Example

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;

$logger = new AgentCtrlConsoleLogger(
    showStreaming: true,
    showPipeline: true,
);

$response = AgentCtrl::openCode()
    ->withModel('anthropic/claude-sonnet-4-5')
    ->withAgent('coder')
    ->withTitle('Comprehensive architecture review')
    ->withFiles([
        '/projects/my-app/src/Kernel.php',
        '/projects/my-app/src/routes.php',
    ])
    ->shareSession()
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->wiretap($logger->wiretap())
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("\n> [{$tool}]\n"))
    ->onComplete(fn(AgentResponse $r) => print("\n--- Complete ---\n"))
    ->executeStreaming('Review the application architecture and suggest improvements.');

if ($response->isSuccess()) {
    echo "\nReview completed successfully.\n";
    echo "Tools used: " . count($response->toolCalls) . "\n";

    $usage = $response->usage();
    if ($usage !== null) {
        echo "Tokens: {$usage->total()} (in: {$usage->input}, out: {$usage->output})\n";
    }

    $cost = $response->cost();
    if ($cost !== null) {
        echo sprintf("Cost: $%.4f\n", $cost);
    }

    $sessionId = $response->sessionId();
    if ($sessionId !== null) {
        echo "Session: {$sessionId}\n";
        echo "Use this ID with resumeSession() to continue later.\n";
    }
} else {
    echo "\nReview failed with exit code: {$response->exitCode}\n";
    echo "Partial output: " . substr($response->text(), 0, 500) . "\n";
}
```

## Comparison with Other Bridges

| Feature | Claude Code | Codex | OpenCode |
|---------|------------|-------|----------|
| System prompts | Yes (replace + append) | No | No |
| Permission modes | Yes (4 levels) | No | No |
| Turn limits | Yes | No | No |
| Sandbox modes | No | Yes (3 levels) | No |
| Image input | No | Yes | No |
| Named agents | No | No | Yes |
| File attachments | No | No | Yes |
| Session sharing | No | No | Yes |
| Session titles | No | No | Yes |
| Token usage | No | Yes (partial) | Yes (full) |
| Cost tracking | No | No | Yes |
| Multi-provider models | No | No | Yes |
