# AgentCtrl Cheat Sheet

## Entry Points

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Config\AgentConfig;
use Cognesy\AgentCtrl\Enum\AgentType;

$builder = AgentCtrl::claudeCode();
$builder = AgentCtrl::codex();
$builder = AgentCtrl::openCode();
$builder = AgentCtrl::make(AgentType::Codex);
```

`AgentType` values:

- `AgentType::ClaudeCode`
- `AgentType::Codex`
- `AgentType::OpenCode`

Backed values:

- `claude-code`
- `codex`
- `opencode`

## Common Builder API

All builders support:

- `withConfig(AgentConfig $config): static`
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

`AgentConfig` shared fields:

- `model`
- `timeout`
- `workingDirectory`
- `sandboxDriver`

`AgentConfig::fromArray()` also accepts:

- `directory` -> `workingDirectory`
- `sandbox` -> `sandboxDriver`

```php
$builder = AgentCtrl::codex()->withConfig(AgentConfig::fromArray([
    'model' => 'gpt-5-codex',
    'timeout' => 300,
    'directory' => getcwd(),
    'sandbox' => 'host',
]));
```

## Claude Code

- `withSystemPrompt(string $prompt): static`
- `appendSystemPrompt(string $prompt): static`
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

## Sessions

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

`sessionId()` returns `AgentSessionId|null`.

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

## Events

Concrete builders also expose event wiring through `HandlesEvents`:

- `withEventHandler(CanHandleEvents $events): static`
- `wiretap(?callable $listener): self`
- `onEvent(string $class, ?callable $listener): self`

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
