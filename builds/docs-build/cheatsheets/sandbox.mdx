---
title: Sandbox
description: Code execution sandbox — execution policies, drivers, streaming results, and security
package: sandbox
---

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

- `using(string|SandboxDriver $driver): CanExecuteCommand` (uses default image/binary for container drivers)

## Driver Enum

`SandboxDriver` values:

- `SandboxDriver::Host` (`host`)
- `SandboxDriver::Docker` (`docker`)
- `SandboxDriver::Podman` (`podman`)
- `SandboxDriver::Firejail` (`firejail`)
- `SandboxDriver::Bubblewrap` (`bubblewrap`)

## ExecutionPolicy

Create policy:

- `ExecutionPolicy::default(): ExecutionPolicy` (baseDir: `/tmp`)
- `ExecutionPolicy::in(string $baseDir): ExecutionPolicy`

Defaults: timeout 5s, memory `128M`, no idle timeout, no network, no env inheritance, 1MB output caps.

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
- `with(?string $baseDir, ?int $timeoutSeconds, ?int $idleTimeoutSeconds, ?string $memoryLimit, ?array $readablePaths, ?array $writablePaths, ?array $env, ?bool $inheritEnv, ?bool $networkEnabled, ?int $stdoutLimitBytes, ?int $stderrLimitBytes): self` (all params nullable, unset params keep current values)

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

Constructor:

```php
new ExecResult(
    string $stdout,
    string $stderr,
    int $exitCode,
    float $duration,
    bool $timedOut = false,
    bool $truncatedStdout = false,
    bool $truncatedStderr = false,
)
```

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

## Value Objects

`Argv`:

- `Argv::of(array $items): Argv`
- `with(string $value): Argv`
- `toArray(): array`

`CommandSpec`:

- `new CommandSpec(Argv $argv, ?string $stdin = null)`
- `argv(): Argv`
- `stdin(): ?string`

## Testing

`FakeSandbox` (implements `CanExecuteCommand`):

- `new FakeSandbox(ExecutionPolicy $policy, array $responses = [], ?ExecResult $defaultResponse = null)`
- `FakeSandbox::fromResponses(array $responses, ?ExecResult $defaultResponse = null): FakeSandbox`

Responses format: `array<string, list<ExecResult|array>>` -- each entry can be an `ExecResult` or an associative array with keys: `stdout`, `stderr`, `exit_code`, `duration`, `timed_out`, `truncated_stdout`, `truncated_stderr`.

- `policy(): ExecutionPolicy`
- `commands(): array` (recorded argv calls)
- `enqueue(string $commandKey, ExecResult $result): void`
- `execute(array $argv, ?string $stdin = null, ?callable $onOutput = null): ExecResult`

Command key format for queued responses:

- `'cmd arg1 arg2'` (joined with spaces)

## Mount (container drivers)

`Mount` (used by Docker/Podman drivers for volume binds):

- `new Mount(string $host, string $container, string $options)`
- `host(): string`
- `container(): string`
- `options(): string`
- `toVolumeArg(): string` (returns `host:container:options`)

## Exit Code Constants

`ExitCodes`:

- `ExitCodes::TIMEOUT` = `124` (GNU timeout convention)

## Useful Environment Variables

Driver binary overrides:

- `DOCKER_BIN`
- `PODMAN_BIN`
- `FIREJAIL_BIN`
- `BWRAP_BIN`
