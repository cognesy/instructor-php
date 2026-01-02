# OpenAI Codex CLI Bridge - Implementation Plan

**Date**: 2025-12-28
**Status**: Completed
**Reference**: `packages/auxiliary/src/Agents/ClaudeCode` (existing pattern)
**Target**: `packages/auxiliary/src/Agents/OpenAICodex`

---

## 1. Executive Summary

Implement a PHP bridge to OpenAI's Codex CLI following the established pattern from ClaudeCodeCli. This enables headless execution with JSONL streaming, sandbox integration, and typed DTOs.

### Key Differences from Claude Code CLI

| Aspect | Claude Code | OpenAI Codex |
|--------|-------------|--------------|
| Headless command | `claude -p` | `codex exec` |
| Streaming flag | `--output-format stream-json` | `--json` |
| Partial messages | `--include-partial-messages` | N/A (always streams) |
| Sandbox modes | `--permission-mode` (7 modes) | `--sandbox` (3 modes) + `--ask-for-approval` (4 modes) |
| Session resume | `--continue` / `--resume <id>` | `resume --last` / `resume <id>` (subcommand) |
| Images | Not supported | `--image` / `-i` |
| Structured output | N/A | `--output-schema` |
| Git check bypass | N/A | `--skip-git-repo-check` |
| Dangerous mode | `--dangerously-skip-permissions` | `--dangerously-bypass-approvals-and-sandbox` / `--yolo` |

---

## 2. Codex CLI Reference

### Command Structure

```bash
# Basic headless execution
codex exec "prompt"

# With streaming JSON
codex exec --json "prompt"

# Full-auto mode (workspace-write + approvals on-failure)
codex exec --full-auto "prompt"

# Resume session
codex exec resume --last "follow-up prompt"
codex exec resume <SESSION_ID> "follow-up prompt"

# With images
codex exec --image screenshot.png "explain this"

# Structured output
codex exec --output-schema schema.json "extract data"

# Dangerous bypass (CI only)
codex exec --yolo "prompt"
```

### Sandbox Modes (`--sandbox`)

| Value | Description |
|-------|-------------|
| `read-only` | Default. No writes, no network |
| `workspace-write` | Write to workspace only |
| `danger-full-access` | Full access (dangerous) |

### Approval Modes (`--ask-for-approval`)

| Value | Description |
|-------|-------------|
| `untrusted` | Default. Ask before risky operations |
| `on-failure` | Only ask after failures |
| `on-request` | Ask when explicitly requested |
| `never` | Never ask (requires sandbox) |

### Event Types (JSONL with `--json`)

From the SDK documentation:

```jsonl
{"type":"thread.started","thread_id":"uuid"}
{"type":"turn.started"}
{"type":"item.started","item":{"id":"item_1","type":"command_execution","command":"bash -lc ls","status":"in_progress"}}
{"type":"item.completed","item":{"id":"item_1","type":"command_execution","command":"bash -lc ls","status":"completed"}}
{"type":"item.started","item":{"id":"item_2","type":"agent_message","text":"..."}}
{"type":"item.completed","item":{"id":"item_2","type":"agent_message","text":"..."}}
{"type":"turn.completed","usage":{"input_tokens":24763,"cached_input_tokens":24448,"output_tokens":122}}
```

**Event Types**:
- `thread.started` - Session begins, provides thread_id
- `turn.started` - New turn begins
- `turn.completed` - Turn ends with usage stats
- `turn.failed` - Turn failed
- `item.started` - Item begins (message, command, etc.)
- `item.completed` - Item completes
- `error` - Error occurred

**Item Types**:
- `agent_message` - Text from agent
- `command_execution` - Shell command
- `file_change` - File modification
- `mcp_tool_call` - MCP tool invocation
- `web_search` - Web search
- `plan_update` - Plan modification
- `reasoning` - Reasoning trace

---

## 3. Shared Components Extraction

