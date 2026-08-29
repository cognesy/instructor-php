# Instructor Tell

The supported SDK, CLI, persistence, event, trace, and exit contracts are
tracked in [COMPATIBILITY.md](COMPATIBILITY.md).

Cold-start and discovery-scan budgets are tracked in
[STARTUP_BASELINE.md](STARTUP_BASELINE.md).

The static host primitive and rejected Context/Layer adapter are documented in
[STATIC_COMPOSITION_DECISION.md](STATIC_COMPOSITION_DECISION.md).

Application replacement seams and their dependency rules are documented in
[CONTRACTS.md](CONTRACTS.md).

The minimal factory-backed composition boundary is documented in
[HOST.md](HOST.md).

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

With no prompt, `tell` shows help: where a turn would run, which agents are
available, and what to type next. `--output=toon` and `--output=json` return
the same discovery as data; the formats that exist only to carry an answer have
no form for this screen and say so.

A turn defaults to `--output=human`: the answer rendered as Markdown for a
terminal. It decorates only when stdout is a terminal, so a redirected or piped
turn stays the plain Markdown the model wrote and remains usable as input to
something else. Use `--output=toon` for TOON, `--output=text` for the raw final
answer undecorated, `--output=json` for JSON terminal state, or
`--output=events` for a payload-free NDJSON stream using the versioned
`tell.event.v1` envelope.
List commands accept `--fields` for a smaller schema; session detail is
bounded unless `tell sessions show ID --full` is requested.

Put a prompt that matches a subcommand name after `--`, for example
`tell -- agents`. The explicit `tell tell "agents"` form is also available.

Use `--session NAME` to persist and continue a conversation. Without that
option, Tell performs no session storage I/O.

## PHP SDK

Tell is also controllable directly from PHP. The default request is stateless;
call `durable()` only when the application deliberately wants workspace
history. Use event callbacks for live lifecycle data and `runStream()` when a
worker, HTTP stream, or UI needs completed tool/inference checkpoints without
parsing terminal output.

```php
use Cognesy\Tell\Tell;
use Cognesy\Tell\TellRequest;

$tell = Tell::open(__DIR__);

$result = $tell->run(
    TellRequest::prompt('Summarize the release risks')
        ->connection('deepseek')
        ->model('deepseek-v4-flash'),
);

foreach ($tell->runStream(
    TellRequest::prompt('Investigate and report progress')
        ->onEvent(fn ($event) => $logger->info($event->type(), $event->data())),
) as $progress) {
    $reportProgress($progress->stepCount(), $progress->usage());
}
```

Consume the stream to completion before reading its `TellResult` via
`Generator::getReturn()`. A durable streamed turn publishes only after this
successful completion; abandoning the generator leaves its selected ref
unchanged.

Use workspace handles for intentional durable work. They return SDK values,
never arena or canonical records:

```php
$workspace = $tell->workspace();
$workspace->initialize();

$conversation = $tell->conversation('release-review');
$conversation->send(TellRequest::prompt('Record the decision.'));
$history = $conversation->history(limit: 10);
// Moves only this selector to empty; immutable history remains.
$conversation->clear();
```

`TellEvent::envelope()` returns the same safe `tell.event.v1` projection used by
NDJSON and default traces. `source()` remains available for an application that
deliberately needs the original typed Agent event; never serialize it at a
process boundary.

### Deterministic SDK tests

Applications can test Tell orchestration without HTTP calls or real provider
credentials. The convenience API scripts final responses:

```php
$result = Tell::testing($temporaryProject, 'scripted answer')->run(
    TellRequest::prompt('Exercise the integration.'),
);
```

Use `TellTestFactory::steps()` with Agents' `ScenarioStep` values for multi-step
tool, usage, and terminal-failure scenarios. This keeps Tell's request
compilation, tools, policies, events, workspaces, and persistence real; only
provider inference is replaced. The factory writes isolated test state under
`$temporaryProject/.tell-testing`, so callers should supply a temporary project
and own its cleanup.

