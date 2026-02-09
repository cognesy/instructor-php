# Lessons from Pi-Mono for Instructor PHP Agents

Based on detailed comparison of Pi-Mono and Instructor PHP agent architectures.

---

## Instructor PHP Strengths

### Architecture
1. **Fully immutable state model** - `readonly` classes with `with*()` methods provide safety, predictability, and easy debugging
2. **Clean session/execution separation** - `AgentState` holds persistent session data, `ExecutionState` holds transient execution data (nullable between runs)
3. **Generator-based iteration** - `iterate()` yields state per step, enabling real-time observation and external control

### Extensibility
4. **Capabilities system** - Composable bundles that install tools + hooks + config; no equivalent in Pi-Mono
5. **Three-level tool metadata** - Browse (lightweight) → FullSpec (docs) → Instance (execute); enables efficient discovery
6. **Full-text tool search** - `ToolRegistry->search()` across name/description/namespace/tags
7. **Hook priority system** - Explicit numeric priorities for deterministic ordering

### Context Management
8. **Multi-section MessageStore** - Named sections (`messages`, `buffer`, `summary`, `execution_buffer`) with ordered compilation for inference
9. **Rich Message hierarchy** - `Message` → `Content` → `ContentParts` → `ContentPart` supporting multimodal (text, images, audio, files)
10. **Two-phase compaction** - Proactive token management: move-to-buffer at threshold, summarize-buffer at higher threshold
11. **Per-message metadata** - Both context-level and message-level metadata support

### Error Handling
12. **ErrorList aggregation** - Errors collected across steps and execution, not just single error per message

### Skills
13. **On-demand skill loading** - Agent requests skills via `LoadSkillTool`, keeping context leaner than prompt-injection

---

## Instructor PHP Weaknesses / Gaps

### Missing from Pi-Mono

| Gap | Impact | Pi-Mono Solution |
|-----|--------|------------------|
| **No async message injection** | Cannot interrupt running agent with external input | `agent.steer()` queue for mid-execution, `agent.followUp()` for post-turn |
| **No streaming tool updates** | Long-running tools appear frozen; no progress feedback | `onUpdate` callback during tool execution |
| **No AbortSignal propagation** | No clean cancellation mechanism throughout stack | `AbortSignal` passed to tools, LLM calls, everywhere |
| **Linear sessions only** | Cannot branch, fork, or navigate session history | Session tree with `fork()`, `navigateTree()`, branch summaries |
| **No session checkpoints** | Cannot bookmark points in conversation for later return | `LabelEntry` for user-defined checkpoints |
| **Single skill directory** | Less flexible skill organization | Multi-source: `~/.pi/agent/skills/`, `$CWD/.pi/skills/`, explicit paths |
| **No file operation tracking in compaction** | Context recovery after compaction loses file awareness | `CompactionDetails` tracks `readFiles`, `modifiedFiles` |
| **No extension UI context** | Extensions cannot prompt user for input | `ExtensionUIContext` with `select()`, `confirm()`, `input()`, `notify()`, `setStatus()`, `setWidget()` |
| **No thinking level control** | Cannot adjust reasoning depth per-request | `thinkingLevel` setting with budgets |

---

## High-Value Pi-Mono Features to Incorporate

### Priority 1: Critical for Interactive Agents

#### 1.1 Async Message Injection (Steering/Follow-up Queues)

**What it is**: External code can inject messages while agent is running.

**Pi-Mono implementation**:
```typescript
// Steering: interrupts current turn, skips remaining tools
agent.steer({ role: "user", content: "Stop and focus on X" });

// Follow-up: queued for after current turn completes
agent.followUp({ role: "user", content: "Also check Y" });
```

**Why valuable**:
- User can redirect agent mid-task
- Background processes can inject context
- Enables "human-in-the-loop" patterns

**Instructor implementation approach**:
```php
interface CanInjectMessages {
    public function steer(Message $message): void;  // Interrupt current step
    public function followUp(Message $message): void;  // Queue for next step
    public function clearQueues(): void;
}

// AgentLoop checks queues after tool execution
// Steering messages skip remaining tools in current step
// Follow-up messages processed when agent would otherwise stop
```

#### 1.2 Streaming Tool Updates

**What it is**: Tools can emit progress updates during execution.

**Pi-Mono implementation**:
```typescript
interface AgentTool {
    execute(
        toolCallId: string,
        params: TParams,
        signal?: AbortSignal,
        onUpdate?: (update: ToolUpdate) => void,  // Progress callback
    ): Promise<AgentToolResult>;
}

// Tool implementation
async execute(id, params, signal, onUpdate) {
    onUpdate?.({ progress: 0.1, message: "Starting..." });
    // ... work ...
    onUpdate?.({ progress: 0.5, message: "Halfway done..." });
    // ... more work ...
    return result;
}
```

