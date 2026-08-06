---
title: 'Eval Traces and Artifacts'
description: 'The EvalStep/AgentRun trace projection, the safe-by-default trace policy, the artifact layout written to disk, and the provenance and cost data that make one eval score comparable to another'
---

# Eval Traces and Artifacts

Every deterministic assertion, every judge, and every reporter reads the same underlying record of what the target did: a safe, structured projection of its execution, not a serialized copy of internal agent state. This page covers that projection, the policy that governs how much of it is safe to write to disk, the files a run leaves behind, and the provenance and cost data that make one eval's score comparable to another's.

## Why a projection, not a serialized `AgentState`

`AgentState`, `AgentStep`, and the `InferenceResponse` behind each step carry runtime detail that is too broad for a durable eval artifact -- in particular, raw provider response payloads and reasoning content that must never land in a file written to disk by default. Rather than serialize any of that, the target execution is projected into `EvalStep`, an immutable, purpose-built record of one step:

```php
$step->id();                  // AgentStepId
$step->turn();                // int -- stamped by the session, not AgentState
$step->index();                // int -- position within the accumulated run
$step->type();                 // AgentStepType: ToolExecution | FinalResponse | Error
$step->outputMessages();       // Messages -- what the step actually contributed
$step->requestedToolCalls();   // ToolCalls
$step->toolExecutions();       // ToolExecutions
$step->finishReason();         // ?InferenceFinishReason
$step->usage();                 // InferenceUsage
$step->duration();              // float
$step->stopSignal();            // ?StopSignal -- THIS step's signal, not the run's
$step->errors();                 // ErrorList
$step->hasErrors();              // bool
```

There is deliberately no `inputMessages()` accessor and no way to reach the raw `InferenceResponse` from an `EvalStep`. Two exclusions are load-bearing, not oversights:

- `toArray()` never calls `InferenceResponse::toArray()`, so provider response data and reasoning content cannot leak into a serialized trace.
- Per-step input messages are never serialized. `AgentStep::inputMessages()` holds the *entire* prior conversation, so writing it once per step would write the conversation N times over an N-step run -- quadratic for no benefit, since every step in a turn shares the same input.

`EvalSteps` is the immutable, ordered collection of steps across a run: `none()`, `with(...)`, `all()`, `count()`, `last()`, plus accumulated `usage()` and total `duration()`. Order is preserved across turns, not reset between them.

## Multi-turn semantics on `AgentRun`

`AgentRun` is the accumulated projection of an eval session, and it spans every `send()` call made on that session, not just the most recent one:

```php
$run->steps();      // EvalSteps -- every step across every turn, in order
$run->usage();       // InferenceUsage accumulated across all steps of all turns
$run->duration();     // float, summed across all turns
$run->stepCount();     // int
```

`stopSignal()` is the one accessor that does **not** aggregate:

```php
$run->stopSignal();  // the LAST turn's resolved StopSignal only
```

A three-turn conversation's `stopSignal()` reflects only how the third `send()` ended -- it is not a merge or a "worst of" across turns. If you need to know how an earlier turn stopped, read that turn's own `EvalStep::stopSignal()` from `steps()`, not `AgentRun::stopSignal()`.

## `EvalTracePolicy`: a data-handling decision, not a debugging convenience

Every target and judge run carries an `EvalTracePolicy` that governs how much of a tool call's arguments and result value is safe to keep. There are exactly two constructors:

```php
use Cognesy\Agents\Evals\EvalTracePolicy;

EvalTracePolicy::safe();                        // default everywhere
EvalTracePolicy::safe()->withPreviewBytes(512);  // widen the preview, still shape-only
EvalTracePolicy::full();                          // explicit opt-in: verbatim payloads
```

`safe()` is the default on every target and every judge; `full()` is reachable only by explicit construction and is never a default anywhere in the package. This is a data-handling decision, not a debugging convenience: tool arguments and results are where customer records, file contents, API responses, and credentials live, and by default the artifact reporter writes every eval's trace to `.instructor/evals` on disk. A default that leaked those values would leak them into every CI run's filesystem.