## Host-scoped shell jobs

Applications that need a background command can opt into a separate resource
host. It is not booted by `Tell::open()`, the CLI, or the one-run protocol.
Denial is the default, so the embedding boundary must explicitly supply an
approval policy:

```php
use Cognesy\Tell\Resource\TellShellJobApprovals;
use Cognesy\Tell\Resource\TellResourceHost;
use Cognesy\Tell\Shell\TellShellJobRequest;

$resources = TellResourceHost::shellJobs(
    project: __DIR__,
    approval: TellShellJobApprovals::allowAll(),
)->boot();

try {
    $job = $resources->jobs()->start(
        TellShellJobRequest::command('php -S 127.0.0.1:8080')
            ->forMilliseconds(30_000),
    );
    $page = $resources->jobs()->read($job->id, after: 0);
    $finished = $resources->jobs()->cancel($job->id);
} finally {
    $resources->dispose();
}
```

Jobs may outlive `start()` but never their resource host or PHP process. Host
policy bounds their project-local working directory, concurrency, lifetime,
retained output, each cursored read, and cancellation grace. Public callers get
immutable snapshots and output chunks—not process, pipe, Cordis context, or
fiber handles. `tell.resource.event.v1` observations are distinct from agent
execution events and never contain commands, environment values, or output.

## External one-run protocol

Non-PHP supervisors can execute the same public request model through a small
process boundary:

```bash
request='{"schema":"tell.agent.request.v1","id":"job-42",'
request+='"prompt":"Review the release","mode":"stateless"}'
printf '%s\n' "$request" | tell agent --rpc --dir /path/to/project
```

The command reads exactly one JSON object (one line, at most 1 MiB) from stdin.
The request schema is `tell.agent.request.v1`:

```json
{
  "schema": "tell.agent.request.v1",
  "id": "job-42",
  "prompt": "Review the release",
  "agent": "default",
  "connection": "deepseek",
  "model": "deepseek-v4-flash",
  "reasoningEffort": "medium",
  "mode": "stateless",
  "tools": ["read_file"],
  "maxSteps": 5,
  "policy": {
    "maxRetries": 1,
    "timeoutMs": 30000,
    "maxOutputChars": 20000,
    "maxToolOutputChars": 4000,
    "maxToolCalls": 8
  }
}
```

Only `schema`, `id`, and `prompt` are required. `mode` is `stateless` by
default and may also be `durable` or `transient`; durable/transient requests can
select one `session` or `branch`. Unknown fields and schema versions are
rejected before inference. The boundary deliberately accepts no DSN, raw
provider options, credentials, headers, or pre-supplied `ask_user` answers.

Stdout contains only newline-delimited `tell.agent.frame.v1` objects. Sequence
numbers start at one and increase monotonically. A run emits zero or more
`progress` frames followed by exactly one terminal frame:

<!-- markdownlint-disable MD013 -->
| Terminal type | Meaning | Exit status |
| --- | --- | --- |
| `result` | completed run with a bounded answer and usage | `0` |
| `error` | invalid request, stopped budget, or failed run | `2` for invalid input; otherwise `1` |
| `cancelled` | cooperative caller/SIGINT cancellation | `130` |
<!-- markdownlint-enable MD013 -->

Each frame is capped at 1 MiB; terminal answers are UTF-8 safely capped at
200,000 bytes and carry `answerTruncated`. Prompts, tool arguments/results,
provider payloads, exception messages, credentials, and absolute workspace
paths are not serialized. Bounded human diagnostics are written to stderr.

Compatibility is schema-versioned, not inferred from the Tell package version.
Within `v1`, existing fields and meanings remain stable and new optional fields
may be added. Controllers must ignore unknown response fields but should reject
an unknown `schema`. Any breaking request or frame change requires a new schema
identifier and parallel support during a documented migration window. This is
a one-run protocol—not a resident daemon, bidirectional JSON-RPC session, or
pause/resume API. Cancellation uses the process signal/cooperative hook.

