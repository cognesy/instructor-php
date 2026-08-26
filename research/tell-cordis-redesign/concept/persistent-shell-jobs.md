# Persistent shell jobs

## Decision

Tell's first scoped-resource feature is a **host-scoped persistent shell job**.
A job may outlive the SDK call or agent tool call that starts it, but it may not
outlive its owning resource host. This is deliberately not a durable daemon or
a job record that a later PHP process can recover.

This closes a concrete D11/fyai gap with a smaller product surface than MCP:
developers can start a bounded server, watcher, or long build, inspect output
without blocking, wait for completion, and cancel it. MCP would additionally
require transport negotiation, protocol/version handling, server discovery,
tool-schema conversion, authentication, and MCP-specific failure policy before
it delivered equivalent value. MCP remains the next lifecycle candidate; it is
not part of this feature.

## Ownership and public shape

The opt-in resource host owns one `shell.jobs` capability. Ordinary
`Tell::open()`, the standard CLI, and the one-run protocol do not construct it
or boot Cordis.

The public PHP surface is capability-oriented:

```php
$host = TellResourceHost::shellJobs(
    project: $project,
    approval: $approval,
)->boot();

$jobs = $host->jobs();
$job = $jobs->start(TellShellJobRequest::command('php -S 127.0.0.1:8080'));
$output = $jobs->read($job->id, after: 0);
$result = $jobs->cancel($job->id);
$host->dispose();
```

Requests, identifiers, snapshots, output pages, and terminal results are
immutable values. A caller never receives a raw process, pipe, Cordis scope,
or mutable container handle. Operations go back through the host-owned
capability, so a disposed host cannot accidentally keep a job usable.

The first release provides `start`, `status`, `read`, `wait`, `cancel`, and
`all`. A tool adapter may expose the same operations to an agent after the PHP
contract is proven. Interactive PTY input is intentionally separate: exposing
a terminal without a complete `write_stdin` and screen model can make a
non-interactive command wait forever.

## Lifecycle contract

The lifecycle is:

```text
requested -> approved -> starting -> running -> exited
                                  \-> failed
                                  \-> cancelled
                                  \-> timed_out
```

- Validate the command, working directory, timeout, output limit, and approval
  before creating a process or publishing a job identifier.
- Start each job in a process group. Cancellation targets the group, waits a
  bounded grace period, then escalates once when necessary.
- The owner scope registers process, output readers, buffers, and cleanup
  together. Normal exit closes live resources but retains a bounded immutable
  terminal snapshot until host disposal.
- `cancel` and `dispose` are idempotent. Disposing the host cancels running jobs
  and attempts every cleanup even if one job fails to stop.
- A timeout uses the same cancellation path and records `timed_out`, not a
  generic process failure.
- A failed start publishes no partial job. Restart means a new `start` request
  and a new identifier; no disposed process or scope is reused.
- Host abandonment is equivalent to disposal only where PHP can guarantee a
  `finally`; destructors are a last-resort safety net, not correctness.

## Approval and bounds

Approval is supplied by the embedding application and is evaluated before any
external effect. The default policy denies starting jobs. A policy may admit a
command based on its structured request and project-local context, but the
model cannot weaken sandbox or approval policy in its request.

Each request is bounded by host policy:

- project-root working-directory containment;
- maximum wall-clock lifetime;
- maximum retained stdout and stderr bytes;
- maximum returned bytes per `read` call;
- maximum concurrent jobs; and
- cancellation grace time.

Output is stored in bounded per-stream ring buffers. `read(after: $cursor)`
returns a monotonic next cursor, separate stdout/stderr chunks, and an explicit
truncation marker when old bytes were evicted. Lifecycle observations contain
job identifier, state, exit code, byte counts, timing, and a redacted command
summary; they never contain environment values or unbounded output.

## Executable acceptance scenarios

1. **Start and inspect:** an approved job starts without blocking, output can
   be read with a cursor, and waiting returns its exit code and final state.
2. **Approval before effects:** a denied request creates no process, publishes
   no job identifier, and records an actionable denial without the command's
   secret environment.
3. **Invalid request before effects:** an escaping working directory or an
   excessive bound fails before Cordis loads a resource module.
4. **Cancellation ownership:** cancelling a running job terminates its process
   group, closes readers, returns `cancelled`, and is safe to repeat.
5. **Timeout:** a job exceeding its approved lifetime follows the same cleanup
   path and terminates as `timed_out`.
6. **Output bounds:** a noisy job cannot exceed retained or returned output
   limits; cursor reads report eviction explicitly.
7. **Failed start:** an unavailable executable or startup exception leaves no
   listable partial job and cleans any resources already acquired.
8. **Host disposal:** disposing with multiple running jobs stops all of them in
   reverse acquisition order; one cleanup failure does not skip the rest.
9. **Isolation:** two resource hosts cannot inspect or control one another's
   identifiers, even when they use the same project directory.
10. **Ordinary-host isolation:** a standard Tell SDK/CLI run never constructs
    the Cordis host or starts a shell process.
11. **Observation:** resource lifecycle events are normalized and redacted,
    and remain distinguishable from Agents inference events.
12. **D11 DX:** an executable example starts a short background job, polls
    output, cancels or awaits it, and disposes its owner in `finally`.

## Non-goals for the first release

- jobs durable across PHP processes or host restarts;
- PTY allocation, terminal screen interpretation, or `write_stdin`;
- arbitrary shell approval controlled by the model;
- live configuration reconciliation;
- concurrent agent scheduling; and
- MCP client or server lifecycle.
