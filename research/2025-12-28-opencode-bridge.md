# OpenCode CLI Bridge - Implementation Plan

**Date**: 2025-12-28
**Status**: Planning
**Reference**: `packages/auxiliary/src/Agents/ClaudeCode` (Claude pattern)
**Reference**: `packages/auxiliary/src/Agents/OpenAICodex` (Codex pattern)
**Target**: `packages/auxiliary/src/Agents/OpenCode`

---

## 1. Executive Summary

Implement a PHP bridge to the OpenCode CLI (`opencode run` command) following the established patterns from ClaudeCode and OpenAICodex. OpenCode is a Go-based coding agent with support for 75+ LLM providers via the Vercel AI SDK.

### Key Differences from Existing Bridges

| Aspect | Claude Code | OpenAI Codex | OpenCode |
|--------|-------------|--------------|----------|
| Headless command | `claude -p` | `codex exec` | `opencode run` |
| JSON streaming | `--output-format stream-json` | `--json` | `--format json` |
| Session resume | `--continue` / `--resume <id>` | `resume --last` / `resume <id>` | `--continue` / `--session <id>` |
| Model format | `claude-sonnet-4-5` | `gpt-5-codex` | `provider/model` (e.g., `anthropic/claude-sonnet-4-5`) |
| File attachments | Not supported | `--image` | `--file` / `-f` |
| Sandbox modes | `--permission-mode` (7 modes) | `--sandbox` (3 modes) | Config-based permissions |
| Agent selection | `--agents` (JSON) | N/A | `--agent` |
| Server attachment | N/A | N/A | `--attach <url>` |
| Session sharing | N/A | N/A | `--share` |

### OpenCode Unique Features

1. **Provider/Model format**: Uses `provider/model` syntax (e.g., `anthropic/claude-sonnet-4-5`)
2. **75+ providers**: Via AI SDK integration (Models.dev catalog)
3. **Agent system**: Named agents with custom system prompts and tool configs
4. **Server mode**: Can attach to running `opencode serve` instance
5. **Session sharing**: Built-in session sharing via `--share`
6. **Config-based permissions**: No command-line sandbox flags - uses config file

---

## 2. OpenCode CLI Reference

### Command Structure

```bash
# Basic headless execution
opencode run "prompt"

# With JSON streaming output
opencode run --format json "prompt"

# Specify model (provider/model format)
opencode run --model anthropic/claude-sonnet-4-5 "prompt"

# Attach files
opencode run --file screenshot.png --file data.csv "explain these"

# Continue last session
opencode run --continue "follow-up prompt"

# Continue specific session
opencode run --session abc123 "follow-up prompt"

# Use specific agent
opencode run --agent code-reviewer "review this file"

# Attach to running server (faster - no MCP cold boot)
opencode run --attach http://localhost:4096 "prompt"

# With session sharing
opencode run --share "prompt"

# With custom title
opencode run --title "Feature: Auth" "implement auth"
```

### Run Command Flags

| Flag | Short | Description |
|------|-------|-------------|
| `--format` | | Output format: `default` (formatted) or `json` (raw JSON events) |
| `--continue` | `-c` | Continue the last session |
| `--session` | `-s` | Session ID to continue |
| `--model` | `-m` | Model in `provider/model` format |
| `--agent` | | Agent to use (named agents from config) |
| `--file` | `-f` | File(s) to attach (repeatable) |
| `--share` | | Share the session after completion |
| `--title` | | Title for the session |
| `--attach` | | Attach to running server URL |
| `--port` | | Port for local server (random if not specified) |
| `--command` | | Command to run, use message for args |

### Environment Variables

| Variable | Description |
|----------|-------------|
| `OPENCODE_CONFIG` | Path to config file |
| `OPENCODE_CONFIG_DIR` | Path to config directory |
| `OPENCODE_CONFIG_CONTENT` | Inline JSON config content |
| `OPENCODE_PERMISSION` | Inline JSON permissions config |

### Event Types (JSON format)

Based on actual nd-JSON output (verified via `opencode run --format json`):