## Non-interactive questions

Tell never opens a terminal prompt. An agent can call its Tell-owned
`ask_user` tool only to consume an answer supplied before execution. Provide
ordered answers with repeatable `--answer`, or use one UTF-8 JSON array source:

```bash
tell --answer yes --answer production "run the release check"
tell --answers-file answers.json "run the release check"
printf '%s' '[{"id":"target","value":"production"}]' | \
  tell --answers-stdin "run the release check"
```

An array item is either a string (the next ordered answer) or an object with
`id` and `value`. IDs select exactly one matching `ask_user` call; an answer
outside declared choices, a missing answer, malformed input, duplicate IDs, or
an oversized value fails immediately with a typed tool result. Tell accepts at
most 32 answers of 8192 bytes each. Extra answers are reported only as a count.
Answers are redacted from normalized events and default traces. A completed
durable turn keeps the semantic tool result in its canonical history; a
transient turn does not publish it.

PHP callers provide the same bounded queue explicitly:

```php
use Cognesy\Tell\Capability\AskUser\TellAnswerQueue;

$request = TellRequest::prompt('Run the release check')
    ->withAnswers(new TellAnswerQueue([
        ['id' => 'target', 'value' => 'production', 'source' => 'cli'],
    ]));
```

## Durable project workspaces

Durable project history is opt-in. Initialize it once from the project root:

```bash
tell init
```

This creates a private, versioned `.tell/arena` in the project. On later turns
Tell discovers the nearest initialized workspace, compiles the selected
canonical history before the new prompt, and publishes a new immutable turn
only after a completed execution. Projects without `.tell/` keep the normal
stateless behavior.

The default durable conversation is `main`. `--session NAME` selects a
compatible named conversation; an existing legacy Tell session is imported once
when a durable named turn first needs it, then the canonical arena is
authoritative. Existing legacy session files are never rewritten by the
compatibility path.

Tell branches are immutable-head user references for planning independent lines
of work. Creation shares the existing canonical head; it never copies canonical
objects. Each workspace starts on `main`; use `checkout` to make a different
branch current, or pass `--branch` to select one invocation without changing
the workspace selection:

```bash
tell branch list --json
tell branch create review             # points at the current main head
tell branch create followup --from review
tell branch create scratch --empty
tell branch show review --json
tell checkout review
tell --branch main "compare the original plan"
```

Branch names are 1-64 lowercase ASCII characters, begin with a letter, and may
otherwise contain letters, digits, and hyphens. `main`, `internal-*`,
`session-*`, and `agent-*` are reserved; uppercase and Unicode are rejected to
avoid cross-filesystem case ambiguity. `list` and `show` only verify local refs
and canonical objects—no inference or writes occur. `create` atomically writes
only its new branch ref plus immutable creation provenance. Reset, checkout,
merge, rebase, deletion, and garbage collection are intentionally not part of
the `branch` command.

`tell reset` moves only one selected branch ref backwards, either by a bounded
number of parent links or to a verified reachable canonical ancestor. It never
deletes immutable objects, and deliberately has no public reflog; make a
recovery branch before moving a head if you need a durable return point:

```bash
tell branch create before-reset --from review
tell reset --branch review --steps 1 --json
tell reset --branch review --to <ancestor-hash> --json
```

The reset succeeds only if the selected head has not changed since validation;
a concurrent update fails safely rather than overwriting another turn.

PHP consumers can inspect any branch without changing the current checkout and
can pin a verified immutable head or root:

```php
$review = $tell->workspace()->branch('review');
$frozen = $review->pin();

$reviewHistory = $review->history(); // follows the named branch ref
$frozenHistory = $frozen->history(); // remains fixed after branch changes
$sameSnapshot = $tell->workspace()->ref($frozen->hash());
```

