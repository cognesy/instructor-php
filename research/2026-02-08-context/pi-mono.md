# Pi-Mono Context Management Analysis

## Overview
Pi-mono (Claude Code competitor) is a TypeScript-based coding agent with a sophisticated session-based context management system. Context is managed through a tree-structured session file rather than a linear message array.

## How They Manage Context

### Data Structures
- **SessionManager** - Core class managing session state as a tree of `SessionEntry` objects stored in a JSONL file
- **SessionEntry** types: `message`, `compaction`, `branch_summary`, `custom_message`, `custom`, `thinking_level_change`, `model_change`, `label`
- **Tree Structure** - Each entry has `id` (UUID) and `parentId`, forming a tree supporting branching/forking
- **SessionContext** - Result of `buildSessionContext()`: `{ messages: AgentMessage[], thinkingLevel, model }`

### Context Assembly for LLM Calls
`buildSessionContext()` walks from the leaf node to root, collecting entries along the path:
1. If compaction exists on the path, emits the summary message first
2. Then emits "kept messages" (between `firstKeptEntryId` and compaction entry)
3. Then emits messages after compaction
4. Non-message entries (thinking_level_change, model_change, custom, label) are skipped

### Extension `context` Event
Before each LLM call, extensions can intercept and modify the message array via the `context` event:
```typescript
pi.on("context", async (event, ctx) => {
    // event.messages is the full message array
    // Return { messages: modified } to change it
});
```

## How They Control Token Budget

### Token Estimation
- Uses `chars/4` heuristic for estimating tokens per message
- Different handling per message role (user, assistant, toolResult, bashExecution, etc.)
- Images estimated at 1200 tokens (4800 chars)
- Uses actual `Usage` data from last assistant response when available (`estimateContextTokens`)
- Combines API-reported usage with estimated trailing tokens for messages after last response

### Context Window Awareness
- Each model specifies `contextWindow` in its configuration
- `getContextUsage()` returns: `{ tokens, contextWindow, percent, usageTokens, trailingTokens, lastUsageIndex }`
- Available to extensions and UI for display

## How They Decide on Compaction

### Trigger Conditions
Two triggers via `shouldCompact()`:
1. **Threshold** - After each successful turn: `contextTokens > contextWindow - reserveTokens` (default reserve: 16384)
2. **Overflow** - On context overflow error from API: immediate compaction, then retry

### Compaction Settings
```typescript
CompactionSettings {
    enabled: boolean;        // default: true
    reserveTokens: number;   // default: 16384
    keepRecentTokens: number; // default: 20000
}
```

### Auto-Compaction Flow
1. After agent loop ends, check if compaction needed
2. If threshold or overflow triggered, run `_runAutoCompaction()`
3. Emits events for extensions to observe/override
4. Uses AbortController for cancellation

## How They Allow Custom Context Management

### Extension System (Very Flexible)
1. **`session_before_compact` event** - Extensions can:
   - Cancel compaction (`return { cancel: true }`)
   - Replace with custom compaction result (`return { compaction: { summary, firstKeptEntryId, tokensBefore, details } }`)
   - Use a different model (e.g., cheaper Gemini Flash)

2. **`context` event** - Modify messages before each LLM call:
   - Add messages, filter messages, reorder
   - Return modified message array

3. **`before_agent_start` event** - Modify system prompt per-turn:
   - Return `{ systemPrompt: "..." }` to override

4. **Custom entries** - Extensions can persist state in session:
   - `pi.appendEntry(customType, data)` - State that doesn't go to LLM
   - `pi.sendMessage(message)` - Custom messages that DO go to LLM

5. **`session_before_tree` event** - Override branch summarization behavior

### CompactionPreparation Object
Extensions receive rich preparation data:
```typescript
CompactionPreparation {
    firstKeptEntryId: string;
    messagesToSummarize: AgentMessage[];
    turnPrefixMessages: AgentMessage[];  // When splitting mid-turn
    isSplitTurn: boolean;
    tokensBefore: number;
    previousSummary?: string;  // For iterative updates
    fileOps: FileOperations;   // Read/modified file tracking
    settings: CompactionSettings;
}
```

## Context Components

1. **System Prompt** - Built by `buildSystemPrompt()`, loaded from resources
2. **Compaction Summary** - Previous context summarized into structured markdown
3. **Kept Messages** - Recent messages after the cut point
4. **Branch Summaries** - When navigating in session tree
5. **Custom Messages** - Extension-injected context (user role)
6. **File Operations** - Tracked reads/edits across compactions
7. **Tool Results** - Inline with messages

## Key Insights

### 1. Tree-Based Session (Not Linear)
Sessions are DAGs with branching/forking. This enables:
- Navigating back to earlier points
- Forking to try different approaches
- Branch summaries when leaving a branch

### 2. Iterative Summary Updates
When compaction runs again, it doesn't re-summarize everything. It passes `previousSummary` to the LLM with an UPDATE prompt, asking to merge new information into the existing summary. This is more token-efficient.

### 3. Structured Summarization Format
Uses a rigid template: Goal, Constraints & Preferences, Progress (Done/In Progress/Blocked), Key Decisions, Next Steps, Critical Context. Preserves exact file paths and function names.

### 4. Split-Turn Handling
When the cut point falls mid-turn (inside an assistant's tool-use sequence), it generates a separate "turn prefix summary" explaining the early part of the turn, merged with the main summary.

### 5. File Operation Tracking Across Compactions
Tracks which files were read/modified, carries forward through compaction details, appends to summary. Ensures the LLM always knows what files were touched even after context compression.

### 6. Extension-Driven Customization
The compaction system is fully pluggable via events. Extensions can completely replace the summarization strategy, use different models, or add custom data to the compaction entry.

### 7. Configurable Thresholds
Users can adjust `reserveTokens` and `keepRecentTokens` via settings, providing control over aggressiveness of compaction.

## Recommendations for AgentContext

1. **Separate context assembly from storage** - Pi-mono's `buildSessionContext()` is a pure function taking entries and returning messages
2. **Event-based extension points** - Allow intercepting context assembly (`context` event) and compaction (`before_compact`)
3. **Iterative summaries** - Don't re-summarize from scratch; update previous summaries incrementally
4. **Rich compaction preparation** - Give custom strategies all the data they need (messages, previous summary, file ops, settings)
5. **File tracking across compactions** - Maintain awareness of touched files even after context compression
6. **Structured summary format** - Use a consistent template that preserves critical details