```jsonl
{"type":"step_start","timestamp":1766946527085,"sessionID":"ses_xxx","part":{"id":"prt_xxx","sessionID":"ses_xxx","messageID":"msg_xxx","type":"step-start","snapshot":"..."}}
{"type":"text","timestamp":1766946527085,"sessionID":"ses_xxx","part":{"id":"prt_xxx","sessionID":"ses_xxx","messageID":"msg_xxx","type":"text","text":"Hello...","time":{"start":...,"end":...},"metadata":{...}}}
{"type":"tool_use","timestamp":...,"sessionID":"ses_xxx","part":{"id":"prt_xxx","sessionID":"ses_xxx","messageID":"msg_xxx","type":"tool","callID":"call_xxx","tool":"read","state":{"status":"completed","input":{...},"output":"..."},...}}
{"type":"step_finish","timestamp":1766946527150,"sessionID":"ses_xxx","part":{"id":"prt_xxx","sessionID":"ses_xxx","messageID":"msg_xxx","type":"step-finish","reason":"stop|tool-calls","snapshot":"...","cost":0.024,"tokens":{"input":13998,"output":7,"reasoning":0,"cache":{"read":0,"write":0}}}}
```

**Event Types** (verified via actual CLI v1.0.204):
- `step_start` - Turn/step begins, contains sessionID, messageID, snapshot hash
- `text` - Text content from assistant, contains `part.text` with the actual message
- `tool_use` - Tool invocation AND result combined:
  - `part.type`: "tool"
  - `part.tool`: tool name (e.g., "read", "bash", "glob")
  - `part.callID`: unique call identifier
  - `part.state.status`: "completed" | "error"
  - `part.state.input`: tool input arguments
  - `part.state.output`: tool output/result
- `step_finish` - Turn/step ends:
  - `part.reason`: "stop" (final), "tool-calls" (more steps needed)
  - `part.cost`: cost in USD
  - `part.tokens`: `{input, output, reasoning, cache: {read, write}}`
- `error` - Error occurred (to be verified)

**Tool Types** (built-in):
- `bash` - Shell command execution
- `read` - Read file contents
- `write` - Write file
- `edit` - Edit file (search/replace)
- `patch` - Apply patches
- `glob` - Find files by pattern
- `grep` - Search file contents
- `list` - List directory
- `webfetch` - Fetch web content
- `todoread` / `todowrite` - Task management
- `skill` - Custom skills

---

## 3. Architecture

### Directory Structure

```
packages/auxiliary/src/Agents/
├── Common/                               # Shared components (existing)
│   ├── Collection/
│   │   └── DecodedObjectCollection.php
│   ├── Contract/
│   │   └── CommandExecutor.php
│   ├── Enum/
│   │   └── SandboxDriver.php
│   ├── Execution/
│   │   ├── ExecutionPolicy.php
│   │   └── SandboxCommandExecutor.php
│   └── Value/
│       ├── Argv.php
│       ├── CommandSpec.php
│       ├── DecodedObject.php
│       └── PathList.php
├── ClaudeCode/                           # Existing
├── OpenAICodex/                          # Existing
└── OpenCode/                             # NEW
    ├── Application/
    │   ├── Builder/
    │   │   └── OpenCodeCommandBuilder.php
    │   ├── Dto/
    │   │   ├── OpenCodeRequest.php
    │   │   └── OpenCodeResponse.php
    │   └── Parser/
    │       └── ResponseParser.php
    ├── Domain/
    │   ├── Dto/
    │   │   └── StreamEvent/
    │   │       ├── StreamEvent.php           # Abstract base with factory
    │   │       ├── StepStartEvent.php        # Turn begins
    │   │       ├── TextEvent.php             # Text content
    │   │       ├── ToolUseEvent.php          # Tool invocation + result
    │   │       ├── StepFinishEvent.php       # Turn ends (usage, cost)
    │   │       ├── ErrorEvent.php
    │   │       └── UnknownEvent.php
    │   ├── Enum/
    │   │   └── OutputFormat.php              # default | json
    │   └── Value/
    │       ├── UsageStats.php
    │       └── ModelId.php                   # provider/model value object
    └── README.md
```

### Key Design Decisions

