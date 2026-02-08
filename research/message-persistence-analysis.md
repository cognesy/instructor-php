# Message Persistence Analysis: Decoupling MessageStore from AgentState

Deep investigation comparing Laravel AI, Pi-Mono, and Instructor PHP approaches to message persistence, with recommendations for database-backed storage.

---

## Executive Summary

| System | Storage | Message Identity | Branching | Compaction Tracking |
|--------|---------|------------------|-----------|---------------------|
| **Laravel AI** | 2-table relational DB | UUID per message | None | None |
| **Pi-Mono** | Append-only JSONL | 8-char hex ID + parentId chain | Full tree structure | readFiles/modifiedFiles |
| **Instructor PHP** | In-memory (JSON serialization) | None (array position only) | None | None |

**Key Finding**: Instructor PHP's MessageStore lacks message identity, making database persistence challenging. Both Laravel AI and Pi-Mono assign stable IDs to messages, enabling efficient storage, retrieval, and (in Pi-Mono's case) branching.

---

## 1. Laravel AI ConversationStore

### Architecture

```
┌─────────────────────────┐     ┌──────────────────────────────┐
│  agent_conversations    │     │  agent_conversation_messages │
├─────────────────────────┤     ├──────────────────────────────┤
│ id (UUID7)              │◄────│ conversation_id (FK)         │
│ user_id (FK)            │     │ id (UUID7)                   │
│ title                   │     │ user_id (FK)                 │
│ created_at              │     │ agent (class name)           │
│ updated_at              │     │ role (user/assistant/tool)   │
└─────────────────────────┘     │ content (text)               │
                                │ attachments (JSON)           │
                                │ tool_calls (JSON)            │
                                │ tool_results (JSON)          │
                                │ usage (JSON)                 │
                                │ meta (JSON)                  │
                                │ created_at                   │
                                └──────────────────────────────┘
```

### Key Design Choices

1. **Flat message list**: No sections, no branching - just chronological messages
2. **UUID7 for ordering**: Sortable UUIDs provide natural chronological order
3. **JSON for complex data**: Tool calls, results, usage stored as JSON blobs
4. **User-centric**: All queries scoped by user_id
5. **Simple retrieval**: `getLatestConversationMessages(id, limit)` - last N messages only

### Strengths
- Simple schema, easy to implement
- Database-native (Laravel Query Builder)
- Multi-tenant ready (user_id scoping)
- Automatic conversation titling

### Weaknesses
- No message search or filtering
- No pagination (just limit)
- No branching or checkpoints
- No compaction/summarization
- Tool data not queryable (JSON blobs)

---

## 2. Pi-Mono Session Storage

### Architecture

```
Session File (JSONL - append only)
┌─────────────────────────────────────────────────────────────────┐
│ {"type":"session","version":3,"id":"...","cwd":"..."}  // Header │
├─────────────────────────────────────────────────────────────────┤
│ {"type":"message","id":"abc1","parentId":null,...}              │
│ {"type":"message","id":"abc2","parentId":"abc1",...}            │
│ {"type":"model_change","id":"abc3","parentId":"abc2",...}       │
│ {"type":"message","id":"abc4","parentId":"abc3",...}            │
│ {"type":"compaction","id":"abc5","parentId":"abc4",...}         │
│ {"type":"branch_summary","id":"xyz1","parentId":"abc2",...}     │ ← Branch!
│ {"type":"message","id":"xyz2","parentId":"xyz1",...}            │
│ {"type":"label","id":"xyz3","parentId":"xyz2","targetId":"abc2"}│
└─────────────────────────────────────────────────────────────────┘

Tree Structure (derived from parentId chain):

     [abc1] ─── [abc2] ─── [abc3] ─── [abc4] ─── [abc5]
                   │
                   └─── [xyz1] ─── [xyz2] ─── [xyz3]
                       (branch)
```

### Entry Types

| Type | Purpose | Key Fields |
|------|---------|------------|
| `session` | File header | version, cwd, parentSession |
| `message` | User/assistant/tool messages | message (AgentMessage) |
| `model_change` | Track model switches | provider, modelId |
| `thinking_level_change` | Track reasoning depth | thinkingLevel |
| `compaction` | Context summarization | summary, firstKeptEntryId, details |
| `branch_summary` | Abandoned branch summary | fromId, summary, details |
| `custom` | Extension state (not in context) | customType, data |
| `custom_message` | Extension messages (in context) | customType, content, display |
| `label` | Checkpoints/bookmarks | targetId, label |
| `session_info` | Session metadata | name |

### Key Design Choices