### Components to Move to `packages/auxiliary/src/Agents/Common`

Before implementing the Codex bridge, extract these reusable components:

#### Value Objects (100% Reusable)

| Component | Current Location | New Location |
|-----------|------------------|--------------|
| `Argv` | `ClaudeCodeCli/Domain/Value/` | `Agents/Common/Value/` |
| `CommandSpec` | `ClaudeCodeCli/Domain/Value/` | `Agents/Common/Value/` |
| `PathList` | `ClaudeCodeCli/Domain/Value/` | `Agents/Common/Value/` |
| `DecodedObject` | `ClaudeCodeCli/Domain/Value/` | `Agents/Common/Value/` |

#### Collections (100% Reusable)

| Component | Current Location | New Location |
|-----------|------------------|--------------|
| `DecodedObjectCollection` | `ClaudeCodeCli/Domain/Collection/` | `Agents/Common/Collection/` |

#### Execution Infrastructure (100% Reusable)

| Component | Current Location | New Location |
|-----------|------------------|--------------|
| `SandboxDriver` | `ClaudeCodeCli/Infrastructure/Execution/` | `Agents/Common/Enum/` |
| `CommandExecutor` | `ClaudeCodeCli/Infrastructure/Execution/` | `Agents/Common/Contract/` |
| `SandboxCommandExecutor` | `ClaudeCodeCli/Infrastructure/Execution/` | `Agents/Common/Execution/` |

#### Needs Generalization

| Component | Current Location | Issue | Solution |
|-----------|------------------|-------|----------|
| `ExecutionPolicy` | `ClaudeCodeCli/Infrastructure/Execution/` | Hardcoded `.claude` path | Create `ExecutionPolicyFactory` with CLI-specific config |

### Proposed Common Directory Structure

```
packages/auxiliary/src/Agents/
├── Common/
│   ├── Collection/
│   │   └── DecodedObjectCollection.php
│   ├── Contract/
│   │   └── CommandExecutor.php
│   ├── Enum/
│   │   └── SandboxDriver.php
│   ├── Execution/
│   │   ├── ExecutionPolicy.php          # Generalized with configurable cache dir
│   │   └── SandboxCommandExecutor.php
│   └── Value/
│       ├── Argv.php
│       ├── CommandSpec.php
│       ├── DecodedObject.php
│       └── PathList.php
├── ClaudeCode/                          # Renamed from ClaudeCodeCli
│   └── ... (uses Common namespace)
└── OpenAICodex/                         # New implementation
    └── ... (uses Common namespace)
```

### Migration Steps

1. Create `Agents/Common/` directory structure
2. Move generic components, update namespace to `Cognesy\Auxiliary\Agents\Common\...`
3. Update `ClaudeCodeCli` to use new common imports
4. Rename `ClaudeCodeCli` to `Agents/ClaudeCode` for consistency
5. Implement `OpenAICodex` using shared components

### ExecutionPolicy Generalization

```php
// Before (Claude-specific)
->withWritablePaths($baseDir . '/.claude');

// After (Generic)
final readonly class ExecutionPolicy {
    public static function forCli(
        string $baseDir,
        string $cacheSubdir,  // '.claude' or '.codex'
        int $timeoutSeconds = 60,
        // ...
    ): self {
        return new self(
            SandboxPolicy::in($baseDir)
                ->withWritablePaths($baseDir . '/' . $cacheSubdir)
                // ...
        );
    }
}
```

---

## 4. Architecture

### Directory Structure

