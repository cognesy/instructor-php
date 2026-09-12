---
title: Gemini Bridge
description: 'Use the Gemini bridge for Google''s CLI coding agent with model aliases, approval modes, sandbox, extensions, MCP servers, and granular stream-json event streaming.'
---

## Overview

The Gemini bridge wraps the `gemini` CLI (from [@google/gemini-cli](https://github.com/google-gemini/gemini-cli)), Google's terminal-based coding agent. Gemini CLI supports model aliases, approval modes (default, auto_edit, yolo, plan), sandbox isolation, extensions, MCP servers, policy files, session management, and stream-json event streaming. It provides token usage data including cached token counts.

The bridge is implemented by `GeminiBridge` and configured through `GeminiBridgeBuilder`. Access the builder through the `AgentCtrl` facade:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

// Dedicated factory method
$builder = AgentCtrl::gemini();

// Or via the generic factory
$builder = AgentCtrl::make(AgentType::Gemini);
```

### Prerequisites

Install Gemini CLI globally:

```bash
# npm
npm install -g @google/gemini-cli

# Homebrew
brew install gemini-cli

# npx (no install)
npx @google/gemini-cli
```

Configure authentication (one of):

```bash
# Gemini API key
export GEMINI_API_KEY=...

# Google Cloud API key
export GOOGLE_API_KEY=...

# Or authenticate via Google account (free tier)
gemini
```

## Basic Usage

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::gemini()
    ->execute('Explain the architecture of this project.');

echo $response->text();
```

With model selection:

```php
$response = AgentCtrl::gemini()
    ->withModel('flash')
    ->execute('Review the test suite.');

echo $response->text();
```

## Model Selection

Gemini CLI supports model aliases and full model names:

```php
// Model aliases
AgentCtrl::gemini()->withModel('auto');        // Default (gemini-2.5-pro)
AgentCtrl::gemini()->withModel('pro');         // gemini-2.5-pro
AgentCtrl::gemini()->withModel('flash');       // gemini-2.5-flash
AgentCtrl::gemini()->withModel('flash-lite');  // gemini-2.5-flash-lite

// Full model name
AgentCtrl::gemini()->withModel('gemini-2.5-pro');
```

## Approval Modes

Gemini CLI supports four approval modes that control how tool execution is approved:

```php
use Cognesy\AgentCtrl\Gemini\Domain\Enum\ApprovalMode;

// Default — prompt for approval on each tool use
AgentCtrl::gemini()->withApprovalMode(ApprovalMode::Default);

// Auto-edit — auto-approve edit tools, prompt for others
AgentCtrl::gemini()->withApprovalMode(ApprovalMode::AutoEdit);

// YOLO — auto-approve all tool executions
AgentCtrl::gemini()->yolo();

// Plan — read-only analysis mode
AgentCtrl::gemini()->planMode();
```

## Sandbox Mode

Enable sandboxed execution for process isolation:

```php
AgentCtrl::gemini()
    ->withSandbox()
    ->execute('Analyze the codebase.');
```

On macOS, this uses Seatbelt (`sandbox-exec`). Docker, Podman, and gVisor are also supported.

## System Prompt

Gemini CLI reads instructions from a `GEMINI.md` file in the project root (similar to `CLAUDE.md`). You can also set the `GEMINI_SYSTEM_MD` environment variable to point to a custom system prompt file.

## Include Directories

Add additional workspace directories for the agent to access:

```php
AgentCtrl::gemini()
    ->withIncludeDirectories(['/projects/shared-lib', '/projects/config'])
    ->execute('Check for shared dependencies.');
```

## Extensions

Use specific extensions:

```php
AgentCtrl::gemini()
    ->withExtensions(['my-extension'])
    ->execute('...');
```

## MCP Servers

Restrict which MCP servers are available:

```php
AgentCtrl::gemini()
    ->withAllowedMcpServers(['filesystem', 'github'])
    ->execute('...');
```

## Policy Files

Load additional policy files for fine-grained tool approval rules:

```php
AgentCtrl::gemini()
    ->withPolicy(['/path/to/policy.yaml'])
    ->execute('...');
```

## Allowed Tools

Restrict which tools the agent can use:

```php
AgentCtrl::gemini()
    ->withAllowedTools(['read_file', 'search_files', 'list_directory'])
    ->execute('Analyze the codebase structure.');
```

## Debug Mode

Enable debug output for troubleshooting CLI behavior:

```php
AgentCtrl::gemini()
    ->debug()
    ->execute('Analyze the codebase.');
```

## Streaming with Gemini

Gemini streams output as JSONL with the `stream-json` format. The bridge normalizes these into the standard callback API:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;

$response = AgentCtrl::gemini()
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("\n> [{$tool}]\n"))
    ->onError(fn(string $message, ?string $code) => print("\nError: {$message}\n"))
    ->onComplete(fn(AgentResponse $r) => print("\n--- Done ---\n"))
    ->executeStreaming('Analyze the error handling in this codebase.');
```

### Event Normalization

Gemini emits stream-json events that are normalized:

- **`message` (role=assistant, delta=true)** -- Text deltas delivered through `onText()`.
- **`tool_result`** -- Tool results delivered through `onToolUse()` with tool name, input (from paired `tool_use` event), result, and error status.
- **`error`** -- Errors delivered through `onError()` with severity and message.
- **`init`, `tool_use`, `result`** -- Lifecycle events available through the `wiretap()` event system.

## Session Management

Gemini CLI maintains session history. Agent-Ctrl extracts session IDs from the `init` event:

```php
// First execution
$first = AgentCtrl::gemini()->execute('Create an implementation plan.');
$sessionId = $first->sessionId();

// Continue the most recent session
$next = AgentCtrl::gemini()
    ->continueSession()
    ->execute('Begin implementing the plan.');

// Resume a specific session by ID
if ($sessionId !== null) {
    $next = AgentCtrl::gemini()
        ->resumeSession((string) $sessionId)
        ->execute('Continue with the next step.');
}
```

## Usage Data

Gemini provides token usage data from the `result` event stats:

```php
$response = AgentCtrl::gemini()
    ->withModel('flash')
    ->execute('Analyze the project dependencies.');

$usage = $response->usage();
if ($usage !== null) {
    echo "Input tokens:    {$usage->input}\n";
    echo "Output tokens:   {$usage->output}\n";
    echo "Total tokens:    {$usage->total()}\n";

    if ($usage->cacheRead !== null) {
        echo "Cached tokens:   {$usage->cacheRead}\n";
    }
}
```

## Data Availability

| Data Point | Available | Notes |
|------------|-----------|-------|
| Text output | Yes | Extracted from `message` events (role=assistant, delta=true) |
| Tool calls | Yes | Normalized from `tool_use` + `tool_result` event pairs |
| Session ID | Yes | Extracted from `init` event |
| Token usage | Yes | Input, output, cached tokens from `result` stats |
| Cost | No | Gemini CLI does not expose cost data |
| Parse diagnostics | Yes | Malformed JSON line counts and samples |

## Complete Example

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Gemini\Domain\Enum\ApprovalMode;

$response = AgentCtrl::gemini()
    ->withModel('pro')
    ->withApprovalMode(ApprovalMode::AutoEdit)
    ->withIncludeDirectories(['/projects/shared'])
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
} else {
    echo "\nFailed with exit code: {$response->exitCode}\n";
}
```

## Comparison with Other Bridges

| Feature | Claude Code | Codex | OpenCode | Pi | Gemini |
|---------|------------|-------|----------|-----|--------|
| System prompts | Yes (replace + append) | No | No | Yes (replace + append) | Yes (GEMINI.md file) |
| Permission modes | Yes (4 levels) | No | No | No | Yes (4 modes) |
| Turn limits | Yes | No | No | No | Yes (via settings) |
| Sandbox modes | No | Yes (3 levels) | No | No | Yes (Seatbelt/Docker/Podman/gVisor) |
| Image input | No | Yes | No | No | No |
| Thinking levels | No | No | No | Yes (6 levels) | No |
| Named agents | No | No | Yes | No | No |
| File attachments | No | No | Yes | Yes (@-prefix) | No |
| Extensions | No | No | No | Yes (TypeScript) | Yes |
| Skills | No | No | No | Yes | No |
| Tool control | No | No | No | Yes (select/disable) | Yes (allowlist) |
| MCP servers | No | No | No | No | Yes |
| Policy engine | No | No | No | No | Yes |
| Session sharing | No | No | Yes | No | No |
| Session titles | No | No | Yes | No | No |
| Ephemeral mode | No | No | No | Yes | No |
| API key override | No | No | No | Yes | No |
| Token usage | No | Yes (partial) | Yes (full) | Yes | Yes |
| Cost tracking | No | No | Yes | Yes | No |
| Multi-provider models | No | No | Yes | Yes | No |
| Include directories | No | No | No | No | Yes |
| Debug mode | No | No | No | No | Yes |
| Free tier | No | No | No | No | Yes |

## Environment Variables

| Variable | Description |
|----------|-------------|
| `GEMINI_API_KEY` | Gemini API key |
| `GOOGLE_API_KEY` | Google Cloud API key |
| `GOOGLE_APPLICATION_CREDENTIALS` | Service account JSON path |
| `GOOGLE_CLOUD_PROJECT` | Project ID for Code Assist |
| `GOOGLE_GENAI_USE_VERTEXAI` | Enable Vertex AI |
| `GEMINI_SANDBOX` | Enable sandbox without CLI flag |