`TellBranch` and `TellRef` are read-only. Their bounded `history()`,
`transcript()`, and `context()` projections use the same canonical validation
and preview rules as conversation inspection. Mutation remains explicit on
`workspace()->branches()`.

Each branch may also keep secret-free runtime intent. It is versioned and
atomically updated, so configuration for one branch cannot modify another:

```bash
tell config show --branch review --json
tell config set connection '"deepseek"' --if-version 0 --branch review
tell config set model '"deepseek-v4-flash"' --if-version 1 --branch review
tell config set reasoningEffort '"medium"' --if-version 2 --branch review
tell config set output '"human"' --if-version 3 --branch review
tell --branch review --reasoning-effort low "review the release"
tell config effective --branch review --json
```

Allowed keys are `connection`, `model`, `reasoningEffort`, `output`, `tools`,
`maxRetries`, `timeoutMs`, `maxOutputChars`, `maxToolOutputChars`, and
`maxToolCalls`. `output` selects the default turn format for the branch and
accepts the same values as `--output`. Values are
labels, model names, tool profiles, and bounded policy values only: Tell
rejects credentials, tokens,
headers, raw environment values, and DSNs with embedded credentials. New
branches copy source intent by value and later edits remain independent.
Explicit connection, model, reasoning-effort, output, and tool flags take
precedence over branch intent. PHP callers select the typed value with
`TellRequest::reasoningEffort(TellReasoningEffort::Low)`; supported values are
`low`, `medium`, and `high`. Tell rejects provider/model combinations without
declared reasoning-effort support before inference and translates supported
intent to provider-native Polyglot options at runtime.
`effective` identifies the source of each branch/bundled value; it never
resolves or displays credential material.

## Providers and models

Tell reads connection presets and declared driver capability metadata from
Polyglot; it does not keep a second provider table. These inspection commands
need neither credentials nor network access:

```bash
tell providers --json
tell providers --fields=connection,provider,defaultModel,source --json
tell models deepseek --json
tell models qwen --json
tell config effective --branch review --json
```

`providers` lists the resolved connection precedence and its preset default
model. `models` accepts either a provider or a connection name and lists only
models explicitly declared by those presets. Full provider rows include known
context and tool/structured-output metadata with source provenance. Metadata
Polyglot does not declare—such as vision, thinking, or a full remote model
catalogue—is returned as explicitly unknown with a reason, never inferred from
model names. `config effective` reports the selected connection/model and their
sources without resolving or displaying an API key.

## Coding tools and direct dispatch

The default Tell agent exposes one bounded implementation for each canonical
coding operation: `read_file`, `write_file`, `apply_patch`, and `shell`.
Existing `read`, `write`, `edit`, and `bash` names remain compatibility aliases
over those same operations and policy. `apply_patch` validates all hunks before
writing, confines paths to the project, and never falls back to an arbitrary
shell command.

Shell agents can invoke the exact same registered tool without inference or
conversation publication:

```bash
tell tool read_file '{"path":"README.md"}' --json
tell tool apply_patch --input-file patch.json --json
printf '%s' '{"command":"printf ready"}' | tell tool shell --stdin --json
```

Direct dispatch validates one strict JSON argument object, applies the selected
branch's tool and execution policy, and returns a bounded structured result.
It may perform the named tool's declared file/shell side effect, but it never
runs a model or appends a Tell turn. Event output uses the same redacted,
versioned envelope as agent execution.

## Bounded child delegation

The built-in `spawn_subagent` tool gives a Tell agent one sequential delegated
run. The tool creates a private `agent-*` branch before it starts, records
non-secret policy/configuration provenance, and returns a bounded child result
to the parent on successful completion. `context: "fork"` starts at the
parent's captured canonical head; `context: "fresh"` starts from empty context.
Later parent changes cannot alter either start point.