```
packages/auxiliary/src/Agents/
├── Common/                               # Shared components (from ClaudeCodeCli)
│   ├── Collection/
│   │   └── DecodedObjectCollection.php
│   ├── Contract/
│   │   └── CommandExecutor.php
│   ├── Enum/
│   │   └── SandboxDriver.php
│   ├── Execution/
│   │   ├── ExecutionPolicy.php          # Generalized
│   │   └── SandboxCommandExecutor.php
│   └── Value/
│       ├── Argv.php
│       ├── CommandSpec.php
│       ├── DecodedObject.php
│       └── PathList.php
├── ClaudeCode/                           # Renamed from ClaudeCodeCli
│   └── ... (updated imports)
└── OpenAICodex/                          # New implementation
    ├── Application/
    │   ├── Builder/
    │   │   └── CodexCommandBuilder.php
    │   ├── Dto/
    │   │   ├── CodexRequest.php
    │   │   └── CodexResponse.php
    │   └── Parser/
    │       └── ResponseParser.php
    ├── Domain/
    │   ├── Dto/
    │   │   ├── StreamEvent/
    │   │   │   ├── StreamEvent.php         # Abstract base
    │   │   │   ├── ThreadStartedEvent.php
    │   │   │   ├── TurnStartedEvent.php
    │   │   │   ├── TurnCompletedEvent.php
    │   │   │   ├── TurnFailedEvent.php
    │   │   │   ├── ItemStartedEvent.php
    │   │   │   ├── ItemCompletedEvent.php
    │   │   │   ├── ErrorEvent.php
    │   │   │   └── UnknownEvent.php
    │   │   └── Item/
    │   │       ├── Item.php                # Abstract base
    │   │       ├── AgentMessage.php
    │   │       ├── CommandExecution.php
    │   │       ├── FileChange.php
    │   │       ├── McpToolCall.php
    │   │       ├── WebSearch.php
    │   │       ├── PlanUpdate.php
    │   │       ├── Reasoning.php
    │   │       └── UnknownItem.php
    │   ├── Enum/
    │   │   ├── OutputFormat.php            # text | json
    │   │   ├── SandboxMode.php             # Codex sandbox modes
    │   │   └── ApprovalMode.php            # Codex approval modes
    │   └── Value/
    │       └── UsageStats.php              # Token usage
    └── README.md
```

### Component Reuse Strategy

Shared components from `Agents/Common`:
- `Argv` - Command line argument builder
- `CommandSpec` - Command + stdin specification
- `PathList` - Path collection
- `DecodedObject` - Raw decoded JSON wrapper
- `DecodedObjectCollection` - Collection of decoded objects
- `SandboxDriver` - Driver enum (Host, Docker, Podman, Firejail, Bubblewrap)
- `CommandExecutor` - Interface contract
- `SandboxCommandExecutor` - Core executor with streaming
- `ExecutionPolicy` - Generalized sandbox policy builder

**Decision**: Extract to Common namespace FIRST, then build OpenAICodex using shared components.

---

## 5. Implementation Phases

### Phase 0: Shared Components Extraction (Pre-requisite)

**Goal**: Extract reusable components from `ClaudeCodeCli` to `Agents/Common`

**Tasks**:
1. Create `Agents/Common/` directory structure
2. Move value objects (`Argv`, `CommandSpec`, `PathList`, `DecodedObject`)
3. Move collection (`DecodedObjectCollection`)
4. Move execution infrastructure (`SandboxDriver`, `CommandExecutor`, `SandboxCommandExecutor`)
5. Generalize `ExecutionPolicy` (parameterize cache subdirectory)
6. Update all namespaces to `Cognesy\Auxiliary\Agents\Common\...`
7. Update `ClaudeCodeCli` imports to use new Common namespace
8. Rename `ClaudeCodeCli` directory to `Agents/ClaudeCode`
9. Update examples to use new namespace
10. Run tests to verify nothing broken

