# Codex Context Management Analysis

## Overview
Codex (OpenAI's CLI coding agent) has a Rust-based compaction system that builds a compacted history from three components: initial context, recent user messages (with a token budget), and a generated summary. It supports both local and remote (OpenAI API) compaction.

## How They Manage Context

### Data Structures
- **CompactionSettings** - Configuration for compaction behavior
- **ResponseItem** - Message/response items that form the conversation history
- **Compaction builds** a new history from: `initial_context + recent_user_messages + summary`

### Context Assembly (build_compacted_history)
```rust
pub(crate) fn build_compacted_history(
    initial_context: Vec<ResponseItem>,
    user_messages: &[String],
    summary_text: &str,
) -> Vec<ResponseItem>
```
1. Start with `initial_context` (system setup, initial instructions)
2. Add recent user messages (newest first, up to token budget)
3. Prepend summary of older conversation
4. Result replaces the entire conversation history

### Token-Budgeted User Message Selection
- `COMPACT_USER_MESSAGE_MAX_TOKENS = 20,000`
- Iterates user messages in reverse (newest first)
- Keeps adding messages until budget exhausted
- Ensures most recent context is preserved

## How They Control Token Budget

### Compaction Trigger
- Compaction triggered when context approaches the model's context window limit
- On context overflow error from the API: removes oldest history items and retries
- Uses the model's context window configuration to determine when compaction is needed

### Token Estimation
- Uses character-based estimation (similar to chars/4 heuristic)
- Budget tracking per component (initial context, user messages, summary)

### Remote Compaction Option
- Can send compaction work to OpenAI's API (for their models)
- Local compaction as fallback
- Model-specific compaction prompts

## How They Decide on Running Compaction

1. **Threshold**: Context token count exceeds model context window minus reserve
2. **Overflow**: On API context overflow error, compact and retry
3. No periodic/scheduled compaction — purely reactive

## How They Allow Custom Context Management

### Limited Customization
- No extension/plugin system for compaction
- CompactionSettings provides basic configuration knobs
- Remote vs local compaction is the main choice
- No hooks for intercepting or modifying compaction

### Hardcoded Strategies
- Summary generation prompt is hardcoded
- User message budget (20k tokens) is a constant
- Initial context is always preserved in full

## Context Components

1. **Initial Context** - System messages, instructions (always preserved)
2. **Summary** - LLM-generated summary of older conversation
3. **Recent User Messages** - Token-budgeted selection of newest messages
4. **Tool Results** - Inline with response items

## Key Insights

### 1. Three-Component Architecture
The `initial_context + summary + recent_messages` structure is clean and predictable. Users always get their setup context, a summary of what happened, and recent interactions.

### 2. Reverse Iteration for Recency
Selecting user messages by iterating newest-first ensures the most recent context is always preserved. Simple and effective.

### 3. Rust Performance
Implementing compaction in Rust gives performance benefits for token estimation and history manipulation, especially with large conversation histories.

### 4. Remote Compaction
The ability to offload summarization to the API provider is unique. This could reduce local compute needs but adds a network dependency.

### 5. Overflow as Trigger
Using API overflow errors as a compaction trigger is pragmatic — it means the system doesn't need to perfectly estimate token counts, just react when the limit is actually hit.

### 6. Preserving Initial Context
Always keeping the initial context (system instructions, project setup) intact ensures the agent doesn't lose its foundational instructions after compaction.

## Recommendations for AgentContext

1. **Three-component model** - Consider `initial_context + summary + recent_messages` as a standard compacted structure.
2. **Token-budgeted message selection** - Use reverse iteration with a token budget to select which recent messages to keep.
3. **Preserve initial context always** - System instructions and setup should survive compaction.
4. **React to overflow** - Use API errors as a fallback trigger, not just proactive estimation.
5. **Remote compaction option** - Allow delegating summarization to an external service or different model.
