# Claude Code CLI research

Objectives:

- Understand Claude Code CLI capabilities, constraints, and control surfaces for automation.
- Mirror the controlled invocation approach used in `packages/auxiliary/src/Beads` for a PHP-facing API under `packages/auxiliary/src/ClaudeCodeCli`.
- Capture findings in one place before drafting integration designs.

Sources reviewed:

- docs-internal/claude-code/getting-started/overview.md
- docs-internal/claude-code/getting-started/quickstart.md
- docs-internal/claude-code/getting-started/common-workflows.md
- docs-internal/claude-code/reference/cli-reference.md
- docs-internal/claude-code/reference/hooks-reference.md

Key capabilities and workflows:

- Primary modes: interactive `claude`, headless `claude -p "prompt"`, continuation (`--continue`/`--resume`), and piped stdin for Unix-style chaining.
- Plan Mode supports read-only exploration: `--permission-mode plan` or Shift+Tab during a session; useful for safe analysis before edits.
- Common flows show Claude handling navigation, refactors, tests, PR prep, and Git commands with explicit user approvals; auto-accept mode exists but default is gated edits.
- Output control for scripting: `--output-format text|json|stream-json`, optional `--include-partial-messages` for streaming, and `--input-format stream-json` when feeding structured turns.
- Session management: resume by ID, continue last session, or start with preset prompt; supports conversation storage and restoration of tool state.

CLI commands and flags relevant to controlled execution:

- Core invocations: `claude`, quoted prompt startup, `-p/--print` for one-shot SDK-style runs, `-c/--continue`, `-r/--resume`, and `claude update`.
- Context scoping: `--add-dir` to whitelist extra directories; `--permission-mode` to start in plan/accept/bypass; `--dangerously-skip-permissions` for fully ungated
- 
- runs (avoid by default).
- Agent customization: `--agents` JSON definition for subagents (description, prompt, allowed tools, optional model).
- Prompt controls: `--system-prompt`, `--system-prompt-file` (print mode), `--append-system-prompt` to extend defaults.
- Execution bounds: `--max-turns` for non-interactive limits, `--model` selection (aliases or full names), `--verbose` for turn-by-turn logs.
- Permission handling in automation: `--permission-prompt-tool` to route permission prompts via MCP in non-interactive runs.
- IO flexibility: piping via stdin works; supports structured outputs for downstream parsing (JSON/stream-json).

Hooks and governance surfaces:

- Hooks configured via `.claude/settings.json` (or user/global equivalents) with matcher-driven arrays per event.
- Supported events include `PreToolUse`, `PermissionRequest`, `PostToolUse`, `Notification`, `UserPromptSubmit`, `Stop`, `SubagentStop`, `PreCompact`, `SessionStart`, and `SessionEnd`.
- Matchers filter by tool (`Read`, `Edit`, `Bash`, `Task`, etc.) or notification type; `*` or regex allowed. Some events omit matchers.
- Hook types: `command` (bash) or `prompt` (LLM-backed decisioning for Stop/SubagentStop/UserPromptSubmit/PreToolUse/PermissionRequest).
- Control mechanics:
  - Exit code 0 proceeds; 2 blocks with stderr reason; other codes are non-blocking noise.
  - JSON stdout allows structured control: permission decisions (`allow`/`deny`/`ask`), updated tool inputs, additional context, stop/continue toggles, and decision reasons.
  - `PermissionRequest` hooks can auto-allow/deny and adjust inputs; `PostToolUse` can block further steps or add context.
  - `UserPromptSubmit` can inject context or block prompts; `Stop`/`SubagentStop` can block stopping and request follow-up.
- Environment aids: `$CLAUDE_PROJECT_DIR` for project-local scripts; `$CLAUDE_ENV_FILE` (SessionStart) to persist env mutations for later tool calls. Plugins use `${CLAUDE_PLUGIN_ROOT}`.
- Hooks run in parallel when multiple match; prompt hooks use Haiku for fast policy decisions.

Existing sandbox utilities to align with Claude execution:

- `packages/utils/src/Sandbox/Config/ExecutionPolicy` defines immutable constraints: base dir, wall/idle timeouts, memory clamp, readable/writable paths, env map with optional inheritance, network toggle, stdout/stderr caps.
- `packages/utils/src/Sandbox/Drivers/HostSandbox` creates a locked-down temp workdir per run, builds a filtered environment via `EnvUtils::build` (blocks sensitive vars), executes with `SymfonyProcessRunner` + `TimeoutTracker`, caps stdout/stderr, and cleans up via `Workdir::remove`.
- Other drivers (docker/podman/firejail/bubblewrap) are selectable through `Sandbox::using('driver')`, giving pluggable containment for command execution.
- For the Claude CLI wrapper, reuse `ExecutionPolicy` + `HostSandbox` (or container driver) to bound `claude` processes, feed stdin, and capture `ExecResult` with enforced time/IO/memory limits similar to Beads safety posture.

Patterns from Beads execution layer to mirror:

- `packages/auxiliary/src/Beads/Infrastructure/Execution/ExecutionPolicy` wraps Sandbox policy with defaults (30s timeout, network on, stdout/stderr caps, readable/writable paths) tailored to bd/bv runs.
- `SandboxCommandExecutor` constructs `Sandbox::host(...)`, executes argv with optional retries and exponential backoff, and exposes policy accessor; retry count configured via service provider/env.
- `CommandExecutor` interface enforces argv + optional stdin, returning `ExecResult`; keeps execution concerns separate from parsing/DTO layers.
- This structure offers a template: define a Claude-specific policy factory, executor with retries/timeouts, and keep CLI parsing/translation elsewhere in `ClaudeCodeCli` package.

Notes for integration design (next step):

- Headless `-p` with `--output-format json` or `stream-json` is the primary automation entry point; track stdout for cost/duration metadata.
- Permission gating should default to plan/ask; avoid `--dangerously-skip-permissions` unless explicitly requested.
- Hook surfaces enable pre-flight validation and post-call auditing, aligning with controlled execution similar to Beads; consider project-level settings authoring alongside PHP API.
- Need to inspect `packages/auxiliary/src/Beads` patterns to mirror safe command execution, logging, and stdout tracking for Claude Code CLI wrapper.
