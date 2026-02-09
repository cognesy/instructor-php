# Gemini-CLI Context Management Analysis

## Overview
Gemini-CLI has one of the most sophisticated context management systems among the projects analyzed. It features a multi-phase compression pipeline with threshold-based triggers, a "reverse token budget" for tool responses, state snapshot summarization with verification probes, and a hook system for extensibility.

## How They Manage Context

### Data Structures
- **ChatCompressionService** - Core service managing compression lifecycle
- **TokenLimits** - Model-specific context window configurations
- Messages are a linear history with function call/response pairs

### Key Constants
```typescript
DEFAULT_COMPRESSION_TOKEN_THRESHOLD = 0.5  // Compress at 50% of context window
COMPRESSION_PRESERVE_THRESHOLD = 0.3       // Keep last 30% of messages
COMPRESSION_FUNCTION_RESPONSE_TOKEN_BUDGET = 50,000  // Budget for function responses
```

### Context Assembly
1. Check if compression needed (threshold)
2. If triggered, find split point (70% old / 30% recent)
3. Truncate old function responses via "reverse token budget"
4. Summarize the old portion with LLM
5. Verify summary with a "probe" step
6. Replace old messages with summary as `<state_snapshot>`

## How They Control Token Budget

### Threshold-Based Triggers
- **Compression threshold**: 50% of context window used → trigger compression
- **Preserve threshold**: Keep the newest 30% of messages intact
- This means compression targets the oldest 70% of the conversation

### Reverse Token Budget for Function Responses
`truncateHistoryToBudget()`:
- Iterates messages from newest to oldest
- Keeps recent function responses intact
- Older function responses: truncated to first 30 lines + saved to temp file
- Total function response tokens capped at 50,000
- Provides file paths so the agent can re-read if needed

### Token Estimation
- Uses model-specific token limits (1,048,576 default for Gemini)
- Character-based estimation for quick checks
- Actual model token counting for precise checks

### Tool Output Summarization
`utils/summarizer.ts`:
- Uses LLM to summarize large tool outputs
- Skips summarization if output is shorter than `maxOutputTokens`
- Separate utility from the main compression pipeline

## How They Decide on Running Compaction

### Two-Phase Decision
1. **Threshold check**: `totalTokens >= contextWindow * 0.5`
2. **Split point**: `findCompressSplitPoint()` by character fraction — finds where 70% of the conversation ends

### Compression Flow
1. Check threshold
2. Emit `PreCompress` hook event (allows cancellation/modification)
3. Truncate old function responses (reverse token budget)
4. Generate summary with `<state_snapshot>` template
5. Run verification probe (LLM checks summary quality)
6. Replace old messages with summary

## How They Allow Custom Context Management

### Hook System
- **PreCompress event** - Fired before compression begins
  - Extensions can cancel compression
  - Extensions can modify the messages before compression
  - Extensions can inject additional context

### State Snapshot Template
The summary uses a structured `<state_snapshot>` XML template:
- Current state of the project
- What has been accomplished
- What the user is currently working on
- Key decisions made
- File state tracking

### Verification Probe
After generating a summary, a second LLM call verifies the summary quality:
- Checks if critical information was preserved
- Can flag issues for re-summarization
- Unique among all projects analyzed

## Context Components

1. **System Prompt** - Model instructions and tool definitions
2. **State Snapshot** - Structured summary of older conversation (XML format)
3. **Recent Messages** - Last 30% of conversation preserved intact
4. **Function Responses** - Token-budgeted, older ones truncated with file references
5. **Temp Files** - Truncated tool outputs saved to disk for re-reading

## Key Insights

### 1. Aggressive Early Compression (50% Threshold)
Compressing at 50% of context window means there's always headroom. Most other projects wait until 80-90%. This prevents context overflow errors but may compress more than necessary.

### 2. Two-Phase Summarization with Verification
The "probe" step after summarization is unique and valuable. Having the LLM verify its own summary helps catch information loss. This adds one extra LLM call but improves quality.

### 3. Reverse Token Budget is Clever
Instead of treating all messages equally, the reverse token budget specifically targets function responses (the biggest token consumers). By iterating newest-first, it ensures recent tool outputs are preserved while older ones are truncated.

### 4. Temp File Fallback
Saving truncated tool outputs to temp files and providing file paths in the truncated message means the agent can re-read full outputs if needed. Information is not truly lost, just moved out of context.

### 5. State Snapshot Format
Using structured XML `<state_snapshot>` tags for the summary makes it easy for the model to identify and parse the compacted context. The template ensures consistent structure.

### 6. Hook-Based Extensibility
The PreCompress event allows extensions to participate in the compression decision without needing full pipeline access.

## Recommendations for AgentContext

1. **Two-phase summarization** - Add a verification step after generating summaries to catch information loss.
2. **Reverse token budget** - Target tool responses specifically for truncation, iterating newest-first.
3. **Temp file fallback** - Save truncated content to files so agents can re-read if needed.
4. **Structured summary template** - Use a consistent XML/markdown template for summaries.
5. **Configurable thresholds** - Allow users to tune compression and preservation thresholds.
6. **50% threshold** - Consider earlier compression to maintain headroom for long tool-use sequences.
