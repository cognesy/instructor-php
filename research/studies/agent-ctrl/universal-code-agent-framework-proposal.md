# Universal Code Agent Execution Framework - Proposal

## Executive Summary

This proposal outlines a universal framework for executing code agents (Claude Code, OpenAI Codex, OpenCode, etc.) with a consistent PHP API. The framework provides type-safe execution, real-time streaming, sandboxing, and adapter patterns for different engines.

**Key Goals:**
1. **Unified Interface** - Single contract for all code agents
2. **Streaming Support** - Real-time event processing with fallback to buffered results
3. **Type Safety** - Typed DTOs instead of associative arrays
4. **Engine Agnostic** - Adapter pattern for multiple backends
5. **Format Flexibility** - JSON, JSONL, plain text output
6. **Developer Experience** - Fluent API with autocomplete

---

## Comparative Analysis

### Feature Matrix

| Feature | Claude Code | OpenAI Codex | OpenCode | Universal Framework |
|---------|-------------|--------------|----------|---------------------|
| **Execution Modes** | | | | |
| Interactive (TUI) | ✓ | ✓ | ✓ | Adapter-specific |
| Headless/Non-interactive | ✓ (`-p`) | ✓ (`exec`) | ✓ (`run`) | ✓ |
| Server Mode (HTTP) | ✗ | ✗ | ✓ (`serve`) | ✓ (via adapters) |
| SDK/Programmatic | Partial | ✗ | ✓ (JS/TS) | ✓ (PHP) |
| **Output Formats** | | | | |
| Plain Text | ✓ | ✓ | ✓ | ✓ |
| JSON (single) | ✓ | ✗ | ✗ | ✓ |
| JSONL/Stream Events | ✓ | ✓ | ✓ | ✓ |
| Server-Sent Events | ✗ | ✗ | ✓ | ✓ (HTTP adapters) |
| **Streaming** | | | | |
| Real-time callbacks | ✓ | ✓ | ✓ | ✓ |
| Typed event DTOs | ✓ | ✗ | Partial | ✓ |
| Line buffering | Manual | Manual | Auto | ✓ (abstracted) |
| Partial messages | ✓ (`--include-partial`) | ✓ | ✓ | ✓ |
| **Security** | | | | |
| Sandboxing | ✓ (5 drivers) | ✓ (macOS/Linux) | ✓ (modes) | ✓ (pluggable) |
| Permission modes | ✓ (6 modes) | ✓ (3 modes) | ✓ (tool control) | ✓ (unified) |
| Approval workflows | ✓ | ✓ | ✓ | ✓ |
| **Session Management** | | | | |
| Resume sessions | ✓ | ✓ | ✓ | ✓ |
| Session IDs | ✓ | ✓ | ✓ | ✓ |
| Context preservation | ✓ | ✓ | ✓ | ✓ |
| **Advanced Features** | | | | |
| Subagents | ✓ | ✗ | ✓ (agents) | ✓ |
| MCP servers | ✓ | ✓ | ✓ | ✓ |
| Custom modes | Partial | Partial | ✓ | ✓ |
| Image inputs | ✗ | ✓ | ✓ | ✓ (adapter-specific) |
| Web search | ✓ | ✓ | ✓ | ✓ (tool-based) |

### Event Type Comparison

| Event Type | Claude Code | Codex | OpenCode | Universal |
|------------|-------------|-------|----------|-----------|
| System/Init | `system` | `system` | `system` | `SystemEvent` |
| Messages | `stream_event`, `assistant`, `user` | `message` | `message` | `MessageEvent` |
| Tool Calls | In message content | In message content | `tool_use` | `ToolUseEvent` |
| Tool Results | In message content | In message content | `tool_result` | `ToolResultEvent` |
| Completion | `result` | `result` | `done` | `CompletionEvent` |
| Errors | `error` | `error` | `error` | `ErrorEvent` |
| Progress | Partial in stream_event | N/A | `thinking` | `ProgressEvent` |

---

## Proposed Architecture

### 1. Core Contracts (Interfaces)

