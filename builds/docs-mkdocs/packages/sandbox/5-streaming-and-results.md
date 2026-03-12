---
title: 'Streaming and Results'
description: 'Consume command output in real time with streaming callbacks, and inspect execution results through the ExecResult API.'
---

## Introduction

The Sandbox package supports two complementary ways to work with command output. You can consume output **incrementally** through a streaming callback as the command runs, and you can inspect the **complete result** after execution finishes through the `ExecResult` value object. Both mechanisms work with every driver.

## Streaming Output

### The Streaming Callback

The `execute()` method accepts an optional third argument: a callable that receives output chunks in real time as the command produces them. This is useful for progress reporting, logging, or forwarding output to a user interface.

```php
$result = $sandbox->execute(
    ['php', 'long-running-script.php'],
    null,
    function (string $type, string $chunk): void {
        if ($type === 'out') {
            echo "[stdout] " . $chunk;
        } else {
            echo "[stderr] " . $chunk;
        }
    }
);
// @doctest id="3d8a"
```

The callback receives two arguments:

| Argument | Type | Description |
|---|---|---|
| `$type` | `string` | `'out'` for stdout, `'err'` for stderr |
| `$chunk` | `string` | Raw bytes from the output stream |

Chunks are delivered as they arrive from the process. They are **not** line-buffered -- a chunk may contain a partial line, multiple lines, or even binary data depending on how the underlying process writes to its output streams.

### Streaming and Output Caps

The streaming callback receives **all** output, even when the retained buffer in `ExecResult` has been truncated due to output caps. This means you can use the callback to write output to a file or database without worrying about the cap:

```php
$logFile = fopen('/tmp/execution.log', 'w');

$result = $sandbox->execute(
    ['php', 'noisy-script.php'],
    null,
    function (string $type, string $chunk) use ($logFile): void {
        fwrite($logFile, "[{$type}] {$chunk}");
    }
);

fclose($logFile);

// $result->stdout() may be truncated, but the log file has everything
// @doctest id="d39f"
```

### Streaming with Standard Input

You can combine stdin and the streaming callback in the same call:

```php
$result = $sandbox->execute(
    ['php', '-r', 'echo strtoupper(fgets(STDIN));'],
    'hello world',
    function (string $type, string $chunk): void {
        echo $chunk; // "HELLO WORLD"
    }
);
// @doctest id="c16c"
```

### Idle Timeout Interaction

The streaming callback does not affect idle timeout tracking. The idle timeout is reset whenever the process produces any output on either stdout or stderr, regardless of whether a callback is provided. However, the callback gives you visibility into when output arrives, which is valuable for diagnosing timeout issues:

```php
$result = $sandbox->execute(
    $argv,
    null,
    function (string $type, string $chunk): void {
        $timestamp = date('H:i:s');
        echo "[{$timestamp}] {$type}: " . strlen($chunk) . " bytes\n";
    }
);
// @doctest id="49bb"
```

## The ExecResult API

Every call to `execute()` returns an `ExecResult` instance -- a readonly value object containing the complete outcome of the execution.

### Output Access

```php
$result->stdout();         // string -- captured standard output
$result->stderr();         // string -- captured standard error
$result->combinedOutput(); // string -- stdout + stderr joined with newline
// @doctest id="3a6b"
```

The `combinedOutput()` method appends stderr to stdout, separated by a newline if both are non-empty. This is convenient when you do not need to distinguish between the two streams.

### Exit Status

```php
$result->exitCode(); // int -- the process exit code
$result->success();  // bool -- true when exitCode is 0 AND no timeout occurred
// @doctest id="555c"
```

The `success()` method checks both the exit code and the timeout flag. A command that exits with code 0 but was forcefully terminated due to a timeout is not considered successful.

Common exit codes:

| Code | Meaning |
|---|---|
| `0` | Success |
| `1` | General error |
| `124` | Timeout (GNU convention, used by the Sandbox package) |
| `126` | Command found but not executable |
| `127` | Command not found |
| `128+N` | Killed by signal N (e.g., 137 = SIGKILL) |

### Timing

```php
$result->duration(); // float -- wall-clock seconds (e.g., 1.234)
// @doctest id="a39b"
```

The duration measures the wall-clock time from process start to completion (or termination). It is always available, even for timed-out executions.

### Timeout Detection

```php
$result->timedOut(); // bool -- true if wall-clock or idle timeout was triggered
// @doctest id="f395"
```

When a timeout occurs, the exit code is set to `124` and `timedOut()` returns `true`. The output captured up to the point of termination is still available through `stdout()` and `stderr()`.

### Truncation Detection

```php
$result->truncatedStdout(); // bool -- true if stdout exceeded the output cap
$result->truncatedStderr(); // bool -- true if stderr exceeded the output cap
// @doctest id="597a"
```

When truncation occurs, only the most recent bytes (up to the cap size) are retained. Earlier output is discarded. This tail-preserving strategy ensures you always have the most recent output, which typically contains error messages and final status information.

### Serialization

```php
$result->toArray();
// [
//     'stdout'           => '...',
//     'stderr'           => '...',
//     'exit_code'        => 0,
//     'duration'         => 1.234,
//     'timed_out'        => false,
//     'truncated_stdout' => false,
//     'truncated_stderr' => false,
//     'success'          => true,
// ]
// @doctest id="6d31"
```

The `toArray()` method returns a flat associative array suitable for JSON serialization, logging, or passing to the `FakeSandbox` for test fixtures.

## Common Patterns

### Guard Against Failure

The most common pattern is to check `success()` before using the output:

```php
$result = $sandbox->execute(['php', 'migrate.php']);

if (!$result->success()) {
    throw new RuntimeException(
        "Migration failed (exit {$result->exitCode()}): {$result->stderr()}"
    );
}

echo $result->stdout();
// @doctest id="0b44"
```

### Capture Output with Timeout Awareness

When running potentially long commands, check for both failure and timeout:

```php
$result = $sandbox->execute(['php', 'import.php']);

if ($result->timedOut()) {
    logger()->warning('Import timed out after ' . $result->duration() . 's');
    logger()->warning('Partial output: ' . $result->stdout());
} elseif (!$result->success()) {
    logger()->error('Import failed: ' . $result->stderr());
} else {
    logger()->info('Import complete: ' . $result->stdout());
}
// @doctest id="8369"
```

### Real-Time Progress with Final Summary

Combine the streaming callback for live updates with the result for a final summary:

```php
$lines = 0;

$result = $sandbox->execute(
    ['php', 'process-data.php'],
    null,
    function (string $type, string $chunk) use (&$lines): void {
        if ($type === 'out') {
            $lines += substr_count($chunk, "\n");
            echo "\rProcessed {$lines} lines...";
        }
    }
);

echo "\n";
echo "Finished in {$result->duration()}s with exit code {$result->exitCode()}\n";
// @doctest id="b584"
```

### Parsing Structured Output

When the command produces JSON or other structured output:

```php
$result = $sandbox->execute(['php', '-r', 'echo json_encode(["count" => 42]);']);

if ($result->success()) {
    $data = json_decode($result->stdout(), true);
    echo "Count: " . $data['count'];
}
// @doctest id="fdc8"
```

### Handling Truncated Output

When working with commands that may produce large output, check for truncation:

```php
$result = $sandbox->execute(['php', 'generate-report.php']);

if ($result->truncatedStdout()) {
    logger()->warning(
        'Report output was truncated. Consider increasing output caps or '
        . 'using a streaming callback to capture the full output.'
    );
}
// @doctest id="c6f5"
```
