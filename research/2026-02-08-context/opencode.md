# Opencode Context Management Analysis

## Overview
Opencode is a TypeScript-based coding agent with a two-phase context management approach: **pruning** (erasing old tool outputs to free tokens without LLM calls) and **compaction** (full LLM-based summarization using a separate "compaction agent"). It also has a plugin system for customizing compaction behavior.

## How They Manage Context

### Data Structures
- **SessionCompaction** namespace - Contains all compaction logic
- Messages stored in a session with tool call/result pairs
- Pruning operates on tool outputs; compaction replaces the full history

### Two-Phase Approach
1. **Pruning** - Lightweight, no LLM needed: erases old tool outputs to free tokens
2. **Compaction** - Full LLM-based summarization when pruning isn't enough

### Pruning Details (`prune()`)
```typescript
PRUNE_PROTECT = 40,000  // Protect this many recent tokens
PRUNE_MINIMUM = 20,000  // Must free at least this many tokens
```
- Walks backwards through tool calls
- Marks old completed tool outputs as "compacted" (erases their output content)
- Protects recent tools within PRUNE_PROTECT token window
- Protects "skill" tools (never prunes these)
- Must free at least PRUNE_MINIMUM tokens to be worth doing

### Compaction Details (`process()`)
- Uses a separate "compaction agent" with its own system prompt
- The compaction agent receives the full conversation and generates a summary
- Summary replaces the entire conversation history
- Auto-continues with "Continue if you have next steps" message

## How They Control Token Budget

### Overflow Detection
- `isOverflow()`: checks if token count > usable context window
- Uses model-specific context window configuration
- Triggers compaction pipeline when overflow detected

### Pruning Budget
- PRUNE_PROTECT: 40,000 tokens of recent context are always preserved
- PRUNE_MINIMUM: Only prune if we can free at least 20,000 tokens
- Token estimation for tool outputs to determine what to erase

### No Proactive Threshold
- Compaction is reactive (triggered on overflow), not proactive
- Pruning runs first as a quick fix; if not enough, full compaction follows

## How They Decide on Running Compaction

### Decision Flow
1. Check `isOverflow()` — is context > usable window?
2. If yes, try `prune()` first (quick, no LLM call)
3. If pruning freed enough tokens, done
4. If still overflowing, run full `process()` compaction

### Plugin Trigger
- Emits `"experimental.session.compacting"` plugin event before compaction
- Plugins can inject additional context or modify the compaction prompt
- Allows plugins to influence what the compaction agent sees

## How They Allow Custom Context Management

### Plugin System
- **`"experimental.session.compacting"` event** - Fired before compaction
  - Plugins can inject additional context
  - Plugins can replace the compaction prompt
  - Note: This is marked "experimental", suggesting the API may change

### Compaction Agent
- Compaction is itself an agent with a system prompt
- This prompt can be influenced by plugins
- The agent approach means compaction gets full model capabilities (thinking, analysis)

### Limited Configuration
- PRUNE_PROTECT and PRUNE_MINIMUM are constants, not configurable
- No user-facing settings for compaction aggressiveness

## Context Components

1. **System Prompt** - Agent instructions
2. **Messages** - Linear conversation history
3. **Tool Call/Result Pairs** - With pruneable outputs
4. **Compaction Summary** - Replaces history after compaction
5. **Auto-Continue Message** - "Continue if you have next steps" appended post-compaction

## Key Insights

### 1. Pruning Before Compaction (Two-Phase)
The pruning step is a lightweight optimization that often avoids the need for a full LLM-based compaction. Erasing old tool outputs is cheap and fast — no LLM call needed — and tool outputs are usually the biggest token consumers.

### 2. Compaction as a Separate Agent
Using a dedicated agent for compaction (with its own system prompt) is a powerful pattern. The compaction agent can be specialized for summarization, potentially with different model parameters or even a different model.

### 3. Auto-Continue After Compaction
Appending "Continue if you have next steps" ensures the main agent doesn't stall after compaction. This maintains flow continuity.

### 4. Protected Tool Types
Protecting "skill" tools from pruning recognizes that some tool outputs contain critical context that should never be erased (e.g., codebase understanding, architectural decisions).

### 5. Experimental Plugin API
The "experimental" label is honest about API stability. Providing extensibility while signaling it may change is a reasonable approach for evolving systems.

### 6. Reactive Over Proactive
Only compacting on actual overflow means less unnecessary work, but risks hitting the limit mid-response. The two-phase approach (prune first) mitigates this.

## Recommendations for AgentContext

1. **Two-phase approach** - Implement lightweight pruning (erasing old tool outputs) before falling back to full LLM compaction.
2. **Compaction as agent** - Consider using a separate agent/prompt for compaction to specialize the summarization.
3. **Protected categories** - Allow marking certain tool results or message types as "never prune."
4. **Auto-continue** - After compaction, automatically signal the agent to continue its work.
5. **Plugin extensibility** - Allow plugins to inject context or modify the compaction strategy.