```php
namespace Cognesy\CodeAgent\Contracts;

/**
 * Main execution interface - all code agents must implement this
 */
interface CodeAgent
{
    /**
     * Execute request with optional streaming callback
     *
     * @param ExecutionRequest $request
     * @param callable|null $onEvent function(StreamEvent $event): void
     * @return ExecutionResult
     */
    public function execute(ExecutionRequest $request, ?callable $onEvent = null): ExecutionResult;

    /**
     * Check if agent supports a specific feature
     */
    public function supports(AgentFeature $feature): bool;

    /**
     * Get agent capabilities
     */
    public function capabilities(): AgentCapabilities;
}

/**
 * Streaming-capable agents extend this
 */
interface SupportsStreaming extends CodeAgent
{
    /**
     * Execute with guaranteed streaming support
     */
    public function executeStreaming(ExecutionRequest $request, callable $onEvent): ExecutionResult;
}

/**
 * Server-based agents (OpenCode) extend this
 */
interface SupportsServer extends CodeAgent
{
    /**
     * Get HTTP client for server
     */
    public function client(): ServerClient;

    /**
     * Connect to existing server
     */
    public function connect(string $url): void;

    /**
     * Start embedded server
     */
    public function serve(ServerConfig $config): Server;
}

/**
 * Session management interface
 */
interface ManagesSessions
{
    public function createSession(SessionConfig $config): Session;
    public function resumeSession(string $sessionId): Session;
    public function listSessions(?SessionFilter $filter = null): array;
}
```

### 2. Unified DTOs

```php
namespace Cognesy\CodeAgent\Domain;

/**
 * Execution request - adapter-agnostic
 */
final readonly class ExecutionRequest
{
    public function __construct(
        public string $prompt,
        public OutputFormat $format = OutputFormat::Text,
        public ?SecurityPolicy $security = null,
        public ?SessionResume $resume = null,
        public ?ModelOverride $model = null,
        public ?SystemPrompt $systemPrompt = null,
        public ?int $maxTurns = null,
        public bool $includePartialMessages = false,
        public ?array $images = null,
        public ?AgentConfig $agentConfig = null,
        public ?array $additionalDirs = null,
    ) {}
}

/**
 * Execution result
 */
final readonly class ExecutionResult
{
    public function __construct(
        public string $sessionId,
        public ExecutionStatus $status,
        public ?string $finalResponse = null,
        public EventCollection $events,
        public UsageStats $usage,
        public int $exitCode = 0,
        public ?string $stderr = null,
    ) {}

    public function successful(): bool {
        return $this->status === ExecutionStatus::Completed
            && $this->exitCode === 0;
    }
}

/**
 * Base event class
 */
abstract readonly class StreamEvent
{
    abstract public function type(): EventType;
    abstract public function timestamp(): DateTimeImmutable;

    public static function fromArray(string $engine, array $data): self {
        return StreamEventFactory::create($engine, $data);
    }
}

/**
 * Message event - agent communication
 */
final readonly class MessageEvent extends StreamEvent
{
    public function __construct(
        public Message $message,
        public DateTimeImmutable $timestamp,
    ) {}

    public function type(): EventType {
        return EventType::Message;
    }
}

/**
 * Tool invocation event
 */
final readonly class ToolUseEvent extends StreamEvent
{
    public function __construct(
        public string $id,
        public string $name,
        public array $input,
        public DateTimeImmutable $timestamp,
    ) {}
}

/**
 * Completion event - final result
 */
final readonly class CompletionEvent extends StreamEvent
{
    public function __construct(
        public string $result,
        public UsageStats $usage,
        public int $turnCount,
        public DateTimeImmutable $timestamp,
    ) {}
}

/**
 * Security policy - unified permission/sandbox config
 */
final readonly class SecurityPolicy
{
    public function __construct(
        public SandboxMode $sandbox = SandboxMode::WorkspaceWrite,
        public ApprovalMode $approvals = ApprovalMode::Auto,
        public ?array $allowedCommands = null,
        public ?array $blockedCommands = null,
        public bool $networkAccess = false,
        public ?int $timeoutSeconds = null,
    ) {}
}

/**
 * Enum: Sandbox isolation level
 */
enum SandboxMode: string
{
    case None = 'none';                    // No isolation (dangerous)
    case ReadOnly = 'read-only';            // Can read files only
    case WorkspaceWrite = 'workspace-write'; // Can write in workspace
    case FullAccess = 'full-access';        // Complete system access
}

/**
 * Enum: Approval strategy
 */
enum ApprovalMode: string
{
    case Never = 'never';          // Never ask (auto-approve)
    case Auto = 'auto';            // Ask for risky operations
    case Plan = 'plan';            // Approve plan before execution
    case Always = 'always';        // Ask for every action
    case OnFailure = 'on-failure'; // Ask only when commands fail
}

/**
 * Enum: Output format
 */
enum OutputFormat: string
{
    case Text = 'text';
    case Json = 'json';
    case JsonLines = 'jsonl';
    case ServerSentEvents = 'sse';
}

/**
 * Usage statistics
 */
final readonly class UsageStats
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
        public int $cacheReadTokens = 0,
        public int $cacheCreationTokens = 0,
        public float $costUsd = 0.0,
        public int $durationMs = 0,
    ) {}
}
```

