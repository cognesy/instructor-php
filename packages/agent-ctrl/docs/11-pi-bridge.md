---
title: Pi Bridge
description: 'Use the Pi bridge for a minimal, extensible coding agent with thinking levels, multi-provider models, TypeScript extensions, skills, and granular JSONL event streaming.'
---

## Overview

The Pi bridge wraps the `pi` CLI (from the [pi-mono](https://github.com/badlogic/pi-mono) project), a minimal terminal coding harness that is aggressively extensible. Pi supports multi-provider model selection, thinking levels, TypeScript extensions, skills, prompt templates, and fine-grained JSONL event streaming. It provides both token usage and cost data.

The bridge is implemented by `PiBridge` and configured through `PiBridgeBuilder`. Access the builder through the `AgentCtrl` facade:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

// Dedicated factory method
$builder = AgentCtrl::pi();

// Or via the generic factory
$builder = AgentCtrl::make(AgentType::Pi);
```

### Prerequisites

Install Pi globally via npm or bun:

```bash
npm install -g @mariozechner/pi-coding-agent
# or
bun install -g @mariozechner/pi-coding-agent
```

Configure an API key:

```bash
export ANTHROPIC_API_KEY=sk-ant-...
# or
export OPENAI_API_KEY=sk-...
```

## Basic Usage

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::pi()
    ->execute('Explain the architecture of this project.');

echo $response->text();
```

With model selection:

```php
$response = AgentCtrl::pi()
    ->withModel('sonnet')
    ->execute('Review the test suite.');

echo $response->text();
```

## Model Selection

Pi supports flexible model identification with optional provider prefix and thinking level shorthand:

```php
// Model name only (uses default provider)
AgentCtrl::pi()->withModel('sonnet');

// Provider/model format
AgentCtrl::pi()->withModel('openai/gpt-4o');
AgentCtrl::pi()->withModel('anthropic/claude-opus-4-6');
AgentCtrl::pi()->withModel('google/gemini-2.5-pro');

// Model with thinking level shorthand
AgentCtrl::pi()->withModel('sonnet:high');
```

Use `withProvider()` to explicitly set the provider when the model name alone is ambiguous:

```php
AgentCtrl::pi()
    ->withProvider('anthropic')
    ->withModel('sonnet')
    ->execute('...');
```

## Thinking Levels

Pi supports six thinking levels that control how much the model reasons before responding:

```php
use Cognesy\AgentCtrl\Pi\Domain\Enum\ThinkingLevel;

AgentCtrl::pi()->withThinking(ThinkingLevel::Off);       // No thinking
AgentCtrl::pi()->withThinking(ThinkingLevel::Minimal);   // Minimal reasoning
AgentCtrl::pi()->withThinking(ThinkingLevel::Low);       // Light reasoning
AgentCtrl::pi()->withThinking(ThinkingLevel::Medium);    // Moderate reasoning
AgentCtrl::pi()->withThinking(ThinkingLevel::High);      // Deep reasoning
AgentCtrl::pi()->withThinking(ThinkingLevel::ExtraHigh); // Maximum reasoning
```

Alternatively, use the model shorthand: `->withModel('sonnet:high')`.

## System Prompts

Replace or extend the default system prompt:

```php
// Replace entirely
AgentCtrl::pi()
    ->withSystemPrompt('You are a PHP code reviewer.')
    ->execute('Review this code.');

// Append to default
AgentCtrl::pi()
    ->appendSystemPrompt('Focus on security issues.')
    ->execute('Review the authentication module.');
```

## Tool Control

By default, Pi provides four tools: `read`, `write`, `edit`, and `bash`. Additional built-in tools include `grep`, `find`, and `ls`.

```php
// Restrict to read-only tools
AgentCtrl::pi()
    ->withTools(['read', 'grep', 'find', 'ls'])
    ->execute('Analyze the codebase structure.');

// Disable all tools (pure conversation)
AgentCtrl::pi()
    ->noTools()
    ->execute('Explain dependency injection.');
```

## File Arguments

Attach files to the prompt using `withFiles()`. These are passed as `@`-prefixed arguments to Pi:

```php
$response = AgentCtrl::pi()
    ->withFiles([
        '/projects/app/src/PaymentService.php',
        '/projects/app/tests/PaymentServiceTest.php',
    ])
    ->execute('Review these files for potential issues.');
```

## Extensions and Skills

Pi supports TypeScript extensions and skills that add custom tools, commands, and capabilities:

```php
// Load specific extensions
AgentCtrl::pi()
    ->withExtensions(['./my-extension.ts'])
    ->execute('...');

// Disable extension auto-discovery (load only explicit ones)
AgentCtrl::pi()
    ->noExtensions()
    ->withExtensions(['./deploy-ext.ts'])
    ->execute('...');

// Load specific skills
AgentCtrl::pi()
    ->withSkills(['/path/to/my-skill'])
    ->execute('...');

// Disable skill auto-discovery
AgentCtrl::pi()
    ->noSkills()
    ->execute('...');
```

## Streaming with Pi

Pi streams output as JSONL with granular event types. The bridge normalizes these into the standard callback API:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;

$response = AgentCtrl::pi()
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("\n> [{$tool}]\n"))
    ->onError(fn(string $message, ?string $code) => print("\nError: {$message}\n"))
    ->onComplete(fn(AgentResponse $r) => print("\n--- Done ---\n"))
    ->executeStreaming('Analyze the error handling in this codebase.');
```

### Event Normalization

Pi emits a rich set of JSONL events that are normalized:

- **`MessageUpdateEvent` (text_delta)** -- Text deltas delivered through `onText()`.
- **`ToolExecutionEndEvent`** -- Tool results delivered through `onToolUse()` with tool name, call ID, result, and error flag.
- **`ErrorEvent`** -- Errors delivered through `onError()`.
- **`SessionEvent`, `AgentStart/End`, `TurnStart/End`, `MessageStart/End`, `ToolExecutionStart`** -- Lifecycle events available through the `wiretap()` event system.

## Session Management

Pi maintains sessions as JSONL files. Agent-Ctrl extracts session IDs from the session header event:

```php
// First execution
$first = AgentCtrl::pi()->execute('Create an implementation plan.');
$sessionId = $first->sessionId();

// Continue the most recent session
$next = AgentCtrl::pi()
    ->continueSession()
    ->execute('Begin implementing the plan.');

// Resume a specific session by ID
if ($sessionId !== null) {
    $next = AgentCtrl::pi()
        ->resumeSession((string) $sessionId)
        ->execute('Continue with the next step.');
}

// Ephemeral mode -- don't save session
AgentCtrl::pi()
    ->ephemeral()
    ->execute('Quick one-off question.');

// Custom session storage
AgentCtrl::pi()
    ->withSessionDir('/tmp/pi-sessions')
    ->execute('...');
```

## API Key Override

Override the API key for a specific execution without changing environment variables:

```php
AgentCtrl::pi()
    ->withApiKey('sk-ant-...')
    ->withProvider('anthropic')
    ->execute('...');
```

## Usage and Cost Data

Pi provides token usage and cost data from the message events:

```php
$response = AgentCtrl::pi()
    ->withModel('sonnet')
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
}

