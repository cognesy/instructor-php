---
title: Agent Options
description: 'Configure shared builder options available on every agent and provider-specific options unique to Claude Code, Codex, OpenCode, Pi, and Gemini.'
---

## Introduction

Agent-Ctrl's builder API is split into two layers: a shared set of methods that every builder supports, and agent-specific methods that expose the unique capabilities of each CLI tool. This design lets you write agent-agnostic code for common configuration while still accessing the full feature set of each agent when needed.

All configuration methods return `static`, so they can be chained fluently in any order before calling `execute()` or `executeStreaming()`.

## Shared Options

The following methods are defined in the `AgentBridgeBuilder` interface and implemented by every bridge builder. They work identically regardless of which agent you are using.

### `withConfig(AgentCtrlConfig $config): static`

Apply a typed config object containing the shared builder options:

```php
use Cognesy\AgentCtrl\Config\AgentCtrlConfig;

$config = AgentCtrlConfig::fromArray([
    'model' => 'claude-sonnet-4-5',
    'timeout' => 300,
    'directory' => '/projects/my-app',
    'sandbox' => 'docker',
]);

AgentCtrl::claudeCode()
    ->withConfig($config)
    ->execute('Review the payment flow.');
```

This is the preferred way to pass shared builder defaults around your own application code. The object covers:

- `model`
- `timeout`
- `workingDirectory`
- `sandboxDriver`

### `withModel(string $model): static`

Set the model the agent should use. The accepted model name format depends on the agent:

```php
// Claude Code: Anthropic model names
AgentCtrl::claudeCode()->withModel('claude-sonnet-4-5');

// Codex: OpenAI model names
AgentCtrl::codex()->withModel('o4-mini');

// OpenCode: provider/model format
AgentCtrl::openCode()->withModel('anthropic/claude-sonnet-4-5');
```

If not specified, each agent uses its own default model.

### `withTimeout(int $seconds): static`

Set the maximum execution time in seconds. The default is 120 seconds. The minimum accepted value is 1 second -- values below 1 are clamped.

```php
AgentCtrl::claudeCode()
    ->withTimeout(600) // 10 minutes for complex tasks
    ->execute('Perform a comprehensive codebase review.');
```

When the timeout is reached, the sandbox executor kills the process. The response will contain whatever output was produced before the timeout and will have a non-zero exit code.

### `inDirectory(string $path): static`

Set the working directory for the agent. The bridge validates that the directory exists before execution and throws an `InvalidArgumentException` if it does not.

```php
AgentCtrl::codex()
    ->inDirectory('/projects/my-app')
    ->execute('List the source files.');
```

Always use absolute paths. The bridge changes the PHP process's current working directory for the duration of the execution and restores it afterward.

### `withSandboxDriver(SandboxDriver $driver): static`

Set the sandbox driver for process isolation. The default is `SandboxDriver::Host`, which runs the CLI binary directly on the host system.

```php
use Cognesy\Sandbox\Enums\SandboxDriver;

AgentCtrl::claudeCode()
    ->withSandboxDriver(SandboxDriver::Docker)
    ->execute('Analyze this codebase.');
```

Available drivers: `Host`, `Docker`, `Podman`, `Firejail`, `Bubblewrap`.

### Streaming Callbacks

Four callback methods are shared across all builders. See the [Streaming](/agent-ctrl/streaming) documentation for full details.

- `onText(callable $handler): static` -- Receive incremental text output
- `onToolUse(callable $handler): static` -- Receive normalized tool call events
- `onComplete(callable $handler): static` -- Receive the final `AgentResponse`
- `onError(callable $handler): static` -- Receive streamed error events

### `wiretap(callable $handler): static`

Connect an event observer to the builder's internal event system. This is primarily used with the `AgentCtrlConsoleLogger` for development-time debugging:

```php
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;

$logger = new AgentCtrlConsoleLogger(showStreaming: true);

AgentCtrl::claudeCode()
    ->wiretap($logger->wiretap())
    ->execute('Review this code.');
```

### `build(): AgentBridge`

Build the configured bridge without executing a prompt. This is an advanced method for scenarios where you need to call the bridge's `execute()` or `executeStreaming()` methods directly:

```php
use Cognesy\AgentCtrl\Config\AgentCtrlConfig;

$bridge = AgentCtrl::claudeCode()
    ->withConfig(new AgentCtrlConfig(
        model: 'claude-sonnet-4-5',
        timeout: 300,
    ))
    ->build();

$response = $bridge->execute('First prompt.');
$response2 = $bridge->executeStreaming('Second prompt.', $streamHandler);
```

## Claude Code Options

The `ClaudeCodeBridgeBuilder` adds the following methods on top of the shared options.

### `withSystemPrompt(string|\Stringable $prompt): static`