1. **No SandboxMode enum**: OpenCode uses config-based permissions, not CLI flags
2. **ModelId value object**: Parse and validate `provider/model` format
3. **File attachments**: Support multiple files via `--file` flag (repeatable)
4. **Server attachment**: Optional `--attach` for connecting to running server
5. **Simpler event model**: OpenCode events appear simpler than Codex items

---

## 4. Implementation Phases

### Phase 1: Core Enums and Value Objects

**Files to create**:

1. `Domain/Enum/OutputFormat.php`
   ```php
   enum OutputFormat: string {
       case Default = 'default';  // Formatted output
       case Json = 'json';        // nd-JSON streaming
   }
   ```

2. `Domain/Value/ModelId.php`
   ```php
   final readonly class ModelId {
       public function __construct(
           public string $provider,
           public string $model,
       ) {}

       public static function fromString(string $value): self {
           $parts = explode('/', $value, 2);
           if (count($parts) !== 2) {
               throw new \InvalidArgumentException(
                   "Model must be in provider/model format, got: {$value}"
               );
           }
           return new self($parts[0], $parts[1]);
       }

       public function toString(): string {
           return "{$this->provider}/{$this->model}";
       }
   }
   ```

3. `Domain/Value/UsageStats.php`
   ```php
   final readonly class UsageStats {
       public function __construct(
           public int $inputTokens,
           public int $outputTokens,
           public ?int $cachedInputTokens = null,
       ) {}

       public static function fromArray(array $data): self {
           return new self(
               inputTokens: $data['input_tokens'] ?? 0,
               outputTokens: $data['output_tokens'] ?? 0,
               cachedInputTokens: $data['cached_input_tokens'] ?? null,
           );
       }
   }
   ```

---

### Phase 2: Request DTO

**File**: `Application/Dto/OpenCodeRequest.php`

```php
final readonly class OpenCodeRequest
{
    /**
     * @param string $prompt The message/prompt to send
     * @param OutputFormat $outputFormat Output format (default or json)
     * @param string|ModelId|null $model Model in provider/model format
     * @param string|null $agent Named agent to use
     * @param list<string>|null $files File paths to attach
     * @param bool $continueSession Continue the last session
     * @param string|null $sessionId Specific session ID to continue
     * @param bool $share Share the session after completion
     * @param string|null $title Session title
     * @param string|null $attachUrl Attach to running server URL
     * @param int|null $port Local server port
     * @param string|null $command Command to run (message becomes args)
     */
    public function __construct(
        private string $prompt,
        private OutputFormat $outputFormat = OutputFormat::Default,
        private string|ModelId|null $model = null,
        private ?string $agent = null,
        private ?array $files = null,
        private bool $continueSession = false,
        private ?string $sessionId = null,
        private bool $share = false,
        private ?string $title = null,
        private ?string $attachUrl = null,
        private ?int $port = null,
        private ?string $command = null,
    ) {}

    // Getter methods...

    public function isResume(): bool {
        return $this->continueSession || ($this->sessionId !== null && $this->sessionId !== '');
    }

    public function modelString(): ?string {
        if ($this->model === null) {
            return null;
        }
        return $this->model instanceof ModelId
            ? $this->model->toString()
            : $this->model;
    }
}
```

---

### Phase 3: Command Builder

**File**: `Application/Builder/OpenCodeCommandBuilder.php`