Child branches use the same effective policy, tool registry, cancellation, and
redacted events as their parent. They can be listed or inspected with
`tell branch show`, `tell history --branch`, or `tell transcript --branch`, but
cannot be selected, reset, or written as normal user branches. Delegation is
depth-one and sequential: a child cannot create a grandchild, and it has no
authority to write parent or sibling refs. A failed, cancelled, or stale child
publication leaves the parent ref unchanged; completed child history remains
inspectable on its own branch.

## Execution budgets

Every Tell execution has finite policy defaults: zero provider retries, a 30s
wall deadline, 200,000 total model-output bytes, 40,000 bytes retained from one
tool result, and 100 tool calls. Override one invocation without persisting it:

```bash
tell --max-retries 2 --timeout-ms 60000 --max-output-chars 100000 \
  --max-tool-output-chars 12000 --max-tool-calls 20 "investigate the failure"
```

`SIGINT` is cooperative: Tell stops at the next public agent boundary, emits
one non-success terminal event, and does not publish a durable branch head for
the interrupted turn. This requires PHP's `pcntl` signal support; verbose CLI
output reports when it is unavailable. SDK callers can instead provide their
own public Agents cancellation source to `Tell::open()` for deterministic
programmatic cancellation.

The same limits are available through `TellRequest` (`maxRetries()`,
`timeoutMs()`, `maxOutputChars()`, `maxToolOutputChars()`, and
`maxToolCalls()`). Policy precedence is CLI/SDK override, branch config,
project defaults, user defaults, then bundled values. Project defaults live at
`.tell/arena/config/defaults.json`; user defaults live at
`~/.tell/config/execution-defaults.json`. Both are strict, secret-free JSON:

```json
{"schema":"tell.execution-defaults.v1","values":{"timeoutMs":60000,"maxToolCalls":20}}
```

Tell rejects invalid, zero, negative, or over-limit values before inference.
An exhausted deadline, output, or tool-call limit stops the turn; tool-result
truncation is explicit and UTF-8 safe. An incomplete stopped turn never moves a
durable ref, while a completed answer exactly at a limit may publish.

Use the workspace commands to inspect and manage the selected conversation:

```bash
tell history --json                 # bounded, oldest-first turn summaries
tell transcript --full --json       # ordered semantic messages and tool traces
tell context --json                 # compiled next-turn context, without inference
tell compact "keep release decisions" # explicit, provenance-linked summary
tell clear --json                   # make the selected ref empty; retain objects
```

`history`, `transcript`, and `context` are read-only: they do not resolve a
provider, build an agent loop, run tools, or change state. Their default output
is bounded; pass `--full` only when complete canonical content is required.
`compact` uses the configured inference connection and replaces the selected
ref with a concise immutable summary linked to the prior head. `clear` moves
only the selected ref to empty: it does not delete immutable records, legacy
sessions, traces, or configuration.

For a one-off experiment that may use the ordinary workspace context and tools
but must not change any conversation or session state, use `--transient`:

```bash
tell --transient "compare this approach without recording it"
tell --session review-1 --transient "inspect the current review safely"
```

Transient execution compiles the same selected history as a durable turn but
never writes canonical objects or refs, imports legacy sessions, saves mutable
sessions, or changes configuration. It stays stateless outside a workspace.
Text output states that nothing was persisted; JSON and TOON include
`execution.mode: transient` and `execution.durable: false`; events retain the
same normalized lifecycle envelope. Execution traces remain external
observations under the normal trace privacy policy.

`execution.mode` reports what the turn actually persisted, so it has three
values and is not a restatement of `--transient`:

<!-- markdownlint-disable MD013 -->
| `execution.mode` | `execution.durable` | Turn |
| --- | --- | --- |
| `durable` | `true` | Published an immutable arena turn, or saved a named session. |
| `transient` | `false` | Ran with the workspace context but deliberately wrote no conversation or session state. |
| `stateless` | `false` | Ran outside any initialized workspace with no named session, so there was nothing to publish. |
<!-- markdownlint-enable MD013 -->

`stateless` is the default outside a `.tell/` project. Consumers that branch on
`execution.mode` must accept all three values; `execution.durable` remains the
single boolean answer to whether conversation state was written.