$cost = $response->cost();
if ($cost !== null) {
    echo sprintf("Cost: $%.6f\n", $cost);
}
```

## Data Availability

| Data Point | Available | Notes |
|------------|-----------|-------|
| Text output | Yes | Extracted from `message_update` text_delta events |
| Tool calls | Yes | Normalized from `tool_execution_end` with call IDs and error status |
| Session ID | Yes | Extracted from JSONL `session` header event |
| Token usage | Yes | Input, output, cache read, cache write tokens |
| Cost | Yes | Cost in USD from usage data |
| Parse diagnostics | Yes | Malformed JSON line counts and samples |

## Complete Example

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Pi\Domain\Enum\ThinkingLevel;

$response = AgentCtrl::pi()
    ->withModel('sonnet')
    ->withThinking(ThinkingLevel::High)
    ->appendSystemPrompt('Focus on security and performance.')
    ->withTools(['read', 'bash', 'edit', 'grep'])
    ->withFiles(['/projects/app/src/Kernel.php'])
    ->withTimeout(300)
    ->inDirectory('/projects/app')
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
        echo sprintf("Cost: $%.6f\n", $cost);
    }
} else {
    echo "\nFailed with exit code: {$response->exitCode}\n";
}
```

## Comparison with Other Bridges

| Feature | Claude Code | Codex | OpenCode | Pi |
|---------|------------|-------|----------|-----|
| System prompts | Yes (replace + append) | No | No | Yes (replace + append) |
| Permission modes | Yes (4 levels) | No | No | No |
| Turn limits | Yes | No | No | No |
| Sandbox modes | No | Yes (3 levels) | No | No |
| Image input | No | Yes | No | No |
| Thinking levels | No | No | No | Yes (6 levels) |
| Named agents | No | No | Yes | No |
| File attachments | No | No | Yes | Yes (@-prefix) |
| Extensions | No | No | No | Yes (TypeScript) |
| Skills | No | No | No | Yes |
| Tool control | No | No | No | Yes (select/disable) |
| Session sharing | No | No | Yes | No |
| Session titles | No | No | Yes | No |
| Ephemeral mode | No | No | No | Yes |
| API key override | No | No | No | Yes |
| Token usage | No | Yes (partial) | Yes (full) | Yes |
| Cost tracking | No | No | Yes | Yes |
| Multi-provider models | No | No | Yes | Yes |

## Environment Variables

| Variable | Description |
|----------|-------------|
| `ANTHROPIC_API_KEY` | Anthropic API key |
| `OPENAI_API_KEY` | OpenAI API key |
| `PI_CODING_AGENT_DIR` | Override Pi config directory (default: `~/.pi/agent`) |
| `PI_SKIP_VERSION_CHECK` | Skip version check at startup |
| `PI_CACHE_RETENTION` | Set to `long` for extended prompt cache |