### 3. Adapter Pattern

Each engine gets its own adapter implementing the contracts:

```php
namespace Cognesy\CodeAgent\Adapters;

/**
 * Claude Code adapter
 */
final class ClaudeCodeAdapter implements CodeAgent, SupportsStreaming, ManagesSessions
{
    public function __construct(
        private ClaudeCommandBuilder $builder,
        private SandboxCommandExecutor $executor,
    ) {}

    public function execute(ExecutionRequest $request, ?callable $onEvent = null): ExecutionResult
    {
        // Map request to ClaudeRequest
        $claudeRequest = $this->mapRequest($request);

        // Build command
        $spec = $this->builder->buildHeadless($claudeRequest);

        // Execute with or without streaming
        if ($onEvent !== null) {
            return $this->executeWithCallback($spec, $request, $onEvent);
        }

        return $this->executeBlocking($spec, $request);
    }

    public function executeStreaming(ExecutionRequest $request, callable $onEvent): ExecutionResult
    {
        $claudeRequest = $this->mapRequest($request);
        $spec = $this->builder->buildHeadless($claudeRequest);

        return $this->executeWithCallback($spec, $request, $onEvent);
    }

    private function executeWithCallback(
        CommandSpec $spec,
        ExecutionRequest $request,
        callable $onEvent
    ): ExecutionResult {
        $lineBuffer = '';
        $events = new EventCollection();

        $internalCallback = function(string $type, string $chunk) use (
            &$lineBuffer,
            &$events,
            $onEvent
        ): void {
            // Buffer and parse JSONL
            $lineBuffer .= $chunk;
            $lines = explode("\n", $lineBuffer);
            $lineBuffer = array_pop($lines);

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '') continue;

                $data = json_decode($trimmed, true);
                if (!is_array($data)) continue;

                // Convert to universal event
                $event = StreamEvent::fromArray('claude-code', $data);
                $events->add($event);

                // Notify consumer
                $onEvent($event);
            }
        };

        $execResult = $this->executor->executeStreaming($spec, $internalCallback);

        return $this->buildResult($execResult, $events);
    }

    public function supports(AgentFeature $feature): bool
    {
        return match ($feature) {
            AgentFeature::Streaming => true,
            AgentFeature::Sandboxing => true,
            AgentFeature::SessionManagement => true,
            AgentFeature::Subagents => true,
            AgentFeature::ImageInput => false,
            AgentFeature::HttpServer => false,
        };
    }
}

/**
 * OpenAI Codex adapter
 */
final class CodexAdapter implements CodeAgent, SupportsStreaming
{
    public function execute(ExecutionRequest $request, ?callable $onEvent = null): ExecutionResult
    {
        // Map to Codex CLI command
        $command = $this->buildCodexCommand($request);

        if ($onEvent !== null) {
            return $this->executeWithJsonStreaming($command, $request, $onEvent);
        }

        return $this->executeBlocking($command, $request);
    }

    private function buildCodexCommand(ExecutionRequest $request): array
    {
        $argv = ['codex', 'exec'];

        // Add prompt
        $argv[] = $request->prompt;

        // Security policy
        if ($request->security) {
            $argv[] = '--sandbox';
            $argv[] = match ($request->security->sandbox) {
                SandboxMode::ReadOnly => 'read-only',
                SandboxMode::WorkspaceWrite => 'workspace-write',
                SandboxMode::FullAccess => 'danger-full-access',
                SandboxMode::None => 'danger-full-access',
            };

            $argv[] = '--ask-for-approval';
            $argv[] = match ($request->security->approvals) {
                ApprovalMode::Never => 'never',
                ApprovalMode::Auto => 'untrusted',
                ApprovalMode::OnFailure => 'on-failure',
                ApprovalMode::Always => 'on-request',
                ApprovalMode::Plan => 'untrusted',
            };
        }

        // Streaming
        if ($request->format === OutputFormat::JsonLines) {
            $argv[] = '--json';
        }

        // Model override
        if ($request->model) {
            $argv[] = '--model';
            $argv[] = $request->model->identifier;
        }

        return $argv;
    }

    public function supports(AgentFeature $feature): bool
    {
        return match ($feature) {
            AgentFeature::Streaming => true,
            AgentFeature::Sandboxing => true,
            AgentFeature::SessionManagement => true,
            AgentFeature::ImageInput => true,
            AgentFeature::Subagents => false,
            AgentFeature::HttpServer => false,
        };
    }
}

/**
 * OpenCode adapter (HTTP-based)
 */
final class OpenCodeAdapter implements CodeAgent, SupportsServer, SupportsStreaming
{
    private ?OpencodeClient $client = null;

    public function client(): ServerClient
    {
        if ($this->client === null) {
            throw new \RuntimeException('Not connected to OpenCode server');
        }
        return $this->client;
    }

    public function connect(string $url): void
    {
        $this->client = new OpencodeClient($url);
    }

    public function serve(ServerConfig $config): Server
    {
        // Start embedded OpenCode server
        $process = $this->startServer($config);
        $this->connect($config->url());

        return new EmbeddedServer($process, $config);
    }

    public function execute(ExecutionRequest $request, ?callable $onEvent = null): ExecutionResult
    {
        // Ensure connected
        if ($this->client === null) {
            $server = $this->serve(ServerConfig::default());
            // Server is now running and connected
        }

        // Create session
        $session = $this->client->session->create([
            'body' => ['title' => $request->prompt]
        ]);

        // Send prompt with optional streaming
        if ($onEvent !== null) {
            return $this->executeWithSse($session->id, $request, $onEvent);
        }

        return $this->executeBlocking($session->id, $request);
    }

    private function executeWithSse(
        string $sessionId,
        ExecutionRequest $request,
        callable $onEvent
    ): ExecutionResult {
        // Subscribe to SSE events
        $events = new EventCollection();

        $sseStream = $this->client->event->subscribe();

        // Send prompt
        $this->client->session->prompt([
            'path' => ['id' => $sessionId],
            'body' => [
                'model' => $request->model?->toArray(),
                'parts' => [['type' => 'text', 'text' => $request->prompt]]
            ]
        ]);

        // Process SSE stream
        foreach ($sseStream->stream as $event) {
            // Convert to universal event
            $universalEvent = StreamEvent::fromArray('opencode', $event);
            $events->add($universalEvent);
            $onEvent($universalEvent);

            // Check for completion
            if ($event->type === 'done') {
                break;
            }
        }

        return $this->buildResult($sessionId, $events);
    }

    public function supports(AgentFeature $feature): bool
    {
        return match ($feature) {
            AgentFeature::Streaming => true,
            AgentFeature::Sandboxing => true,
            AgentFeature::SessionManagement => true,
            AgentFeature::ImageInput => true,
            AgentFeature::Subagents => true,
            AgentFeature::HttpServer => true,
        };
    }
}
```

