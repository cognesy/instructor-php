# Architecture boundaries

## Two stages, two responsibilities

The redesign separates static composition from dynamic lifecycle.

### Stage 1: static Tell composition

The ordinary SDK and CLI use an immutable module definition graph. The host
validates the graph, constructs selected implementations from factories,
exposes narrow facades, and disposes the resulting host. Replacement is a
builder operation before boot.

This stage solves the current hidden-composition problem without adding a
runtime service graph, reconciliation semantics, or a new operational model.

### Stage 2: optional resource lifecycle

The opt-in resource host wraps selected modules with Cordis-backed scopes. The
current shell-job host owns processes, bounded output, health, and reverse
cleanup. Dependency restart and approved reconciliation remain conditional;
they are not implied by using Cordis or required for ordinary dependency
inversion.

## Target source boundaries

### `tell-contracts`

This boundary contains PHP interfaces and immutable boundary values required
to implement or consume a Tell capability. It may depend explicitly on stable
public values from `cognesy/agents` and `cognesy/instructor-polyglot` where the
current SDK already exposes them. It does not create duplicate, lossy models
merely to claim package independence.

It must not depend on Cordis, Symfony Console, filesystem implementations, or
private Agents and Polyglot implementation classes.

### `tell-kernel`

The initial kernel owns only:

- module definition and capability graph admission;
- deterministic factory construction;
- standard profile composition;
- typed facade assembly; and
- explicit host disposal.

It does not execute prompts, own Tell storage, restart dependencies, parse
YAML, or expose arbitrary capability lookup to product code. It does not
depend on Cordis.

### Tell modules

Modules implement capability contracts. The standard distribution initially
ships them in source-separated namespaces. Composer package extraction occurs
only after an independent consumer, dependency set, and conformance suite make
the boundary real.

Expected vertical modules are execution, Agents loop construction, Polyglot
model resolution, secrets, filesystem workspace, configuration, extension
discovery, tools, normalized observation, Symfony CLI, one-run protocol, and
deterministic testing.

### Optional Cordis host

The Cordis adapter belongs outside the static kernel. It translates explicitly
selected, factory-backed resource modules into scoped providers and consumers.
Its public surface is operational: start, stop, health, and possibly
reconcile. It is not required to call `Tell::open()`.

### `cognesy/instructor-tell`

The existing package remains the standard distribution and compatibility
facade. It exports `Tell`, requests, results, workspace handles, standard
profiles, and the `tell` CLI.

## Dependency direction

```text
Tell SDK facade -----------+
Symfony CLI module --------+----> capability contracts
standard profile wiring ---+              ^
                                           |
module implementations -------------------+
                                           ^
                                           |
static Tell kernel ------------------------+

optional Cordis resource host -> selected resource-module factories
```

Modules import contracts, never sibling implementations. The composition root
is the only place allowed to name concrete modules together. A future Cordis
adapter imports contracts and Cordis; contracts and the static kernel do not
import the adapter.

## Stable core versus replaceable policy

Stable domain code includes canonical record schemas, hashing, lineage,
request and result semantics, workspace atomicity, Agents state transitions,
and protocol frame rules.

Replaceable policy includes agent construction, model and credential
resolution, workspace backend, extension sources, tool dispatch, observation
sinks, command contributions, and testing substitutes.

## Host shapes

- one-shot CLI: build the CLI profile, execute, and dispose in `finally`;
- application SDK: boot one static host for an application scope and open
  project-bound facades;
- test: build a deterministic profile with explicit substitutes; and
- long-lived resource host: opt into Cordis scopes and their stronger
  lifecycle contract.

The host does not install shutdown handlers or mutate global process state.
