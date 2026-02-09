# Tool Call Sequence Isolation: InstructorPHP vs Other Frameworks

## InstructorPHP's Current Approach

InstructorPHP uses a **multi-section MessageStore** to isolate tool execution from the main conversation:

### The Four Sections
```
DEFAULT_SECTION ('messages')          → Main conversation thread
BUFFER_SECTION ('buffer')             → Summarized old messages
SUMMARY_SECTION ('summary')           → Message summaries
EXECUTION_BUFFER_SECTION ('exec_buf') → Tool execution buffer (isolated)
```

### Routing Logic (AgentContext::withStepOutputRouted)
- **Tool execution steps** → messages go to `EXECUTION_BUFFER_SECTION`
- **Final response step** → response goes to `DEFAULT_SECTION` + `EXECUTION_BUFFER_SECTION` is **cleared**

This means the main conversation only ever sees: `query → agent response`. The intermediate tool call sequences (tool_use + tool_result + errors + retries) live in the execution buffer during processing and are discarded when the final response arrives.

### Additional Isolation Layers
- **AgentState** splits session context (persistent) from ExecutionState (transient)
- **AgentStep** wraps input/output messages separately as immutable snapshots
- **messagesForInference()** selectively compiles from all sections in order for LLM calls

---

## How Other Frameworks Handle This

### Pattern 1: Flat List, Everything Visible (Most Common)

**Pi-mono, Gemini-CLI, Cline, Codex:**
All tool call messages go into a single flat message list. The LLM sees every tool_use and tool_result from every step. No isolation.

- **Pi-mono**: Single `messages` array, all tool calls included. Role-based filtering only for non-tool types.
- **Gemini-CLI**: Flat history with function call/response pairs. Compression later truncates old function responses.
- **Cline**: Flat list, tool_use/tool_result pairs always paired. File read deduplication is the only optimization.
- **Codex**: Flat ResponseItem list, all tool executions visible.

### Pattern 2: Step-Level Buffering (Moderate Isolation)

**OpenAI Agents SDK (Python/JS):**
- Maintains `pre_step_items` (before step) and `new_step_items` (during step) separately
- Tool results accumulated in `new_step_items` during execution
- Merged into run output only when step completes
- Provides `call_model_input_filter` hook to filter what reaches the model
- `handoff_input_filter` can collapse prior transcript into single message

**Pydantic-AI:**
- Graph-based execution with node boundaries
- `CallToolsNode` processes tools internally
- Message history updated at node boundaries, not during tool execution
- HistoryProcessor pipeline can filter tool messages before next LLM call

### Pattern 3: Sub-Agent Isolation (Full Isolation)

**Amp (Sourcegraph):**
- Main thread: flat, simple, inclusive (tool calls visible within the agent)
- Sub-agents: fully isolated — "work in isolation, can't communicate with each other, start fresh without conversation's accumulated context, main agent only receives their final summary"
- Uses `handoff` command to draft new threads with selective context

**OpenAI Agents SDK (handoff pattern):**
- `handoff_input_filter` with opt-in feature to collapse prior transcript
- Effectively creates isolation at agent boundaries

### Pattern 4: Observation Masking (Keep Reasoning, Clear Outputs)

**Anthropic's `clear_tool_uses_20250919` strategy:**
- Keep tool calls in conversation during execution
- Progressively clear older tool results, replacing with placeholders
- Configurable: trigger threshold (100k tokens), keep recent N pairs (default 3), exclude specific tools
- Combined with memory tool: 39% performance improvement

**JetBrains Research (empirical):**
- Tested observation masking vs LLM summarization
- Observation masking: keep agent's reasoning + actions, replace old tool outputs with placeholders
- Result: better performance in 4/5 settings, 52% cheaper than unmanaged context
- LLM summarization: 15% longer runs, 7%+ cost overhead

---

## Comparison Matrix

| Framework | Tool Calls in Main Thread? | Isolation Mechanism | When Cleaned? |
|-----------|---------------------------|-------------------|---------------|
| **InstructorPHP** | No (execution buffer) | Section-based storage | On final response |
| **Pi-mono** | Yes | None (flat list) | Compaction only |
| **Pydantic-AI** | Yes (at boundaries) | Node-level buffering | HistoryProcessor |
| **Agno** | Yes | compressed_content field | Per-tool compression |
| **Codex** | Yes | None (flat list) | Full compaction |
| **Gemini-CLI** | Yes | None (flat list) | Truncation + compression |
| **Opencode** | Yes | None (flat list) | Pruning (erase outputs) |
| **Cline** | Yes | None (flat list) | Truncation + dedup |
| **OpenAI SDK** | Yes (step-buffered) | Step item separation | Filter hooks |
| **Amp** | Yes (within agent) | Sub-agent isolation | Handoff |
| **Anthropic** | Yes | clear_tool_uses strategy | Progressive clearing |