### 4. Fluent Builder API

```php
namespace Cognesy\CodeAgent;

/**
 * Fluent builder for code agent execution
 */
final class CodeAgentBuilder
{
    private function __construct(
        private CodeAgent $agent,
        private ExecutionRequest $request,
    ) {}

    public static function using(CodeAgent|string $agent): self
    {
        $agentInstance = is_string($agent)
            ? CodeAgentFactory::create($agent)
            : $agent;

        return new self(
            $agentInstance,
            new ExecutionRequest(prompt: '')
        );
    }

    public function prompt(string $prompt): self
    {
        return $this->with(prompt: $prompt);
    }

    public function format(OutputFormat $format): self
    {
        return $this->with(format: $format);
    }

    public function security(SecurityPolicy $policy): self
    {
        return $this->with(security: $policy);
    }

    public function sandbox(SandboxMode $mode, ApprovalMode $approvals = ApprovalMode::Auto): self
    {
        $policy = new SecurityPolicy(
            sandbox: $mode,
            approvals: $approvals,
        );
        return $this->with(security: $policy);
    }

    public function model(string $model): self
    {
        return $this->with(model: ModelOverride::parse($model));
    }

    public function maxTurns(int $turns): self
    {
        return $this->with(maxTurns: $turns);
    }

    public function withStreaming(): self
    {
        return $this->with(
            format: OutputFormat::JsonLines,
            includePartialMessages: true
        );
    }

    public function resumeSession(string $sessionId): self
    {
        return $this->with(resume: SessionResume::id($sessionId));
    }

    public function continueLastSession(): self
    {
        return $this->with(resume: SessionResume::last());
    }

    /**
     * Execute without streaming (blocking)
     */
    public function execute(): ExecutionResult
    {
        return $this->agent->execute($this->request);
    }

    /**
     * Execute with streaming callback
     */
    public function stream(callable $onEvent): ExecutionResult
    {
        return $this->agent->execute($this->request, $onEvent);
    }

    /**
     * Execute with typed event handlers
     */
    public function on(EventHandlers $handlers): ExecutionResult
    {
        $callback = function(StreamEvent $event) use ($handlers): void {
            match (true) {
                $event instanceof MessageEvent => $handlers->onMessage($event),
                $event instanceof ToolUseEvent => $handlers->onToolUse($event),
                $event instanceof CompletionEvent => $handlers->onCompletion($event),
                $event instanceof ErrorEvent => $handlers->onError($event),
                default => null,
            };
        };

        return $this->agent->execute($this->request, $callback);
    }

    private function with(...$changes): self
    {
        $clone = clone $this;
        $clone->request = new ExecutionRequest(...[
            ...(array)$this->request,
            ...$changes,
        ]);
        return $clone;
    }
}

/**
 * Typed event handlers
 */
final class EventHandlers
{
    public function __construct(
        private ?callable $onMessage = null,
        private ?callable $onToolUse = null,
        private ?callable $onCompletion = null,
        private ?callable $onError = null,
        private ?callable $onProgress = null,
    ) {}

    public static function create(): EventHandlersBuilder
    {
        return new EventHandlersBuilder();
    }

    public function onMessage(MessageEvent $event): void
    {
        if ($this->onMessage) {
            ($this->onMessage)($event);
        }
    }

    // ... other handlers
}
```

