# Instructor Tell

`tell` is a small, non-interactive reference frontend for `cognesy/agents`.
It loads an agent template, builds the runtime through public APIs, and follows
the [Agent eXperience Interface](https://axi.md/) at its shell boundary.

```bash
tell
tell "summarize this repository"
tell describe --json
tell auth status openai
tell planes --full
tell tools --fields=name,description,deferred
tell agents
tell sessions
```

With no prompt, `tell` returns live workspace discovery plus useful next actions.
The default format is TOON. Use `--output=text` for a raw final answer,
`--output=json` for JSON terminal state, or `--output=events` for an NDJSON event
stream. List commands accept `--fields` for a smaller schema; session detail is
bounded unless `tell sessions show ID --full` is requested.

Put a prompt that matches a subcommand name after `--`, for example
`tell -- agents`. The explicit `tell tell "agents"` form is also available.

Use `--session NAME` to persist and continue a conversation. Without that
option, Tell performs no session storage I/O.

## Local storage and execution traces

Tell keeps its local concerns under one explicit runtime home. Set `TELL_HOME`
to override it; otherwise Tell uses `~/.tell` (`%USERPROFILE%\.tell` on Windows):

```text
~/.tell/
├── config/
│   ├── tell.json
│   ├── credentials.env
│   ├── connections/
│   └── agents/
├── runtime/
│   └── sessions/
└── logs/
    ├── executions/YYYY-MM-DD/<execution-id>.jsonl
    └── sessions/<session-id>-<stable-hash>.jsonl
```

Stateless turns receive one trace file per execution. Every named conversation
has a separate session trace; later turns append to the same file. JSONL writes
use an exclusive file lock, so independently running sessions never share a
target and concurrent appends cannot corrupt a line. Tell creates runtime and
log directories with private permissions and trace files with mode `0600` on
platforms that support POSIX permissions.

## Credentials and connections

Provider credentials resolve in a fixed order:

1. the process environment,
2. the selected workspace's `.env`,
3. `~/.tell/config/credentials.env`.

The Tell credential store is optional and created only by an explicit `auth
set`. It is written atomically with mode `0600` on POSIX systems. Values are
accepted only through stdin and are never included in `tell`, `describe`, auth
status, traces, or errors:

```bash
tell auth status openai --json
printf '%s' "$OPENAI_API_KEY" | tell auth set openai --stdin
tell auth remove openai
```

Tell never copies ambient credentials into its store. `auth status` reports
only whether a variable is configured and which layer supplied it. A missing
credential for a remote connection fails before inference with a safe action.
Local connections such as Ollama do not require a key.

Put user connection overlays in `~/.tell/config/connections/<name>.yaml`.
Workspace files under `config/llm/presets/` take precedence over user files,
which take precedence over bundled presets. `${VARIABLE}` placeholders in all
of them use the credential order above. Raw keys do not belong in `tell.json`.
The resolver is injected through Instructor Config's `CanResolveSecrets`
contract, leaving room for an OS-keychain source without changing connection
files or the data-plane runtime.

Each trace line contains schema, timestamp, event identity and level, agent,
session, workspace, and sanitized event data. Prompts, tool arguments, tool
results, state snapshots, and context payloads are omitted by default. Common
credential fields remain redacted even when payload capture is enabled. Put
this optional configuration in `~/.tell/config/tell.json`:

```json
{
  "schema": "tell.config.v1",
  "observability": {
    "executionTraces": true,
    "includePayloads": false,
    "maxStringLength": 4096
  }
}
```

Unknown configuration keys and invalid values fail loudly before inference.
Trace write failures are deliberately fail-open: the turn still runs and its
normal stdout contract is unchanged. Tell does not rotate or upload logs; the
directory is an external observability boundary for `tail`, `jq`, collectors,
and operator-managed retention.

Errors are structured data on stdout. Exit `0` means success, `1` means the
requested execution failed, and `2` means invalid usage. Unknown flags fail
loudly and include valid flags plus a command-specific help action.

Tell deliberately does not install ambient editor/session hooks or inject a
Tell-usage skill into agents. AXI is applied to the CLI contract only; adding
self-integration here would create a recursive Tell-teaches-Tell layer with no
workspace-state benefit.

`tell planes` exposes the logical operational map for Tell's own runtime
boundary. Agent turns are data-plane work; effective profile/tool resolution is
control-plane work; credential and session lifecycle plus agent inventory are
management-plane work. The data plane receives an already resolved LLM
configuration and owns only its selected trace target, and a trace sink
failure does not block inference. `--full` adds owned state, cross-plane
inputs/outputs, authority, and degraded behavior. These roles stay collocated in
one binary—they are not three parallel command trees or services.
