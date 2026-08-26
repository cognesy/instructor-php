# Step 5: Modularize workspace and conversations

## Outcome

Canonical workspace and conversation operations are one cohesive replaceable
module. SDK facades no longer construct stores, resolvers, or runners.

## Size and parallelism

Large and state-sensitive. The in-memory implementation and shared conformance
suite can develop alongside facade adapters, but filesystem cutover is serial.

## Work

- Implement `workspace.filesystem` for workspace management, conversation
  access, and branch-configuration reading.
- Move arena opening, refs, branches, checkout/reset, append, run storage,
  review, history, inspection, clear, and compaction behind contracts.
- Preserve canonical append ordering, atomicity, locks, lineage, hashing, and
  normalized event persistence inside the module.
- Convert published conversation and workspace facades into thin contract
  consumers. Classify experimental branch/control facades before freezing them.
- Build an in-memory implementation together with the same conformance suite.
- Keep workspace replacement pre-boot and whole-module; do not hot-swap a
  backend or split its invariants into storage micro-modules.

## Acceptance evidence

- Pre-redesign fixtures remain readable and can be extended safely.
- Filesystem and in-memory implementations pass integrity, CAS, lineage,
  branch, configuration, budget, cancellation, and failure-atomicity tests.
- No public facade imports a concrete workspace store or runner.
- A failing append cannot advance a canonical head partially.

## Boundary

Branch configuration persistence belongs to workspace; aggregate precedence
belongs to configuration. A live backend migration remains a separate feature.

## Enables

[Step 6](06-modularize-configuration-tools-and-observation.md) can assemble
configuration and edges without constructing workspace internals.