### What `safe()` digests

Under `safe()`, a tool call's arguments and its (successful) result value each serialize as a digest instead of the value itself:

```php
$policy->digest($value);
// ['hash' => 'sha256:...', 'bytes' => <int>, 'preview' => <string>]
```

- `hash` and `bytes` are computed over the value's real JSON encoding.
- `preview` renders the value's **shape**, never its content: strings become `<string:N>` (N is byte length), `<int>`, `<float>`, `<bool>`, and `<null>` are type placeholders, associative arrays expand recursively with keys preserved and every value elided the same way (up to a depth cap, beyond which a nested map collapses to `<object:N>`), and lists always collapse to `<array:N>` regardless of depth or nesting. `previewBytes` (default 120) is a backstop truncation on the rendered shape string, not a payload excerpt length.

```text
{"hash": "sha256:9f2b...", "bytes": 33, "preview": "{\"card\":\"<string:23>\"}"}
{"user": {"id": "<int>", "ssn": "<string:11>"}, "rows": "<array:40>"}
```

This is deliberately stronger than a bounded excerpt. An earlier revision of the policy rendered a bounded excerpt of the actual payload instead of its shape, and a short secret like `{"card": "SECRET-4111111111111111"}` (33 bytes) fit entirely inside that bound and was written out in full -- bounding a preview by size does not redact it, and credentials are short. Rendering shape instead of content closes that gap: `digest()` has **no size threshold** -- `shapeOf()` renders `<string:N>` whether the string is 4 bytes or 4 megabytes, so a short value is digested exactly like a large one. Nothing about a payload's length exempts it from digesting.

A value already in digest shape -- for example, hydrated from a previously written artifact, or forwarded verbatim by a remote target that constructed no policy of its own -- passes through unchanged on the next `toArray()` call rather than being digested a second time. `EvalTracePolicy::isDigest()` is the recognizer for that shape, and re-serialization is hash-stable because of it. Digesting is one-way: hydrating a previously digested value back through `fromArray()` does not attempt to recover the original text and cannot -- the digest shape carries no path back to the value it was computed from, so what you get on the next read is the same honest `{hash, bytes, preview}` placeholder, never a reconstructed payload.

### Error text is digested too, on four separate serialization paths

A failed tool call's error message is exactly as likely to embed a customer record, a credential, or a rejected card number as a successful result -- an exception message routinely repeats the offending input back (`"Invalid card number 4111..."`, `"HTTP 401 for ...?key=sk-live-..."`) -- so it gets no exemption from `safe()` just because it is error text rather than a return value. This is enforced independently at four sites, because error text is serialized through four different code paths rather than one:

