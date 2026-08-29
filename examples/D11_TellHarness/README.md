# D11 Tell Harness

These examples make Tell usable as a PHP harness rather than a command to
shell out to. They intentionally create and remove temporary projects so a
normal run cannot create `.tell` state in this repository.

<!-- markdownlint-disable MD013 -->
| Example | Scenario | Primary SDK surface |
| --- | --- | --- |
| `StatelessRun` | queue job or request/response endpoint | `Tell::run()` and `TellResult` |
| `ObservableRun` | long-running inference/tool sequence | `runStream()`, `TellProgress`, `TellEventEnvelope` |
| `DurableConversation` | project-local named work | workspace and conversation handles |
| `TransientExperiment` | compare an alternative safely | `transient()` with durable context |
| `CompactionAndClear` | intentional context lifecycle | `compact()` and `clear()` |
| `BranchWorkflow` | isolate, recover, and reset work safely | `workspace()->branches()` |
| `BranchConfiguration` | versioned runtime intent without credentials | `workspace()->configuration()` |
| `ControlledRun` | finite policy, cancellation, and safe lifecycle output | policies, cancellation source, `TellEventEnvelope::toArray()` |
| `AgentCapabilities` | preset discovery, direct tools, answers, and delegation | `catalogue()`, `tools()`, answers, child branches |
| `DeterministicTesting` | deterministic SDK integration without provider I/O | `Tell::testing()` and `TellTestFactory` |
| `ReasoningConfiguration` | typed request and branch reasoning intent | `TellReasoningEffort` and effective provenance |
| `ExternalProtocol` | one run from a shell or non-PHP supervisor | `tell agent --rpc` and versioned JSONL frames |
| `ModularHost` | embedded composition and focused replacement | `TellHost::standard()`, `replace()`, `boot()`, `dispose()` |
| `PersistentShellJobs` | bounded background processes with explicit ownership | `TellResourceHost`, approval, snapshots, cursored output, `dispose()` |
<!-- markdownlint-enable MD013 -->

The agent-run examples are tagged `no-replay` because they invoke the currently
configured Tell agent and no response recordings are committed yet. The
configuration, direct-tool, and deterministic-testing examples are
credential-free and deterministic.
Run one live from the repository root, then record/replay agent behaviour when
it becomes part of the deterministic example corpus:

```bash
php bin/instructor-hub run tell_harness_stateless_run
php bin/instructor-hub run tell_harness_external_protocol
php bin/instructor-hub run tell_harness_persistent_shell_jobs
just examples-record tell_harness_stateless_run
just examples-replay tell_harness_stateless_run
```

Read [GAPS.md](GAPS.md) before treating Tell as an equivalent replacement for
fyai. The examples document the released public surface; the gap analysis
records the deliberate operating-model differences and the remaining future
work.
