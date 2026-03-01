# AgentCtrl Cheat Sheet

## Entry Points

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

$builder = AgentCtrl::make(AgentType::Codex);
$builder = AgentCtrl::claudeCode();
$builder = AgentCtrl::codex();
$builder = AgentCtrl::openCode();
```

## Key Enums

- `Cognesy\AgentCtrl\Enum\AgentType`
- `Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode`
- `Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode`
- `Cognesy\Sandbox\Enums\SandboxDriver`

## Common Builder API (All Agents)

- `withModel(string $model): static`
- `withTimeout(int $seconds): static`
- `inDirectory(string $path): static`
- `withSandboxDriver(SandboxDriver $driver): static`
- `onText(callable $handler): static`
- `onToolUse(callable $handler): static`
- `onComplete(callable $handler): static`
- `onError(callable $handler): static`
- `execute(string $prompt): AgentResponse`
- `executeStreaming(string $prompt): AgentResponse`
- `build(): AgentBridge`

Callback signatures:

- `onText(fn(string $text): void)`
- `onToolUse(fn(string $tool, array $input, ?string $output): void)`
- `onComplete(fn(AgentResponse $response): void)`
- `onError(fn(string $message, ?string $code): void)`

Event hooks available on builders:

- `withEventHandler(CanHandleEvents|EventDispatcherInterface $events): static`
- `wiretap(?callable $listener): self`
- `onEvent(string $class, ?callable $listener): self`

## Claude Code Builder

- `withSystemPrompt(string $prompt): static`
- `appendSystemPrompt(string $prompt): static`
- `withMaxTurns(int $turns): static`
- `withPermissionMode(PermissionMode $mode): static`
- `verbose(bool $enabled = true): static`
- `continueSession(): static`
- `resumeSession(string $sessionId): static`
- `withAdditionalDirs(array $paths): static`

## Codex Builder

- `withSandbox(SandboxMode $mode): static`
- `disableSandbox(): static`
- `fullAuto(bool $enabled = true): static`
- `dangerouslyBypass(bool $enabled = true): static`
- `skipGitRepoCheck(bool $enabled = true): static`
- `continueSession(): static`
- `resumeSession(string $sessionId): static`
- `withAdditionalDirs(array $paths): static`
- `withImages(array $imagePaths): static`

## OpenCode Builder

- `withAgent(string $agentName): static`
- `withFiles(array $filePaths): static`
- `continueSession(): static`
- `resumeSession(string $sessionId): static`
- `shareSession(): static`
- `withTitle(string $title): static`

## Session Management

```php
// start run
$response = AgentCtrl::codex()->execute('Create a plan.');

// continue recent session
$next = AgentCtrl::codex()
    ->continueSession()
    ->execute('Apply step 1.');

// resume by session id
$sessionId = $response->sessionId();
if ($sessionId !== null) {
    $again = AgentCtrl::codex()
        ->resumeSession((string) $sessionId)
        ->execute('Apply step 2.');
}
```

`sessionId()` returns `AgentSessionId|null`.

## Response API (`AgentResponse`)

Public properties:

- `agentType` (`AgentType`)
- `text` (`string`)
- `exitCode` (`int`)
- `usage` (`TokenUsage|null`)
- `cost` (`float|null`)
- `toolCalls` (`list<ToolCall>`)
- `rawResponse` (`mixed`)
- `parseFailures` (`int`)

Methods:

- `sessionId(): ?AgentSessionId`
- `isSuccess(): bool`
- `text(): string`
- `usage(): ?TokenUsage`
- `cost(): ?float`
- `parseFailures(): int`
- `parseFailureSamples(): array`

## Tool Calls and Usage

`ToolCall`:

- `tool` (`string`)
- `input` (`array`)
- `output` (`?string`)
- `isError` (`bool`)
- `callId(): ?AgentToolCallId`
- `isCompleted(): bool`

`TokenUsage`:

- `input` (`int`)
- `output` (`int`)
- `cacheRead` (`?int`)
- `cacheWrite` (`?int`)
- `reasoning` (`?int`)
- `total(): int`

## Minimal Streaming Example

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::openCode()
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("\n[tool:$tool]\n"))
    ->onError(fn(string $message) => print("\n[error:$message]\n"))
    ->executeStreaming('Explain this project structure.');

echo "\nExit code: {$response->exitCode}\n";
```