---

## Usage Examples

### Example 1: Simple Query (Any Engine)

```php
use Cognesy\CodeAgent\CodeAgentBuilder;
use Cognesy\CodeAgent\Domain\OutputFormat;

// Using Claude Code
$result = CodeAgentBuilder::using('claude-code')
    ->prompt('What is the capital of France?')
    ->format(OutputFormat::Text)
    ->sandbox(SandboxMode::ReadOnly)
    ->maxTurns(1)
    ->execute();

echo $result->finalResponse; // "Paris"

// Same code works with Codex
$result = CodeAgentBuilder::using('codex')
    ->prompt('What is the capital of France?')
    ->format(OutputFormat::Text)
    ->sandbox(SandboxMode::ReadOnly)
    ->maxTurns(1)
    ->execute();

// Or OpenCode
$result = CodeAgentBuilder::using('opencode')
    ->prompt('What is the capital of France?')
    ->format(OutputFormat::Text)
    ->execute();
```

### Example 2: Streaming with Typed Events

```php
use Cognesy\CodeAgent\CodeAgentBuilder;
use Cognesy\CodeAgent\Domain\EventHandlers;

$result = CodeAgentBuilder::using('claude-code')
    ->prompt('Find validation examples and explain them')
    ->withStreaming()
    ->sandbox(SandboxMode::WorkspaceWrite, ApprovalMode::Auto)
    ->on(EventHandlers::create()
        ->message(fn($event) =>
            echo "📝 {$event->message->content}\n"
        )
        ->toolUse(fn($event) =>
            echo "🔧 Calling: {$event->name}\n"
        )
        ->completion(fn($event) =>
            echo "✅ Done! Used {$event->usage->inputTokens} tokens\n"
        )
        ->error(fn($event) =>
            echo "❌ Error: {$event->error}\n"
        )
        ->build()
    )
    ->execute();
```

