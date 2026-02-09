# Agno Context Management Analysis

## Overview
Agno is a Python agent framework that separates context management into two distinct concerns: **long-term memory** (DB-backed user memories managed by MemoryManager) and **compression** (tool result compression managed by CompressionManager). There is no traditional compaction/summarization of the conversation history itself.

## How They Manage Context

### Data Structures
- **CompressionManager** - Compresses tool results via LLM to reduce token usage
- **MemoryManager** - Long-term persistent user memories (DB-backed), not conversation context
- **Messages** have a `compressed_content` field alongside regular content
- Memory optimization strategies (e.g., summarize) for long-term memory, not conversation

### Tool Result Compression
`CompressionManager` in `libs/agno/agno/compression/manager.py`:
- Targets tool results specifically (not the full conversation)
- Uses LLM to summarize large tool outputs
- Stores compressed version in `message.compressed_content`
- Original content preserved, compressed version used for context

### Long-Term Memory
`MemoryManager` in `libs/agno/agno/memory/manager.py`:
- Manages persistent user memories across sessions (DB-backed)
- LLM-driven CRUD: the LLM decides what to remember/update/delete via tool calls
- Agentic search for memory retrieval
- Separate from conversation context — this is about remembering user preferences, facts, etc.

## How They Control Token Budget

### Compression Triggers
Two trigger conditions for tool result compression:
1. **compress_tool_results_limit** (default: 3) - After N uncompressed tool results, compress them
2. **compress_token_limit** - When total tokens of uncompressed tool results exceed this threshold

### Compression Execution
- Uses LLM to generate compressed summaries of tool outputs
- Async parallel compression via `asyncio.gather` for multiple results
- Compressed content replaces original in the message sent to LLM
- No global context window awareness — compression is per-tool-result

### Memory Optimization
- `MemoryOptimizationStrategy` base class with `summarize` strategy
- Applied to long-term memories, not conversation history
- Summarizes accumulated memories to keep the memory store manageable

## How They Decide on Running Compaction

No traditional conversation compaction. Two separate mechanisms:
1. **Tool compression**: Triggered by count (3 uncompressed tools) or token limit
2. **Memory optimization**: Triggered by strategy configuration on the memory store

## How They Allow Custom Context Management

### Limited Customization
- No plugin/extension/hook system for context management
- Compression uses the model's LLM — no option to use a different model
- Memory optimization strategies are pluggable via `MemoryOptimizationStrategy` base class
- Users can implement custom memory strategies

### Memory Strategies
```python
class MemoryOptimizationStrategy:
    # Base class for memory optimization
    # "summarize" is the built-in strategy
```

## Context Components

1. **System Prompt** - Agent's instructions
2. **Messages** - Linear conversation history (user/assistant/tool)
3. **Tool Results** - With optional `compressed_content` for LLM-compressed versions
4. **Long-Term Memories** - DB-backed, injected into context when relevant
5. **No Sections** - No section-based organization

## Key Insights

### 1. Targeted Compression Over Full Compaction
Instead of summarizing the entire conversation, Agno targets the biggest token consumers — tool results. This is pragmatic: tool outputs (file contents, API responses, search results) are often the bulk of token usage.

### 2. Separation of Memory Concerns
Long-term memory (MemoryManager) and conversation context (CompressionManager) are completely independent systems. Memory persists across sessions; compression is within a session.

### 3. Parallel Compression
Using `asyncio.gather` to compress multiple tool results simultaneously is an efficient approach for I/O-bound LLM calls.

### 4. Preserving Original Content
Storing both original and compressed versions (`content` vs `compressed_content`) allows switching between them and maintaining auditability.

### 5. Count-Based Trigger is Simple but Effective
The `compress_tool_results_limit = 3` trigger is simple — after 3 uncompressed tool results, compress them. No complex token estimation needed for the trigger itself.

## Recommendations for AgentContext

1. **Tool result compression as a separate concern** - Consider targeted compression of tool outputs rather than (or in addition to) full conversation summarization.
2. **Dual content fields** - Keep both original and compressed versions of content for flexibility.
3. **Async parallel compression** - When compressing multiple items, do it concurrently.
4. **Count-based triggers** - Simple heuristics (N uncompressed items) can complement token-based triggers.
5. **Separate long-term memory from conversation context** - These are fundamentally different concerns with different lifecycles.
