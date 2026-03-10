---
title: Getting Started
description: 'Run your first sandboxed command in three steps: create a policy, pick a driver, and execute.'
---

## Introduction

Getting started with the Sandbox package requires just three steps: define an execution policy, create a sandbox instance with your chosen driver, and execute a command. This guide walks through each step and covers the most common patterns you will use day-to-day.

## Step 1: Create an Execution Policy

Every sandbox requires an `ExecutionPolicy` that defines the constraints for command execution. The simplest way to create one is with the `in()` factory, which sets the base working directory:

```php
use Cognesy\Sandbox\Config\ExecutionPolicy;

$policy = ExecutionPolicy::in(__DIR__);
```

The base directory serves as the working directory for host-mode execution and as the parent for temporary work directories that container drivers create. It must exist and be writable by the PHP process.

If you do not need a specific directory, use the `default()` factory, which defaults to `/tmp`:

```php
$policy = ExecutionPolicy::default();
```

Both factories return a policy with secure defaults: a 5-second timeout, 128 MB memory limit, network disabled, no environment inheritance, and 1 MB output caps for both stdout and stderr.

## Step 2: Create a Sandbox Instance

Use one of the static factory methods on the `Sandbox` class to create an instance with your preferred driver:

```php
use Cognesy\Sandbox\Sandbox;

// Host driver -- runs commands directly on the host
$sandbox = Sandbox::host($policy);

// Docker driver -- runs commands inside a container
$sandbox = Sandbox::docker($policy, image: 'php:8.3-cli-alpine');

// Podman driver -- rootless container execution
$sandbox = Sandbox::podman($policy, image: 'alpine:3');

// Firejail driver -- Linux namespace sandboxing
$sandbox = Sandbox::firejail($policy);

// Bubblewrap driver -- minimal Linux namespace isolation
$sandbox = Sandbox::bubblewrap($policy);
```

All factory methods return an instance of `CanExecuteCommand`, so you can swap drivers without changing your calling code.

### Dynamic Driver Selection

When the driver is determined at runtime (for example, from a configuration file), use the `fromPolicy()` builder combined with the `using()` method. You can pass either a `SandboxDriver` enum case or a plain string:

```php
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\Sandbox\Sandbox;

// Using the enum
$sandbox = Sandbox::fromPolicy($policy)->using(SandboxDriver::Docker);

// Using a string (e.g., from config)
$driverName = config('sandbox.driver'); // 'docker'
$sandbox = Sandbox::fromPolicy($policy)->using($driverName);
```

Valid string values are: `host`, `docker`, `podman`, `firejail`, `bubblewrap`. An `InvalidArgumentException` is thrown if the value does not match any known driver.

## Step 3: Execute a Command

Call the `execute()` method with a command expressed as an array of strings (argv format):

```php
$result = $sandbox->execute(['php', '-v']);

echo $result->stdout();   // "PHP 8.3.0 (cli) ..."
echo $result->exitCode(); // 0
```

Always pass commands in argv format -- each argument as a separate array element. This avoids shell injection vulnerabilities and ensures correct argument parsing across all drivers.

### Passing Standard Input

To pipe data into the command's stdin, pass it as the second argument:

```php
$result = $sandbox->execute(
    ['php', '-r', 'echo strtoupper(fgets(STDIN));'],
    'hello world'
);

echo $result->stdout(); // "HELLO WORLD"
```

### Checking the Result

The `ExecResult` object provides everything you need to determine whether the command succeeded and to retrieve its output:

```php
if ($result->success()) {
    // Exit code is 0 and no timeout occurred
    echo $result->stdout();
} else {
    // Something went wrong
    echo "Exit code: " . $result->exitCode() . "\n";
    echo "Stderr: " . $result->stderr() . "\n";

    if ($result->timedOut()) {
        echo "The command exceeded its time limit.\n";
    }
}
```

## Complete Example

Here is a complete example that creates a policy, builds a sandbox, executes a PHP script, and handles the result:

```php
use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Sandbox;

// 1. Define constraints
$policy = ExecutionPolicy::in('/tmp')
    ->withTimeout(10)
    ->withMemory('256M');

// 2. Create sandbox with host driver
$sandbox = Sandbox::host($policy);

// 3. Execute and inspect result
$result = $sandbox->execute(['php', '-r', 'echo json_encode(["status" => "ok"]);']);

if ($result->success()) {
    $data = json_decode($result->stdout(), true);
    echo "Status: " . $data['status']; // "ok"
} else {
    echo "Command failed with exit code " . $result->exitCode();
}
```

## Dependency Injection

Since all drivers implement the `CanExecuteCommand` interface, you can type-hint against the interface in your application services. This makes it easy to swap implementations and simplifies testing with `FakeSandbox`:

```php
use Cognesy\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Sandbox\Data\ExecResult;

class CodeRunner
{
    public function __construct(
        private readonly CanExecuteCommand $sandbox,
    ) {}

    public function run(string $code): ExecResult
    {
        return $this->sandbox->execute(['php', '-r', $code]);
    }
}
```

## Next Steps

- Learn how to fine-tune execution constraints in [Execution Policy](3-execution-policy.md).
- Explore the available isolation backends in [Drivers](4-drivers.md).
- Set up real-time output consumption in [Streaming and Results](5-streaming-and-results.md).