**Files to Move**:
```
ClaudeCodeCli/Domain/Value/Argv.php           → Common/Value/Argv.php
ClaudeCodeCli/Domain/Value/CommandSpec.php    → Common/Value/CommandSpec.php
ClaudeCodeCli/Domain/Value/PathList.php       → Common/Value/PathList.php
ClaudeCodeCli/Domain/Value/DecodedObject.php  → Common/Value/DecodedObject.php
ClaudeCodeCli/Domain/Collection/DecodedObjectCollection.php → Common/Collection/DecodedObjectCollection.php
ClaudeCodeCli/Infrastructure/Execution/SandboxDriver.php → Common/Enum/SandboxDriver.php
ClaudeCodeCli/Infrastructure/Execution/CommandExecutor.php → Common/Contract/CommandExecutor.php
ClaudeCodeCli/Infrastructure/Execution/SandboxCommandExecutor.php → Common/Execution/SandboxCommandExecutor.php
ClaudeCodeCli/Infrastructure/Execution/ExecutionPolicy.php → Common/Execution/ExecutionPolicy.php (generalized)
```

---

### Phase 1: Codex Core DTOs and Enums

**Files to create**:

1. `Domain/Enum/OutputFormat.php`
   ```php
   enum OutputFormat: string {
       case Text = 'text';
       case Json = 'json';  // JSONL streaming
   }
   ```

2. `Domain/Enum/SandboxMode.php`
   ```php
   enum SandboxMode: string {
       case ReadOnly = 'read-only';
       case WorkspaceWrite = 'workspace-write';
       case DangerFullAccess = 'danger-full-access';
   }
   ```

3. `Domain/Enum/ApprovalMode.php`
   ```php
   enum ApprovalMode: string {
       case Untrusted = 'untrusted';
       case OnFailure = 'on-failure';
       case OnRequest = 'on-request';
       case Never = 'never';
   }
   ```

4. `Application/Dto/CodexRequest.php`
   ```php
   final readonly class CodexRequest {
       public function __construct(
           private string $prompt,
           private OutputFormat $outputFormat = OutputFormat::Text,
           private ?SandboxMode $sandboxMode = null,
           private ?ApprovalMode $approvalMode = null,
           private ?string $model = null,
           private ?array $images = null,
           private ?string $workingDirectory = null,
           private ?array $additionalDirs = null,
           private ?string $outputSchemaFile = null,
           private ?string $outputLastMessageFile = null,
           private ?string $profile = null,
           private bool $fullAuto = false,
           private bool $dangerouslyBypass = false,
           private bool $skipGitRepoCheck = false,
           private bool $enableSearch = false,
           private ?string $resumeSessionId = null,
           private bool $resumeLast = false,
           // Config overrides
           private ?array $configOverrides = null,
       ) {}
   }
   ```

### Phase 2: Command Builder

**File**: `Application/Builder/CodexCommandBuilder.php`

```php
final class CodexCommandBuilder {
    public function buildExec(CodexRequest $request): CommandSpec {
        $this->validate($request);

        // Use stdbuf for unbuffered output
        $argv = Argv::of(['stdbuf', '-o0', 'codex', 'exec']);

        // Handle resume subcommand
        $argv = $this->appendResumeFlags($argv, $request);

        // Add prompt
        $argv = $argv->with($request->prompt());

        // Add flags
        $argv = $this->appendSandboxMode($argv, $request->sandboxMode());
        $argv = $this->appendApprovalMode($argv, $request->approvalMode());
        $argv = $this->appendOutputFormat($argv, $request->outputFormat());
        $argv = $this->appendModel($argv, $request->model());
        $argv = $this->appendImages($argv, $request->images());
        $argv = $this->appendWorkingDirectory($argv, $request->workingDirectory());
        $argv = $this->appendAdditionalDirs($argv, $request->additionalDirs());
        $argv = $this->appendOutputSchema($argv, $request->outputSchemaFile());
        $argv = $this->appendOutputLastMessage($argv, $request->outputLastMessageFile());
        $argv = $this->appendProfile($argv, $request->profile());
        $argv = $this->appendFullAuto($argv, $request->fullAuto());
        $argv = $this->appendDangerouslyBypass($argv, $request->dangerouslyBypass());
        $argv = $this->appendSkipGitRepoCheck($argv, $request->skipGitRepoCheck());
        $argv = $this->appendSearch($argv, $request->enableSearch());
        $argv = $this->appendConfigOverrides($argv, $request->configOverrides());

        return new CommandSpec($argv, null);
    }
}
```

