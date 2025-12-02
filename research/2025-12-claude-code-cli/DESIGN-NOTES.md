# Claude Code CLI integration — execution/control outline

Goal: Provide a dev-friendly PHP API (under `packages/auxiliary/src/ClaudeCodeCli`) to run `claude` commands in a controlled, sandboxed way similar to Beads, with stdout tracking for headless tasks.

Proposed components (mirror Beads patterns):

- **ExecutionPolicy** (ClaudeCodeCli\Infrastructure\Execution\ExecutionPolicy): wrap `Cognesy\Utils\Sandbox\Config\ExecutionPolicy` with defaults for claude runs:
  - Base dir: `getcwd()`
  - Timeout: 60s default (claude can be slower than bd), configurable
  - Network: enabled by default (LLM calls), toggleable
  - Output caps: e.g., 2–5MB stdout, 1MB stderr; configurable
  - Readable/writable: current project root; allow `.claude/` by default
  - Env: allow opt-in inherit + explicit additions; block sensitive vars via `EnvUtils`
- **CommandExecutor** interface: argv + optional stdin → `ExecResult`; expose policy.
- **SandboxCommandExecutor**: uses `Sandbox::host()` (or driver name) with policy, optional retries/backoff; thin wrapper around Sandbox `execute`.
- **ClaudeCommandBuilder**: construct argv for common flows:
  - Headless: `claude -p "<prompt>"` with `--output-format json|stream-json`, optional `--input-format stream-json`, `--max-turns`, `--model`, `--permission-mode plan`, `--append-system-prompt`, `--agents`, `--permission-prompt-tool`, `--add-dir`.
  - Resume/continue: `--continue`, `--resume <id>`, optional `--print`.
  - System prompt file: support file path injection for print mode.
  - Danger flags (e.g., `--dangerously-skip-permissions`) off by default, require explicit opt-in.
- **Result parsing**:
  - For `--output-format json`: parse to DTO capturing messages, cost, duration, exit code; preserve raw stdout for audit.
  - For `stream-json`: streaming parser that emits events; store complete transcript as array.
  - Surface stderr and exit codes for caller; allow downstream policy decisions.
- **Permission/plan defaults**:
  - Default to `--permission-mode plan` for analysis flows.
  - Option to switch to default/accept with explicit flag.
  - Provide hook configuration guidance (separate task) to align with project governance.
- **Driver selection**:
  - Configurable driver string (`host|docker|podman|firejail|bubblewrap`) mirroring Sandbox factory.
  - Default to host; allow image/bin overrides for containerized runs.
- **Retry strategy**:
  - Optional limited retries for transient failures (network/timeouts), with backoff similar to Beads (100ms * 2^(attempt-1)).
  - No retries on deterministic failures (exit code 2? permission denials?).
- **Contracts/DTOs**:
  - `ClaudeRequest`: prompt, mode (interactive/headless), args (model, permission, dirs, system prompt, max turns, output format), stdin payload.
  - `ClaudeResponse`: exit code, stdout raw, stderr, parsed messages (optional), cost/duration metadata, streamed events (optional).
  - `ExecutionContext`: driver, policy snapshot, timestamp, command argv.

Workflow sketch:

1) Caller builds `ClaudeRequest` (prompt + options).
2) `ClaudeCommandBuilder` produces argv and stdin.
3) `SandboxCommandExecutor` runs via Sandbox using configured driver/policy.
4) Parse stdout based on requested output format; capture stderr/exit code.
5) Return `ClaudeResponse` with raw + parsed data for higher-level orchestration.

Open follow-ups:

- Define precise stdout caps and timeout defaults after a few trial runs.
- Decide whether to expose hook authoring helpers or just document `.claude/settings.json` templates.
- Add tests: command builder arg ordering, parsing of json/stream-json, policy defaults, retry behavior.