1. `EvalStep::toolExecutions[].error` (`EvalStep.php`) -- a failed tool execution's message, in the per-step trajectory.
2. `AgentRun::tools[].error` (`AgentRun.php`) -- the same failed execution's message, in the run's legacy aggregate tool view (kept for `EvalContext`'s tool-name/tool-count assertions).
3. `AgentRun::errors` (`AgentRun.php`) -- the run-level, newline-joined string accumulated from every step's framework errors. This one used to be a separate, undigested code path with no policy involvement at all; it is now digested under `safe()` exactly like the other three, guarded so a clean run with nothing to report still serializes `''` rather than a digest of an empty string.
4. `EvalStep::errors[].message` (`EvalStep.php`) -- a distinct, step-level list of framework errors (as opposed to tool-execution errors), populated from the same kind of underlying exceptions and just as capable of embedding offending input.

One field is a deliberate exception to all of this: `EvalStep::errors[].message`'s sibling field, `errors[].class`, **stays in the clear**. A PHP exception's class name is not payload-derived -- it doesn't carry customer data -- and knowing whether a step failed with, say, a `ToolExecutionException` versus something else is useful for triage without opening a `full()` trace. Only the message text on that entry is digested; the class name next to it is not, and a reader who assumes the whole error object is opaque under `safe()` would be wrong about that one field.

None of this affects reading errors programmatically. `AgentRun::errors(): string` and `EvalStep::errors(): ErrorList` are accessors on the in-memory object, not the serialized trace, and they always return the raw, undigested value -- digesting happens only inside `toArray()`. This is exactly why `EvalContext::noFailedActions()` and the other deterministic assertions covered on the [eval assertions](22-eval-assertions.md) page keep working unchanged: they read the accessors, never the serialized form. If you need to assert on or inspect an error programmatically, read it from the run or step object directly; go to the serialized trace (or artifact) only for a redacted-but-stable record of what happened.

### What is never digested

Tool **names**, call **order**, **error flags** (`hasError`/`hasErrors`), **timing**, and **usage** all stay in the clear under `safe()`, deliberately. Every deterministic trajectory assertion -- `calledTool`, `toolOrder`, `notCalledTool`, `noFailedActions`, and the step/token assertions covered on the [eval assertions](22-eval-assertions.md) page -- reads these fields directly, so digesting them would break the harness's own ability to grade a trajectory. Only the payload values themselves (arguments, results, error messages) are digested.

### `full()` and when to reach for it

`EvalTracePolicy::full()` writes tool arguments, results, and error messages verbatim, with no digesting at all. It exists for local debugging of a specific failing case -- constructed explicitly, passed to a target (`LocalAgentTarget::fromFactory($factory, $policy)`) or a judge builder, and never left on for a suite that writes artifacts anywhere shared or durable. Nothing in the package ever falls back to `full()` on your behalf, including on the HTTP path: a remote target's response is passed through your configured policy before it reaches your assertions or an artifact, so attaching to a third-party agent does not silently widen what gets written to disk.

## The artifact layout

`ArtifactEvalReporter` writes a timestamped run directory (default root `.instructor/evals`) with one subdirectory per eval case, mirroring the case's id:

```text
.instructor/evals/<run>/
  summary.json
  results.jsonl
  evals/
    <case-id>/
      details.json
      target-trace.json
      target-steps.jsonl
      events.ndjson
      judges/
        001.json
        001-steps.jsonl
        002.json
        002-steps.jsonl
```

- `summary.json` -- one document per run: verdict counts, run-level provenance, and run-level token totals.
- `results.jsonl` -- one JSON line per eval result, in completion order.
- `details.json` -- the full serialized `EvalResult` for that case: verdict, assertions, the target run, and per-case provenance and token totals.
- `target-trace.json` -- `AgentRun::toArray()` for the target: reply, status, steps, stop signal, and (when resolved) the target's LLM profile.
- `target-steps.jsonl` -- one `EvalStep::toArray()` per line, in order.
- `events.ndjson` -- one line per event dispatched during the target's execution, `{"type": "<fully-qualified event class>", "data": {...}}`.
- `judges/NNN.json` / `judges/NNN-steps.jsonl` -- one pair per judged assertion whose `JudgeScore` carries the judge's own `AgentRun`; `NNN` numbers assertions in the order the case recorded them (insertion order, not filesystem or hash order), so numbering is stable across repeated runs of the same eval. A lightweight judge that returns a score with no run -- `FakeAgentJudge`, or any `CanJudgeAgentEval` that doesn't attach one -- produces no `judges/` file for that assertion; its score is already fully captured in `details.json`. `NNN.json` carries the assertion name, label, score, reason, evidence, and a *concise* run summary (status, step count, tool count, usage, duration, stop signal, LLM profile, `guardsWarningObserved`); the judge's full step-by-step trace lives only in the sibling `-steps.jsonl` file, so it is never duplicated between the two.

There is no `target-messages.json` file, and none is written under any policy. Nothing reachable in the eval pipeline actually captures a per-turn full-conversation snapshot -- `EvalStep` deliberately carries no input messages (see above), and no session writes one out of band either -- so emitting the file would mean fabricating its contents. A full verbatim conversation also embeds every tool argument and result exactly as sent, which would bypass `safe()`'s digesting entirely and reintroduce the payload-leak class the trace policy exists to close. If you need the raw conversation for local debugging, that has to come from your own logging around the target, not from the eval artifact.

## Provenance: why a score needs context to mean anything

A score is only meaningful next to another score, and once the judge is itself a nondeterministic multi-step agent, a target regression is indistinguishable from a judge-model change or a judge prompt revision unless the configuration that produced the score is recorded alongside it. `summary.json` and every `details.json` carry a `provenance` block for exactly this reason:

```json
{
  "provenance": {
    "target": {
      "driver": "openai",
      "model": "gpt-5-target",
      "maxTokens": 1024,
      "contextLength": 8000,
      "maxOutputLength": 4096
    },
    "judge": {
      "class": "Cognesy\\Agents\\Evals\\AgentLoopJudge",
      "llm": { "driver": "openai", "model": "gpt-5-judge", "maxTokens": 1024, "contextLength": 8000, "maxOutputLength": 4096 },
      "temperature": null,
      "guardsWarningObserved": true
    },
    "package": { "version": "2.6.2", "gitSha": "fb88527c9" },
    "startedAt": "2026-08-05T09:12:44+00:00",
    "repeat": 1
  }
}
```

- **`target`** is the resolved `LLMConfigProfile` the target loop actually built with -- driver, model, and its numeric context/output limits. It carries no API key, no base URL, and no other credential; that type exposes only those five fields. It is `null` when the underlying loop never resolved an `LLMConfig` (some test doubles) or when a remote HTTP target's payload didn't supply one -- reported as absent, never guessed.
- **`judge`** is present only when at least one assertion's `JudgeScore` carried the judge's own `AgentRun` -- a lightweight judge that returned a score but no run contributes nothing here. `class` is the real `CanJudgeAgentEval` implementation observed at the point the assertion was resolved, never inferred from the shape of the result; a judge resolved through some other path than the case's normal expectation-resolution reports `class: null` honestly rather than guessing.
- **`judge.temperature` is always `null`.** This is not a placeholder for a future field -- there is no way to read a built judge loop's inference options back out once it exists, so the actual sampling temperature in effect for a given judge run is genuinely unrecoverable after the fact. `UseJudgeInference` (covered on the [eval judges](23-eval-judges.md) page) defaults new judge builders to `temperature: 0.0`, but that is an opt-in construction-time default, not something `AgentLoopJudge` installs on your behalf or something the harness can observe and report back. Reporting `0.0` here would assert an observation that never happened.
- **`judge.guardsWarningObserved`** is `true` when a `JudgeGuardsNotConfigured` event appears on that judge run's own events, and `false` otherwise -- it is derived from the *presence* of the warning, never from its absence, because absence does not prove guards were configured. Read the caveat below before treating a `false` here as "this judge was guarded."
- **`package`** and **`startedAt`** come from the reporter's own environment, not from anything the target or judge exposes: `gitSha` from a pure filesystem walk to the nearest `.git` (never shells out, resolves `null` outside a checkout or when the ref can't be read), `version` from Composer's `InstalledVersions` (`null` when the package isn't installed via Composer, e.g. a path repository with no lock entry). Neither is fabricated when unavailable.
- **`repeat`** is always `1` for a single run; per-trial repetition and pass-rate reporting are covered on the running-evals page.

