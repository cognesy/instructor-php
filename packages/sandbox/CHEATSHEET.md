# Sandbox Cheat Sheet

## Core Entry Points

```php
use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\Sandbox\Sandbox;

$policy = ExecutionPolicy::in(__DIR__);
$sandbox = Sandbox::fromPolicy($policy)->using(SandboxDriver::Host);
```

Static constructors:

- `Sandbox::fromPolicy(ExecutionPolicy $policy): Sandbox`
- `Sandbox::host(ExecutionPolicy $policy): CanExecuteCommand`
- `Sandbox::docker(ExecutionPolicy $policy, ?string $image = null, ?string $dockerBin = null): CanExecuteCommand`
- `Sandbox::podman(ExecutionPolicy $policy, ?string $image = null, ?string $podmanBin = null): CanExecuteCommand`
- `Sandbox::firejail(ExecutionPolicy $policy, ?string $firejailBin = null): CanExecuteCommand`
- `Sandbox::bubblewrap(ExecutionPolicy $policy, ?string $bubblewrapBin = null): CanExecuteCommand`

Driver selection:

- `using(string|SandboxDriver $driver): CanExecuteCommand`

## Driver Enum

`SandboxDriver` values:

- `SandboxDriver::Host` (`host`)
- `SandboxDriver::Docker` (`docker`)
- `SandboxDriver::Podman` (`podman`)
- `SandboxDriver::Firejail` (`firejail`)
- `SandboxDriver::Bubblewrap` (`bubblewrap`)

## ExecutionPolicy

Create policy:

- `ExecutionPolicy::default(): ExecutionPolicy`
- `ExecutionPolicy::in(string $baseDir): ExecutionPolicy`

Accessors:

- `baseDir(): string`
- `timeoutSeconds(): int`
- `idleTimeoutSeconds(): ?int`
- `memoryLimit(): string`
- `readablePaths(): array`
- `writablePaths(): array`
- `env(): array`
- `inheritEnv(): bool`
- `networkEnabled(): bool`
- `stdoutLimitBytes(): int`
- `stderrLimitBytes(): int`

Immutable mutators:

- `withTimeout(int $seconds): self`
- `withIdleTimeout(?int $seconds): self`
- `withMemory(string $limit): self`
- `withReadablePaths(string ...$paths): self`
- `withWritablePaths(string ...$paths): self`
- `withEnv(array $env, ?bool $inherit = null): self`
- `inheritEnvironment(bool $inherit = true): self`
- `withNetwork(bool $enabled): self`
- `withOutputCaps(int $stdoutBytes, int $stderrBytes): self`
- `with(...): self` (full low-level override method)

## Command Execution API

Contract (`CanExecuteCommand`):

```php
interface CanExecuteCommand {
    public function policy(): ExecutionPolicy;
    public function execute(array $argv, ?string $stdin = null, ?callable $onOutput = null): ExecResult;
}
```

Streaming callback:

- Signature: `fn(string $type, string $chunk): void`
- `$type` is `'out'` or `'err'`

Example:

```php
$result = $sandbox->execute(
    ['ls', '-la'],
    null,
    function (string $type, string $chunk): void {
        echo $chunk;
    }
);
```

## ExecResult

Getters:

- `stdout(): string`
- `stderr(): string`
- `exitCode(): int`
- `duration(): float`
- `timedOut(): bool`
- `truncatedStdout(): bool`
- `truncatedStderr(): bool`
- `success(): bool`
- `combinedOutput(): string`
- `toArray(): array`

## Testing

`FakeSandbox` (implements `CanExecuteCommand`):

- `new FakeSandbox(ExecutionPolicy $policy, array $responses = [], ?ExecResult $defaultResponse = null)`
- `FakeSandbox::fromResponses(array $responses, ?ExecResult $defaultResponse = null): FakeSandbox`
- `commands(): array` (recorded argv calls)
- `enqueue(string $commandKey, ExecResult $result): void`
- `execute(array $argv, ?string $stdin = null, ?callable $onOutput = null): ExecResult`

Command key format for queued responses:

- `'cmd arg1 arg2'` (joined with spaces)

## Useful Environment Variables

Driver binary overrides:

- `DOCKER_BIN`
- `PODMAN_BIN`
- `FIREJAIL_BIN`
- `BWRAP_BIN`
