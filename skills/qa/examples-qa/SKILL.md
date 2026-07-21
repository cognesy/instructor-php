---
name: examples-qa
description: Verify Instructor behavior through the repository's ./examples/ suite in pass, live record, or hermetic replay mode. Use when running selected examples or the corpus, capturing and reusing recorded LLM HTTP responses, diagnosing hub results, or distinguishing real errors, assertion failures, skipped examples, and live-only flaky outcomes.
---

# Instructor examples QA

Use the examples suite as an end-to-end check of Instructor behavior, but keep the
execution mode explicit. A live run tests provider and transport integration; a
record run creates reusable HTTP fixtures; a replay run tests the current code
against fixed provider responses without spending tokens or contacting a network.

## Inspect before running

Run commands from the repository root. Discover the target and its canonical
selector first:

```bash
php bin/instructor-hub list
php bin/instructor-hub show getters_and_setters
```

The hub accepts a front-matter `docname`, numeric index, short ID such as `xe4bb`,
or example path. Read the target's `run.php` before diagnosing it. Check its tags
and whether it builds an HTTP client implicitly or supplies a custom client/mock.

Treat these sources as authoritative when the prose documentation differs:

- `just/examples.just` — preferred record/replay command wrappers
- `examples/boot.php` and `examples/Support/HttpRecordingBoot.php` — mode and
  recording-directory behavior
- `packages/hub/src/Commands/EnhancedRunAllExamples.php` — corpus filtering and
  `OK`/`ASSERT`/`ERROR`/`FLAKY` classification
- `packages/hub/src/Services/ExampleRepository.php` — selector resolution
- `QUALITY.md` — repository QA policy and operational caveats

## Choose the execution mode

Use the smallest mode that answers the question:

| Mode | Command shape | What it proves |
| --- | --- | --- |
| pass | `INSTRUCTOR_EXAMPLES_HTTP=pass ...` or no mode | Normal behavior; no record/replay middleware is attached. LLM examples use the live provider. |
| record | `just examples-record <selector>` | Live provider call succeeds and the response can be persisted as a sanitized cassette. |
| replay | `just examples-replay <selector>` | Current code consumes the fixed response correctly, with no network or provider credentials required. |

## Run without record/replay

Use pass mode for a normal baseline or a live provider smoke check:

```bash
INSTRUCTOR_EXAMPLES_HTTP=pass \
  php bin/instructor-hub run getters_and_setters
```

Omitting `INSTRUCTOR_EXAMPLES_HTTP` has the same effect because `pass` is the
default. Pass mode attaches no ambient middleware. LLM examples therefore need
their normal provider keys and network access; mock-only examples remain local.

For a code change, prefer this sequence:

1. Run a narrow live or existing-fixture baseline when provider integration is in scope.
2. Record only the affected examples if the fixture is missing or intentionally refreshed.
3. Replay the same selector to verify deterministic behavior.
4. Run the replay gate for the affected group, then the full replay corpus when practical.
5. Run package tests separately when the example result does not identify the failing layer.

Do not treat a replay pass as proof that provider authentication, DNS, TLS, rate
limits, or current provider behavior work. Do not treat a live LLM failure as a
product regression until it has been reproduced with replay or an appropriate
unit/integration test.

## Record one example

The preferred scratch-fixture workflow is:

```bash
just examples-record getters_and_setters
```

The wrapper sets:

```text
INSTRUCTOR_EXAMPLES_HTTP=record
INSTRUCTOR_EXAMPLES_RECORDINGS_DIR=examples-recordings
```

The live response is stored under a namespaced directory such as:

```text
examples-recordings/A01_Basics/BasicGetSet/
├── cassette.json
└── interactions/000001/
    ├── interaction.json
    └── response.body                 # or response.chunks.ndjson
```

Record the whole corpus only deliberately; it requires keys for every provider
used and can be expensive:

```bash
just examples-record-all --limit=20
just examples-record-all --filter=errors
```

To create fixtures in each example's co-located `recordings/` directory, omit the
recordings-directory override:

```bash
INSTRUCTOR_EXAMPLES_HTTP=record \
  php bin/instructor-hub run getters_and_setters
```

The direct hub sugar is equivalent and is useful when keeping the mode visible at
the invocation site:

```bash
php bin/instructor-hub run getters_and_setters --record
php bin/instructor-hub all --record --limit=20
```

The `bin/instructor-hub` launcher consumes `--record`, `--replay`, and
`--recordings-dir=<dir>` before dispatching the hub command. If using a custom
directory, use the same directory for record and replay:

```bash
php bin/instructor-hub run getters_and_setters \
  --record --recordings-dir=examples-recordings
```

Recording is live. Confirm the relevant provider key exists, expect token/network
cost, and do not record broad personal or production data. Common credentials are
sanitized before persistence, but prompts, model outputs, PII, and provider-specific
secrets still require review.

Streaming recordings are published only after the stream completes naturally. An
interrupted or failed stream must not be treated as a valid fixture.

## Replay selected examples

Replay from the same root used for recording:

```bash
just examples-replay getters_and_setters
```

Or use the direct form:

```bash
php bin/instructor-hub run getters_and_setters --replay \
  --recordings-dir=examples-recordings
```

For keyless replay through a raw example path:

```bash
INSTRUCTOR_EXAMPLES_HTTP=replay \
INSTRUCTOR_EXAMPLES_RECORDINGS_DIR=examples-recordings \
php examples/A01_Basics/BasicGetSet/run.php
```