```php
final class OpenCodeCommandBuilder
{
    public function buildRun(OpenCodeRequest $request): CommandSpec
    {
        $this->validate($request);

        // Use stdbuf for unbuffered output (Linux)
        $argv = Argv::of(['stdbuf', '-o0', 'opencode', 'run']);

        // Add flags before prompt
        $argv = $this->appendOutputFormat($argv, $request->outputFormat());
        $argv = $this->appendModel($argv, $request->modelString());
        $argv = $this->appendAgent($argv, $request->agent());
        $argv = $this->appendFiles($argv, $request->files());
        $argv = $this->appendSessionFlags($argv, $request);
        $argv = $this->appendShare($argv, $request->share());
        $argv = $this->appendTitle($argv, $request->title());
        $argv = $this->appendAttach($argv, $request->attachUrl());
        $argv = $this->appendPort($argv, $request->port());
        $argv = $this->appendCommand($argv, $request->command());

        // Prompt goes last
        $argv = $argv->with($request->prompt());

        return new CommandSpec($argv, null);
    }

    private function appendOutputFormat(Argv $argv, OutputFormat $format): Argv {
        if ($format === OutputFormat::Default) {
            return $argv;
        }
        return $argv->with('--format')->with($format->value);
    }

    private function appendModel(Argv $argv, ?string $model): Argv {
        if ($model === null || $model === '') {
            return $argv;
        }
        return $argv->with('--model')->with($model);
    }

    private function appendAgent(Argv $argv, ?string $agent): Argv {
        if ($agent === null || $agent === '') {
            return $argv;
        }
        return $argv->with('--agent')->with($agent);
    }

    /**
     * @param list<string>|null $files
     */
    private function appendFiles(Argv $argv, ?array $files): Argv {
        if ($files === null || count($files) === 0) {
            return $argv;
        }
        $current = $argv;
        foreach ($files as $file) {
            $current = $current->with('--file')->with($file);
        }
        return $current;
    }

    private function appendSessionFlags(Argv $argv, OpenCodeRequest $request): Argv {
        if ($request->continueSession()) {
            return $argv->with('--continue');
        }
        $sessionId = $request->sessionId();
        if ($sessionId !== null && $sessionId !== '') {
            return $argv->with('--session')->with($sessionId);
        }
        return $argv;
    }

    // ... other append methods

    private function validate(OpenCodeRequest $request): void {
        if (trim($request->prompt()) === '') {
            throw new \InvalidArgumentException('Prompt must not be empty');
        }
        if ($request->continueSession() && $request->sessionId() !== null) {
            throw new \InvalidArgumentException(
                'Cannot set both continueSession and sessionId'
            );
        }
    }
}
```

---

### Phase 4: Stream Event DTOs

**File**: `Domain/Dto/StreamEvent/StreamEvent.php`

```php
abstract readonly class StreamEvent
{
    abstract public function type(): string;

    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? 'unknown';

        return match ($type) {
            'step_start' => StepStartEvent::fromArray($data),
            'text' => TextEvent::fromArray($data),
            'tool_use' => ToolUseEvent::fromArray($data),
            'step_finish' => StepFinishEvent::fromArray($data),
            'error' => ErrorEvent::fromArray($data),
            default => UnknownEvent::fromArray($data),
        };
    }
}
```

**Event DTOs** (to implement based on verified format):

1. `StepStartEvent` - Turn begins
   - `sessionId`: Session identifier
   - `messageId`: Message identifier
   - `snapshot`: Git snapshot hash
2. `TextEvent` - Text content
   - `text`: The actual message text
   - `time`: Start/end timestamps
3. `ToolUseEvent` - Tool invocation AND result (combined in single event)
   - `tool`: Tool name (read, bash, glob, etc.)
   - `callId`: Unique call identifier
   - `status`: "completed" | "error"
   - `input`: Tool input arguments (varies by tool)
   - `output`: Tool result/output
4. `StepFinishEvent` - Turn ends
   - `reason`: "stop" (final) | "tool-calls" (more steps follow)
   - `cost`: Cost in USD
   - `tokens`: TokenUsage object
5. `ErrorEvent` - Contains error details
6. `UnknownEvent` - Fallback for unrecognized types

---

### Phase 5: Response Parser

**File**: `Application/Parser/ResponseParser.php`

```php
final class ResponseParser
{
    public function parse(ExecResult $result, OutputFormat $format): OpenCodeResponse
    {
        return match ($format) {
            OutputFormat::Default => $this->fromText($result),
            OutputFormat::Json => $this->fromJsonLines($result),
        };
    }

    private function fromJsonLines(ExecResult $result): OpenCodeResponse
    {
        $lines = preg_split('/\r\n|\r|\n/', $result->stdout());
        if (!is_array($lines)) {
            return new OpenCodeResponse($result, DecodedObjectCollection::empty());
        }

        $items = [];
        $sessionId = null;
        $messageId = null;
        $usage = null;
        $cost = null;
        $messageText = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded)) {
                continue;
            }

            $items[] = new DecodedObject($decoded);
            $event = StreamEvent::fromArray($decoded);

            // Extract sessionID from first event
            if ($sessionId === null && isset($decoded['sessionID'])) {
                $sessionId = $decoded['sessionID'];
            }

            // Accumulate text content
            if ($event instanceof TextEvent) {
                $messageText .= $event->text;
            }

            // Extract usage from step_finish
            if ($event instanceof StepFinishEvent) {
                $usage = $event->tokens;
                $cost = $event->cost;
                $messageId = $event->messageId;
            }
        }

        return new OpenCodeResponse(
            result: $result,
            decoded: DecodedObjectCollection::of($items),
            sessionId: $sessionId,
            messageId: $messageId,
            messageText: $messageText,
            usage: $usage,
            cost: $cost,
        );
    }
}
```