Replace the agent's default system prompt entirely with a custom one:

```php
AgentCtrl::claudeCode()
    ->withSystemPrompt('You are a security auditor. Focus on vulnerabilities.')
    ->execute('Audit the authentication module.');
```

### `appendSystemPrompt(string|\Stringable $prompt): static`

Add instructions on top of the default system prompt without replacing it. This preserves Claude Code's built-in behavior while layering in project-specific context:

```php
AgentCtrl::claudeCode()
    ->appendSystemPrompt('This project uses Laravel conventions. Follow PSR-12.')
    ->execute('Refactor the UserService class.');
```

You can use both `withSystemPrompt()` and `appendSystemPrompt()` together -- `withSystemPrompt()` sets the base and `appendSystemPrompt()` appends to it. Both accept `Stringable` objects (e.g. xprompt `Prompt` classes), which are cast to string at the boundary.

### `withMaxTurns(int $turns): static`

Limit the number of agentic turns. Each turn represents one cycle where the agent reads context, reasons, and takes an action. The minimum is 1.

```php
AgentCtrl::claudeCode()
    ->withMaxTurns(10)
    ->execute('Make a focused improvement to the README.');
```

Without a turn limit, Claude Code continues working until it decides the task is complete or the timeout is reached. For simple tasks, 5-10 turns is often sufficient. Complex refactoring may need 20-50 turns.

### `withPermissionMode(PermissionMode $mode): static`

Control how the agent handles tool permission requests during headless execution. The default is `BypassPermissions` because Agent-Ctrl runs headlessly and cannot respond to interactive permission prompts.

```php
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;

AgentCtrl::claudeCode()
    ->withPermissionMode(PermissionMode::AcceptEdits)
    ->execute('Write unit tests for the PaymentService.');
```

| Mode | Behavior |
|------|----------|
| `PermissionMode::DefaultMode` | Standard interactive prompts. Not suitable for headless execution. |
| `PermissionMode::Plan` | Agent can plan and reason but prompts before executing any tool. |
| `PermissionMode::AcceptEdits` | Auto-approve file editing tools; prompt for shell commands and other actions. |
| `PermissionMode::BypassPermissions` | Auto-approve all tool uses without prompting (default). |

### `verbose(bool $enabled = true): static`

Enable or disable verbose output. Verbose mode is required for proper stream-JSON parsing and is enabled by default. You generally do not need to change this setting.

### `continueSession(): static`

Continue the most recent Claude Code session. See [Session Management](/agent-ctrl/session-management).

### `resumeSession(string $sessionId): static`

Resume a specific Claude Code session by its ID. See [Session Management](/agent-ctrl/session-management).

### `withAdditionalDirs(array $paths): static`

Grant the agent access to additional directories beyond the working directory:

```php
AgentCtrl::claudeCode()
    ->inDirectory('/projects/my-app')
    ->withAdditionalDirs(['/shared/libraries', '/configs/production'])
    ->execute('Update the app to use the latest shared auth library.');
```

### Complete Claude Code Example

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;

$response = AgentCtrl::claudeCode()
    ->withModel('claude-sonnet-4-5')
    ->withSystemPrompt('You are a careful code reviewer.')
    ->appendSystemPrompt('Focus on error handling and edge cases.')
    ->withPermissionMode(PermissionMode::BypassPermissions)
    ->withMaxTurns(15)
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->withAdditionalDirs(['/shared/utils'])
    ->onText(fn(string $text) => print($text))
    ->executeStreaming('Review the PaymentService for error handling issues.');
```

## Codex Options

The `CodexBridgeBuilder` adds the following methods on top of the shared options.

### `withSandbox(SandboxMode $mode): static`

Set the Codex sandbox mode, which controls filesystem and network access:

```php
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;

AgentCtrl::codex()
    ->withSandbox(SandboxMode::WorkspaceWrite)
    ->execute('Write tests for the UserService.');
```

| Mode | Filesystem | Network |
|------|-----------|---------|
| `SandboxMode::ReadOnly` | Read-only access | No |
| `SandboxMode::WorkspaceWrite` | Write access to workspace only | No |
| `SandboxMode::DangerFullAccess` | Full access | Yes |

### `disableSandbox(): static`

Shorthand for `withSandbox(SandboxMode::DangerFullAccess)`. Provides full filesystem and network access:

```php
AgentCtrl::codex()
    ->disableSandbox()
    ->execute('Install dependencies and run the test suite.');
```

### `fullAuto(bool $enabled = true): static`

Enable full-auto mode, which combines workspace-write sandbox access with automatic on-failure approval. This is enabled by default for headless execution:

```php
AgentCtrl::codex()
    ->fullAuto()
    ->execute('Refactor the database layer.');