### The `guardsWarningObserved` asymmetry

`JudgeGuardsNotConfigured` is dispatched at most **once per `AgentLoopJudge` instance**, on the first `judge()` call that finds no `UseGuards` capability installed -- an internal flag on the judge suppresses every later dispatch from that same instance, even though the judge remains exactly as unguarded on every subsequent call. If one `AgentLoopJudge` instance backs two judged assertions in the same case and neither installed `UseGuards`, only the *first* assertion's own `AgentRun` carries the warning event; the second assertion's `judges/NNN.json` reports `guardsWarningObserved: false` for its own run, even though that judge call was just as unguarded as the first.

At the `EvalResult` and `EvalRunResult` level this is largely papered over -- `provenance()` at both levels reports `true` if *any* judged assertion in scope observed the warning -- but reading a single `judges/NNN.json` file in isolation and seeing `guardsWarningObserved: false` does not mean that particular judge call was guarded. It may only mean an earlier call on the same instance already used up the one warning it was ever going to emit.

## Cost: target and judge, never folded together

`summary.json`, every `details.json`, and the console footer all report token usage split by who spent it:

```json
{ "tokens": { "target": 4210, "judge": 9880, "total": 14090 } }
```

`target` sums `InferenceUsage` across the target run's own steps; `judge` sums usage across every judged assertion's own judge `AgentRun` (a lightweight judge with no run contributes `0`, never an error). The split is deliberate and never collapsed: once a judge is itself a multi-step agent with its own evidence tools, judging routinely costs several times what the target run cost, per assertion, per case, per suite run -- folding the two together would hide exactly the cost signal that matters most for controlling an agentic eval suite's spend.

