# Consolidated Summary: Context Management Across Agent Projects

## Projects Analyzed
1. **Pi-mono** - TypeScript coding agent (Claude Code competitor)
2. **Pydantic-AI** - Python agent framework
3. **Agno** - Python agent framework
4. **Codex** - OpenAI's CLI coding agent (Rust + TypeScript)
5. **Gemini-CLI** - Google's CLI coding agent (TypeScript)
6. **Opencode** - TypeScript coding agent
7. **Cline** - VS Code-based AI coding assistant

---

## Cross-Project Comparison

### Context Data Structures

| Project | Structure | Sections | Tree-Based |
|---------|-----------|----------|------------|
| Pi-mono | SessionEntry DAG | No (compaction entries) | Yes (branching/forking) |
| Pydantic-AI | Linear list | No | No |
| Agno | Linear list | No | No |
| Codex | Linear list | No (3-component rebuild) | No |
| Gemini-CLI | Linear list | No | No |
| Opencode | Linear list | No | No |
| Cline | Linear list | No (deleted ranges) | No |

**Key finding**: Only Pi-mono uses a tree/DAG structure. All others use a simple linear message list. No project uses a section-based approach like AgentContext's current design.

### Compaction Strategies

| Project | Strategy | LLM-Based | Pruning | Deduplication |
|---------|----------|-----------|---------|---------------|
| Pi-mono | Full summarization | Yes | No | No |
| Pydantic-AI | User-provided (HistoryProcessor) | User choice | User choice | User choice |
| Agno | Tool result compression only | Yes (per-tool) | No | No |
| Codex | Full summarization | Yes | No | No |
| Gemini-CLI | Summarize + truncate tool outputs | Yes | Yes (tool outputs) | No |
| Opencode | Prune first, then summarize | Yes | Yes (tool outputs) | No |
| Cline | Truncation + optional condense | Optional | Yes (message removal) | Yes (file reads) |

### Compaction Triggers

| Project | Trigger | Threshold | Fallback |
|---------|---------|-----------|----------|
| Pi-mono | Proactive + overflow | contextWindow - 16,384 reserve | API overflow error |
| Pydantic-AI | N/A (user-implemented) | N/A | UsageLimitExceeded |
| Agno | Count or token limit | 3 uncompressed tools OR token limit | None |
| Codex | Proactive + overflow | Context window threshold | API overflow error |
| Gemini-CLI | Proactive | 50% of context window | None |
| Opencode | Reactive (overflow only) | Context > usable window | Prune then compact |
| Cline | Reactive | totalTokens >= maxAllowedSize | Adaptive half/quarter |

### Extension/Customization Mechanisms

| Project | Mechanism | Customizability |
|---------|-----------|-----------------|
| Pi-mono | Event system (session_before_compact, context) | High - can cancel, replace, use different model |
| Pydantic-AI | HistoryProcessor pipeline | High - full control via callable chain |
| Agno | MemoryOptimizationStrategy class | Low - only for long-term memory |
| Codex | None | None |
| Gemini-CLI | PreCompress hook event | Medium - can cancel/modify |
| Opencode | Plugin trigger event | Medium - experimental API |
| Cline | Strategy selection | Low - choose truncate vs condense |

---

## Recurring Patterns

### 1. Tool Results Are the Biggest Token Consumer
Every project that implements compaction recognizes that tool results (file reads, API responses, command outputs) consume the majority of tokens. The responses to this vary:
- **Agno**: Compresses tool results specifically via LLM
- **Gemini-CLI**: "Reverse token budget" truncates older tool outputs, saves to temp files
- **Opencode**: Prunes (erases) old tool outputs before attempting full compaction
- **Cline**: Deduplicates repeated file reads

### 2. Preserve Recent Context, Summarize/Remove Older
All compaction strategies preserve recent messages and target older ones:
- **Pi-mono**: keepRecentTokens = 20,000
- **Codex**: COMPACT_USER_MESSAGE_MAX_TOKENS = 20,000 (reverse iteration)
- **Gemini-CLI**: COMPRESSION_PRESERVE_THRESHOLD = 0.3 (keep last 30%)
- **Opencode**: PRUNE_PROTECT = 40,000
- **Cline**: Adaptive half/quarter truncation from the oldest end

### 3. Preserve Initial Context
Multiple projects explicitly preserve the initial system/user context:
- **Codex**: Always includes `initial_context` in compacted history
- **Cline**: Always keeps first user-assistant pair
- **Pi-mono**: System prompt rebuilt fresh each time

### 4. Multi-Phase Approaches
Several projects use lightweight optimization before heavy LLM summarization:
- **Opencode**: Prune (erase tool outputs) → Compact (LLM summary)
- **Cline**: Deduplicate file reads → Truncate/Condense
- **Gemini-CLI**: Truncate tool outputs → Summarize → Verify (probe)

### 5. Structured Summary Templates
Projects that generate summaries use structured formats:
- **Pi-mono**: Goal, Constraints, Progress (Done/In Progress/Blocked), Key Decisions, Next Steps, Critical Context
- **Gemini-CLI**: `<state_snapshot>` XML tags with project state, accomplishments, current work, key decisions
- **Codex**: System-message format with conversation summary