```

### `dangerouslyBypass(bool $enabled = true): static`

Skip all approval prompts and sandbox restrictions. This is the most permissive mode and should be used with caution:

```php
AgentCtrl::codex()
    ->dangerouslyBypass()
    ->execute('Deploy to staging.');
```

### `skipGitRepoCheck(bool $enabled = true): static`

Allow the agent to run outside a Git repository. By default, Codex requires the working directory to be inside a Git repository:

```php
AgentCtrl::codex()
    ->skipGitRepoCheck()
    ->inDirectory('/tmp/workspace')
    ->execute('Create a new project skeleton.');
```

### `withImages(array $imagePaths): static`

Attach image files to the prompt for visual analysis:

```php
AgentCtrl::codex()
    ->withImages(['/tmp/mockup.png', '/tmp/screenshot.png'])
    ->execute('Implement the UI shown in the mockup images.');
```

### `continueSession(): static`

Continue the most recent Codex session. See [Session Management](/agent-ctrl/session-management).

### `resumeSession(string $sessionId): static`

Resume a specific Codex session by its thread ID. See [Session Management](/agent-ctrl/session-management).

### `withAdditionalDirs(array $paths): static`

Add additional writable directories for the Codex agent:

```php
AgentCtrl::codex()
    ->withAdditionalDirs(['/shared/assets'])
    ->execute('Update the shared configuration.');
```

### Complete Codex Example

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;

$response = AgentCtrl::codex()
    ->withModel('o4-mini')
    ->withSandbox(SandboxMode::WorkspaceWrite)
    ->fullAuto()
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->withImages(['/tmp/design-spec.png'])
    ->onText(fn(string $text) => print($text))
    ->executeStreaming('Implement the component shown in the design spec.');
```

## OpenCode Options

The `OpenCodeBridgeBuilder` adds the following methods on top of the shared options.

### `withAgent(string $agentName): static`

Select a named agent within OpenCode (e.g., `'coder'`, `'task'`):

```php
AgentCtrl::openCode()
    ->withAgent('coder')
    ->execute('Refactor the authentication module.');
```

### `withFiles(array $filePaths): static`

Attach specific files to the prompt for the agent to reference:

```php
AgentCtrl::openCode()
    ->withFiles(['/projects/my-app/src/UserService.php'])
    ->execute('Review this file for potential issues.');
```

### `withTitle(string $title): static`

Set a descriptive title for the session, which appears in OpenCode's session listing:

```php
AgentCtrl::openCode()
    ->withTitle('Payment module refactoring')
    ->execute('Plan the payment module refactoring.');
```

### `shareSession(): static`

Mark the session for sharing after completion, making it accessible to other users or tools:

```php
AgentCtrl::openCode()
    ->shareSession()
    ->execute('Create a code review summary.');
```

### `continueSession(): static`

Continue the most recent OpenCode session. See [Session Management](/agent-ctrl/session-management).

### `resumeSession(string $sessionId): static`

Resume a specific OpenCode session by its ID. See [Session Management](/agent-ctrl/session-management).

### Complete OpenCode Example

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::openCode()
    ->withModel('anthropic/claude-sonnet-4-5')
    ->withAgent('coder')
    ->withTitle('Architecture review')
    ->withFiles(['/projects/my-app/src/Kernel.php'])
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->onText(fn(string $text) => print($text))
    ->executeStreaming('Review the application architecture.');

if ($response->cost() !== null) {
    echo sprintf("\nCost: $%.4f\n", $response->cost());
}
```

## Pi Options

The `PiBridgeBuilder` adds the following methods on top of the shared options.

### `withProvider(string $provider): static`

Set the provider explicitly (e.g., `'anthropic'`, `'openai'`, `'google'`):

```php
AgentCtrl::pi()
    ->withProvider('anthropic')
    ->execute('Analyze this codebase.');
```

### `withThinking(ThinkingLevel $level): static`

Set the thinking level, which controls how much reasoning the agent does:

```php
use Cognesy\AgentCtrl\Pi\Domain\Enum\ThinkingLevel;

AgentCtrl::pi()
    ->withThinking(ThinkingLevel::High)
    ->execute('Solve this complex problem.');
```

| Level | Value |
|-------|-------|
| `ThinkingLevel::Off` | `'off'` |
| `ThinkingLevel::Minimal` | `'minimal'` |
| `ThinkingLevel::Low` | `'low'` |
| `ThinkingLevel::Medium` | `'medium'` |
| `ThinkingLevel::High` | `'high'` |
| `ThinkingLevel::ExtraHigh` | `'xhigh'` |

### `withSystemPrompt(string|\Stringable $prompt): static`

Replace the agent's default system prompt:

```php
AgentCtrl::pi()
    ->withSystemPrompt('You are a security auditor.')
    ->execute('Audit the authentication module.');