**Key Mappings**:
- `--sandbox` for SandboxMode
- `--ask-for-approval` for ApprovalMode
- `--json` for JSON streaming (not `--output-format`)
- `--model` / `-m` for model override
- `--image` / `-i` for images (repeatable, comma-separated)
- `--cd` / `-C` for working directory
- `--add-dir` for additional writable dirs
- `--output-schema` for structured output
- `-o` / `--output-last-message` for final message file
- `-p` / `--profile` for config profile
- `--full-auto` for automation preset
- `--yolo` / `--dangerously-bypass-approvals-and-sandbox`
- `--skip-git-repo-check` for non-git directories
- `--search` for web search
- `-c` / `--config` for config overrides

### Phase 3: Stream Event DTOs

**Base class**: `Domain/Dto/StreamEvent/StreamEvent.php`

```php
abstract readonly class StreamEvent {
    abstract public function type(): string;

    public static function fromArray(array $data): self {
        $type = $data['type'] ?? 'unknown';

        return match ($type) {
            'thread.started' => ThreadStartedEvent::fromArray($data),
            'turn.started' => TurnStartedEvent::fromArray($data),
            'turn.completed' => TurnCompletedEvent::fromArray($data),
            'turn.failed' => TurnFailedEvent::fromArray($data),
            'item.started' => ItemStartedEvent::fromArray($data),
            'item.completed' => ItemCompletedEvent::fromArray($data),
            'error' => ErrorEvent::fromArray($data),
            default => UnknownEvent::fromArray($data),
        };
    }
}
```

**Event DTOs**:

1. `ThreadStartedEvent` - Contains `thread_id`
2. `TurnStartedEvent` - Empty, just marks turn start
3. `TurnCompletedEvent` - Contains `usage` (input_tokens, cached_input_tokens, output_tokens)
4. `TurnFailedEvent` - Contains error details
5. `ItemStartedEvent` - Contains `item` (with type, id, status)
6. `ItemCompletedEvent` - Contains `item` (with type, id, text/command/etc)
7. `ErrorEvent` - Contains error message
8. `UnknownEvent` - Fallback for unrecognized types

### Phase 4: Item DTOs

**Base class**: `Domain/Dto/Item/Item.php`

```php
abstract readonly class Item {
    abstract public function itemType(): string;

    public function __construct(
        public string $id,
        public string $status,
    ) {}

    public static function fromArray(array $data): self {
        $type = $data['type'] ?? 'unknown';

        return match ($type) {
            'agent_message' => AgentMessage::fromArray($data),
            'command_execution' => CommandExecution::fromArray($data),
            'file_change' => FileChange::fromArray($data),
            'mcp_tool_call' => McpToolCall::fromArray($data),
            'web_search' => WebSearch::fromArray($data),
            'plan_update' => PlanUpdate::fromArray($data),
            'reasoning' => Reasoning::fromArray($data),
            default => UnknownItem::fromArray($data),
        };
    }
}
```

### Phase 5: Response Parser and Executor

**File**: `Application/Parser/ResponseParser.php`

```php
final class ResponseParser {
    public function parse(ExecResult $result, OutputFormat $format): CodexResponse {
        return match ($format) {
            OutputFormat::Text => $this->parseText($result),
            OutputFormat::Json => $this->parseJsonLines($result),
        };
    }
}
```

**File**: `Infrastructure/Execution/ExecutionPolicy.php`

Maps CodexRequest's sandbox/approval modes to Sandbox library's policy.

### Phase 6: Examples

Create examples following ClaudeCode pattern:

1. `examples/B05_LLMExtras/CodexBasic/run.php` - Simple text response
2. `examples/B05_LLMExtras/CodexStreaming/run.php` - JSONL streaming
3. `examples/B05_LLMExtras/CodexStructured/run.php` - Using output-schema
4. `examples/B05_LLMExtras/CodexImages/run.php` - Image input

---

## 6. Anticipated Gotchas

### 1. Resume Subcommand Syntax

Unlike Claude Code where `--resume` is a flag, Codex uses a subcommand:
```bash
# Claude Code
claude -p "prompt" --resume <id>

# Codex
codex exec resume <id> "prompt"
codex exec resume --last "prompt"
```

The command builder must handle this structural difference.

### 2. No Partial Messages Flag

Codex with `--json` always streams all events. No need for `--include-partial-messages` equivalent.

### 3. Item vs Message Hierarchy

Codex wraps messages in `item` objects:
```json
{"type":"item.completed","item":{"id":"item_2","type":"agent_message","text":"Hello"}}
```

Unlike Claude Code which has direct message events:
```json
{"type":"assistant","message":{"content":[{"type":"text","text":"Hello"}]}}
```

### 4. Usage Stats Location

Codex puts usage in `turn.completed`:
```json
{"type":"turn.completed","usage":{"input_tokens":24763,"output_tokens":122}}
```

Claude Code puts usage in `result` event.

### 5. No stdbuf on macOS/Windows

The `stdbuf` command is Linux-specific. Need conditional handling or document requirement.

### 6. Authentication

Codex uses `CODEX_API_KEY` environment variable for programmatic auth:
```bash
CODEX_API_KEY=your-key codex exec --json "prompt"
```

### 7. Exit Codes

Need to verify Codex exit code semantics - may differ from Claude Code.

### 8. Color Output

Codex has `--color` flag (always/never/auto). Default is auto. For parsing, should use `--color never` to avoid ANSI codes in text output.

---

## 7. Testing Strategy

1. **Unit Tests**: DTO parsing, command building
2. **Integration Tests**: Actual CLI execution (requires Codex installed)
3. **Manual Tests**: Streaming behavior verification

---

## 8. Success Criteria

### Phase 0 (Prerequisite)
- [ ] Common namespace created at `Agents/Common`
- [ ] All shared components moved with updated namespaces
- [ ] ClaudeCode imports updated to use Common
- [ ] ClaudeCodeCli renamed to Agents/ClaudeCode
- [ ] Existing examples still work
- [ ] Tests pass

### Codex Bridge
- [ ] Basic text execution works
- [ ] JSONL streaming with callback works
- [ ] All event types parsed correctly
- [ ] All item types parsed correctly
- [ ] Session resume works
- [ ] Image input works
- [ ] Structured output works
- [ ] Sandbox modes map correctly
- [ ] Examples demonstrate all features
- [ ] README documents all gotchas

---

## 9. Open Questions

1. ~~**Shared value objects**: Extract to shared location?~~ **RESOLVED**: Moving to `Agents/Common`
2. ~~**Executor reuse**: Can `SandboxCommandExecutor` be shared?~~ **RESOLVED**: Yes, moving to Common
3. **Event normalization**: Should we create adapter layer now or wait for OpenCode bridge?
   - **Decision**: Wait. Follow "Rule of Three" - implement 3 bridges first, then extract common patterns
4. **stdbuf alternative**: Research alternatives for macOS/Windows output unbuffering
   - Linux: `stdbuf -o0`
   - macOS: May need to use `script -q /dev/null` or accept buffered output
   - Windows: Different approach needed entirely

---

## 10. References

- Codex CLI Options: `docs-internal/openai-codex/using/cli-options.md`
- Codex CLI Features: `docs-internal/openai-codex/using/cli-features.md`
- Codex SDK: `docs-internal/openai-codex/automation/sdk.md`
- ClaudeCodeCli Pattern: `packages/auxiliary/src/ClaudeCodeCli/`
- Sandbox Library: `packages/utils/src/Sandbox/`