---

### Phase 6: Response DTO

**File**: `Application/Dto/OpenCodeResponse.php`

```php
final readonly class OpenCodeResponse
{
    public function __construct(
        private ExecResult $result,
        private DecodedObjectCollection $decoded,
        private ?string $sessionId = null,
        private ?string $messageId = null,
        private string $messageText = '',
        private ?TokenUsage $usage = null,
        private ?float $cost = null,
    ) {}

    public function exitCode(): int {
        return $this->result->exitCode();
    }

    public function stdout(): string {
        return $this->result->stdout();
    }

    public function stderr(): string {
        return $this->result->stderr();
    }

    public function decoded(): DecodedObjectCollection {
        return $this->decoded;
    }

    public function sessionId(): ?string {
        return $this->sessionId;
    }

    public function messageId(): ?string {
        return $this->messageId;
    }

    public function messageText(): string {
        return $this->messageText;
    }

    public function usage(): ?TokenUsage {
        return $this->usage;
    }

    /** Cost in USD */
    public function cost(): ?float {
        return $this->cost;
    }
}
```

**File**: `Domain/Value/TokenUsage.php`

```php
final readonly class TokenUsage
{
    public function __construct(
        public int $input,
        public int $output,
        public int $reasoning = 0,
        public int $cacheRead = 0,
        public int $cacheWrite = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        $cache = $data['cache'] ?? [];
        return new self(
            input: $data['input'] ?? 0,
            output: $data['output'] ?? 0,
            reasoning: $data['reasoning'] ?? 0,
            cacheRead: $cache['read'] ?? 0,
            cacheWrite: $cache['write'] ?? 0,
        );
    }

    public function total(): int {
        return $this->input + $this->output + $this->reasoning;
    }
}
```

---

### Phase 7: Executor Integration

**File**: `Common/Execution/SandboxCommandExecutor.php` (extend)

Add factory method for OpenCode:

```php
public static function forOpenCode(): self
{
    $policy = ExecutionPolicy::forCli(
        baseDir: getcwd(),
        cacheSubdir: '.opencode',
        timeoutSeconds: 300,
    );
    return new self(
        policy: $policy,
        sandboxExecutor: new SandboxCommandExecutor(),
    );
}
```

---

### Phase 8: Examples

Create examples following existing patterns:

1. `examples/B05_LLMExtras/OpenCodeBasic/run.php` - Simple text response
2. `examples/B05_LLMExtras/OpenCodeStreaming/run.php` - JSON streaming
3. `examples/B05_LLMExtras/OpenCodeFiles/run.php` - File attachments
4. `examples/B05_LLMExtras/OpenCodeAgents/run.php` - Using named agents

---

## 5. Anticipated Gotchas

### 1. Event Format Verification

The event format above is speculative based on documentation. Need to run actual CLI with `--format json` to verify exact event structure:

```bash
opencode run --format json "Hello"
```

### 2. Provider/Model Validation

The `ModelId` value object should validate provider names against known providers, or at minimum validate the format contains exactly one `/`.

### 3. No Sandbox CLI Flags

Unlike Codex, OpenCode doesn't have `--sandbox` flags. Permissions are config-based:

```jsonc
// opencode.jsonc
{
  "permissions": {
    "execute": ["allowed_command_1", "allowed_command_2"],
    "write": {
      "allow": ["./src/**", "./tests/**"],
      "deny": ["./node_modules/**"]
    }
  }
}
```

Consider adding a `configContent` parameter to `OpenCodeRequest` for passing `OPENCODE_CONFIG_CONTENT` environment variable.

