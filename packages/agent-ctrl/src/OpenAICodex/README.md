# OpenAI Codex CLI Bridge

PHP integration for the OpenAI Codex CLI (`codex exec` command).

## Overview

This module provides a structured PHP API for invoking the Codex CLI in headless/non-interactive mode. It supports:

- JSONL streaming output parsing
- Sandbox isolation (via instructor-php Sandbox library)
- Full type safety with readonly DTOs
- Session resume capability

## Installation

Requires the Codex CLI to be installed:

```bash
npm install -g @openai/codex
```

## Usage

```php
use Cognesy\AgentCtrl\OpenAICodex\Application\Builder\CodexCommandBuilder;
use Cognesy\AgentCtrl\OpenAICodex\Application\Dto\CodexRequest;
use Cognesy\AgentCtrl\OpenAICodex\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;

// 1. Create request
$request = new CodexRequest(
    prompt: 'Explain the directory structure of this project.',
    outputFormat: OutputFormat::Json,
    sandboxMode: SandboxMode::ReadOnly,
);

// 2. Build command
$builder = new CodexCommandBuilder();
$spec = $builder->buildExec($request);

// 3. Execute
$executor = SandboxCommandExecutor::forCodex();
$result = $executor->execute($spec);

// 4. Parse response
$parser = new ResponseParser();
$response = $parser->parse($result, OutputFormat::Json);

// 5. Use response
echo "Thread ID: " . $response->threadId() . "\n";
echo "Exit code: " . $response->exitCode() . "\n";

if ($response->usage()) {
    echo "Input tokens: " . $response->usage()->inputTokens . "\n";
    echo "Output tokens: " . $response->usage()->outputTokens . "\n";
}
```

## Streaming Output

For real-time output processing:

```php
$executor->executeStreaming($spec, function (string $type, string $chunk) {
    if ($type === 'out') {
        // Parse JSONL line
        $event = StreamEvent::fromArray(json_decode($chunk, true));
        // Handle event...
    }
});
```

## Request Options

| Option | Type | Description |
|--------|------|-------------|
| `prompt` | string | The task/query to send to Codex |
| `outputFormat` | OutputFormat | Text or Json (JSONL streaming) |
| `sandboxMode` | SandboxMode | read-only, workspace-write, danger-full-access |
| `model` | string | Model override (e.g., 'gpt-5-codex') |
| `images` | array | Image file paths to attach |
| `workingDirectory` | string | Working directory for the agent |
| `additionalDirs` | PathList | Additional writable directories |
| `fullAuto` | bool | Shortcut for workspace-write + on-failure approvals |
| `dangerouslyBypass` | bool | Skip all approvals and sandbox (DANGEROUS) |
| `skipGitRepoCheck` | bool | Allow running outside git repository |
| `resumeSessionId` | string | Resume specific session by ID |
| `resumeLast` | bool | Resume most recent session |
| `configOverrides` | array | Inline config overrides (key=value pairs) |

> **Note**: Some flags like `--ask-for-approval` and `--search` are only available in
> interactive mode (`codex`) and not in exec mode (`codex exec`).

## Event Types

When using JSON output format, these events are streamed:

- `thread.started` - Contains thread_id
- `turn.started` - Marks turn start
- `turn.completed` - Contains usage statistics
- `turn.failed` - Contains error details
- `item.started` - Item processing started
- `item.completed` - Item processing completed
- `error` - Error occurred

## Item Types

Items represent individual actions/outputs:

- `agent_message` - Text message from agent
- `command_execution` - Shell command execution
- `file_change` - File modification
- `mcp_tool_call` - MCP tool invocation
- `web_search` - Web search result
- `plan_update` - Plan modification
- `reasoning` - Internal reasoning step

## See Also

- [Codex CLI Documentation](https://platform.openai.com/docs/codex)
- [Agents/Common](../Common/) - Shared components
- [Agents/ClaudeCode](../ClaudeCode/) - Claude Code CLI bridge
