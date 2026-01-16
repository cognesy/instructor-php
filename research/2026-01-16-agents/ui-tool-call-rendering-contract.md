# Minimal UI Rendering Contract: Tool Calls + Tool Results

## Purpose
Define the smallest stable payload the UI needs to render tool calls and tool results alongside user/assistant messages without leaking internal agent details.

---

## Message Schema (UI-facing)

### TypeScript Definition
```typescript
interface Message {
  id: string;
  role: 'user' | 'assistant' | 'tool' | 'status';
  content: string;
  timestamp: string;
  metadata?: MessageMetadata;
}

interface MessageMetadata {
  // For assistant messages with tool calls
  tool_calls?: ToolCallInfo[];

  // For tool result messages
  tool_call_id?: string;
  tool_name?: string;
  tool_result?: unknown;
  tool_error?: string;
  tool_duration_ms?: number;

  // For status messages
  status_type?: 'thinking' | 'tool_call' | 'tool_result' | 'error' | 'info' | 'continuation';

  // For continuation status
  continuation?: ContinuationInfo;
}

interface ToolCallInfo {
  id?: string;
  name: string;
  args?: Record<string, unknown>;
  args_summary?: string;  // Human-readable summary
}

interface ContinuationInfo {
  should_continue: boolean;
  stop_reason: string;
  resolved_by: string;
}
```

### JSON Example
```json
{
  "id": "msg_001",
  "role": "assistant",
  "content": "",
  "timestamp": "2026-01-16T10:05:00Z",
  "metadata": {
    "tool_calls": [
      {
        "id": "call_abc123",
        "name": "SearchEntities",
        "args_summary": "query: 'dbplus', types: ['program']"
      }
    ]
  }
}
```

---

## Rendering Rules (Minimal)

### 1) User
- Render as right-aligned bubble.
- Ignore tool metadata.
- Style: Primary color background, white text.

### 2) Assistant (no tool_calls)
- Render as standard assistant message (left-aligned).
- Support markdown formatting.
- Style: Light gray background.

