# OpenCode CLI Bridge

PHP integration for the OpenCode CLI (`opencode run` command).

## Overview

This module provides a structured PHP API for invoking the OpenCode CLI in headless/non-interactive mode. It supports:

- nd-JSON streaming output parsing
- Full type safety with readonly DTOs
- Session resume capability
- Multi-provider model support (75+ providers via AI SDK)
- File attachments
- Named agent selection

## Installation

Requires the OpenCode CLI to be installed:

```bash
# Via npm
npm install -g opencode

# Or via curl
curl -fsSL https://get.opencode.dev | bash
```

## Usage

```php
use Cognesy\AgentCtrl\OpenCode\Application\Builder\OpenCodeCommandBuilder;
use Cognesy\AgentCtrl\OpenCode\Application\Dto\OpenCodeRequest;
use Cognesy\AgentCtrl\OpenCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\OpenCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;

// 1. Create request
$request = new OpenCodeRequest(
    prompt: 'Explain the directory structure of this project.',
    outputFormat: OutputFormat::Json,
);

// 2. Build command
$builder = new OpenCodeCommandBuilder();
$spec = $builder->buildRun($request);

// 3. Execute
$executor = SandboxCommandExecutor::forOpenCode();
$result = $executor->execute($spec);

// 4. Parse response
$parser = new ResponseParser();
$response = $parser->parse($result, OutputFormat::Json);

// 5. Use response
echo "Session ID: " . $response->sessionId() . "\n";
echo "Exit code: " . $response->exitCode() . "\n";
echo "Answer: " . $response->messageText() . "\n";

if ($response->usage()) {
    echo "Input tokens: " . $response->usage()->input . "\n";
    echo "Output tokens: " . $response->usage()->output . "\n";
}

if ($response->cost()) {
    echo "Cost: $" . $response->cost() . "\n";
}

// Optional typed IDs for domain code:
// $response->sessionIdValue(); // OpenCodeSessionId|null
// $response->messageIdValue(); // OpenCodeMessageId|null
```

## Streaming Output

For real-time output processing:

```php
$executor->executeStreaming($spec, function (string $type, string $chunk) {
    if ($type === 'out') {
        // Parse nd-JSON line
        $event = StreamEvent::fromArray(json_decode($chunk, true));

        match (true) {
            $event instanceof TextEvent => print($event->text),
            $event instanceof ToolUseEvent => print("[Tool: {$event->tool}]\n"),
            $event instanceof StepFinishEvent => print("[Done]\n"),
            default => null,
        };
    }
});
```

## Request Options

| Option | Type | Description |
|--------|------|-------------|
| `prompt` | string | The message/query to send to OpenCode |
| `outputFormat` | OutputFormat | Default or Json (nd-JSON streaming) |
| `model` | string\|ModelId | Model in provider/model format (e.g., `anthropic/claude-sonnet-4-5`) |
| `agent` | string | Named agent to use |
| `files` | array | File paths to attach (repeatable) |
| `continueSession` | bool | Continue the last session |
| `sessionId` | string | Resume specific session by ID |
| `share` | bool | Share the session after completion |
| `title` | string | Session title |
| `attachUrl` | string | Attach to running server (e.g., `http://localhost:4096`) |
| `port` | int | Local server port |
| `command` | string | Command to run (prompt becomes args) |

## Event Types

When using JSON output format, these events are streamed:

- `step_start` - Step/turn begins
- `text` - Text content from assistant
- `tool_use` - Tool invocation with result
- `step_finish` - Step/turn ends with usage stats
- `error` - Error occurred

### Event Details

#### StepStartEvent
```php
$event->sessionId;   // Session identifier
$event->messageId;   // Message identifier
$event->sessionIdValue; // OpenCodeSessionId|null
$event->messageIdValue; // OpenCodeMessageId|null
$event->snapshot;    // Git snapshot hash
```

#### TextEvent
```php
$event->text;        // The actual message text
$event->startTime;   // Timestamp when text generation started
$event->endTime;     // Timestamp when text generation ended
```

#### ToolUseEvent
```php
$event->tool;        // Tool name (read, bash, glob, etc.)
$event->callId;      // Unique call identifier
$event->callIdValue; // OpenCodeCallId|null
$event->status;      // "completed" or "error"
$event->input;       // Tool input arguments (array)
$event->output;      // Tool output/result
```

#### StepFinishEvent
```php
$event->reason;      // "stop" (final) or "tool-calls" (more steps)
$event->cost;        // Cost in USD
$event->tokens;      // TokenUsage object
$event->isFinal();   // Check if this is the last step
```

## Model Format

OpenCode uses `provider/model` format for model selection:

```php
use Cognesy\AgentCtrl\OpenCode\Domain\Value\ModelId;

// Using string directly
$request = new OpenCodeRequest(
    prompt: 'Hello',
    model: 'anthropic/claude-sonnet-4-5',
);

// Using ModelId value object
$model = ModelId::fromString('openai/gpt-4o');
$request = new OpenCodeRequest(
    prompt: 'Hello',
    model: $model,
);
```

## See Also

- [OpenCode Documentation](https://opencode.dev/docs)
- [Agents/Common](../Common/) - Shared components
- [Agents/ClaudeCode](../ClaudeCode/) - Claude Code CLI bridge
- [Agents/OpenAICodex](../OpenAICodex/) - OpenAI Codex CLI bridge
