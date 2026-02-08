# Shotgun - Spec-Driven Development Platform

## Overview

Shotgun is a spec-driven development tool that turns natural-language feature requests into structured, codebase-aware implementation artifacts (research notes, specifications, plans, and task breakdowns). It sits upstream of AI coding agents like Cursor, Claude Code, or Windsurf — doing the thinking and planning so those tools can focus on writing code.

The core thesis: **understand the codebase deeply, then plan carefully, before generating any code.**

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | Python 3.11–3.13 |
| Agent Framework | Pydantic AI 1.44+ |
| LLM Gateway | LiteLLM (multi-provider proxy) |
| LLM Providers | Anthropic Claude, OpenAI GPT, Google Gemini, Ollama (local) |
| Graph Database | Kuzu (embedded) |
| Code Parsing | Tree-sitter (Python, JS, TS, Go, Rust) |
| TUI Framework | Textual 6.1+ (also serves as web UI via textual-serve) |
| CLI Framework | Typer |
| Templating | Jinja2 (for agent prompts) |
| DI | dependency-injector |
| Tokenization | tiktoken, sentencepiece |
| Observability | Logfire (Pydantic), PostHog (analytics) |
| Build | Hatchling, uv |
| Testing | pytest (async), Playwright (TUI testing) |
| Subprocess Integration | Claude Agent SDK (for autopilot mode) |

---

## Core Concept: Spec-Driven Development

Shotgun implements a phased workflow that mirrors how a senior engineer approaches a feature:

```
User Prompt
    |
    v
[1. Research]  -- Explore the codebase, understand existing patterns
    |
    v
[2. Specify]   -- Define requirements, constraints, edge cases
    |
    v
[3. Plan]      -- Create an implementation roadmap with stages
    |
    v
[4. Tasks]     -- Break the plan into concrete, actionable steps
    |
    v
[5. Export]    -- Format everything for AI coding tools (AGENTS.md)
```

Each phase produces a markdown artifact in the project's `.shotgun/` directory:

| File | Purpose |
|------|---------|
| `research.md` | Findings from codebase exploration and web research |
| `specify.md` | Formal requirements and acceptance criteria |
| `plan.md` | Implementation roadmap, often staged into multiple PRs |
| `tasks.md` | Concrete steps with alphanumeric stage labels (a, b, c...) |
| `AGENTS.md` | Formatted instructions for Cursor / Claude Code / Windsurf |

The user can review and refine at each stage before proceeding — or switch to "Drafting" mode to run all phases automatically.

---

## Agent Architecture

### Agent Types

Shotgun uses a **Router + Sub-Agent** pattern:

```
                   +-----------+
                   |  Router   |  <-- single user-facing agent
                   +-----+-----+
                         |
          +--------------+--------------+
          |       |       |      |      |
     Research  Specify   Plan  Tasks  Export
```

- **Router Agent** — Orchestrator. Interprets user intent, creates an `ExecutionPlan` (ordered list of `ExecutionStep`s), and delegates to sub-agents. Has read-only access to `.shotgun/` artifacts. Supports two modes:
  - *Planning mode* — pauses after each step for user review
  - *Drafting mode* — runs all steps without intermediate confirmation

- **Research Agent** — Explores the codebase via graph queries, reads files, and performs web searches. Writes findings to `research.md`.

- **Specify Agent** — Takes research context and user requirements to produce a formal specification in `specify.md`.

- **Plan Agent** — Creates a phased implementation roadmap in `plan.md`. Designed to break large features into staged PRs.

- **Tasks Agent** — Converts the plan into concrete, actionable steps in `tasks.md` with alphanumeric stage labels.

- **Export Agent** — Formats artifacts into `AGENTS.md` optimized for consumption by AI coding agents.

- **File Read Agent** — Specialized agent for loading file content (including PDFs, images via multimodal support).

### Agent Construction Pattern

All agents follow an identical factory pattern:

```python
async def create_XXX_agent(
    agent_runtime_options: AgentRuntimeOptions,
    provider: ProviderType | None = None,
    for_sub_agent: bool = False,
) -> tuple[ShotgunAgent, AgentDeps]:
    system_prompt_fn = partial(build_agent_system_prompt, "xxx")
    agent, deps = await create_base_agent(...)
    return agent, deps
```

