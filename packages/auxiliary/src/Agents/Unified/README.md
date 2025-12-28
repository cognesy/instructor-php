# Unified Agent Bridge

A common abstraction layer for CLI-based code agents (Claude Code, OpenAI Codex, OpenCode).

## Overview

The Unified Agent Bridge provides:

- **Fluent Builder API** - Configure agents with typed, IDE-friendly methods
- **Runtime Switching** - Change agents without code changes
- **Common Response Format** - Normalized `UnifiedResponse` across all agents
- **Streaming Support** - Real-time callbacks for text and tool events

## Quick Start

### Basic Usage

```php
use Cognesy\Auxiliary\Agents\Unified\AgentCtrl;
use Cognesy\Auxiliary\Agents\Unified\Enum\AgentType;

// Execute a prompt
$response = AgentCtrl::make(AgentType::ClaudeCode)
    ->execute('Fix the bug in AuthController');

echo $response->text();
```

### With Configuration

```php
// Claude Code with full configuration
$response = AgentCtrl::claudeCode()
    ->withModel('claude-opus-4-5')
    ->withSystemPrompt('You are a senior PHP developer')
    ->withMaxTurns(10)
    ->withPermissionMode(PermissionMode::BypassPermissions)
    ->execute('Refactor the User model');

// OpenCode with provider/model format
$response = AgentCtrl::openCode()
    ->withModel('anthropic/claude-sonnet-4-5')
    ->withAgent('coder')
    ->withFiles(['src/User.php'])
    ->execute('Add validation');

// Codex with sandbox control
$response = AgentCtrl::codex()
    ->withModel('codex')
    ->withSandbox(SandboxMode::NetworkOnly)
    ->fullAuto()
    ->execute('Write tests for the API');
```

### Streaming

```php
$response = AgentCtrl::openCode()
    ->withModel('anthropic/claude-sonnet-4-5')
    ->onText(fn($text) => print($text))
    ->onToolUse(fn($tool, $input, $output) => print("[Tool: $tool]\n"))
    ->onComplete(fn($response) => print("\nDone!\n"))
    ->executeStreaming('Explain this codebase');
```

### Runtime Switching

```php
// Select agent from configuration
$agentType = AgentType::from($config['agent']); // 'claude-code', 'codex', 'opencode'

$response = AgentCtrl::make($agentType)
    ->withModel($config['model'])
    ->execute($prompt);
```

## Builder Methods

### Common (All Agents)

| Method | Description |
|--------|-------------|
| `withModel(string)` | Set the model to use |
| `withTimeout(int)` | Set execution timeout in seconds |
| `inDirectory(string)` | Set working directory |
| `withSandboxDriver(SandboxDriver)` | Set sandbox driver (Host, Docker, etc.) |
| `onText(callable)` | Set text event callback |
| `onToolUse(callable)` | Set tool use callback |
| `onComplete(callable)` | Set completion callback |

### Claude Code Specific

| Method | Description |
|--------|-------------|
| `withSystemPrompt(string)` | Set system prompt |
| `appendSystemPrompt(string)` | Append to default system prompt |
| `withMaxTurns(int)` | Limit agentic turns |
| `withPermissionMode(PermissionMode)` | Set permission handling |
| `verbose(bool)` | Enable verbose output |
| `continueSession()` | Continue most recent session |
| `resumeSession(string)` | Resume specific session by ID |
| `withAdditionalDirs(array)` | Add accessible directories |

### Codex Specific

| Method | Description |
|--------|-------------|
| `withSandbox(SandboxMode)` | Set sandbox mode |
| `disableSandbox()` | Disable sandbox |
| `fullAuto(bool)` | Enable full auto mode |
| `dangerouslyBypass(bool)` | Skip all approvals (DANGEROUS) |
| `skipGitRepoCheck(bool)` | Allow running outside git |
| `withImages(array)` | Attach image files |

### OpenCode Specific

| Method | Description |
|--------|-------------|
| `withAgent(string)` | Use named agent |
| `withFiles(array)` | Attach files to prompt |
| `continueSession()` | Continue last session |
| `resumeSession(string)` | Resume specific session |
| `shareSession()` | Share session after completion |
| `withTitle(string)` | Set session title |

## Response

All agents return `UnifiedResponse`:

```php
$response->agentType;   // AgentType enum
$response->text;        // Response text
$response->exitCode;    // Process exit code
$response->sessionId;   // Session ID (if available)
$response->usage;       // TokenUsage (input/output tokens)
$response->cost;        // Cost in USD (if available)
$response->toolCalls;   // Array of ToolCall objects
$response->rawResponse; // Original bridge response

$response->isSuccess(); // true if exitCode === 0
$response->text();      // Alias for $response->text
$response->usage();     // Alias for $response->usage
$response->cost();      // Alias for $response->cost
```

## Architecture

```
AgentCtrl::make(AgentType)
    ↓
AgentBridgeBuilder (ClaudeCode|Codex|OpenCode)
    ↓ .build()
AgentBridge (wraps native components)
    ↓ .execute() or .executeStreaming()
UnifiedResponse (normalized output)
```

## See Also

- [ClaudeCode Bridge](../ClaudeCode/README.md)
- [OpenAICodex Bridge](../OpenAICodex/README.md)
- [OpenCode Bridge](../OpenCode/README.md)
- [Common Components](../Common/)