1. **Append-only**: Never modify existing entries - only add new ones
2. **Tree via parentId**: Each entry points to its parent, forming a DAG
3. **8-char hex IDs**: Short, collision-checked identifiers
4. **File tracking**: CompactionDetails stores readFiles/modifiedFiles
5. **Branch summaries**: LLM-generated summaries when switching branches
6. **Version migrations**: v1→v2→v3 auto-migration on load

### Strengths
- Full history preservation (append-only)
- Non-linear conversation trees
- Checkpoints via labels
- File operation tracking through compaction
- Extension-friendly (custom entries)
- No database dependency

### Weaknesses
- File-based (not queryable at scale)
- Requires full file scan to build tree
- No multi-user support built-in
- Complex navigation logic

---

## 3. Instructor PHP Current State

### Architecture

```
AgentState (readonly, immutable)
├── agentId: string
├── parentAgentId: ?string
├── createdAt: DateTimeImmutable
├── updatedAt: DateTimeImmutable
├── context: AgentContext
│   ├── store: MessageStore
│   │   ├── sections: Sections
│   │   │   ├── "messages" (main conversation)
│   │   │   ├── "buffer" (pending summarization)
│   │   │   ├── "summary" (compacted history)
│   │   │   └── "execution_buffer" (transient step output)
│   │   └── parameters: Metadata
│   ├── metadata: Metadata
│   ├── systemPrompt: string
│   └── responseFormat: ResponseFormat
└── execution: ?ExecutionState (null between executions)
```

### Message Structure

```php
final readonly class Message {
    protected string $role;        // user, assistant, system, tool
    protected string $name;        // Optional
    protected Content $content;    // ContentParts (text, image, audio, file)
    protected Metadata $metadata;  // Custom data (not sent to LLM)
}
// NO ID, NO TIMESTAMP in Message class!
```

### Key Design Choices

1. **Multi-section MessageStore**: Named sections for different message types
2. **Fully immutable**: All operations return new instances
3. **Rich content model**: Multimodal support (text, images, audio, files)
4. **Per-message metadata**: `_metadata` field for custom data
5. **JSON serialization**: `toArray()`/`fromArray()` for persistence

### Current Limitations

| Limitation | Impact |
|------------|--------|
| No message IDs | Cannot reference specific messages |
| No timestamps | Cannot track message timing |
| Array position = identity | Lost during filtering/compaction |
| Tight coupling | MessageStore embedded in AgentContext in AgentState |
| No branching | Linear conversation only |
| No file tracking | Lost context after compaction |

---

## 4. Is Database Persistence a Good Idea?

### Arguments For

1. **Queryability**: Search messages, filter by role, find by content
2. **Scalability**: Handle long conversations without loading all into memory
3. **Durability**: Database transactions, backups, replication
4. **Multi-user**: Easy user scoping and access control
5. **Analytics**: Track usage, patterns, model performance
6. **Compliance**: Audit trails, data retention policies

### Arguments Against

1. **Complexity**: Additional infrastructure (database, migrations, ORM)
2. **Latency**: Database round-trips vs in-memory operations
3. **Consistency**: Managing transactions during multi-step agent execution
4. **Coupling**: Tight binding to specific database technology
5. **Overhead**: Simple use cases don't need persistence

### Recommendation

**Hybrid approach**: Keep in-memory MessageStore for execution, add optional persistence layer that syncs to database. This preserves:
- Fast in-memory operations during execution
- Optional durability for production use cases
- Framework-agnostic core

---

## 5. Proposed Design: Decoupling MessageStore

### Design Principles

1. **Messages need identity**: Add ID and timestamp to Message class
2. **Sections need identity**: Track section membership
3. **Optional persistence**: `MessageStoreInterface` with in-memory and database implementations
4. **Event sourcing**: Track changes, not just current state (like Pi-Mono)
5. **Backward compatible**: Existing `toArray()`/`fromArray()` must still work

### Proposed Message Changes

```php
final readonly class Message {
    protected ?string $id;              // NEW: UUID or null (assigned on persist)
    protected ?DateTimeImmutable $createdAt;  // NEW: Timestamp
    protected string $role;
    protected string $name;
    protected Content $content;
    protected Metadata $metadata;
    protected ?string $parentId;        // NEW: For branching (optional)
}
```

### Proposed Section Changes

```php
final readonly class Section {
    public string $name;
    public Messages $messages;
    public ?string $id;                 // NEW: Section identity
    public ?DateTimeImmutable $createdAt;
    public ?DateTimeImmutable $updatedAt;
}
```

### Proposed Persistence Interface

