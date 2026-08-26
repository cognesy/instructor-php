# Step 1: Freeze behavior and baseline

## Outcome

Tell's supported public behavior, platform floor, performance baseline, and
legacy obligations are explicit before construction moves.

## Size and parallelism

Medium. Public API, command, persistence, and performance inventories can run
in parallel, but one owner must reconcile them into the acceptance matrix.

## Work

- Retain `php: ^8.3` and verify Tell on PHP 8.3, 8.4, and 8.5.
- Inventory public API in the latest package release, committed unreleased API,
  and current experiments separately.
- Inventory all 19 command classes, including `TellApplication` routing and
  operational plane maps, and link each route to an acceptance test.
- Characterize stateless, transient, durable, branch, checkout, reset, local
  configuration, budget, cancellation, reasoning, subagent, and direct-tool
  behavior.
- Freeze normalized event order, raw source attachment compatibility,
  redaction, trace output, protocol frames, and exits.
- Record contractual workspace formats separately from accidental layout.
- Decide the legacy session support horizon before creating a legacy module.
- Establish a repeatable CLI startup benchmark and count workspace discovery,
  definition scans, and Composer manifest scans.
- Make package-split CI respect each package's PHP constraint rather than
  treating PHP 8.2 resolution failure as a Tell defect.

## Acceptance evidence

- Clean Tell consumers resolve and test on PHP 8.3, 8.4, and 8.5.
- A named behavior matrix covers every supported facade and CLI route.
- Pre-redesign workspace fixtures remain readable and safely writable.
- Event, trace, and protocol characterizations fail on ordering or redaction
  drift.
- Startup timing and scan counts can be reproduced locally and in CI.
- Legacy sessions have a dated retain, migrate, or remove decision.

## Boundary

This step adds evidence and corrects the PHP constraint. It adds no kernel,
Cordis dependency, or new public SDK abstraction.

## Enables

[Step 2](02-simplify-runtime-and-global-state.md) can reduce accidental
complexity against a reliable behavior oracle.