### Example 3: Session Continuation

```php
// First request
$result1 = CodeAgentBuilder::using('codex')
    ->prompt('Analyze the database schema')
    ->execute();

$sessionId = $result1->sessionId;

// Continue session
$result2 = CodeAgentBuilder::using('codex')
    ->resumeSession($sessionId)
    ->prompt('Now generate migration scripts')
    ->execute();
```

### Example 4: Engine-Specific Features

```php
// Claude Code with subagents
$result = CodeAgentBuilder::using('claude-code')
    ->prompt('Review codebase for security issues')
    ->agent(ClaudeAgentConfig::create()
        ->name('security-reviewer')
        ->description('Expert security auditor')
        ->systemPrompt('Focus on OWASP top 10')
        ->tools(['Read', 'Grep', 'Glob'])
    )
    ->execute();

// OpenCode with HTTP server
$opencode = CodeAgentFactory::create('opencode');
$server = $opencode->serve(ServerConfig::port(4096));

// Use HTTP API
$client = $opencode->client();
$sessions = $client->session->list();
```

### Example 5: Bulk Processing

```php
$prompts = [
    'Explain authentication flow',
    'Document API endpoints',
    'Review error handling',
];

$results = collect($prompts)->map(fn($prompt) =>
    CodeAgentBuilder::using('claude-code')
        ->prompt($prompt)
        ->sandbox(SandboxMode::ReadOnly)
        ->execute()
)->toArray();

foreach ($results as $result) {
    echo $result->finalResponse . "\n\n";
}
```

---

## Implementation Plan

### Phase 1: Core Contracts (Week 1)
- [ ] Define all interfaces (`CodeAgent`, `SupportsStreaming`, `ManagesSessions`, etc.)
- [ ] Create base DTOs (`ExecutionRequest`, `ExecutionResult`, `StreamEvent` hierarchy)
- [ ] Implement enum types (`OutputFormat`, `SandboxMode`, `ApprovalMode`, etc.)
- [ ] Create `StreamEventFactory` for engine-agnostic event parsing

### Phase 2: Claude Code Adapter (Week 2)
- [ ] Refactor existing `ClaudeCodeCli` to implement contracts
- [ ] Create `ClaudeCodeAdapter` implementing `CodeAgent` + `SupportsStreaming`
- [ ] Map `ExecutionRequest` → `ClaudeRequest`
- [ ] Map Claude events → universal `StreamEvent` DTOs
- [ ] Write comprehensive tests

### Phase 3: Codex Adapter (Week 3)
- [ ] Implement `CodexAdapter` with CLI execution
- [ ] Map requests to Codex CLI flags
- [ ] Parse Codex `--json` output to universal events
- [ ] Handle image inputs (Codex-specific)
- [ ] Session management support

### Phase 4: OpenCode Adapter (Week 4)
- [ ] Implement `OpenCodeAdapter` with HTTP client
- [ ] Server mode support (`SupportsServer`)
- [ ] SSE event streaming
- [ ] SDK-style API access
- [ ] Agent/mode configuration

### Phase 5: Fluent API & Examples (Week 5)
- [ ] Implement `CodeAgentBuilder` with fluent interface
- [ ] Create `EventHandlers` and typed callback system
- [ ] Write 10+ usage examples
- [ ] Comprehensive README
- [ ] Migration guide from old API

### Phase 6: Testing & Documentation (Week 6)
- [ ] Unit tests for all adapters
- [ ] Integration tests with real CLIs
- [ ] Performance benchmarks
- [ ] API documentation
- [ ] Gotchas and troubleshooting guide

---

## Benefits

### For Developers
1. **One API to learn** - switch engines without code changes
2. **Type safety** - autocomplete and compile-time checks
3. **Streaming abstracted** - don't worry about buffering/parsing
4. **Flexible** - use blocking or streaming based on needs

### For the Project
1. **Future-proof** - easily add new engines (Devin, Cursor, etc.)
2. **Testable** - mock any engine with standard contract
3. **Maintainable** - engine-specific code isolated to adapters
4. **Documented** - comprehensive examples and guides

### For Operations
1. **Observability** - unified event logging
2. **Resource management** - consistent timeout/limit handling
3. **Security** - standardized sandbox/approval policies
4. **Metrics** - unified usage tracking across engines