```php
interface MessagePersistence {
    // Session management
    public function createSession(string $agentId, ?string $parentAgentId = null): string;
    public function loadSession(string $sessionId): MessageStore;

    // Message operations
    public function appendMessage(string $sessionId, string $section, Message $message): Message;
    public function getMessages(string $sessionId, string $section, ?int $limit = null): Messages;

    // Section operations
    public function clearSection(string $sessionId, string $section): void;
    public function moveMessages(string $sessionId, string $fromSection, string $toSection): void;

    // Query operations (optional)
    public function searchMessages(string $sessionId, string $query): Messages;
    public function getMessageById(string $messageId): ?Message;

    // Branching (optional, Pi-Mono style)
    public function fork(string $sessionId, string $fromMessageId): string;
    public function getTree(string $sessionId): SessionTree;
}
```

### Implementation Options

#### Option A: Database Tables (Laravel AI style)

```sql
CREATE TABLE agent_sessions (
    id UUID PRIMARY KEY,
    agent_id VARCHAR(255) NOT NULL,
    parent_agent_id VARCHAR(255),
    parent_session_id UUID REFERENCES agent_sessions(id),
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    metadata JSONB
);

CREATE TABLE agent_sections (
    id UUID PRIMARY KEY,
    session_id UUID NOT NULL REFERENCES agent_sessions(id),
    name VARCHAR(255) NOT NULL,
    parameters JSONB,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    UNIQUE(session_id, name)
);

CREATE TABLE agent_messages (
    id UUID PRIMARY KEY,
    section_id UUID NOT NULL REFERENCES agent_sections(id),
    parent_id UUID REFERENCES agent_messages(id),  -- For branching
    position INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    name VARCHAR(255),
    content JSONB NOT NULL,  -- ContentParts as JSON
    metadata JSONB,
    created_at TIMESTAMP NOT NULL,
    INDEX(section_id, position),
    INDEX(section_id, created_at)
);

-- Optional: Denormalized tool tracking
CREATE TABLE agent_tool_calls (
    id UUID PRIMARY KEY,
    message_id UUID NOT NULL REFERENCES agent_messages(id),
    tool_name VARCHAR(255) NOT NULL,
    arguments JSONB,
    result JSONB,
    is_error BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL,
    INDEX(message_id),
    INDEX(tool_name)
);

-- Optional: File operation tracking (Pi-Mono style)
CREATE TABLE agent_file_operations (
    id UUID PRIMARY KEY,
    session_id UUID NOT NULL REFERENCES agent_sessions(id),
    message_id UUID REFERENCES agent_messages(id),
    operation VARCHAR(50) NOT NULL,  -- read, write, edit, delete
    file_path TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL,
    INDEX(session_id, file_path)
);
```

#### Option B: JSONL Files (Pi-Mono style)

```php
class JsonlMessagePersistence implements MessagePersistence {
    private string $basePath;

    public function appendMessage(string $sessionId, string $section, Message $message): Message {
        $entry = [
            'type' => 'message',
            'id' => $message->id() ?? Uuid::uuid7()->toString(),
            'parentId' => $this->getLeafId($sessionId),
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'section' => $section,
            'message' => $message->toArray(),
        ];

        file_put_contents(
            $this->sessionPath($sessionId),
            json_encode($entry) . "\n",
            FILE_APPEND
        );

        return $message->withId($entry['id'])->withCreatedAt(new DateTimeImmutable($entry['timestamp']));
    }
}
```

#### Option C: Hybrid (In-Memory + Sync)

```php
class SyncedMessageStore {
    private MessageStore $store;           // In-memory (fast)
    private ?MessagePersistence $persistence;  // Optional database

    public function appendMessage(string $section, Message $message): self {
        // 1. Update in-memory store
        $newStore = $this->store->section($section)->appendMessage($message);

        // 2. Optionally sync to database
        if ($this->persistence) {
            $message = $this->persistence->appendMessage(
                $this->sessionId,
                $section,
                $message
            );
        }

        return new self($newStore, $this->persistence);
    }
}
```

---

## 6. Migration Path

### Phase 1: Add Identity to Messages (Non-Breaking)

```php
// Message gains optional id/timestamp
final readonly class Message {
    protected ?string $id = null;
    protected ?DateTimeImmutable $createdAt = null;
    // ... existing fields

    public function withId(string $id): self { ... }
    public function withCreatedAt(DateTimeImmutable $createdAt): self { ... }
}

// toArray() includes id/createdAt if present
public function toArray(): array {
    return array_filter([
        'id' => $this->id,
        'created_at' => $this->createdAt?->format(DATE_ATOM),
        'role' => $this->role,
        // ...
    ]);
}
```

### Phase 2: Add MessagePersistence Interface