**Why valuable**:
- Long-running tools (bash, file operations, API calls) show progress
- Better UX - user sees activity, not frozen state
- Can display partial results during execution

**Instructor implementation approach**:
```php
interface ToolInterface {
    public function __invoke(
        mixed ...$args,
        ?callable $onProgress = null,  // fn(float $progress, string $message)
    ): Result;
}

// ToolExecutor passes callback, emits events
// AgentEventEmitter broadcasts ToolProgressUpdated events
```

#### 1.3 Cancellation Signal Propagation

**What it is**: Clean abort mechanism that propagates through entire stack.

**Pi-Mono implementation**:
- `AbortSignal` passed to agent loop, tools, LLM calls
- Tools can check `signal.aborted` and exit early
- `stopReason: "aborted"` in final state

**Why valuable**:
- Clean cancellation without resource leaks
- Tools can checkpoint and clean up
- Consistent abort handling everywhere

**Instructor implementation approach**:
```php
// Add CancellationToken to execution context
class CancellationToken {
    private bool $cancelled = false;
    public function cancel(): void { $this->cancelled = true; }
    public function isCancelled(): bool { return $this->cancelled; }
    public function throwIfCancelled(): void {
        if ($this->cancelled) throw new CancelledException();
    }
}

// Pass to tools, check in loop
interface ToolInterface {
    public function __invoke(
        mixed ...$args,
        ?CancellationToken $cancellation = null,
    ): Result;
}
```

### Priority 2: Important for Production Use

#### 2.1 Session Tree with Branching ✅ IMPLEMENTED

**What it is**: Non-linear session history with fork, branch, navigate.

**Pi-Mono implementation**:
- Sessions stored as JSONL with typed entries
- `fork(entryId)` creates new session branching from checkpoint
- `navigateTree(targetId)` switches to different branch
- Branch summaries preserve context when switching

**Why valuable**:
- Explore alternative approaches without losing history
- Return to earlier state and try different direction
- Compare different solution paths

**Instructor PHP implementation** (packages/messages):

1. **Message identity** - `Message::$id` (immutable UUID), `Message::$createdAt`, `Message::$parentId` for tree structure

2. **Storage contract** - `CanStoreMessages` interface:
```php
interface CanStoreMessages {
    // Session operations
    public function createSession(?string $sessionId = null): string;
    public function load(string $sessionId): MessageStore;
    public function save(string $sessionId, MessageStore $store): StoreMessagesResult;

    // Branching operations
    public function getLeafId(string $sessionId): ?string;
    public function navigateTo(string $sessionId, string $messageId): void;
    public function getPath(string $sessionId, ?string $toMessageId = null): Messages;
    public function fork(string $sessionId, string $fromMessageId): string;

    // Labels (checkpoints)
    public function addLabel(string $sessionId, string $messageId, string $label): void;
    public function getLabels(string $sessionId): array;
}
```

3. **Storage implementations**:
   - `InMemoryStorage` - for testing/single-request
   - `JsonlStorage` - Pi-Mono style append-only JSONL with typed entries

4. **MessageStore integration**:
```php
$store = MessageStore::fromStorage($storage, $sessionId);
$result = $store->toStorage($storage, $sessionId);  // Returns StoreMessagesResult
```

**Files**:
- `packages/messages/src/Message.php` - id, createdAt, parentId fields
- `packages/messages/src/MessageStore/Contracts/CanStoreMessages.php`
- `packages/messages/src/MessageStore/Storage/InMemoryStorage.php`
- `packages/messages/src/MessageStore/Storage/JsonlStorage.php`
- `packages/messages/src/MessageStore/Data/StoreMessagesResult.php`

#### 2.2 File Operation Tracking in Compaction

**What it is**: Track which files were read/modified, preserve through compaction.

**Pi-Mono implementation**:
```typescript
interface CompactionDetails {
    readFiles: string[];      // Files read during compacted period
    modifiedFiles: string[];  // Files modified during compacted period
}
```

**Why valuable**:
- After compaction, agent still knows what files it worked with
- Can re-read files if needed for context recovery
- Better continuity in long sessions

**Instructor implementation approach**:
```php
// Track in hook, store in compaction metadata
class FileOperationTrackingHook implements HookInterface {
    private array $readFiles = [];
    private array $modifiedFiles = [];

    public function handle(HookContext $context): HookContext {
        // Extract from tool executions
        // Store in context metadata
    }
}

// Include in summary section metadata
$context->metadata()->withKeyValue('compaction_files', [
    'read' => $this->readFiles,
    'modified' => $this->modifiedFiles,
]);
```

