# Instructor Sandbox

Sandboxed command execution for PHP with multiple drivers.

## Features

- Execute shell commands with timeout and output limits
- Multiple sandbox drivers:
  - **Host** - Direct execution on the host system
  - **Docker** - Container isolation via Docker
  - **Podman** - Container isolation via Podman
  - **Firejail** - Linux sandbox using Firejail
  - **Bubblewrap** - Linux sandbox using bubblewrap (bwrap)
- Configurable execution policies (timeout, memory, network, paths)
- Output streaming support
- Mock sandbox for testing

## Installation

```bash
composer require cognesy/instructor-sandbox
```

## Basic Usage

```php
use Cognesy\Sandbox\Sandbox;
use Cognesy\Sandbox\Config\ExecutionPolicy;

// Create a sandbox with default policy
$sandbox = Sandbox::host(ExecutionPolicy::in('/path/to/workdir'));

// Execute a command
$result = $sandbox->execute(['ls', '-la']);

echo $result->stdout();
echo $result->exitCode();
```

## Execution Policy

```php
$policy = ExecutionPolicy::in('/path/to/workdir')
    ->withTimeout(60)
    ->withNetwork(false)
    ->withOutputCaps(5 * 1024 * 1024, 1 * 1024 * 1024)
    ->inheritEnvironment();

$sandbox = Sandbox::host($policy);
```

## Streaming Output

```php
$result = $sandbox->execute(
    ['long-running-command'],
    null, // stdin
    function (string $type, string $chunk) {
        // $type is 'out' or 'err'
        echo $chunk;
    }
);
```

## License

MIT License - see LICENSE.md
