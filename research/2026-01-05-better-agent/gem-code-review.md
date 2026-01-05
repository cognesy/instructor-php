# Deep Dive: Improving PHP Agent Implementation based on "Mini Claude Code" Lessons

**Date:** 2026-01-05
**Topic:** Assessment of `instructor-php` Agent implementation against "Mini Claude Code" (v0-v4) principles.

## 1. Core Philosophy Alignment

The "Mini Claude Code" tutorial emphasizes **"The Model is the Agent"** (80% model, 20% code). The code's role is strictly to provide tools and a reliable execution loop.

**Assessment:**
The `instructor-php` implementation aligns well with this.
- **The Loop:** `Agent` class (extending `StepByStep`) implements the core `Think -> Act -> Observe` loop.
- **Tools:** The `Tools` collection and `ToolExecutor` provide the capabilities.
- **State:** `AgentState` manages the conversation history.

However, the PHP implementation is significantly more "heavyweight" due to its framework nature (Events, Drivers, Processors, immutable state objects). This offers robustness but risks over-engineering if not careful.

## 2. Feature-by-Feature Comparison & Improvements

### v0: Bash is All You Need
*Concept: A single `bash` tool + recursive process spawning = full agent.*

**PHP Implementation:**
- `BashTool` exists and uses a `Sandbox` for safety.
- **Gap:** The "recursive process spawning" (v0's subagent trick) isn't the primary subagent mechanism here. Instead, we have a structured `SpawnSubagentTool`.
- **Improvement:** Ensure `BashTool` is robust enough to handle complex commands without needing specialized tools for everything. The `BashTool` description suggests preferring dedicated tools (`read_file` vs `cat`). This contradicts v0 slightly ("Bash is all you need"), but aligns with v1 ("4 tools cover 90%"). **Keep the dedicated tools** for reliability and structured output (e.g., line numbers in `read_file`), but ensure `BashTool` is unhindered for general tasks.

### v1: Model as Agent (The Loop & Core Tools)
*Concept: `bash`, `read_file`, `write_file`, `edit_file` are the core.*

**PHP Implementation:**
- `Agent` class handles the loop.
- `FileTools` (`read/write/edit`) and `BashTool` cover the core set.
- **Improvement:**
    - **Tool output conciseness:** Check `BashTool` and `ReadFileTool` output truncation. `BashTool` has `MAX_OUTPUT_LENGTH = 50000`, which matches the tutorial.
    - **System Prompt:** Ensure the system prompt is simple and static. v1 uses a very minimal prompt. The PHP agent's prompt strategy isn't fully visible in the inspected files (likely in `AgentSpec` or configured externally), but we should verify it doesn't inject dynamic state that breaks caching (see below).

### v2: Structured Planning (Todos)
*Concept: `TodoWrite` tool to externalize the plan and prevent context fade.*

**PHP Implementation:**
- `TodoWriteTool` and `Task` classes exist.
- `TodoReminderProcessor` likely injects reminders.
- **Improvement:**
    - **Constraint Enforcement:** v2 enforces "Max 20 items" and "One `in_progress`". Ensure `TodoWriteTool` enforces configurable limits (X in progress, Y max items) in its logic to guide the model.
    - **Visibility:** Ensure the Todo list is rendered into the context effectively (e.g., as a tool result or appended message) so the model sees it every X (configured) turns. `PersistTasksProcessor` likely handles storage, but we need to ensure *presentation* to the model is consistent.

### v3: Subagents (Context Isolation)
*Concept: `Task` tool spawns a fresh agent with isolated history to prevent context pollution.*

**PHP Implementation:**
- `SpawnSubagentTool` creates a new `AgentBuilder` -> `Agent`.
- **Context Isolation:** It creates a fresh state `AgentState::empty()` with a new system prompt. This is **correct**.
- **Return Value:** It extracts `outputMessages()` and summarizes them.
- **Improvement:**
    - **Summary Logic:** The `summarizeResponse` method in `SpawnSubagentTool` uses regex (`preg_replace`, `preg_split`) and hard limits (6 lines). This is brittle.
    - **Recommendation:** Allow the subagent to return its own final summary naturally, or use a "cheaper" LLM call to summarize the output if it's too long. Or, simply return the full text if it's within a token budget (e.g., 2k tokens) to avoid losing nuance.
    - **Tool Filtering:** `createSubagent` filters tools. Ensure we prevent infinite recursion (e.g., limiting depth, which `SpawnSubagentTool` does via `$maxDepth`).

### v4: Skills (Knowledge Externalization)
*Concept: `Skill` tool loads `SKILL.md` content into context on demand. Crucially: **Append to history, do not modify system prompt** to preserve cache.*

**PHP Implementation:**
- `LoadSkillTool` loads content from `SkillLibrary`.
- `SpawnSubagentTool` can also bootstrap with skills.
- **Critical Check (Caching Economics):**
    - The v4 tutorial screams: **"Do not modify system prompt every time"**.
    - `LoadSkillTool` returns content as a **tool result**. This is **perfect**. It appends to the history as a User message (tool result).
    - `SpawnSubagentTool::appendSkillMessages` simulates tool executions (`ToolCall`, `AgentExecution`) to inject skills at the *start* of a subagent's life. This is also **good**—it establishes a base context without creating a dynamic system prompt string.
    - **Verify:** Ensure `AppendContextMetadata` or `TodoReminderProcessor` do not modify the *System Prompt*. They should append *User/Assistant messages* or *Tool results*. If `TodoReminderProcessor` modifies the system message, it breaks the cache for the entire session.

## 3. Specific Recommendations for `instructor-php`

### A. Strict Cache Discipline
Review `StateProcessors`.
- **Bad:** `$systemPrompt .= "\nCurrent time: " . date(...)`
- **Good:** `$messages->append(Message::user("Current time: " ...))`
- **Audit:** Check `TodoReminderProcessor`. If it injects the todo list into the system prompt, refactor it to inject it as a pseudo-tool-result or a user message at the end of the context window (or periodically).

### B. Refine Subagent Summarization
In `SpawnSubagentTool::summarizeResponse`:
- The current implementation is aggressive (`array_slice(..., 0, 6)`).
- **Change:**
  1. If response length < 2000 chars, return as is.
  2. If longer, consider a "smart truncation" (head + tail) or just the tail (most recent conclusion).
  3. Don't strip markdown (`stripMarkdown`) aggressively; formatting is useful for the parent agent to parse.

### C. Tool Output Standardization
- Ensure all tools return strings (or objects `__toString`-able).
- For `BashTool`, the `MAX_OUTPUT_LENGTH` is 50,000. This is reasonable but large. Ensure we don't accidentally fill the context with 50k chars of `npm install` logs.
- **Feature:** Add a `head/tail` logic to `BashTool` output formatting to prioritize the most relevant parts of the output (usually the error at the end).

### D. Skill "Hot-Swapping"
- v4 mentions "Skills are knowledge packages".
- Ensure `SkillLibrary` can load from a simple directory of `.md` files easily, supporting the `YAML` frontmatter + `Markdown` body format described.
- `LoadSkillTool` seems to implement this. Verify it parses the frontmatter correctly to provide the description to the LLM *before* loading the full content.

## 4. Conclusion
The `instructor-php` agent architecture is mature and aligns surprisingly well with the advanced concepts (v3/v4) of the tutorial. The separation of `Agent`, `Driver`, and `Tools` allows for the exact kind of "Model as Agent" composition advocated.

**Primary Focus:**
1.  **Cache Hygiene:** Audit all processors to ensure "append-only" context management.
2.  **Subagent Fidelity:** Improve `SpawnSubagentTool` output handling to avoid information loss.
3.  **Simplicity:** Keep tool definitions clean and prompts static.