#### 2.3 Multi-Source Skill Discovery

**What it is**: Load skills from multiple locations with priority.

**Pi-Mono implementation**:
- User skills: `~/.pi/agent/skills/`
- Project skills: `$CWD/.pi/skills/`
- Explicit paths via config
- Collision detection with winner/loser tracking

**Why valuable**:
- User-specific skills available everywhere
- Project-specific skills override user defaults
- Explicit paths for special cases

**Instructor implementation approach**:
```php
class SkillLibrary {
    private array $sources = [];  // [source => path]

    public static function withSources(array $sources): self;

    // Priority: explicit > project > user
    // Track source in Skill metadata
    // Detect and report collisions
}
```

### Priority 3: Nice to Have

#### 3.1 Thinking Level Control

**What it is**: Adjust LLM reasoning depth per-request.

**Pi-Mono implementation**:
```typescript
type ThinkingLevel = "off" | "minimal" | "low" | "medium" | "high" | "xhigh";

agent.setThinkingLevel("high");  // More reasoning for complex tasks
```

**Instructor implementation approach**:
```php
// Add to AgentContext or ExecutionState
enum ThinkingLevel: string {
    case Off = 'off';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}

// Pass to inference request
$context->withThinkingLevel(ThinkingLevel::High);
```

#### 3.2 Session Labels/Checkpoints

**What it is**: User-defined bookmarks in session history.

**Pi-Mono implementation**:
```typescript
sessionManager.addLabel("before-refactor");
// Later...
sessionManager.navigateTo("before-refactor");
```

**Why valuable**:
- Mark important points for later return
- Named checkpoints more intuitive than entry IDs
- Useful for long sessions with many turns

#### 3.3 Extension UI Context

**What it is**: Extensions can prompt user for input.

**Pi-Mono implementation**:
```typescript
interface ExtensionUIContext {
    select(title: string, options: string[]): Promise<string | undefined>;
    confirm(title: string, message: string): Promise<boolean>;
    input(title: string, placeholder?: string): Promise<string | undefined>;
    notify(message: string, type?: "info" | "warning" | "error"): void;
    setStatus(key: string, text: string | undefined): void;
    setWidget(key: string, content: string[] | undefined): void;
}
```

**Why valuable**:
- Extensions can gather input without tool calls
- Better UX for interactive workflows
- Status/widget display for real-time feedback

---

## Implementation Roadmap

### Phase 1: Core Loop Enhancements
1. ⬜ Add `CancellationToken` to execution context
2. ⬜ Add `onProgress` callback to `ToolInterface`
3. ⬜ Add steering/follow-up message queues to `AgentLoop`

### Phase 2: Session Management ✅ COMPLETE
4. ✅ Implement JSONL session storage with typed entries - `JsonlStorage`
5. ✅ Add session forking and navigation - `fork()`, `navigateTo()`, `getPath()`
6. ✅ Add label/checkpoint support - `addLabel()`, `getLabels()`
7. ✅ Add Message identity - `Message::$id`, `Message::$createdAt`, `Message::$parentId`
8. ✅ Add storage result reporting - `StoreMessagesResult`

### Phase 3: Context Improvements
9. ⬜ Add file operation tracking to compaction
10. ⬜ Implement multi-source skill discovery
11. ⬜ Add thinking level control

### Phase 4: Extension Support
12. ⬜ Add UI context interface for interactive capabilities
13. ⬜ Consider extension event system expansion (more granular hooks)

---

## Summary

**Keep from Instructor PHP**:
- Immutable state model
- Capabilities system
- Three-level tool discovery
- Multi-section MessageStore
- Hook priority system
- On-demand skill loading

**Add from Pi-Mono**:
- ✅ Session tree with branching - **IMPLEMENTED** via `Message::$parentId`, `CanStoreMessages`, `JsonlStorage`
- ✅ Session labels/checkpoints - **IMPLEMENTED** via `addLabel()` / `getLabels()`
- ✅ JSONL session storage - **IMPLEMENTED** via `JsonlStorage`
- ⬜ Async message injection (steering/follow-up)
- ⬜ Streaming tool updates
- ⬜ Cancellation signal propagation
- ⬜ File operation tracking in compaction
- ⬜ Multi-source skill discovery
- ⬜ Thinking level control

**Result**: Instructor PHP now has Pi-Mono's session tree and branching capabilities while retaining its cleaner architecture. Remaining items focus on interactive features (steering, streaming, cancellation) and context improvements (file tracking, skill sources, thinking levels).
