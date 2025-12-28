# Agents/ClaudeCode - PHP Bridge to Claude Code CLI

**Purpose**: Controlled, sandboxed invocation of the `claude` CLI with a type-safe PHP API, real-time streaming support, and comprehensive DTO parsing.

## Table of Contents

- [Overview](#overview)
- [Key Concepts](#key-concepts)
- [Quick Start](#quick-start)
- [Architecture](#architecture)
- [Usage Examples](#usage-examples)
- [Configuration Options](#configuration-options)
- [Streaming Support](#streaming-support)
- [DTO Hierarchy](#dto-hierarchy)
- [Sandbox Drivers](#sandbox-drivers)
- [Gotchas & Important Notes](#gotchas--important-notes)
- [Troubleshooting](#troubleshooting)

## Overview

Agents/ClaudeCode provides a PHP wrapper around Anthropic's `claude` CLI tool, enabling:

- **Type-safe configuration** via `ClaudeRequest` DTO
- **Real-time streaming** with typed event DTOs
- **Sandboxed execution** across multiple isolation technologies
- **Headless mode** for automation and scripting
- **Session management** with resume/continue support
- **Subagent orchestration** for complex workflows

### Why This Bridge?

The `claude` CLI is powerful but outputs untyped JSON streams. This bridge provides:

1. **Type safety**: Replace `$data['message']['content'][0]['text']` with `$event->message->textContent()[0]->text`
2. **Real-time feedback**: Process agent progress as it happens, not after completion
3. **Sandboxing**: Run Claude with filesystem/network isolation
4. **Developer experience**: Fluent API with autocomplete and type hints

## Key Concepts

### Streaming vs Non-Streaming

**Non-streaming** (Traditional):
- Waits for CLI to complete
- Returns buffered output
- Parses JSONL after execution
- Good for: Simple queries, batch processing

**Streaming** (Real-time):
- Processes output as it arrives
- Shows agent progress incrementally
- Displays tool calls in real-time
- Good for: Agentic tasks, long-running operations, user feedback

### Event Types

Claude CLI outputs JSONL with different event types:

| Type | Description | Maps To |
|------|-------------|---------|
| `stream_event` | Incremental content chunks | `MessageEvent` |
| `assistant` | Complete assistant messages | `MessageEvent` |
| `user` | Complete user messages | `MessageEvent` |
| `result` | Final execution result | `ResultEvent` |
| `error` | Error information | `ErrorEvent` |
| `system` | System events (hooks, init) | `SystemEvent` |

### Sandbox Modes

| Driver | Isolation | Use Case |
|--------|-----------|----------|
| `Host` | None - direct filesystem access | Development, local testing |
| `Docker` | Container isolation | CI/CD, production |
| `Podman` | Rootless container | Security-focused environments |
| `Firejail` | Namespace isolation | Linux desktops |
| `Bubblewrap` | Lightweight sandboxing | Minimal overhead needed |

## Quick Start

### Basic Non-Streaming Example

```php
use Cognesy\Auxiliary\Agents\ClaudeCode\Application\Builder\ClaudeCommandBuilder;
use Cognesy\Auxiliary\Agents\ClaudeCode\Application\Dto\ClaudeRequest;
use Cognesy\Auxiliary\Agents\ClaudeCode\Application\Parser\ResponseParser;
use Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Enum\PermissionMode;
use Cognesy\Auxiliary\Agents\ClaudeCode\Infrastructure\Execution\SandboxCommandExecutor;

// 1. Create request
$request = new ClaudeRequest(
    prompt: 'What is the capital of France? Answer briefly.',
    outputFormat: OutputFormat::Json,
    permissionMode: PermissionMode::Plan,
    maxTurns: 1,
);

// 2. Build command
$builder = new ClaudeCommandBuilder();
$spec = $builder->buildHeadless($request);

// 3. Execute
$executor = SandboxCommandExecutor::default();
$execResult = $executor->execute($spec);

// 4. Parse response
$response = (new ResponseParser())->parse($execResult, OutputFormat::Json);
foreach ($response->decoded()->all() as $event) {
    $data = $event->data();
    if (isset($data['message']['content'][0]['text'])) {
        echo $data['message']['content'][0]['text'] . "\n";
        // Output: Paris
    }
}
```

### Streaming Example with Typed DTOs

```php
use Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Dto\StreamEvent\MessageEvent;
use Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Dto\StreamEvent\ResultEvent;
use Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Dto\StreamEvent\ErrorEvent;
use Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Dto\StreamEvent\StreamEvent;
use Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Enum\PermissionMode;

// 1. Create streaming request
$request = new ClaudeRequest(
    prompt: 'Find validation examples in ./examples and explain how they work',
    outputFormat: OutputFormat::StreamJson,
    permissionMode: PermissionMode::BypassPermissions,
    includePartialMessages: true,  // CRITICAL for real-time streaming
    maxTurns: 10,
    verbose: true,
);

// 2. Build and execute with streaming callback
$builder = new ClaudeCommandBuilder();
$spec = $builder->buildHeadless($request);
$executor = SandboxCommandExecutor::default();

$lineBuffer = '';
$streamingCallback = function(string $type, string $chunk) use (&$lineBuffer): void {
    // Buffer and process complete JSON lines
    $lineBuffer .= $chunk;
    $lines = explode("\n", $lineBuffer);
    $lineBuffer = array_pop($lines); // Keep incomplete line

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;

        $data = json_decode($trimmed, true);
        if (!is_array($data)) continue;

        // Parse into typed DTO
        $event = StreamEvent::fromArray($data);

        // Handle different event types
        if ($event instanceof MessageEvent) {
            // Display text content
            foreach ($event->message->textContent() as $textContent) {
                echo "📝 Agent: {$textContent->text}\n";
            }

            // Display tool calls
            foreach ($event->message->toolUses() as $toolUse) {
                echo "🔧 Tool: {$toolUse->name}\n";
            }
        }

        if ($event instanceof ResultEvent) {
            echo "✅ Result: {$event->result}\n";
        }

        if ($event instanceof ErrorEvent) {
            echo "❌ Error: {$event->error}\n";
        }
    }
};

$execResult = $executor->executeStreaming($spec, $streamingCallback);
```

## Architecture

### Components

```
Agents/ClaudeCode/
├── Application/
│   ├── Builder/
│   │   └── ClaudeCommandBuilder.php      # Builds claude CLI argv
│   ├── Dto/
│   │   ├── ClaudeRequest.php             # Request configuration
│   │   └── ClaudeResponse.php            # Parsed response wrapper
│   └── Parser/
│       └── ResponseParser.php            # Parses JSON/JSONL output
├── Domain/
│   ├── Dto/
│   │   └── StreamEvent/                  # Typed event DTOs
│   │       ├── StreamEvent.php           # Base class
│   │       ├── MessageEvent.php          # Agent messages
│   │       ├── ResultEvent.php           # Final results
│   │       ├── ErrorEvent.php            # Errors
│   │       ├── SystemEvent.php           # System events
│   │       └── MessageContent/           # Content types
│   │           ├── TextContent.php       # Text chunks
│   │           ├── ToolUseContent.php    # Tool invocations
│   │           └── ToolResultContent.php # Tool results
│   ├── Enum/
│   │   ├── OutputFormat.php              # Text/Json/StreamJson
│   │   ├── InputFormat.php               # Text/StreamJson
│   │   └── PermissionMode.php            # Security modes
│   └── Value/
│       ├── Argv.php                      # Type-safe argv builder
│       ├── CommandSpec.php               # Command + stdin
│       └── PathList.php                  # Additional directories
└── Infrastructure/
    └── Execution/
        ├── SandboxCommandExecutor.php    # Main executor
        ├── ExecutionPolicy.php           # Sandbox configuration
        └── SandboxDriver.php             # Driver enum
```

### Request Flow

```
ClaudeRequest
    ↓
ClaudeCommandBuilder::buildHeadless()
    ↓
CommandSpec (argv + stdin)
    ↓
SandboxCommandExecutor::executeStreaming()
    ↓
Sandbox Driver (Host/Docker/Podman/etc.)
    ↓
Process Runner (Symfony/Proc)
    ↓
Streaming Callback (per chunk)
    ↓
StreamEvent::fromArray()
    ↓
Typed DTO (MessageEvent/ResultEvent/etc.)
```

## Usage Examples

### Example 1: Simple Query

```php
$request = new ClaudeRequest(
    prompt: 'List PHP files in ./src',
    outputFormat: OutputFormat::Json,
    permissionMode: PermissionMode::Plan,
    maxTurns: 3,
);

$spec = (new ClaudeCommandBuilder())->buildHeadless($request);
$result = SandboxCommandExecutor::default()->execute($spec);
```

### Example 2: Agentic Search with Progress

See `examples/B05_LLMExtras/ClaudeCodeSearch/run.php` for a complete example showing:
- Real-time streaming
- Tool call tracking
- Progress display
- Error handling

### Example 3: Custom Sandbox Policy

```php
use Cognesy\Auxiliary\Agents\ClaudeCode\Infrastructure\Execution\ExecutionPolicy;
use Cognesy\Auxiliary\Agents\ClaudeCode\Infrastructure\Execution\SandboxDriver;

$policy = ExecutionPolicy::custom(
    timeoutSeconds: 120,
    networkEnabled: false,  // Disable network for security
    stdoutLimitBytes: 5 * 1024 * 1024,
    stderrLimitBytes: 1 * 1024 * 1024,
    baseDir: '/path/to/project',
    inheritEnv: true,
);

$executor = new SandboxCommandExecutor(
    policy: $policy,
    driver: SandboxDriver::Podman,  // Use podman for isolation
);
```

### Example 4: Session Continuation

```php
// First request
$request1 = new ClaudeRequest(
    prompt: 'Analyze the database schema',
    outputFormat: OutputFormat::Json,
);

$result1 = $executor->execute($builder->buildHeadless($request1));
$response1 = (new ResponseParser())->parse($result1, OutputFormat::Json);
$sessionId = $response1->sessionId(); // Extract session ID

// Continue session
$request2 = new ClaudeRequest(
    prompt: 'Now generate migration scripts',
    outputFormat: OutputFormat::Json,
    resumeSessionId: $sessionId,  // Resume previous session
);

$result2 = $executor->execute($builder->buildHeadless($request2));
```

### Example 5: Custom Subagent

```php
$agentsJson = json_encode([
    'code-reviewer' => [
        'description' => 'Expert code reviewer for security issues',
        'prompt' => 'You are a senior security engineer. Focus on vulnerabilities.',
        'tools' => ['Read', 'Grep', 'Glob'],
        'model' => 'sonnet',
    ],
]);

$request = new ClaudeRequest(
    prompt: 'Review this codebase for security issues',
    outputFormat: OutputFormat::StreamJson,
    agentsJson: $agentsJson,
    additionalDirs: PathList::of(['../shared-lib']),
);
```

## Configuration Options

### ClaudeRequest Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `prompt` | `string` | *required* | The prompt/query to send |
| `outputFormat` | `OutputFormat` | `Text` | Output format (Text/Json/StreamJson) |
| `permissionMode` | `PermissionMode` | `DefaultMode` | Permission handling mode |
| `includePartialMessages` | `bool` | `false` | **CRITICAL for streaming** - enables real-time chunks |
| `maxTurns` | `?int` | `null` | Limit agentic turns |
| `verbose` | `bool` | `false` | Enable verbose logging |
| `model` | `?string` | `null` | Model override (e.g., 'sonnet', 'opus') |
| `continueMostRecent` | `bool` | `false` | Continue last session |
| `resumeSessionId` | `?string` | `null` | Resume specific session |
| `systemPrompt` | `?string` | `null` | Replace default system prompt |
| `systemPromptFile` | `?string` | `null` | Load system prompt from file |
| `appendSystemPrompt` | `?string` | `null` | Append to default prompt |
| `agentsJson` | `?string` | `null` | Custom subagents JSON |
| `additionalDirs` | `PathList` | `empty` | Additional working directories |
| `inputFormat` | `?InputFormat` | `null` | Input format for stdin |
| `stdin` | `?string` | `null` | Input data for StreamJson |
| `permissionPromptTool` | `?string` | `null` | MCP tool for permission prompts |
| `dangerouslySkipPermissions` | `bool` | `false` | Skip all permission prompts |

### Permission Modes

```php
enum PermissionMode: string {
    case DefaultMode = 'default';           // Interactive prompts
    case Plan = 'plan';                     // Approve execution plan first
    case AcceptEdits = 'acceptEdits';       // Auto-approve edits
    case BypassPermissions = 'bypassPermissions';  // Skip all prompts
    case DontAsk = 'dontAsk';              // Assume yes
    case Delegate = 'delegate';             // Use permission tool
}
```

### Output Formats

```php
enum OutputFormat: string {
    case Text = 'text';           // Plain text output
    case Json = 'json';           // Single JSON result
    case StreamJson = 'stream-json';  // JSONL streaming
}
```

## Streaming Support

### How Streaming Works

1. **Claude CLI** outputs JSONL with `--output-format stream-json --include-partial-messages`
2. **Process Runner** reads output in chunks (8KB at a time)
3. **Callback** receives chunks and buffers incomplete JSON lines
4. **Parser** splits buffered lines and parses each JSON object
5. **Factory** creates typed DTO from raw array
6. **Consumer** handles typed events in real-time

### Critical Flags for Streaming

```php
$request = new ClaudeRequest(
    prompt: 'Long-running task',
    outputFormat: OutputFormat::StreamJson,      // REQUIRED
    includePartialMessages: true,                 // REQUIRED for real-time
    verbose: true,                                // Recommended for debugging
);
```

**Without `includePartialMessages: true`**, you'll only get complete messages after each turn finishes, defeating the purpose of streaming.

### Streaming Callback Pattern

```php
$lineBuffer = '';  // IMPORTANT: Must persist across callback invocations

$callback = function(string $type, string $chunk) use (&$lineBuffer): void {
    // 1. Buffer incoming chunks
    $lineBuffer .= $chunk;

    // 2. Split on newlines
    $lines = explode("\n", $lineBuffer);

    // 3. Keep last (incomplete) line in buffer
    $lineBuffer = array_pop($lines);

    // 4. Process complete lines
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;

        $data = json_decode($trimmed, true);
        if (!is_array($data)) continue;

        // 5. Parse into typed DTO
        $event = StreamEvent::fromArray($data);

        // 6. Handle event
        match (true) {
            $event instanceof MessageEvent => $this->handleMessage($event),
            $event instanceof ResultEvent => $this->handleResult($event),
            $event instanceof ErrorEvent => $this->handleError($event),
            default => null,
        };
    }
};

$executor->executeStreaming($spec, $callback);

// 7. Process any remaining buffered line after execution
if (!empty($lineBuffer)) {
    // ... parse final line
}
```

## DTO Hierarchy

### StreamEvent Family

```php
abstract class StreamEvent
    ├── MessageEvent        // Agent messages, tool calls, content
    ├── ResultEvent         // Final execution result
    ├── ErrorEvent          // Error information
    ├── SystemEvent         // System events (hooks, init)
    └── UnknownEvent        // Fallback for unknown types
```

### MessageContent Family

```php
abstract class MessageContent
    ├── TextContent         // Text output from agent
    ├── ToolUseContent      // Tool invocation (name, id, input)
    ├── ToolResultContent   // Tool result (id, content)
    └── UnknownContent      // Fallback for unknown content
```

### Message Helper

```php
class Message {
    public string $role;                    // 'assistant' | 'user'
    public array $content;                  // MessageContent[]

    // Convenience methods
    public function textContent(): TextContent[];
    public function toolUses(): ToolUseContent[];
    public function toolResults(): ToolResultContent[];
}
```

### Usage Example

```php
if ($event instanceof MessageEvent) {
    // Type-safe access
    foreach ($event->message->textContent() as $text) {
        echo $text->text;  // No array indexing needed!
    }

    foreach ($event->message->toolUses() as $tool) {
        echo "Calling: {$tool->name}\n";
        echo "Input: " . json_encode($tool->input) . "\n";
    }
}
```

## Sandbox Drivers

### Host Driver (Development)

```php
$executor = new SandboxCommandExecutor(
    policy: ExecutionPolicy::default(),
    driver: SandboxDriver::Host,  // Direct filesystem access
);
```

**Use when:**
- Local development
- Full filesystem access needed
- Testing with real project files

**Risks:**
- No isolation
- Can access entire filesystem
- Network unrestricted

### Docker Driver (Production)

```php
$executor = new SandboxCommandExecutor(
    policy: ExecutionPolicy::custom(
        timeoutSeconds: 300,
        networkEnabled: false,
        baseDir: '/app',
    ),
    driver: SandboxDriver::Docker,
);
```

**Use when:**
- CI/CD pipelines
- Production environments
- Strong isolation needed

**Requires:**
- Docker installed
- Proper container configuration

### Podman Driver (Rootless)

```php
$executor = new SandboxCommandExecutor(
    policy: ExecutionPolicy::custom(
        baseDir: '/workspace',
        inheritEnv: true,
    ),
    driver: SandboxDriver::Podman,
);
```

**Use when:**
- Rootless execution required
- Security-focused environments
- Docker alternative needed

### Firejail/Bubblewrap (Linux)

```php
// Firejail - namespace isolation
$executor = new SandboxCommandExecutor(
    driver: SandboxDriver::Firejail,
);

// Bubblewrap - lightweight sandboxing
$executor = new SandboxCommandExecutor(
    driver: SandboxDriver::Bubblewrap,
);
```

**Use when:**
- Linux desktop environments
- Minimal overhead needed
- Container-less sandboxing preferred

## Gotchas & Important Notes

### 1. Event Type Mapping (CRITICAL)

The `StreamEvent::fromArray()` factory maps THREE types to `MessageEvent`:

```php
'stream_event', 'assistant', 'user' => MessageEvent::fromArray($data)
```

**Why?** Claude CLI outputs:
- `stream_event` - Incremental chunks during streaming
- `assistant` - Complete assistant messages
- `user` - Complete user messages (tool results, etc.)

All three contain message data and should be handled the same way.

### 2. includePartialMessages Flag

**Without this flag:**
```php
includePartialMessages: false  // Only get complete messages per turn
// Output arrives in large batches AFTER each turn completes
```

**With this flag:**
```php
includePartialMessages: true   // Get incremental chunks as they're generated
// Output arrives in real-time as agent thinks/acts
```

For true streaming, **ALWAYS set `includePartialMessages: true`**.

### 3. Line Buffering in Streaming

The callback may receive:
- Multiple complete JSON lines in one chunk
- Partial JSON lines split across chunks
- Empty chunks between data

**Always buffer:**
```php
$lineBuffer = '';  // Persistent across invocations

$callback = function($type, $chunk) use (&$lineBuffer) {
    $lineBuffer .= $chunk;           // Accumulate
    $lines = explode("\n", $lineBuffer);
    $lineBuffer = array_pop($lines);  // Keep incomplete line

    foreach ($lines as $line) {
        // Process complete lines
    }
};
```

### 4. Sandbox Base Directory

When using Host driver:

```php
// WRONG - sandbox creates temp subdirectory
$policy = ExecutionPolicy::custom(baseDir: sys_get_temp_dir());
// Claude runs in: /tmp/sandbox-abc123/ (can't access your files!)

// CORRECT - use project root
$policy = ExecutionPolicy::custom(baseDir: '/path/to/your/project');
// Claude runs in project root with full access
```

### 5. stdbuf Prefix

The `ClaudeCommandBuilder` automatically prefixes commands with `stdbuf -o0`:

```bash
stdbuf -o0 claude -p "..." --output-format stream-json
```

This disables output buffering for better real-time streaming. **Do not remove this** unless you know what you're doing.

### 6. Permission Modes

```php
// Development - interactive approvals
PermissionMode::Plan           // Shows plan, asks for approval

// Automation - no interaction
PermissionMode::BypassPermissions   // Skip all prompts
PermissionMode::DontAsk            // Assume yes to everything

// Production - use MCP tool
PermissionMode::Delegate           // Delegate to MCP tool
```

Never use `BypassPermissions` in production without understanding the security implications.

### 7. Message vs Event Structure

Different event types have different structures:

```json
// stream_event - nested event object
{"type":"stream_event","event":{"type":"content_block_delta","delta":{"text":"..."}}}

// assistant - direct message object
{"type":"assistant","message":{"role":"assistant","content":[{"type":"text","text":"..."}]}}
```

The DTOs handle both automatically, but raw parsing needs to account for this.

### 8. Tool Call Tracking

Tool calls appear in `toolUses()`:

```php
if ($event instanceof MessageEvent) {
    foreach ($event->message->toolUses() as $tool) {
        // Track what's happening
        $toolCallCount[$tool->name] = ($toolCallCount[$tool->name] ?? 0) + 1;
    }
}
```

Tool results appear separately in `toolResults()` or as `user` message events.

### 9. Error Handling

```php
try {
    $result = $executor->execute($spec);
} catch (\Throwable $e) {
    // Execution failed (timeout, sandbox error, etc.)
}

// Check exit code
if ($result->exitCode() !== 0) {
    // Claude CLI returned error
    echo "STDERR: " . $result->stderr();
}

// Check for error events in stream
if ($event instanceof ErrorEvent) {
    // Agent encountered error during execution
    echo "Agent error: " . $event->error;
}
```

### 10. Session Management

Sessions are automatically created:

```php
// First request creates session
$result1 = $executor->execute($spec1);
$response1 = (new ResponseParser())->parse($result1, OutputFormat::Json);
$sessionId = $response1->sessionId();

// Subsequent requests use session ID
$request2 = new ClaudeRequest(
    prompt: 'Continue from previous',
    resumeSessionId: $sessionId,  // Maintains context
);
```

Or use `continueMostRecent: true` to auto-resume the last session.

## Troubleshooting

### Streaming Not Working

**Symptom:** Only getting final result, no intermediate events

**Check:**
1. `outputFormat: OutputFormat::StreamJson` ✓
2. `includePartialMessages: true` ✓
3. Using `executeStreaming()` not `execute()` ✓
4. Event type mapping includes 'assistant'/'user' ✓

### Empty Output

**Symptom:** `$result->stdout()` is empty

**Possible causes:**
1. Claude CLI failed (check `$result->exitCode()` and `$result->stderr()`)
2. Timeout too short (increase `timeoutSeconds` in policy)
3. Permission prompts blocking (use appropriate permission mode)
4. Sandbox isolation preventing access (use Host driver for debugging)

### Tool Calls Not Appearing

**Symptom:** Agent runs but no tool calls visible

**Check:**
1. `verbose: true` to see all events
2. Event type is `MessageEvent` not `UnknownEvent`
3. Calling `$event->message->toolUses()` not just `textContent()`
4. Permission mode allows tool execution

### Sandbox Permission Errors

**Symptom:** "Permission denied" or file not found errors

**Solutions:**
```php
// Option 1: Use Host driver for debugging
driver: SandboxDriver::Host

// Option 2: Set correct base directory
baseDir: '/actual/project/path'  // Not /tmp!

// Option 3: Add additional directories
additionalDirs: PathList::of(['/path/to/shared/libs'])
```

### JSON Parse Errors

**Symptom:** `json_decode()` returns null

**Cause:** Incomplete JSON line in buffer

**Solution:** Always buffer and process complete lines:
```php
$lineBuffer .= $chunk;
$lines = explode("\n", $lineBuffer);
$lineBuffer = array_pop($lines);  // Critical!
```

### Performance Issues

**Symptom:** Slow execution or high memory usage

**Optimizations:**
```php
// 1. Limit output buffer sizes
ExecutionPolicy::custom(
    stdoutLimitBytes: 5 * 1024 * 1024,  // 5MB max
    stderrLimitBytes: 1 * 1024 * 1024,  // 1MB max
)

// 2. Limit turns
maxTurns: 10  // Prevent runaway loops

// 3. Shorter timeout
timeoutSeconds: 60  // Force completion
```

## Real-World Examples

See the `examples/B05_LLMExtras/` directory:

1. **ClaudeCode/run.php** - Simple synchronous query
2. **ClaudeCodeSearch/run.php** - Agentic search with streaming
3. **streaming-demo.php** - Minimal streaming example

## Additional Resources

- Claude Code CLI docs: `docs-internal/claude-code/`
- Streaming format: `build/headless-mode.md`
- Subagents: `build/subagents.md`
- Permission modes: `administration/iam.md`

## Contributing

When extending this bridge:

1. **Add new DTOs** to `Domain/Dto/StreamEvent/` for new event types
2. **Update factory** in `StreamEvent::fromArray()` to handle new types
3. **Test streaming** with real Claude CLI, not mocked data
4. **Document gotchas** - streaming is subtle, help future developers
5. **Use Host driver** for development/testing, other drivers for CI/CD

---

**Remember:** This bridge is about **type safety** and **developer experience**. When in doubt, add DTOs and helper methods rather than exposing raw arrays.
