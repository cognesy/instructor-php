# D11 Tell Harness

These examples make Tell usable as a PHP harness rather than a command to
shell out to. They intentionally create and remove temporary projects so a
normal run cannot create `.tell` state in this repository.

<!-- markdownlint-disable MD013 -->
| Example | Scenario | Primary SDK surface |
| --- | --- | --- |
| `StatelessRun` | queue job or request/response endpoint | `Tell::run()` and `TellResult` |
| `ObservableRun` | long-running inference/tool sequence | `runStream()`, `TellProgress`, `TellEvent` |
| `DurableConversation` | project-local named work | workspace and conversation handles |
| `TransientExperiment` | compare an alternative safely | `transient()` with durable context |
| `CompactionAndClear` | intentional context lifecycle | `compact()` and `clear()` |
<!-- markdownlint-enable MD013 -->

They are tagged `no-replay` because each invokes the currently configured Tell
agent and no response recordings are committed yet. Run one live from the
repository root, then record/replay it when the behavior becomes part of the
deterministic example corpus:

```bash
php bin/instructor-hub run tell_harness_stateless_run
just examples-record tell_harness_stateless_run
just examples-replay tell_harness_stateless_run
```

Read [GAPS.md](GAPS.md) before treating Tell as an equivalent replacement for
fyai. The examples document the current public surface; the gap analysis maps
the missing operational model to existing tracked P1 work.
