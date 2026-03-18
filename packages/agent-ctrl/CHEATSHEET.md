---
title: AgentCtrl
description: External coding agent control — Claude Code, Codex, OpenCode, and Gemini CLI bridges
package: agent-ctrl
---

# AgentCtrl Cheat Sheet

## Entry Points

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Config\AgentCtrlConfig;
use Cognesy\AgentCtrl\Enum\AgentType;

$builder = AgentCtrl::claudeCode();
$builder = AgentCtrl::codex();
$builder = AgentCtrl::openCode();
$builder = AgentCtrl::pi();
$builder = AgentCtrl::gemini();
$builder = AgentCtrl::make(AgentType::Codex);
```

`AgentType` values:

- `AgentType::ClaudeCode`
- `AgentType::Codex`
- `AgentType::OpenCode`
- `AgentType::Pi`
- `AgentType::Gemini`

Backed values:

- `claude-code`
- `codex`
- `opencode`
- `pi`
- `gemini`

## Common Builder API

All builders support:

- `withConfig(AgentCtrlConfig $config): static`
- `withModel(string $model): static`
- `withTimeout(int $seconds): static`
- `inDirectory(string $path): static`
- `withSandboxDriver(SandboxDriver $driver): static`
- `onText(callable $handler): static`
- `onToolUse(callable $handler): static`
- `onComplete(callable $handler): static`
- `onError(callable $handler): static`
- `execute(string|\Stringable $prompt): AgentResponse`
- `executeStreaming(string|\Stringable $prompt): AgentResponse`
- `build(): AgentBridge`

Callback signatures:

- `onText(fn(string $text): void)`
- `onToolUse(fn(string $tool, array $input, ?string $output): void)`
- `onComplete(fn(AgentResponse $response): void)`
- `onError(fn(string $message, ?string $code): void)`

`AgentCtrlConfig` shared fields:

- `model`
- `timeout`
- `workingDirectory`
- `sandboxDriver`

`AgentCtrlConfig` additional methods:

- `fromDsn(string $dsn): self`
- `withOverrides(array $values): self`
- `toArray(): array`

`AgentCtrlConfig::fromArray()` also accepts:

- `directory` -> `workingDirectory`
- `sandbox` -> `sandboxDriver`

```php
$builder = AgentCtrl::codex()->withConfig(AgentCtrlConfig::fromArray([
    'model' => 'gpt-5-codex',
    'timeout' => 300,
    'directory' => getcwd(),
    'sandbox' => 'host',
]));
```

## Claude Code

- `withSystemPrompt(string|\Stringable $prompt): static`
- `appendSystemPrompt(string|\Stringable $prompt): static`
- `withMaxTurns(int $turns): static`
- `withPermissionMode(PermissionMode $mode): static`
- `verbose(bool $enabled = true): static`
- `continueSession(): static`
- `resumeSession(string $sessionId): static`
- `withAdditionalDirs(array $paths): static`

## Codex

- `withSandbox(SandboxMode $mode): static`
- `disableSandbox(): static`
- `fullAuto(bool $enabled = true): static`
- `dangerouslyBypass(bool $enabled = true): static`
- `skipGitRepoCheck(bool $enabled = true): static`
- `continueSession(): static`
- `resumeSession(string $sessionId): static`
- `withAdditionalDirs(array $paths): static`
- `withImages(array $imagePaths): static`

## OpenCode

- `withAgent(string $agentName): static`
- `withFiles(array $filePaths): static`
- `continueSession(): static`
- `resumeSession(string $sessionId): static`
- `shareSession(): static`
- `withTitle(string $title): static`

## Pi

- `withProvider(string $provider): static`
- `withThinking(ThinkingLevel $level): static`
- `withSystemPrompt(string|\Stringable $prompt): static`
- `appendSystemPrompt(string|\Stringable $prompt): static`
- `withTools(array $tools): static`
- `noTools(): static`
- `withFiles(array $filePaths): static`
- `withExtensions(array $extensions): static`
- `noExtensions(): static`
- `withSkills(array $skills): static`
- `noSkills(): static`
- `withApiKey(string $apiKey): static`
- `continueSession(): static`
- `resumeSession(string $sessionId): static`
- `ephemeral(): static`
- `withSessionDir(string $dir): static`
- `verbose(bool $enabled = true): static`

## Gemini

- `withApprovalMode(ApprovalMode $mode): static`
- `yolo(): static`
- `planMode(): static`
- `withSandbox(bool $enabled = true): static`
- `withIncludeDirectories(array $paths): static`
- `withExtensions(array $extensions): static`
- `withAllowedTools(array $tools): static`
- `withAllowedMcpServers(array $names): static`
- `withPolicy(array $paths): static`
- `continueSession(): static`
- `resumeSession(string $sessionId): static`
- `debug(bool $enabled = true): static`

## Sessions and Executions

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::codex()->execute('Create a plan.');

$next = AgentCtrl::codex()
    ->continueSession()
    ->execute('Apply step 1.');

$sessionId = $response->sessionId();

if ($sessionId !== null) {
    $again = AgentCtrl::codex()
        ->resumeSession((string) $sessionId)
        ->execute('Apply step 2.');
}
```