Canonical workspace records contain semantic messages and tool-call/result
relationships only. Provider requests and responses, credentials, headers,
usage, timing, rendering data, traces, and absolute paths are not part of
canonical hashes. Immutable records can remain after a failed compare-and-swap
publication, compaction, or clear; Tell does not run garbage collection.

## Watching a turn happen

Two stderr channels report a turn in progress. `-v` writes it for a reader;
`--debug` writes it for a program. Both compose with whichever `--output`
format stdout was asked for, and neither can be combined with `--quiet`.

`-v` shows each step, each tool call with the argument that matters, and each
result:

```text
● step 1
  ▸ shell [check the suite]
    │ vendor/bin/pest packages/tell
  ✔ shell 812ms
    │ Tests:    342 passed (2033 assertions)
● step 2
  ▸ write `notes.md` (184 bytes)
    │ # Findings
    ⋯ 6 more lines
  ✔ write 3ms
● completed 2 steps, 4210 in / 318 out tokens
```

Known tools show what they are doing rather than their JSON arguments: a shell
call shows its command, a file call its path, a write its size, an edit the
lines it replaces. Anything else falls back to name plus arguments. Bodies are
previewed at twelve lines and elision is stated, not silent; `-vvv` stops
abridging.

`--debug` writes one bracketed `key=value` line per event instead:

```text
[step.start] step=1 messages=14 tools=8
[tool.start] name=shell step=1 args={"command":"vendor/bin/pest packages/tell"}
[tool.complete] name=shell status=ok step=1 duration=812ms result={"success":true,…}
[step.complete] step=1 toolCalls=yes errors=0 in=4210 out=318 finish=tool_calls
[execution.complete] status=completed steps=2 in=4210 out=318
```

Kinds and keys are the ones from the normalized `tell.event.v1` contract, so
the lines read against the same vocabulary as `--output=events`. `status` is
`failed` whenever the call failed or the tool returned its own failure
envelope. Payload values are always valid JSON and bounded to 512 bytes; an
excerpt is emitted as a JSON string and carries a companion `argsBytes` or
`resultBytes` giving the real size, so the presence of that key is what says
the value is an excerpt.

`--output=human` asks for a reader at a terminal, so with neither flag it gets
a third thing: one self-erasing line saying what the turn is doing.

```text
⠹ step 2 · shell: check the suite  14s
```

It names the step, the running tool and its salient argument, and how long the
turn has been going. The frames advance on a clock rather than on events,
because a PHP turn spends most of its wall clock blocked inside the inference
request where nothing in-process can run; the drawing happens in a forked
child that is killed and reaped when the turn ends. Where that is unavailable
the line still reports status, it just advances when something happens. A tool
that asks the person a question takes the terminal back for the duration.

The line exists only on a terminal. Redirect or pipe stderr and it is not
written at all - a line that erases itself is noise in a file - and `-v`,
`--debug`, and `--quiet` each supersede it.

Without any of this, `toon`, `text`, and redirected `human` output keep the
bare `[inference.start] step=N` heartbeat they have always written.

Whenever a channel wrote anything, a blank line follows it before the answer,
so progress never runs straight into the result. That separator goes on stderr
along with the progress that earned it, so a piped or redirected stdout is
unchanged.

Both channels show tool arguments and results, which no other Tell surface
does. That is what asking for them means, and it is why they exist only for
the invocation that asked: they are never persisted, never enter the
normalized `tell.event.v1` stream, and never reach an execution trace file.

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

Each default trace line is the same payload-free `tell.event.v1` envelope as
the NDJSON renderer: schema, stable kind, sequence, execution ID, selected
branch/session, bounded public metadata, and one terminal status. Prompts,
tool arguments/results, exception details, state snapshots, and provider
payloads are omitted by default. `includePayloads: true` adds a separate,
sanitized trace-only `payload` field; common credential fields remain redacted.
Put
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