### 3) Assistant (tool_calls present)
- **Do NOT render raw content** (it may contain tool args JSON due to Issue #1).
- Render a compact "Tool call" indicator block.
- Show tool name(s) from `metadata.tool_calls[].name`.
- Show `args_summary` if available (collapsed by default).
- **Important**: If `content` looks like JSON and `tool_calls` is present, ignore content entirely.

```tsx
// Pseudo-code
function shouldRenderContent(message: Message): boolean {
  if (!message.metadata?.tool_calls?.length) {
    return true;
  }
  // Skip content if it looks like tool args JSON
  const content = message.content.trim();
  return content !== '' && !content.startsWith('{') && !content.startsWith('[');
}
```

### 4) Tool
- Render as a result block associated with `tool_call_id` or `tool_name`.
- If `tool_error` present, style as error (red border, error icon).
- If `tool_result` present, allow JSON formatting (collapsed by default).
- Show duration if `tool_duration_ms` is available.
- Style: Indented under the tool call, monospace font for results.

### 5) Status
- Render as inline status message (thinking spinner, error banner, info toast).
- Do not insert into conversation history unless needed for audit.
- Continuation status (`status_type: 'continuation'`) can show "Agent stopped: {reason}".

---

## Event-to-Message Mapping

### Tool call started (`agent.tool.started`)
Options:
1. Create a `status` message with `status_type: 'tool_call'` (ephemeral, removed on completion).
2. Create a `tool` placeholder message that will be updated on completion.

Recommended: Option 1 for simplicity, update to `tool` message on completion.

### Tool call completed (`agent.tool.completed`)
- Create or update a `tool` message:
  ```json
  {
    "id": "tool_abc123",
    "role": "tool",
    "content": "Found 3 entities",
    "timestamp": "...",
    "metadata": {
      "tool_call_id": "call_abc123",
      "tool_name": "SearchEntities",
      "tool_result": { ... },
      "tool_duration_ms": 250
    }
  }
  ```
- If error, set `tool_error` instead of `tool_result`.

### Step completed (`agent.step.completed`)
- If assistant output is natural language text, store as `role: 'assistant'`.
- If assistant output contains `tool_calls` metadata and no natural language, **skip** the assistant message and rely on tool entries.
- Apply `shouldRenderContent()` filter (see above).

### Continuation evaluated (`agent.continuation`)
- Optionally create a `status` message with `status_type: 'continuation'`:
  ```json
  {
    "id": "status_cont_001",
    "role": "status",
    "content": "Agent stopped: steps_limit",
    "timestamp": "...",
    "metadata": {
      "status_type": "continuation",
      "continuation": {
        "should_continue": false,
        "stop_reason": "steps_limit",
        "resolved_by": "StepsLimit"
      }
    }
  }
  ```

---

## Required Backend Fields

| Field | Required For | Description |
|-------|--------------|-------------|
| `tool_calls` | Assistant messages | Array of tool call info when tools are invoked |
| `tool_call_id` | Tool messages | Links result to the original call |
| `tool_name` | Tool messages | Tool that produced the result |
| `tool_result` | Tool messages | The actual result payload (optional) |
| `tool_error` | Tool messages | Error message if tool failed |
| `args_summary` | Tool calls | Human-readable args summary |

---

## State Reconciliation Strategy

The UI should:
1. **Stream events** for real-time updates during execution.
2. **Fetch final state** on completion for consistency.
3. **Merge** streamed messages with persisted messages on completion.

### Reconciliation Rules
- On `agent.status` with `status: 'completed'` or `status: 'failed'`:
  1. Fetch canonical message history from backend.
  2. Replace streamed messages with persisted versions.
  3. Remove ephemeral `status` messages (except errors).
- Use message `id` or `tool_call_id` for matching.

---

## Error Handling

### Tool Errors
```tsx
function renderToolMessage(message: Message) {
  if (message.metadata?.tool_error) {
    return (
      <ToolErrorBlock
        toolName={message.metadata.tool_name}
        error={message.metadata.tool_error}
        duration={message.metadata.tool_duration_ms}
      />
    );
  }
  // Normal result rendering...
}
```

### Agent Errors
- On `agent.status` with `status: 'failed'`:
  - Show error banner with `error_message`.
  - Keep conversation history visible.
  - Optionally show continuation trace if available.

---

## Optional UI Enhancements

### 1) Tool Call Grouping
Group tool call + result under a single expandable panel:
```
▼ SearchEntities (250ms)
  Args: query='dbplus', types=['program']
  Result: Found 3 entities
```

### 2) Timing Display
Show timing and tokens as small metadata:
```
Assistant · 1.2s · 650 tokens
```

### 3) Details Toggle
Provide a "Show details" toggle for:
- Full tool args JSON
- Full tool result JSON
- Continuation trace evaluations

### 4) Retry Indicator
If tool failed and agent is retrying:
```
⟳ SearchEntities - Retrying (2/3)
```

---

## Frontend Type Definitions (Full)

```typescript
// messages.ts
export type MessageRole = 'user' | 'assistant' | 'tool' | 'status';

export type StatusType =
  | 'thinking'
  | 'tool_call'
  | 'tool_result'
  | 'error'
  | 'info'
  | 'continuation';

export interface ToolCallInfo {
  id?: string;
  name: string;
  args?: Record<string, unknown>;
  args_summary?: string;
}

export interface ContinuationInfo {
  should_continue: boolean;
  stop_reason: string;
  resolved_by: string;
}

export interface MessageMetadata {
  tool_calls?: ToolCallInfo[];
  tool_call_id?: string;
  tool_name?: string;
  tool_result?: unknown;
  tool_error?: string;
  tool_duration_ms?: number;
  status_type?: StatusType;
  continuation?: ContinuationInfo;
}

export interface Message {
  id: string;
  role: MessageRole;
  content: string;
  timestamp: string;
  metadata?: MessageMetadata;
}

// Helper functions
export function isToolCallMessage(msg: Message): boolean {
  return msg.role === 'assistant' && !!msg.metadata?.tool_calls?.length;
}

export function isToolResultMessage(msg: Message): boolean {
  return msg.role === 'tool';
}

export function hasToolError(msg: Message): boolean {
  return msg.role === 'tool' && !!msg.metadata?.tool_error;
}

export function shouldRenderAssistantContent(msg: Message): boolean {
  if (msg.role !== 'assistant') return false;
  if (!msg.metadata?.tool_calls?.length) return true;

  const content = msg.content.trim();
  if (content === '') return false;
  if (content.startsWith('{') || content.startsWith('[')) return false;

  return true;
}
```

---

## Migration Checklist for Partnerspot

1. [ ] Update `Message` TypeScript interface to include new fields
2. [ ] Add `tool` role support to message renderer
3. [ ] Implement `shouldRenderAssistantContent()` filter
4. [ ] Handle `tool_error` styling
5. [ ] Add continuation status display (optional)
6. [ ] Implement state reconciliation on completion
7. [ ] Test with various tool call scenarios

---

## Files to Modify (Partnerspot)

| File | Change |
|------|--------|
| `resources/js/types/assistant.ts` | Add `tool` role, new metadata fields |
| `resources/js/components/agent-chat-sidebar.tsx` | Add tool message renderer |
| `resources/js/components/message-bubble.tsx` | Handle tool_calls, filter JSON content |
| `resources/js/hooks/use-agent-execution.ts` | Handle new event types |
| `src/.../AssistantEventBroadcaster.php` | Use correct event keys |