```php
interface MessagePersistence {
    public function appendMessage(string $sessionId, string $section, Message $message): Message;
    public function loadSession(string $sessionId): MessageStore;
    // ...
}

// In-memory implementation (default, current behavior)
class InMemoryMessagePersistence implements MessagePersistence {
    private array $sessions = [];
    // ...
}
```

### Phase 3: Database Implementation (Optional Package)

```php
// New package: cognesy/agents-persistence
class DatabaseMessagePersistence implements MessagePersistence {
    public function __construct(
        private PDO|Connection $connection,
        private string $tablePrefix = 'agent_'
    ) {}
    // ...
}
```

### Phase 4: Add Branching Support (Optional)

```php
// Pi-Mono style branching
interface BranchableMessagePersistence extends MessagePersistence {
    public function fork(string $sessionId, string $fromMessageId): string;
    public function navigateTo(string $sessionId, string $messageId): void;
    public function getTree(string $sessionId): SessionTree;
    public function addLabel(string $sessionId, string $messageId, string $label): void;
}
```

---

## 7. Comparison Matrix

| Feature | Laravel AI | Pi-Mono | Instructor PHP (Current) | Instructor PHP (Proposed) |
|---------|------------|---------|--------------------------|---------------------------|
| **Storage** | PostgreSQL/MySQL | JSONL files | In-memory JSON | Pluggable (memory/DB/JSONL) |
| **Message IDs** | UUID7 | 8-char hex | None | UUID (optional) |
| **Timestamps** | Per-message | Per-entry | AgentState only | Per-message (optional) |
| **Sections** | None | None (entry types) | 4 named sections | Named sections with IDs |
| **Branching** | None | Full tree | None | Optional via interface |
| **Compaction** | Message limit | Summary + file tracking | Token-based summary | Token-based + file tracking |
| **Querying** | Last N only | Full file scan | In-memory only | Pluggable (SQL/search) |
| **Multi-user** | Built-in | None | None | Via persistence layer |
| **Tool tracking** | JSON blob | In message | In metadata | Optional dedicated table |
| **File tracking** | None | CompactionDetails | None | Optional (Pi-Mono style) |

---

## 8. Recommendations

### Immediate (Low Effort)

1. **Add optional `id` and `createdAt` to Message class**
   - Backward compatible (null by default)
   - Enables future persistence

2. **Add `MessagePersistence` interface**
   - `InMemoryMessagePersistence` as default
   - No behavioral change for existing code

### Medium Term (Moderate Effort)

3. **Create `cognesy/agents-persistence` package**
   - Database implementation for common DBs
   - Migrations for schema
   - Doctrine/Eloquent adapters

4. **Add file operation tracking**
   - Track `readFiles`/`modifiedFiles` in compaction metadata
   - Inspired by Pi-Mono's `CompactionDetails`

### Long Term (High Effort)

5. **Branching support**
   - `parentId` on messages
   - Tree navigation API
   - Branch summaries on navigation

6. **Query capabilities**
   - Full-text search on content
   - Filter by role, tool, time range
   - Pagination with cursors

---

## 9. Conclusion

**Is decoupling MessageStore for database persistence a good idea?**

**Yes, with caveats:**

1. **Do add message identity** - This is foundational and non-breaking
2. **Do create persistence interface** - Enables choice without forcing database
3. **Don't force database** - Keep in-memory as valid option
4. **Consider Pi-Mono's branching** - Valuable for exploration workflows
5. **Consider file tracking** - Improves context recovery after compaction

The key insight from comparing these systems is that **message identity is fundamental** to any persistence strategy. Pi-Mono's append-only + parentId approach is elegant for branching, while Laravel AI's simplicity works for linear conversations. Instructor PHP can support both by making identity optional and persistence pluggable.

---

## Appendix: Key File References

### Laravel AI
- `src/Contracts/ConversationStore.php` - Interface
- `src/Storage/DatabaseConversationStore.php` - Implementation
- `database/migrations/2026_01_11_000001_create_agent_conversations_table.php` - Schema
- `src/Concerns/RemembersConversations.php` - Trait integration

### Pi-Mono
- `packages/coding-agent/src/core/session-manager.ts` - SessionManager, entry types
- `packages/coding-agent/src/core/agent-session.ts` - fork(), navigateTree()
- `packages/coding-agent/src/core/compaction/branch-summarization.ts` - Branch summaries
- `packages/coding-agent/src/core/compaction/compaction.ts` - Compaction with file tracking

### Instructor PHP
- `packages/messages/src/MessageStore/MessageStore.php` - Current implementation
- `packages/messages/src/Message.php` - Message class (needs ID)
- `packages/agents/src/Core/Data/AgentState.php` - Serialization
- `packages/agents/src/Core/Context/AgentContext.php` - Section constants
