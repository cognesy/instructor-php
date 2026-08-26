# Step 2: Simplify runtime and global state

## Outcome

Current Tell construction is safer and smaller before interfaces freeze its
shape. Duplicate run paths, repeated discovery, silent extension errors, and
process-global configuration are removed.

## Size and parallelism

Medium. Runtime path simplification and path/environment cleanup can proceed in
parallel after Step 1 characterization; discovery error handling touches both.

## Work

- Make synchronous execution drain the streaming path where characterized
  results and errors remain identical.
- Consolidate repeated workspace, definition, and manifest discovery for one
  request or host construction.
- Compare scan counts with Step 1 and add narrowly scoped caching only where it
  produces a measured reduction.
- Replace `putenv()`-based driver configuration with explicit immutable input.
- Introduce one path/environment owner without introducing the module kernel.
- Propagate `CapabilityDiscovery` failures as structured SDK, CLI, and protocol
  diagnostics.
- Verify the current abandonment publication rule: an abandoned run is not
  durably published unless a separate product decision says otherwise.
- Preserve deterministic driver construction as an explicit internal seam.

## Acceptance evidence

- Public behavior and workspace fixture suites remain green.
- Sync and stream terminal results share one characterized execution path.
- A test proves two concurrent configurations do not communicate through
  process environment mutation.
- Invalid extension manifests produce useful diagnostics.
- Startup and scan metrics improve or remain within the recorded budget.

## Boundary

This is a preparatory refactor. It does not create public module APIs or change
storage, event, command, or protocol contracts.

## Enables

[Step 3](03-extract-contracts-and-static-composition.md) extracts deliberate
boundaries rather than preserving current duplication.