Replay is hermetic by default for requests that enter the configured ambient
middleware. It is not a blanket network sandbox: examples that construct their
own client can bypass the hook and need the `no-replay` treatment or separate
inspection. For intercepted requests, the examples boot hook provisions dummy
provider keys only so configuration resolution succeeds; the recorded response
should be served before any provider driver can authenticate or open a network
connection. A missing recording, request mismatch, exhausted ordered session,
corrupt payload, or unsupported cassette version is a diagnostic failure, not a
reason to fall back to the live provider.

Replay the corpus with optional controls:

```bash
just examples-replay-all --limit=20
just examples-replay-all --filter=errors
php bin/instructor-hub all --replay --dry-run
php bin/instructor-hub all --replay --filter=errors
```

`--dry-run` verifies the selected set without executing it. Use `--filter=errors`
after a tracked run to focus on previously failing examples; use `--force` when the
hub's status cache would otherwise omit an example.

## Understand what is actually covered

The ambient hook in `examples/boot.php` attaches middleware through
`HttpClientDefaults` only when the example's runtime resolves an implicit HTTP
client. Examples that construct their own client, use a mock driver, or are marked
`no-replay` may not exercise the cassette path. Inspect the example before calling
an unchanged result evidence for record/replay.

Corpus filtering is intentional:

- `skip: true` examples are never selected.
- The `broken` tag skips known-broken examples in both modes.
- The `no-replay` tag skips examples that cannot be recorded/replayed when replay
  mode is active; the hub logs the skipped set.
- The `flaky` tag describes live LLM nondeterminism. It does not make a replay
  failure acceptable.

No individual example needs to be edited to enable the shared mode switch. Avoid
bulk-editing the example corpus just to add record/replay setup.

## Read hub output

For `php bin/instructor-hub run`, the hub executes one example and reports `OK` or
`ERROR`; the detailed cause comes from the example subprocess and its
`ExecutionResult`. For `hub all`, `EnhancedRunAllExamples` prints one row per
example and a summary. Its result categories mean:

| Output | Meaning | Gate effect |
| --- | --- | --- |
| `OK` | The example process completed successfully, including its assertions. | Pass. |
| `ASSERT` | The example's assertion/expected-behavior check failed. | Real failure; inspect the assertion and current response. |
| `ERROR` | The process failed for a non-assertion reason: exception, missing cassette, provider/transport failure, syntax/runtime error, or infrastructure problem. | Real failure. |
| `FLAKY` | A live-mode example tagged `flaky` failed and the hub tolerated it. | Does not fail the live corpus gate, but is not proof of correctness. |
| skipped | The example was filtered by `skip`, `broken`, or replay-only `no-replay`. | Not evidence of a pass. |

The `flaky` exception applies only to live mode. In replay mode the response is
fixed, so a failure from a `flaky`-tagged example is counted as an error. This is
the mechanism that turns a recorded flaky scenario into a deterministic regression
check.

The all-examples exit code fails when real errors remain; tolerated live `FLAKY`
rows do not fail it. The final summary and the per-example diagnostic line are more
authoritative than a process that merely printed some expected-looking output.

## Diagnose failures by source

Classify the failure before changing code:

### Record-mode failures

- Provider authentication, DNS, TLS, rate limit, timeout, or HTTP errors are live
  integration failures. Check the key, provider availability, and request first.
- A stream that fails before natural completion should not produce a completed
  recording. Fix the live condition or record a controlled deterministic fixture;
  do not accept a partial cassette.
- A custom-client or mock example not creating a cassette is expected when the
  ambient hook is bypassed.

### Replay-mode failures

- `RecordingNotFoundException`: the selected example has no fixture under the
  supplied root, or the request changed enough not to match. Confirm the exact
  root, namespace, selector, and whether the recording was made in stream mode.
- `CassetteMismatchException`: the next ordered interaction does not match the
  current method, URL semantics, body, stream mode, or response-shaping headers.
  Re-record only after deciding that the request change is intentional.
- `CassetteExhaustedException`: the example made more calls than the recorded
  session. Investigate changed retry/loop behavior; do not enable live fallback.
- `CassetteCorruptException` or unsupported-version errors: the fixture is damaged
  or incompatible. Restore/re-record/migrate it; do not reinterpret the bytes or
  silently call the network.
- A provider-key error in replay usually means the example bypassed
  `examples/boot.php` or the invocation did not propagate replay mode. Verify the
  entrypoint and environment before changing provider configuration.

### Assertion and application failures

An `ASSERT` result means the example ran far enough to make its own correctness
check and that check failed. Compare the current implementation with the fixed
replayed response, then inspect the example's assertion and the relevant package
tests. An `ERROR` with a stack trace or subprocess error is a runtime/infrastructure
problem first, not automatically an Instructor semantic regression.

## Inspect persisted diagnostics

Use the hub's tracked status after an `all` run:

```bash
php bin/instructor-hub status
php bin/instructor-hub status --detailed
php bin/instructor-hub status --errors-only
php bin/instructor-hub errors --list
php bin/instructor-hub show getters_and_setters
```

Use `errors --run` to re-run tracked failures only after choosing the intended
mode, and `errors --clear` only when intentionally resetting the diagnostic history.
The hub stores execution summaries and recent errors; those records explain what
the hub observed, while the cassette files and example subprocess output explain
whether the cause was request matching, provider transport, or Instructor logic.

For a suspected library regression, reduce the diagnosis to the narrowest evidence:

1. Reproduce the same example in replay mode.
2. Confirm the cassette is present and the failure category is stable.
3. Run the relevant package test or add a focused regression test.
4. Run the example again after the fix and confirm the error category disappears.

Do not convert an `ERROR` to a tolerated tag merely to make the gate green. Add or
remove `flaky`, `broken`, and `no-replay` only when the example's documented behavior
actually meets the tag's meaning and the change is reviewed.