---

## Expert Positions

### FOR Isolation / Aggressive Management

**Jason Liu (instructor, jxnl.co):**
- Slash commands (inline execution) = 169,000 tokens, 91% noise
- Sub-agents = 21,000 tokens, 76% signal
- **8x improvement in context quality**

**Phil Schmid (Hugging Face):**
- "If an agent writes a 500-line file, the chat history should not contain the file content — only the file path"
- Sub-agents are a technical mechanism for context pollution prevention
- Shared context breaks KV-cache

**JetBrains Research:**
- Observation masking (keep reasoning, clear outputs) outperformed in 4/5 settings
- 52% cheaper than unmanaged context
- Key: "The agent's reasoning about tool calls is more valuable than the raw tool outputs"

**HumanLayer Framework:**
- Searches generating 5000+ lines push utilization from 50% to 95%
- Sub-agents prevent context pollution

**Claude Code GitHub #20304:**
- When a QA reviewer sees developer reasoning, review becomes validation not critique
- Without isolation, adding agents creates echo chambers

### AGAINST Full Isolation / FOR Keeping Tool Calls

**sketch.dev / Braintrust:**
- Tool responses = 67.6% of total tokens; tools comprise ~80% of what agent sees
- Removing them removes most context
- Agent adapts when tools fail — needs to see failures

**Amp/Claude Code:**
- "Do the simple thing first" — flat message history without complex threading
- Complexity of isolation mechanisms can outweigh benefits

**LangChain:**
- Summaries often lack specificity needed for responses
- Better to keep and selectively filter than to remove

---

## The Emerging Consensus

The industry is converging on a **layered approach**:

1. **Within the agent loop**: Keep tool calls visible to the LLM during the current execution
2. **After the execution**: Aggressively clear/compact tool outputs (Anthropic's clear_tool_uses)
3. **For expensive operations**: Delegate to sub-agents returning condensed summaries
4. **For independent evaluation**: Full isolation (no parent context)
5. **Observation masking > summarization**: Keep reasoning, replace outputs with placeholders

### The Critical Insight
**The agent's reasoning about tool calls is more valuable than the raw tool outputs.**

- Keep: "I read config.php and found the database settings use PDO with mysql driver"
- Clear: The actual 200-line contents of config.php
- Keep: "The test failed because the mock wasn't configured for the new parameter"
- Clear: The full 500-line test output

---

## Assessment of InstructorPHP's Approach

### What It Does Well
1. **Clean main thread** — The user-facing conversation is uncluttered
2. **Predictable structure** — Tool execution is always in a known location
3. **Automatic cleanup** — Execution buffer cleared on final response
4. **State separation** — Session vs execution is a good architectural boundary

### What It May Lose
1. **Inter-step reasoning** — If the agent's reasoning about failed tool calls is cleared, the LLM loses valuable context for future steps within the same execution
2. **Error recovery patterns** — Seeing "I tried X, it failed, so I tried Y" helps the model avoid repeating mistakes
3. **Debugging/observability** — Cleared tool execution makes it harder to understand what happened
4. **Progressive learning** — The model can learn from tool output patterns across steps

### The Nuance
InstructorPHP's isolation works well for the **query → response** boundary (between executions). But within a single execution, the tool calls ARE visible via the execution buffer section being included in `messagesForInference()`. The isolation is specifically at the **execution boundary**, not within the execution loop.

This is actually close to the emerging best practice:
- During execution: tool calls visible (via EXECUTION_BUFFER_SECTION in messagesForInference)
- After execution: cleared (on final response, buffer is wiped)
- Next execution: starts fresh with only the main conversation

### Potential Improvements
1. **Observation masking instead of full clearing** — When clearing the execution buffer, keep the agent's reasoning about tool results but replace raw outputs with placeholders
2. **Selective preservation** — Allow marking certain tool results as "important" to survive into the main thread (e.g., key findings, architectural decisions)
3. **Summary injection** — Instead of just clearing the buffer, generate a brief summary of what the tool execution accomplished and inject it into the main thread alongside the response
4. **Configurable strategy** — Let users choose: full isolation (current), observation masking, or full transparency