## Console output

`ConsoleEvalReporter` stays compact in normal mode -- one line per eval, plus a summary and a token footer at the end of the run:

```text
passed=3 failed=1 scored=0 skipped=0
TOKENS target=4210 judge=9880 total=14090
```

Verbose mode (`EvalRunOptions::default()->withVerbose(true)`) adds a target line, a judge line per judged assertion, and evidence lines underneath it:

```text
TARGET steps=1 tools=0 tokens=142 stop=completed
JUDGE score=0.40 steps=1 tools=1 tokens=15
  EVIDENCE no verification step observed
  EVIDENCE policy requires order-owner check
```

`ConsoleEvalReporter::fromWriter(Closure $write, bool $verbose = false)` takes any `Closure(string): void` as its sink, so it can write to STDOUT, a buffer, or anywhere else you choose.

## PHPUnit and Pest failure messages

The test-suite reporters translate a failed run into a framework failure; they never re-execute anything. `EvalTestFailureMessage::fromResult($runResult)` builds a failure string that includes, for every failing case, the target's step count and stop reason and every failed judged assertion's evidence -- so a judge-driven failure is diagnosable straight from CI output, without opening an artifact:

```text
Agent eval suite failed (1 failed, 0 scored, 0 reporter errors; strict mode).
- [failed] provenance/failure-message — Refund replies require verification.
  - target: steps=1 stop=completed
  - closedQa [gate]: score 0.40, required 1.00
    - evidence: no verification step observed
```

## Choosing and combining reporters

A run can use any number of reporters at once; each one receives every `onRunStarted` / `onEvalCompleted` / `onRunCompleted` callback independently:

```php
use Cognesy\Agents\Evals\ArtifactEvalReporter;
use Cognesy\Agents\Evals\ConsoleEvalReporter;
use Cognesy\Agents\Evals\EvalConfig;
use Cognesy\Agents\Evals\JUnitEvalReporter;

$config = EvalConfig::default()
    ->withReporters(
        ConsoleEvalReporter::fromWriter(fn (string $text) => fwrite(STDOUT, $text)),
        new ArtifactEvalReporter('.instructor/evals'),
        new JUnitEvalReporter('build/eval-results.xml'),
    );
```

`JUnitEvalReporter` writes a single JUnit XML document at `onRunCompleted`: a `<testcase>` per eval, `<failure>` for a `Failed` verdict (and for a `Scored` verdict when the run is strict), and `<skipped>` for `Skipped`. It is meant for CI systems that consume JUnit XML directly, as an alternative or complement to failing the PHPUnit/Pest process itself.

## Where to go next

- **[Eval assertions](22-eval-assertions.md)** -- the deterministic assertion catalogue that reads tool names, order, and error flags directly from the trace this page describes.
- **[Eval judges](23-eval-judges.md)** -- what a judge can and cannot see through the trace under `safe()`, and why judge run cost is reported separately from target cost.
- **Running evals** -- the CLI, `--repeat=N` and pass-rate verdicts (and how `provenance.repeat` reflects it), and wiring these reporters into PHPUnit and Pest.
