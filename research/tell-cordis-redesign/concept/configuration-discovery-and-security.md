# Configuration, discovery, and security

## Explicit PHP composition is primary

The normal integration surface is PHP factory construction. Applications get
constructor injection, static analysis, IDE navigation, and a reviewable list
of trusted implementations. YAML is optional operational configuration for a
future long-lived supervisor.

## Execution configuration

Tell retains this precedence:

```text
request intent
    over branch-local configuration
    over host configuration
    over user/default configuration
```

These are immutable data layers, not module registrations. Selecting a model
for one request does not replace the model module. Branch configuration stays
secret-free and provider-independent where possible.

`configuration.standard` is the sole precedence aggregator. It consumes the
optional `CanReadTellBranchConfiguration` dependency from the workspace and
path-resolved host/user inputs. Missing optional branch configuration is not a
missing module error.

## Environment and path ownership

`paths.standard` owns Tell-specific environment reads and resolution of user,
workspace, definition, credential, cache, and trace locations. Callers may
inject an immutable environment map or resolver.

No Tell module uses `putenv()` to smuggle request configuration into a driver.
Driver configuration is passed explicitly. Product modules do not independently
scan `$HOME`, working directories, or environment variables.

## Composer discovery boundary

Existing `extra.cognesy-agents` discovery remains behind
`extensions.composer`. It creates Agents capabilities and tools according to
the current manifest contract.

Discovery returns accepted descriptors and structured errors. The standard
agent builder must not discard invalid-manifest diagnostics. Host modules are
never auto-mounted merely because a package is installed; processes,
credentials, commands, and persistent resources require explicit selection.

## Optional YAML composition

If a resource host later needs reconciliation, Tell reuses the Cordis YAML
loader with an allowlisted registry and a closed envelope schema. The accepted
vocabulary is stable module ID, registered module name, validated opaque
configuration, and structured non-executable metadata.

YAML cannot name arbitrary PHP classes, callbacks, or executable files.
Unknown or invalid entries fail before the healthy graph changes.

## Secret isolation

Secret values are available only to modules declaring the secret contract.
Untrusted tools and tenant-specific children cannot access that binding.
Health and diagnostics expose capability names and redacted references, never
resolved values.

## Request-local policy

Timeout, retry, model, budget, approval, and telemetry sampling overrides are
computed as immutable execution context. They do not mutate provider objects
or global process state and disappear with the run.

## Named ownership

- application code owns selected module factories;
- `paths.standard` owns environment and path resolution;
- `configuration.standard` owns execution-setting aggregation;
- `workspace.filesystem` owns branch-setting persistence;
- `extensions.composer` owns manifest diagnostics;
- `secrets.standard` owns secret-source precedence;
- `observation.standard` owns normalized event and telemetry adaptation;
- `protocol.one-run` owns external frame rules; and
- `agent.cognesy` owns the deterministic driver seam.