Each agent gets:
- A Jinja2 system prompt template (`prompts/agents/<name>.j2`)
- A set of tools appropriate to its role
- Shared `AgentDeps` providing access to codebase service, model config, session usage tracking, and router mode

### Agent Response Model

Agents return structured `AgentResponse` objects containing:
- Text content
- Clarifying questions (for interactive refinement)
- File requests (to load additional context)

---

## LLM Integration

### Multi-Provider Architecture

Shotgun is provider-agnostic. LLM access flows through:

```
Agent (Pydantic AI)
    |
    v
ModelConfig (lazy model instantiation)
    |
    +-- Anthropic (direct SDK)
    +-- OpenAI (direct SDK)
    +-- Google Gemini (direct SDK)
    +-- OpenAI-Compatible (Ollama, custom servers)
    +-- Shotgun Account (LiteLLM proxy)
```

**Supported models include:**
- Anthropic: Claude Opus 4.5, Sonnet 4.5, Haiku 4.5
- OpenAI: GPT-5.1, GPT-5.2
- Google: Gemini 2.5 Flash Lite, Gemini 3 Pro, Gemini 3 Flash
- Local: Any Ollama-served model

### Configuration Resolution

Provider/key fallback chain:
1. Custom environment variables (`SHOTGUN_OPENAI_API_KEY`, etc.)
2. Ollama (local, no key needed)
3. Shotgun Account (LiteLLM proxy with budget)
4. BYOK (bring your own key via config)

### Prompt System

Prompts are Jinja2 templates in `src/shotgun/prompts/`:
- `agents/*.j2` — Per-agent system prompts (router.j2 is ~700 lines)
- `partials/` — Reusable template fragments
- `history/` — Conversation context formatting
- `state/` — State-dependent prompt sections

Context variables are injected via `AgentSystemPromptContext`, including codebase info, current artifacts, execution plan state, and user preferences.

---

## Codebase Knowledge Graph

A distinguishing feature — Shotgun builds a **graph database** of the codebase using Tree-sitter parsing and Kuzu:

### Indexing Pipeline

```
Source Files
    |
    v
Tree-sitter Parsing (Python, JS, TS, Go, Rust)
    |
    v
Entity Extraction (classes, functions, methods, imports)
    |
    v
Relationship Detection (calls, imports, inheritance, overrides)
    |
    v
Kuzu Graph Database (~/.shotgun-sh/codebases/)
```

### Graph Schema

**Node types:** Project, Package, Folder, File, Module, Class, Function, Method, FileMetadata, ExternalPackage, DeletionLog

**Relationship types:** CONTAINS_FOLDER, CONTAINS_FILE, DEFINES_CLASS, DEFINES_FUNCTION, CALLS_FUNCTION, CALLS_METHOD, IMPORTS, INHERITS, OVERRIDES, DEPENDS_ON_EXTERNAL

### Querying

Agents can query the graph in two ways:
- **Natural language** — translated to Cypher via LLM
- **Direct Cypher** — for precise structural queries

The `CodebaseService` provides a high-level API for listing, creating, updating, and querying graphs, with change detection via file hashing.

---

## Tool System

Tools are registered via a decorator-based system with categories:

| Category | Tools | Used By |
|----------|-------|---------|
| Codebase Understanding | `query_graph`, `retrieve_code`, `file_read`, `directory_lister`, `codebase_shell` | Research, sub-agents |
| Artifact Management | `read_file`, `write_file`, `append_file`, `insert_markdown_section`, `replace_markdown_section`, `remove_markdown_section`, `validate_mermaid` | All sub-agents |
| Web Research | Provider-specific search tools (Anthropic, OpenAI, Gemini, OpenAI-compatible) | Research |
| Planning | `create_plan`, `mark_step_done`, `add_step`, `remove_step` | Router |
| Delegation | `delegate_to_research`, `delegate_to_specification`, `delegate_to_plan`, `delegate_to_tasks`, `delegate_to_export` | Router |
| Agent Response | Structured response formatting | All agents |

Delegation tools use `prepare()` functions that gate execution — the Router can only delegate when appropriate conditions are met.

---

## User Interface

### TUI (Textual)

The primary interface is a terminal UI built with Textual:

- **ChatScreen** — Main conversation view with streaming responses, tool call display, and mode indicator
- **ModelPickerScreen** — LLM model selection
- **ProviderConfigScreen** — API key configuration
- **Command Palette** (`/`) — Quick access to features

**Key interactions:**
- `Shift+Tab` — Toggle Planning / Drafting mode
- `/` — Open command palette
- `Ctrl+U` — View token usage and cost stats
- `Escape` — Exit Q&A mode or close modals

### Web Mode

The same TUI can be served as a web application:
```bash
shotgun --web --port 8765
```
Uses `textual-serve` to render the Textual app in a browser.

### CLI

Direct command-line usage for specific operations:
- `shotgun run` — Run the router agent
- `shotgun codebase` — Manage knowledge graphs
- `shotgun config` — Configuration management
- `shotgun context` — Token usage analysis

---

## Autopilot Mode (Claude Code Integration)

Shotgun can execute its `tasks.md` stages automatically using Claude Code as a subprocess:

```
tasks.md (with stages a, b, c, ...)
    |
    v
AutopilotOrchestrator
    |
    v
For each stage:
  1. ClaudeSubprocess executes tasks
  2. Creates PR
  3. Reviews code
  4. Runs QA testing
  5. Gets user approval
  6. Moves to next stage
```

Uses the Claude Agent SDK to manage the subprocess lifecycle. Stage names are alphanumeric for fast parsing (the `LightweightTasksParser` achieves 20x faster startup by avoiding full LLM-based parsing).

---

## Conversation Management

### Persistence

Conversations are stored in `~/.shotgun-sh/conversation.json` using pydantic-ai's `ModelMessage` serialization:

```json
{
  "version": 1,
  "agent_history": [...],
  "ui_history": [...],
  "last_agent_model": "research|specify|plan|tasks|export",
  "updated_at": "..."
}
```

### Token Management

- Context analyzer tracks token usage by message type and tool
- Compaction strategy removes old messages when approaching limits
- Binary content filtering replaces large payloads with references
- Orphaned tool response cleanup

---

## Configuration

| Location | Purpose |
|----------|---------|
| `~/.shotgun-sh/config.json` | User settings (providers, API keys, app state) |
| `~/.shotgun-sh/conversation.json` | Persistent conversation history |
| `~/.shotgun-sh/codebases/` | Kuzu graph databases per project |
| `~/.shotgun-sh/logs/` | Session logs (`shotgun-<timestamp>.log`) |
| `.shotgun/` | Per-project artifacts (research, spec, plan, tasks, AGENTS.md) |

Settings are managed via Pydantic `ShotgunConfig` model with provider configs, Ollama settings, and app state.

---

## Testing

- **Unit tests** (`test/unit/`) — Fast, isolated, no external dependencies
- **Integration tests** (`test/integration/`) — Complete workflows with real databases
- **Performance tests** (`test/performance/`) — Benchmarks
- **Eval tests** (`evals/`) — LLM-as-judge evaluations for agent behavior
- **TUI tests** — Playwright-based interactive testing via web mode

Coverage requirement: 70%+ excluding cli/tui folders. All external APIs are mocked in tests.

---

## Key Architectural Patterns

1. **Protocol-Based Dependency Inversion** — `tui/protocols.py` defines runtime-checkable protocols (`QAStateProvider`, `ProcessingStateProvider`) to avoid circular imports between TUI components

2. **Jinja2 Prompt Templating** — Centralized, composable prompt management with partials and context injection

3. **Decorator-Based Tool Registration** — `@register_tool()` with category enum, display config, and automatic documentation

4. **Lazy Model Instantiation** — `ModelConfig` creates LLM model instances on demand, supporting provider switching without restart

5. **Structured Agent Responses** — All agents return typed `AgentResponse` objects rather than raw text, enabling structured interactions

6. **Staged PR Strategy** — Plans are designed to produce multiple focused PRs rather than one monolithic change, improving reviewability

---

## Summary

Shotgun occupies a unique niche: it's not a code generator, but a **code planning system**. It deeply understands a codebase through graph analysis, then orchestrates multiple specialized LLM agents through a structured workflow (research, specify, plan, tasks, export) to produce implementation artifacts. These artifacts can then be consumed by any AI coding tool. The architecture prioritizes codebase awareness, phased execution, provider independence, and human oversight at every stage.