Identity rules:

- `executionId()` returns `AgentCtrlExecutionId`
- `sessionId()` returns `AgentSessionId|null`
- `executionId()` is generated once per `execute()` or `executeStreaming()` call
- `sessionId()` is provider continuity metadata for resumed conversations
- separate runs may share one `sessionId()` and still have different `executionId()` values

## Response

`AgentResponse` public properties:

- `agentType`
- `text`
- `exitCode`
- `usage`
- `cost`
- `toolCalls`
- `rawResponse`
- `parseFailures`

`AgentResponse` methods:

- `executionId(): AgentCtrlExecutionId`
- `sessionId(): ?AgentSessionId`
- `isSuccess(): bool`
- `text(): string`
- `usage(): ?TokenUsage`
- `cost(): ?float`
- `parseFailures(): int`
- `parseFailureSamples(): array`

## Tool Calls and Usage

`ToolCall`:

- `tool`
- `input`
- `output`
- `isError`
- `callId(): ?AgentToolCallId`
- `isCompleted(): bool`

`TokenUsage`:

- `input`
- `output`
- `cacheRead`
- `cacheWrite`
- `reasoning`
- `total(): int`

## StreamHandler

`StreamHandler` interface (`Cognesy\AgentCtrl\Contract\StreamHandler`):

- `onText(string $text): void`
- `onToolUse(ToolCall $toolCall): void`
- `onComplete(AgentResponse $response): void`
- `onError(StreamError $error): void`

`CallbackStreamHandler` implements `StreamHandler` with optional closures:

- `hasTextHandler(): bool`
- `hasToolUseHandler(): bool`
- `hasCompleteHandler(): bool`
- `hasErrorHandler(): bool`
- `hasAnyHandler(): bool`

`StreamError`:

- `message` (string)
- `code` (?string)
- `details` (`array<string,mixed>`)

## Events

Concrete builders also expose event wiring through `HandlesEvents`:

- `withEventHandler(CanHandleEvents $events): static`
- `wiretap(?callable $listener): self`
- `onEvent(string $class, ?callable $listener): self`

Event classes (`Cognesy\AgentCtrl\Event\*`):

Execution lifecycle:
- `AgentExecutionStarted` -- execution begins
- `AgentExecutionCompleted` -- execution finishes
- `ExecutionAttempted` -- an execution attempt is made

Request building:
- `RequestBuilt` -- request object assembled
- `CommandSpecCreated` -- CLI command spec created

Process execution:
- `ProcessExecutionStarted` -- sandbox process starts
- `ProcessExecutionCompleted` -- sandbox process finishes

Streaming:
- `StreamProcessingStarted` -- stream parsing begins
- `StreamChunkProcessed` -- a JSONL chunk is parsed
- `StreamProcessingCompleted` -- stream parsing finishes

Response parsing:
- `ResponseParsingStarted` -- response parser begins
- `ResponseDataExtracted` -- data extracted from raw response
- `ResponseParsingCompleted` -- response parser finishes

Agent content:
- `AgentTextReceived` -- text content received
- `AgentToolUsed` -- tool call detected
- `AgentErrorOccurred` -- error event from agent

Sandbox:
- `SandboxInitialized` -- sandbox driver initialized
- `SandboxPolicyConfigured` -- sandbox policy set
- `SandboxReady` -- sandbox ready for execution

## Testing

Deterministic test seams:

- core package logic
  - test `AgentCtrlConfig`, command builders, parsers, and DTOs directly
- `Cognesy\Sandbox\Testing\FakeSandbox`
  - use for deterministic command execution without running a real CLI
  - best for bridge execution paths, timeout behavior, and stdout/stderr handling
- `Cognesy\Instructor\Laravel\Testing\AgentCtrlFake`
  - use only when testing the Laravel facade layer
  - queued responses and execution assertions live there, not in core `agent-ctrl`

## Streaming

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;

$response = AgentCtrl::openCode()
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("[{$tool}]"))
    ->onComplete(fn(AgentResponse $response) => print("\nDone\n"))
    ->onError(fn(string $message, ?string $code) => print("\nError: {$message}\n"))
    ->executeStreaming('Explain this project structure.');
```
