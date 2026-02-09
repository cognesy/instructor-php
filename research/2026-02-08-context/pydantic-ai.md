# Pydantic-AI Context Management Analysis

## Overview
Pydantic-AI is a Python agent framework that takes a minimalist approach to context management. It has no built-in compaction or summarization system, instead relying on a composable **HistoryProcessor** pipeline that users can plug in to transform the message history before each LLM call.

## How They Manage Context

### Data Structures
- **GraphAgentState** - Core state object containing `message_history: list[ModelMessage]`
- **HistoryProcessor** - A callable type (sync/async, with/without deps context) that transforms message lists
- Message history is a simple linear list — no tree structure, no sections

### Context Assembly for LLM Calls
In `_agent_graph.py`, `_process_message_history()` chains processors sequentially:
```python
HistoryProcessor = (
    _HistoryProcessorSync | _HistoryProcessorAsync |
    _HistoryProcessorSyncWithCtx[DepsT] | _HistoryProcessorAsyncWithCtx[DepsT]
)
```
1. Start with `message_history`
2. Run through each HistoryProcessor in sequence
3. `_clean_message_history()` merges consecutive same-type messages
4. Result goes to the model

### No Built-in Compaction
Pydantic-AI deliberately does NOT include a compaction/summarization system. The HistoryProcessor pipeline is the extension point — users implement their own summarization strategies as processors.

## How They Control Token Budget

### UsageLimits
```python
@dataclass
class UsageLimits:
    request_limit: int | None = 50
    tool_calls_limit: int | None = None
    input_tokens_limit: int | None = None
    output_tokens_limit: int | None = None
    total_tokens_limit: int | None = None
    count_tokens_before_request: bool = False
```

### Token Checking
- **Before request**: `check_before_request()` checks request count and input token limits
- **After response**: `check_tokens()` checks input, output, and total token limits
- **Before tool call**: `check_before_tool_call()` checks tool calls limit
- **count_tokens_before_request**: Optional pre-flight token counting (supported by Anthropic, Google, Bedrock)
- Raises `UsageLimitExceeded` when limits are exceeded

### Usage Tracking
- **RequestUsage** - Per-request: input_tokens, output_tokens, cache_write/read tokens, audio tokens, details dict
- **RunUsage** - Aggregate across run: adds requests count, tool_calls count
- `incr()` method for in-place accumulation
- `__add__` for combining usages
- `extract()` classmethod uses genai-prices library for provider-specific extraction

## How They Decide on Running Compaction

They don't — there is no automatic compaction trigger. Users must implement this themselves via HistoryProcessor.

## How They Allow Custom Context Management

### HistoryProcessor Pipeline
The primary extension mechanism. Users provide a list of callables that transform `list[ModelMessage]`:
- Processors run in sequence before each LLM call
- Can filter, reorder, summarize, truncate messages
- Can be sync or async
- Can optionally receive dependency context (`DepsT`)
- Simple function signature: `messages -> messages`

### Message Cleaning
Built-in `_clean_message_history()` automatically merges consecutive same-type messages to maintain valid alternating structure.

## Context Components

1. **System Prompt** - Built separately via system prompt functions
2. **Message History** - Linear list of ModelMessages (user/assistant/tool)
3. **Tool Results** - Inline with messages
4. **No Sections** - No concept of message sections or categories

## Key Insights

### 1. Composable Pipeline Over Rigid Structure
The HistoryProcessor pipeline is the simplest possible extension point — just a function that takes messages and returns messages. No events, no hooks, no configuration objects. This makes it extremely easy to compose multiple strategies.

### 2. Separation of Concerns: Limits vs Context Management
Token limits (UsageLimits) and context management (HistoryProcessor) are completely separate systems. Limits are safety guardrails; context management is a processing pipeline.

### 3. No Opinion on Compaction Strategy
By not including built-in compaction, they force users to choose their own strategy. This avoids the problem of a one-size-fits-all approach but places more burden on users.

### 4. Clean Usage Tracking
The RequestUsage/RunUsage split is clean — per-request vs aggregate. The genai-prices integration for usage extraction is notable.

### 5. count_tokens_before_request
The ability to do a pre-flight token count before sending to the model is a useful feature for enforcing limits proactively rather than reactively.

## Recommendations for AgentContext

1. **HistoryProcessor-style pipeline** - Allow users to register callable transformers that run before each LLM call. Simpler than event systems.
2. **Separate limits from context management** - Keep token budget enforcement independent from context assembly.
3. **Pre-flight token counting option** - Allow checking token count before sending to avoid wasted API calls.
4. **Keep composability** - Multiple processors can be chained, each doing one thing (truncate, summarize, filter, deduplicate).