---

## Directory Structure

```
packages/code-agent/
├── src/
│   ├── Contracts/
│   │   ├── CodeAgent.php
│   │   ├── SupportsStreaming.php
│   │   ├── SupportsServer.php
│   │   └── ManagesSessions.php
│   ├── Domain/
│   │   ├── ExecutionRequest.php
│   │   ├── ExecutionResult.php
│   │   ├── SecurityPolicy.php
│   │   ├── SessionResume.php
│   │   ├── ModelOverride.php
│   │   ├── AgentCapabilities.php
│   │   ├── EventCollection.php
│   │   ├── UsageStats.php
│   │   ├── Enum/
│   │   │   ├── OutputFormat.php
│   │   │   ├── SandboxMode.php
│   │   │   ├── ApprovalMode.php
│   │   │   ├── EventType.php
│   │   │   └── ExecutionStatus.php
│   │   └── Events/
│   │       ├── StreamEvent.php
│   │       ├── MessageEvent.php
│   │       ├── ToolUseEvent.php
│   │       ├── ToolResultEvent.php
│   │       ├── CompletionEvent.php
│   │       ├── ErrorEvent.php
│   │       ├── ProgressEvent.php
│   │       └── SystemEvent.php
│   ├── Adapters/
│   │   ├── ClaudeCode/
│   │   │   ├── ClaudeCodeAdapter.php
│   │   │   ├── EventMapper.php
│   │   │   └── RequestMapper.php
│   │   ├── Codex/
│   │   │   ├── CodexAdapter.php
│   │   │   ├── EventMapper.php
│   │   │   └── CommandBuilder.php
│   │   └── OpenCode/
│   │       ├── OpenCodeAdapter.php
│   │       ├── EventMapper.php
│   │       ├── OpencodeClient.php
│   │       └── ServerConfig.php
│   ├── Builder/
│   │   ├── CodeAgentBuilder.php
│   │   ├── EventHandlers.php
│   │   └── EventHandlersBuilder.php
│   ├── Factory/
│   │   ├── CodeAgentFactory.php
│   │   └── StreamEventFactory.php
│   └── README.md
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Fixtures/
└── examples/
    ├── 01-simple-query.php
    ├── 02-streaming.php
    ├── 03-session-management.php
    ├── 04-engine-specific.php
    └── 05-bulk-processing.php
```

---

## Migration Path

### For Existing ClaudeCodeCli Users

**Before:**
```php
use Cognesy\Auxiliary\ClaudeCodeCli\...;

$request = new ClaudeRequest(
    prompt: 'Explain validation',
    outputFormat: OutputFormat::StreamJson,
    includePartialMessages: true,
);

$spec = (new ClaudeCommandBuilder())->buildHeadless($request);
$executor = SandboxCommandExecutor::default();

$execResult = $executor->executeStreaming($spec, $callback);
```

**After:**
```php
use Cognesy\CodeAgent\CodeAgentBuilder;

$result = CodeAgentBuilder::using('claude-code')
    ->prompt('Explain validation')
    ->withStreaming()
    ->stream($callback);
```

### Backward Compatibility

The existing `ClaudeCodeCli` classes remain but become internal implementation details of the adapter. Users can gradually migrate to the new API.

---

## Open Questions

1. **Naming**: `CodeAgent` vs `AgentExecutor` vs `CodeEngine`?
2. **Event granularity**: Should we map all engine-specific events or normalize to common set?
3. **Async support**: Add `executeAsync()` returning promises/futures?
4. **Multi-engine**: Support running same request across multiple engines for comparison?
5. **Caching**: Should framework provide response caching layer?

---

## Conclusion

This universal framework provides:
- ✅ **Type-safe API** across all code agents
- ✅ **Streaming abstraction** with fallback support
- ✅ **Engine flexibility** via adapter pattern
- ✅ **Developer experience** through fluent builders
- ✅ **Future-proof** architecture for new engines

The adapter pattern allows each engine to preserve its unique features while providing a consistent interface for common operations.

**Recommendation**: Implement in phases starting with Claude Code adapter (week 2), then Codex (week 3), then OpenCode (week 4), allowing us to refine the contracts based on real-world mapping challenges.