```

### `appendSystemPrompt(string|\Stringable $prompt): static`

Add instructions on top of the default system prompt:

```php
AgentCtrl::pi()
    ->appendSystemPrompt('Focus on PSR-12 compliance.')
    ->execute('Review this code.');
```

### `withTools(array $tools): static`

Enable specific built-in tools:

```php
AgentCtrl::pi()
    ->withTools(['read', 'bash', 'edit'])
    ->execute('Refactor this file.');
```

### `noTools(): static`

Disable all built-in tools.

### `withFiles(array $filePaths): static`

Attach files to the prompt:

```php
AgentCtrl::pi()
    ->withFiles(['/projects/my-app/src/UserService.php'])
    ->execute('Review this file.');
```

### `withExtensions(array $extensions): static`

Load extensions from paths or sources.

### `noExtensions(): static`

Disable extension discovery.

### `withSkills(array $skills): static`

Load skills from paths.

### `noSkills(): static`

Disable skill discovery.

### `withApiKey(string $apiKey): static`

Override the API key for this execution.

### `ephemeral(): static`

Run in ephemeral mode -- the session is not saved.

### `withSessionDir(string $dir): static`

Set a custom session storage directory.

### `verbose(bool $enabled = true): static`

Enable verbose output.

### `continueSession(): static`

Continue the most recent Pi session. See [Session Management](/agent-ctrl/session-management).

### `resumeSession(string $sessionId): static`

Resume a specific Pi session by its ID. See [Session Management](/agent-ctrl/session-management).

### Complete Pi Example

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Pi\Domain\Enum\ThinkingLevel;

$response = AgentCtrl::pi()
    ->withProvider('anthropic')
    ->withThinking(ThinkingLevel::High)
    ->withSystemPrompt('You are a careful code reviewer.')
    ->withTools(['read', 'bash'])
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->onText(fn(string $text) => print($text))
    ->executeStreaming('Review the PaymentService for edge cases.');
```

## Gemini Options

The `GeminiBridgeBuilder` adds the following methods on top of the shared options.

### `withApprovalMode(ApprovalMode $mode): static`

Set the approval mode for tool execution:

```php
use Cognesy\AgentCtrl\Gemini\Domain\Enum\ApprovalMode;

AgentCtrl::gemini()
    ->withApprovalMode(ApprovalMode::Yolo)
    ->execute('Refactor the database layer.');
```

| Mode | Value | Behavior |
|------|-------|----------|
| `ApprovalMode::Default` | `'default'` | Standard interactive prompts |
| `ApprovalMode::AutoEdit` | `'auto_edit'` | Auto-approve edits, prompt for others |
| `ApprovalMode::Yolo` | `'yolo'` | Auto-approve all actions |
| `ApprovalMode::Plan` | `'plan'` | Read-only analysis mode |

### `yolo(): static`

Shorthand for `withApprovalMode(ApprovalMode::Yolo)`:

```php
AgentCtrl::gemini()
    ->yolo()
    ->execute('Implement the feature.');
```

### `planMode(): static`

Shorthand for `withApprovalMode(ApprovalMode::Plan)`:

```php
AgentCtrl::gemini()
    ->planMode()
    ->execute('Analyze the codebase architecture.');
```

### `withSandbox(bool $enabled = true): static`

Enable Gemini's sandbox mode for additional isolation.

### `withIncludeDirectories(array $paths): static`

Add additional workspace directories:

```php
AgentCtrl::gemini()
    ->withIncludeDirectories(['/shared/libraries', '/configs'])
    ->execute('Review the shared library usage.');
```

### `withExtensions(array $extensions): static`

Use specific extensions.

### `withAllowedTools(array $tools): static`

Restrict which tools the agent can use:

```php
AgentCtrl::gemini()
    ->withAllowedTools(['read_file', 'search_files', 'list_directory'])
    ->execute('Analyze the codebase structure.');
```

### `withAllowedMcpServers(array $names): static`

Set allowed MCP server names.

### `withPolicy(array $paths): static`

Add policy files or directories.

### `debug(bool $enabled = true): static`

Enable debug output for troubleshooting CLI behavior.

### `continueSession(): static`

Continue the most recent Gemini session (resumes `'latest'` internally). See [Session Management](/agent-ctrl/session-management).

### `resumeSession(string $sessionId): static`

Resume a specific Gemini session by its ID or index. See [Session Management](/agent-ctrl/session-management).

### Complete Gemini Example

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Gemini\Domain\Enum\ApprovalMode;

$response = AgentCtrl::gemini()
    ->withApprovalMode(ApprovalMode::Yolo)
    ->withAllowedTools(['read_file', 'edit_file', 'shell'])
    ->withIncludeDirectories(['/shared/utils'])
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->onText(fn(string $text) => print($text))
    ->executeStreaming('Review the authentication module.');
```
