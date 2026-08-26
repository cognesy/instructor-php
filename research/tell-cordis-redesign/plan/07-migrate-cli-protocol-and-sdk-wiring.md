# Step 7: Migrate CLI, protocol, and SDK wiring

## Outcome

The CLI, bounded one-run protocol, and PHP facades use the same static host.
`Tell::open()` remains simple while applications gain explicit control.

## Size and parallelism

Large and integration-heavy. Protocol and SDK adapters can proceed in
parallel, but CLI migration is a dedicated track followed by full parity.

## Work

- Implement `cli.symfony` from the Step 1 command and route inventory.
- Move custom routing, operational plane maps, command contributions, output
  formatting, diagnostics, and exit mapping to the shell edge.
- Implement `protocol.one-run` for bounded requests, normalized events,
  exactly one terminal frame, and deterministic exits.
- Add `TellHost::standard()` with pre-boot `with`, `replace`, `without`,
  `describe`, and `boot` operations.
- Make `Tell::open()` and supported SDK facades delegate to standard wiring.
- Keep direct constructors for one compatibility interval only where callers
  have a documented replacement.
- Update D11 Harness examples for embedding, focused replacement, deterministic
  tests, protocol use, lifecycle ownership, and graceful disposal.
- Compare CLI startup latency and discovery counts with Step 1 budgets.

## Acceptance evidence

- All command names, routes, structured output, and exit codes remain stable.
- Protocol tests cover malformed input, cancellation, normalized events,
  terminal success/failure, and worker exit.
- Existing `Tell::open()` callers need no source change.
- SDK and CLI acceptance tests use the same standard graph.
- D11 examples execute through public modular APIs.
- CLI startup and scan counts remain inside the agreed budget.

## Cutover and rollback

Keep the legacy internal path behind a bounded compatibility switch until
parity and clean-consumer proof pass. Remove it in a separately reviewable
change.

## Enables

[Step 8](08-prove-replacement-and-package-boundaries.md) can test the actual
extension model outside the monorepo.
