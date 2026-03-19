---
title: Streaming
description: 'Receive real-time text output, tool activity, completion events, and streamed errors from any CLI-based code agent while it is still running.'
---

## Introduction

When an agent works on a complex task, it may run for minutes -- reading files, executing commands, reasoning through problems, and producing output incrementally. Streaming lets your application display progress, log tool activity, and react to errors in real time rather than waiting for the agent to finish.

Agent-Ctrl provides streaming through four callback methods on the builder. These callbacks are invoked as the agent's JSON Lines output is parsed, and the same callback API works identically across Claude Code, Codex, OpenCode, Pi, and Gemini.

## Using `executeStreaming()`

To enable streaming, register one or more callbacks and call `executeStreaming()` instead of `execute()`:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;

$response = AgentCtrl::claudeCode()
    ->onText(fn(string $text) => print($text))
    ->onToolUse(fn(string $tool, array $input, ?string $output) => print("\n> [{$tool}]\n"))
    ->onComplete(fn(AgentResponse $response) => print("\nDone\n"))
    ->onError(fn(string $message, ?string $code) => print("\nError: {$message}\n"))
    ->executeStreaming('Explain the architecture of this project.');
```

`executeStreaming()` returns the final `AgentResponse` just like `execute()` does. The callbacks provide real-time visibility into the work, but the complete result is always available at the end for inspection, storage, or further processing.

## Callback Reference

### `onText(callable $handler): static`

Called whenever the agent produces text content. The handler receives a single `string` argument containing the text fragment. Text is delivered incrementally -- each call may contain a word, a sentence, or a paragraph depending on how the agent's CLI emits output.

```php
$agent->onText(function (string $text): void {
    // Append to a buffer, write to a stream, or display directly
    echo $text;
});
```

Empty text fragments are filtered out before reaching your callback.

### `onToolUse(callable $handler): static`

Called whenever the agent invokes a tool or receives a tool result. The handler receives three arguments:

- `string $tool` -- The tool name (e.g., `'bash'`, `'file_change'`, `'web_search'`, `'tool_result'`)
- `array $input` -- The tool's input parameters as an associative array
- `?string $output` -- The tool's output, or `null` if the tool has not completed yet

```php
$agent->onToolUse(function (string $tool, array $input, ?string $output): void {
    echo "[Tool: {$tool}]";
    if ($output !== null) {
        echo " => " . substr($output, 0, 100);
    }
    echo "\n";
});
```

The tool names and input structures are normalized across all agents. For example, Codex `CommandExecution` items become `'bash'` tool calls with `['command' => '...']` input, and Codex `FileChange` items become `'file_change'` tool calls with `['path' => '...', 'action' => '...']` input.

### `onComplete(callable $handler): static`

Called exactly once when the agent finishes and the final `AgentResponse` is assembled. The handler receives the complete response object:

```php
$agent->onComplete(function (AgentResponse $response): void {
    echo "\nCompleted with exit code: {$response->exitCode}";
    echo "\nTool calls made: " . count($response->toolCalls);
});
```

The completion callback is deduplicated internally -- even if the bridge processes both streamed and parsed data, your handler is invoked only once.

### `onError(callable $handler): static`

Called when the agent emits an error event during streaming. These are operational errors reported by the agent itself (e.g., a tool failure, a rate limit, or a malformed request), not PHP exceptions. The handler receives two arguments:

- `string $message` -- The error description
- `?string $code` -- An optional error code (agent-specific)

```php
$agent->onError(function (string $message, ?string $code): void {
    error_log("Agent stream error [{$code}]: {$message}");
});
```

Stream errors do not terminate the execution. The agent may recover and continue working after emitting an error event.

## Streaming Without Callbacks

You can call `executeStreaming()` without registering any callbacks. In this case, the builder still processes the streaming output internally (emitting events for the `wiretap()` system), but no user-facing callbacks are invoked. The final `AgentResponse` is returned normally.

## When to Use `execute()` Instead

Use `execute()` when you only care about the final result and do not need incremental updates. Internally, `execute()` delegates to the same streaming infrastructure -- it simply does not register a stream handler. The performance characteristics are identical; the only difference is whether callbacks fire during execution.

```php
// No streaming -- just get the result
$response = AgentCtrl::codex()->execute('Create a short summary.');

// With streaming -- same result, but with real-time visibility
$response = AgentCtrl::codex()
    ->onText(fn(string $text) => print($text))
    ->executeStreaming('Create a short summary.');
```

## How Streaming Works Internally

Understanding the internal streaming pipeline can help with debugging and advanced usage:

1. **Process execution.** The bridge launches the CLI binary via the `SandboxCommandExecutor`, which runs the process and captures stdout in real time.

2. **JSON Lines buffering.** Raw process output arrives in arbitrary-sized chunks. A `JsonLinesBuffer` accumulates bytes until complete JSON Lines (newline-delimited) are available.

3. **Event parsing.** Each complete JSON line is decoded and passed through the agent-specific `StreamEvent::fromArray()` factory, which produces typed event objects (text events, tool use events, error events, etc.).

4. **Callback dispatch.** Typed events are normalized into the common callback signatures (`onText`, `onToolUse`, `onError`) and dispatched to your handlers.

5. **Event system.** In parallel, the builder dispatches internal events (`AgentTextReceived`, `AgentToolUsed`, `AgentErrorOccurred`, `StreamChunkProcessed`, etc.) that can be observed through the `wiretap()` system.

6. **Final parse.** After the process completes, the full stdout is re-parsed to extract the authoritative final data (text, tool calls, session ID, usage). This ensures no data is lost due to streaming chunk boundaries.

## Combining Streaming with the Console Logger

For development and debugging, you can combine user-facing streaming callbacks with the built-in `AgentCtrlConsoleLogger` to see both the agent's output and detailed execution telemetry:

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;

$logger = new AgentCtrlConsoleLogger(
    showStreaming: true,
    showPipeline: true,
);

$response = AgentCtrl::claudeCode()
    ->wiretap($logger->wiretap())
    ->onText(fn(string $text) => print($text))
    ->executeStreaming('Analyze the test suite.');
```

The console logger displays color-coded events for execution lifecycle, tool usage, stream processing, and response parsing, while your `onText` callback displays the agent's actual output.

## Complete Streaming Example

```php
use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Dto\AgentResponse;

$toolLog = [];

$response = AgentCtrl::openCode()
    ->withModel('anthropic/claude-sonnet-4-5')
    ->withTimeout(300)
    ->inDirectory('/projects/my-app')
    ->onText(function (string $text): void {
        // Stream text to the user in real time
        echo $text;
    })
    ->onToolUse(function (string $tool, array $input, ?string $output) use (&$toolLog): void {
        // Log tool activity for post-execution analysis
        $toolLog[] = ['tool' => $tool, 'input' => $input, 'output' => $output];
    })
    ->onComplete(function (AgentResponse $response): void {
        echo "\n--- Execution complete ---\n";
        echo "Exit code: {$response->exitCode}\n";
        if ($response->cost() !== null) {
            echo sprintf("Cost: $%.4f\n", $response->cost());
        }
    })
    ->onError(function (string $message, ?string $code): void {
        error_log("Stream error [{$code}]: {$message}");
    })
    ->executeStreaming('Review the authentication module and suggest improvements.');

// The response is also available for further processing
if (!$response->isSuccess()) {
    echo "Warning: agent exited with code {$response->exitCode}\n";
}

echo "Total tool calls: " . count($toolLog) . "\n";
```