### 6. Iterative Summary Updates
- **Pi-mono**: Passes `previousSummary` to LLM with UPDATE prompt — merge new info into existing summary rather than re-summarizing everything
- More token-efficient than full re-summarization

---

## Unique/Notable Techniques

### From Pi-mono
- **Tree-based sessions** enabling branching/forking
- **Split-turn handling** when compaction cut point falls mid-tool-use
- **File operation tracking** across compactions (reads/edits survive compaction)
- **Extension-driven compaction** with full override capability

### From Pydantic-AI
- **HistoryProcessor pipeline** — simplest possible extension (callable chain)
- **Pre-flight token counting** (`count_tokens_before_request`)
- **Clean separation** of limits (UsageLimits) from context management (HistoryProcessor)

### From Agno
- **Dual content fields** (`content` + `compressed_content`) preserving originals
- **Async parallel compression** of multiple tool results
- **Count-based triggers** (after N uncompressed items)

### From Gemini-CLI
- **Two-phase summarization with verification probe** — LLM checks its own summary
- **Reverse token budget** — newest tool outputs get full budget, oldest get truncated
- **Temp file fallback** — truncated outputs saved to disk for re-reading
- **50% threshold** — compress early to maintain headroom

### From Opencode
- **Compaction as a separate agent** with its own prompt
- **Protected tool types** — "skill" tools never pruned
- **Auto-continue** after compaction ("Continue if you have next steps")

### From Cline
- **File read deduplication** — replace older reads of same file with notices
- **Adaptive truncation ratios** — half vs three-quarters based on pressure
- **Context modification audit trail** with timestamps
- **Tool pair integrity validation** during truncation

---

## Recommendations for AgentContext Redesign

### Architecture

1. **Drop rigid section-based structure** — No other project uses prescribed sections (DEFAULT, BUFFER, SUMMARY, EXECUTION_BUFFER). Instead, use a flat message list with composable processors.

2. **Separate contract from data structure** — Following Pydantic-AI's lead, define the interface as:
   - A message list (the data)
   - A processor pipeline (the transformation)
   - Configuration/limits (the constraints)

3. **Composable processor pipeline** — Allow registering callables that transform the message list before each LLM call. This replaces rigid sections with flexible, chainable operations:
   ```
   messages → deduplicate_file_reads → prune_old_tool_outputs → summarize_if_needed → result
   ```

4. **Event-based extension points** — For more complex customization, support events:
   - `before_compact` — cancel, replace, or modify compaction
   - `context` — modify messages before LLM call
   - `after_compact` — post-compaction hooks

### Compaction

5. **Multi-phase compaction pipeline**:
   - Phase 1: Deduplication (file reads, repeated content) — cheap, no LLM
   - Phase 2: Pruning (erase old tool outputs) — cheap, no LLM
   - Phase 3: LLM summarization — expensive, only when phases 1-2 aren't enough

6. **Iterative summary updates** — Pass previous summary to LLM and ask to UPDATE rather than re-summarize from scratch.

7. **Structured summary template** — Use a consistent format (Goal, Progress, Decisions, Next Steps, File State) that preserves critical details.

8. **Configurable thresholds** — Allow tuning:
   - When to trigger compaction (% of context window)
   - How much recent context to preserve (token count or %)
   - Reserve tokens for response

### Token Management

9. **Separate limits from context assembly** — Token limits/budgets should be a separate concern from context construction.

10. **Pre-flight token counting** — Option to count tokens before sending to avoid wasted API calls on overflow.

11. **Reverse token budget for tool results** — Budget for tool outputs specifically, truncating oldest first.

### Quality

12. **Summary verification probe** — After generating a summary, have the LLM verify it preserved critical information.

13. **File operation tracking across compactions** — Maintain a list of read/modified files that survives compaction.

14. **Protected message types** — Allow marking certain messages/tool results as "never prune" (e.g., architectural decisions, critical tool outputs).

15. **Auto-continue after compaction** — Signal the agent to continue its work after context has been compacted.

### Data Integrity

16. **Tool pair integrity** — Ensure compaction never breaks tool_use/tool_result pairs.

17. **Preserve initial context** — Always keep system instructions and initial user context.

18. **Audit trail** — Track context modifications with timestamps for debugging and checkpointing.

---

## Priority Ranking

**High Priority** (fundamental architecture):
1. Drop sections, use flat message list with processor pipeline
2. Separate contract from data structure
3. Multi-phase compaction (dedup → prune → summarize)
4. Event-based extension points

**Medium Priority** (significant improvements):
5. Iterative summary updates
6. Configurable thresholds
7. File read deduplication
8. Tool pair integrity validation
9. Preserve initial context

**Lower Priority** (nice-to-have):
10. Summary verification probe
11. Pre-flight token counting
12. Auto-continue after compaction
13. Context modification audit trail
14. Protected message types