### 4. Server Attachment Mode

When using `--attach`, the CLI connects to a running server instead of starting its own. This is faster for multiple invocations but requires the server to be running. Consider documenting this workflow for batch operations.

### 5. File Attachment vs Images

OpenCode uses `--file` for any file attachment, not just images. Multiple files can be attached by repeating the flag:

```bash
opencode run --file a.png --file b.csv --file c.json "analyze these"
```

### 6. stdbuf Requirement

Same as other bridges - `stdbuf` is Linux-specific. Document requirement or add detection.

### 7. Exit Codes

Need to verify OpenCode exit code semantics:
- `0` = success
- Non-zero = various errors

### 8. Session Management

OpenCode has more sophisticated session management:
- `opencode session list` - List all sessions
- `opencode export <id>` - Export session as JSON
- `opencode import <file>` - Import session

Consider adding session listing/export utilities in future phases.

---

## 6. Testing Strategy

1. **Unit Tests**: DTO parsing, command building, ModelId validation
2. **Integration Tests**: Actual CLI execution (requires OpenCode installed)
3. **Manual Tests**: Streaming behavior, file attachments, server attachment

### Test Installation

```bash
# Install OpenCode
npm install -g opencode

# Or via curl
curl -fsSL https://get.opencode.dev | bash

# Verify installation
opencode --version
```

---

## 7. Success Criteria

### Phase 1-2 (Core)
- [ ] OutputFormat enum created
- [ ] ModelId value object with validation
- [ ] UsageStats value object
- [ ] OpenCodeRequest DTO with all flags

### Phase 3 (Builder)
- [ ] OpenCodeCommandBuilder builds correct argv
- [ ] All flags correctly appended
- [ ] Validation catches invalid combinations

### Phase 4-5 (Events)
- [ ] StreamEvent hierarchy with factory
- [ ] All event types parsed correctly
- [ ] ResponseParser extracts session_id and usage

### Phase 6-7 (Integration)
- [ ] OpenCodeResponse aggregates data
- [ ] SandboxCommandExecutor.forOpenCode() works
- [ ] Basic execution produces valid response

### Phase 8 (Examples)
- [ ] Basic example runs successfully
- [ ] Streaming example shows events
- [ ] Files example attaches and processes
- [ ] README documents usage

---

## 8. Open Questions

1. ~~**Event format**: Need to verify exact JSON event structure from actual CLI output~~ **VERIFIED** - Events use `step_start`, `text`, `tool_use`, `step_finish` types
2. ~~**Tool result format**: How does OpenCode format tool results in events?~~ **VERIFIED** - Tool results are embedded in `tool_use` events via `part.state.output`
3. **Partial content**: Does OpenCode support partial message streaming like Claude Code? (Appears to send complete text in single `text` event)
4. **Error handling**: What does the error event look like exactly? (Need to trigger an error to verify)
5. **MCP integration**: OpenCode supports MCP - should we expose MCP-related options?
6. **Cost tracking**: OpenCode provides cost in `step_finish` - useful for monitoring/billing
7. **Multi-step conversations**: When `reason: "tool-calls"`, multiple `step_start`/`step_finish` pairs occur - need to aggregate across steps

---

## 9. Future Enhancements

1. **Session utilities**: List, export, import sessions
2. **Agent management**: Create, list, delete agents
3. **MCP server config**: Configure MCP servers programmatically
4. **Server mode**: Start/stop `opencode serve` from PHP
5. **GitHub integration**: Trigger `opencode github run` for PR automation

---

## 10. References

- OpenCode CLI Docs: `docs-internal/opencode/cli.mdx`
- OpenCode Config: `docs-internal/opencode/config.mdx`
- OpenCode Providers: `docs-internal/opencode/providers.mdx`
- OpenCode Tools: `docs-internal/opencode/tools.mdx`
- OpenCode Troubleshooting: `docs-internal/opencode/troubleshooting.mdx`
- ClaudeCode Bridge: `packages/auxiliary/src/Agents/ClaudeCode/`
- OpenAICodex Bridge: `packages/auxiliary/src/Agents/OpenAICodex/`
- Common Components: `packages/auxiliary/src/Agents/Common/`
